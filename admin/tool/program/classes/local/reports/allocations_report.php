<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class for define the system report of the users allocated to program.
 *
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\reports;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use tool_organisation\organisation;
use tool_program\constants;
use tool_program\local\helpers\format;
use tool_program\local\helpers\programuser_format;
use tool_program\permission;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\local\helpers\format as reportbuilder_format;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use context_system;
use tool_tenant\tenancy;
use moodle_url;
use pix_icon;
use lang_string;

/**
 * This class defines a system report that shows the users allocated to a given program and their allocation origin.
 *
 * @copyright  2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    tool_program
 */
class allocations_report extends system_report {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $programid = $this->get_parameter('id', 0, PARAM_INT);
        $this->set_columns();
        $this->set_main_table('tool_program_users', 'tpu');
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tpu.programid');
        $this->add_base_condition_simple('tpu.programid', $programid);
        $this->add_base_condition_simple('tp.tenantid', tenancy::get_tenant_id());
        // Fields necessary for actions and row class.
        $this->add_base_fields('tpu.id, tpu.certificationid, tpu.programid, tpu.userid, tpu.status, tpu.enddate');

        if (!permission::has_allocateuser_capability(context_system::instance())) {
            // Managers with no system capability are only allowed to see the users they manage.
            if ($manager = organisation::get_user_with_jobs()) {
                [$where, $params] = $manager->get_managed_users_select('u', organisation::PERM_ALLOCATE_PROGRAMS);
                $this->add_base_condition_sql($where, $params);
            }
        }

        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_list(context_system::instance());
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportprogramusers', 'tool_program');
    }

    /**
     * Set the columns for the report.
     *
     * @return mixed
     */
    protected function set_columns(): void {
        $this->annotate_entity('tool_program', new lang_string('entityprogram', 'tool_program'));
        $this->annotate_entity('tool_program_users', new lang_string('entityprogramusers', 'tool_program'));
        $this->annotate_entity('user', new lang_string('entityuser', 'tool_reportbuilder'));

        // Column "fullname".
        $newcolumn = (new report_column(
            'fullname',
            new lang_string('fullname', 'tool_program'),
            'user'
        ))
            ->add_join('INNER JOIN {user} u ON u.id = tpu.userid')
            ->add_fields('tpu.userid, ' . user_entity::get_all_user_name_fields(true, 'u'))
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true, 0)
            ->add_callback([reportbuilder_format::class, 'fullname']);
        $this->add_column($newcolumn);

        // Column "duedate".
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_program'),
            'tool_program_users'
        ))
            ->add_fields('duedate, duedatelocked')
            ->set_is_default(true, 2)
            ->add_callback([programuser_format::class, 'duedate']);
        $this->add_column($newcolumn);

        // Column "allocationtype".
        $newcolumn = (new report_column(
            'allocationtype',
            new lang_string('allocationsource', 'tool_program'),
            'tool_program_users'
        ))
            ->add_field('tpu.allocationtype')
            ->set_is_default(true, 3)
            ->set_is_sortable(true, true, 1, SORT_DESC)
            ->add_callback([programuser_format::class, 'allocationtype']);
        $this->add_column($newcolumn);

        // Column "certification".
        $newcolumn = (new report_column(
            'certificationuser',
            new lang_string('certification', 'tool_program'),
            'tool_program'
        ))
            ->add_field('tpu.certificationid', 'certificationuser')
            ->set_is_default(true, 4)
            ->add_callback([programuser_format::class, 'certificationuser']);
        $this->add_column($newcolumn);

        // Column "certification status".
        $newcolumn = (new report_column(
            'certificationstatus',
            new lang_string('certificationstatus', 'tool_program'),
            'tool_program_users'
        ))
            ->add_fields('tpu.userid, tpu.certificationid')
            ->set_is_default(true, 5)
            ->add_callback([programuser_format::class, 'certificationstatus']);
        $this->add_column($newcolumn);

        // Column "program status".
        $newcolumn = (new report_column(
            'programstatus',
            new lang_string('programstatus', 'tool_program'),
            'tool_program_users'
        ))
            ->add_fields('tpu.userid, tpu.certificationid, tpu.programid')
            ->set_is_default(true, 6)
            ->add_callback([programuser_format::class, 'programstatus']);
        $this->add_column($newcolumn);
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        $canallocate = permission::can_allocate_anybody_as_organisation_manager();
        if ($canallocate || permission::has_allocateuser_capability(context_system::instance())) {
            // User allocation edit icon.
            $editurl = new moodle_url('/admin/tool/program/edit.php', ['id' => ':id']);
            $editicon = new pix_icon('i/settings', get_string('edit'), 'core');
            $action = new report_action($editurl, $editicon, [
                'class' => 'action-icon edit_user',
                'data-action' => 'user_edit_form',
                'data-userid' => ':userid',
                'data-programuserid' => ':id',
                'data-programid' => ':programid'
            ]);
            // Can not edit if is allocation from certification.
            $action->add_callback(static function($row) {
                return (0 === (int) $row->certificationid);
            });
            $this->add_action($action);

            // User allocation delete icon.
            $deleteurl = new moodle_url('/admin/tool/program/delete.php', ['id' => ':id']);
            $deleteicon = new pix_icon('i/trash', get_string('delete'), 'core');
            $action = new report_action($deleteurl, $deleteicon, [
                'class' => 'action-icon confirm_deallocate_user',
                'data-userid' => ':userid',
                'data-id' => ':programid'
            ]);
            // Can not edit if is allocation from certification.
            $action->add_callback(static function($row) {
                return (0 === (int) $row->certificationid);
            });
            $this->add_action($action);
        }
    }

    /**
     * CSS class for the row
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        $issuspendedorafterenddate = constants::STATUS_OVERRIDE_SUSPENDED === (int) $row->status
            || ($row->enddate && $row->enddate < time());
        return $issuspendedorafterenddate ? 'dimmed_text' : '';
    }
}
