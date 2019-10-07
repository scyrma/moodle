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
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\reports;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_organisation\organisation;
use tool_program\constants;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programuser_entity;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_reportbuilder\local\entities\user;
use tool_reportbuilder\report_action;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;
use moodle_url;
use pix_icon;
use tool_program\task\reset_program;

/**
 * This class defines a system report that shows the users allocated to a given program and their allocation origin.
 *
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    tool_program
 */
class allocations_report extends system_report {

    /** @var program */
    protected $program;
    /** @var array */
    protected $tasks = [];

    /**
     * Current program
     *
     * @return program
     */
    protected function get_program(): program {
        if (!$this->program) {
            $programid = $this->get_parameter('id', 0, PARAM_INT);
            $this->program = new program($programid);
        }
        return $this->program;
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $programid = $this->get_program()->get('id');
        $tenantid = tenancy::get_tenant_id();

        $this->set_columns();
        $this->set_main_table('tool_program_users', 'tpu');
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tpu.programid');
        $this->add_base_join('LEFT JOIN {tool_certification} tc ON tc.id = tpu.certificationid');
        $this->add_base_join('LEFT JOIN {tool_certification_users} tcu
        ON tcu.userid = tpu.userid AND tcu.certificationid = tc.id');
        $this->add_base_join('LEFT JOIN {tool_certification_compltion} tcc
        ON tcc.certificationid = tpu.certificationid AND tcc.userid = tpu.userid AND tcc.timerevoked = 0');
        $this->add_base_join('INNER JOIN {user} u ON u.id = tpu.userid');
        $this->add_base_condition_simple('tpu.programid', $programid);
        $this->add_base_condition_simple('tp.tenantid', $tenantid);
        $this->add_base_condition_simple('u.deleted', 0);
        // Fields necessary for actions and row class.
        $this->add_base_fields('tpu.id, tpu.certificationid, tpu.programid, tpu.userid, tpu.status,
        tpu.enddate, tpu.allocationtype');

        // Check tenant id on users in case they have been moved to another tenant.
        [$join, $where, $params] = tenancy::get_users_sql('u', $tenantid);
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);

        if (!permission::has_allocateuser_capability($this->get_program()->get_context())) {
            // Managers with no system capability are only allowed to see the users they manage.
            if ($manager = organisation::get_user_with_jobs()) {
                [$where, $params] = $manager->get_managed_users_select('u', organisation::PERM_ALLOCATE_PROGRAMS);
                $this->add_base_condition_sql($where, $params);
            }
        }

        // Get list of program allocations pending to be reseted.
        $tasks = \core\task\manager::get_adhoc_tasks(reset_program::class);
        foreach ($tasks as $task) {
            $this->tasks[] = $task->get_custom_data()->programuser;
        }

        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);

        // Default columns.
        if ($column = $this->get_column('user:fullname')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('tool_program_users:duedate')) {
            $column->set_is_default(true, 2);
        }
        if ($column = $this->get_column('tool_program_users:allocationtype')) {
            $column->set_is_default(true, 3);
        }
        if ($column = $this->get_column('tool_certification:fullname')) {
            $column->set_is_default(true, 4);
        }
        if ($column = $this->get_column('tool_certification_users:certificationstatus')) {
            $column->set_is_default(true, 5);
        }
        if ($column = $this->get_column('tool_program_users:programstatus')) {
            $column->set_is_default(true, 6);
        }
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_allocated_users($this->get_program());
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
        $this->add_entity(new program_entity('', 'tp', $this->get_program_excluded_columns()));
        $this->add_entity(new programuser_entity('', 'tpu', $this->get_programuser_excluded_columns()));
        $this->add_entity(new user('', 'u', []));
        $this->add_entity(new certification_entity('', 'tc', $this->get_certification_excluded_columns()));
        $this->add_entity(new certificationuser_entity('', 'tcu', $this->get_certificationuser_excluded_columns(), 'tcc'));
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        $program = $this->get_program();

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
        // Check if allocation can be edited.
        $action->add_callback(static function($row) use ($program) {
            $programuser = new program_user(0, $row);
            $programuser->set_program($program);
            return permission::can_edit_user_allocation($programuser);
        });
        $this->add_action($action);

        // User allocation reset program icon.
        $reseturl = new moodle_url('/admin/tool/program/reset.php', ['id' => ':id']);
        $reseticon = new pix_icon('step-backward', get_string('programreset', 'tool_program'), 'tool_wp');
        $action = new report_action($reseturl, $reseticon, [
            'class' => 'action-icon confirm_reset_program',
            'data-userid' => ':userid',
            'data-id' => ':programid',
            'data-programuserid' => ':id',
        ]);
        // Check if program can be reseted for this user or if reset is in task queue.
        $action->add_callback(function($row) use ($program) {
            if (in_array($row->id, $this->tasks, true)) {
                return false;
            }
            $programuser = new program_user(0, $row);
            $programuser->set_program($program);
            return permission::can_edit_user_allocation($programuser);
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
        // Check if allocation can be deleted.
        $action->add_callback(static function($row) use ($program) {
            $programuser = new program_user(0, $row);
            $programuser->set_program($program);
            return permission::can_delete_user_allocation($programuser);
        });
        $this->add_action($action);

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

    /**
     * Returns an array with the excluded columns for program_entity.
     *
     * @return array
     */
    private function get_program_excluded_columns(): array {
        return ['fullnamewithimage', 'programimage', 'idnumber', 'tags', 'description', 'startdate', 'duedate', 'enddate',
            'archived', 'allowdirectallocation', 'allocationstartdate', 'allocationenddate', 'visible',
            'timemodified', 'timecreated', 'numbercoursesunique', 'associatedcertifications', 'numbercurrentallocatedusers'];
    }

    /**
     * Returns an array with the excluded columns for programuser_entity.
     *
     * @return array
     */
    private function get_programuser_excluded_columns(): array {
        return ['startdate', 'enddate', 'programprogress', 'programprogresswithoverview', 'suspended', 'timesuspended',
            'timecreated', 'timemodified', 'associatedcertification'];
    }

    /**
     * Returns an array with the excluded columns for certification_entity.
     *
     * @return array
     */
    private function get_certification_excluded_columns(): array {
        return ['idnumber', 'timearchived', 'archived', 'startdate', 'duedate', 'expirydate',
            'allocationstartdate', 'allocationenddate', 'timemodified', 'timecreated'];
    }

    /**
     * Returns an array with the excluded columns for certificationuser_entity.
     *
     * @return array
     */
    private function get_certificationuser_excluded_columns(): array {
        return ['allocationtype', 'startdate', 'duedate', 'expirydate', 'suspended', 'timesuspended', 'timecreated',
            'timemodified', 'daystakingcertification', 'dayssinceallocation'];
    }
}
