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
 * Class jobs_list
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation;

use tool_organisation\tool_reportbuilder\filter\showpastjobs;
use tool_organisation\tool_reportbuilder\filter\job_department;
use tool_organisation\tool_reportbuilder\filter\job_position;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use tool_reportbuilder\report_action;
use lang_string;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class jobs_list
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobs_list extends system_report {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_filters();
        $this->set_main_table('tool_organisation_job', 'j');
        $this->add_base_join('JOIN {user} u ON u.id = j.userid');
        $this->add_base_fields('j.id, j.userid, j.departmentid, j.tenantid, j.positionid, ' .
            'j.enddate, u.firstname as fullusername,' .
            user_entity::get_all_user_name_fields(true, 'u')); // Necessary for actions and row class.
        $this->add_base_condition_simple('u.deleted', 0);

        // Check tenant id on users in case they have been moved to another tenant.
        [$join, $where, $params] = tenancy::get_users_sql('u', tenancy::get_tenant_id());
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);

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
        return permission::can_view_jobs();
    }

    /**
     * Set the filters of the report
     *
     * @throws \coding_exception
     */
    protected function set_filters() {
        // General filter.
        $f = new report_filter(
            showpastjobs::class,
            'showpastjobs',
            new lang_string('showpastjobs', 'tool_organisation'),
            'tool_organisation_jobs',
            'j.id'
        );
        $f->set_is_default(true);
        $this->add_filter($f);

        // Position filter.
        $f = new report_filter(
            job_position::class,
            'position',
            new lang_string('position', 'tool_organisation'),
            'tool_organisation_jobs',
            'p'
        );
        $f->set_is_default(true);
        $f->set_options(organisation::get_all_positions_menu( ['' => get_string('anyposition', 'tool_organisation')]));
        $this->add_filter($f);

        // Department filter.
        $f = new report_filter(
            job_department::class,
            'department',
            new lang_string('department', 'tool_organisation'),
            'tool_organisation_jobs',
            'd'
        );
        $f->set_is_default(true);
        $f->set_options(organisation::get_all_departments_menu(['' => get_string('anydepartment', 'tool_organisation')]));
        $this->add_filter($f);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('jobs', 'tool_organisation');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns() {
        $this->annotate_entity('user', new lang_string('entityuser', 'tool_reportbuilder'));
        $this->annotate_entity('tool_organisation_jobs', new lang_string('entityjob', 'tool_organisation'));
        $this->annotate_entity('tool_organisation_position', new lang_string('entityposition', 'tool_organisation'));
        $this->annotate_entity('tool_organisation_department', new lang_string('entitydepartment', 'tool_organisation'));

        // Add user column.
        $usercolumn = (new report_column(
            'userid',
            new lang_string('fullname', 'tool_organisation'),
            'user'
        ))
            ->add_fields(user_entity::get_all_user_name_fields(true, 'u') . ', j.userid, j.positionid')
            ->set_is_default(true)
            ->set_is_sortable(true, true);
        $usercolumn->add_callback([format::class, 'fullname']);
        $this->add_column($usercolumn);

        // Add position column.
        $positioncolumn = (new report_column(
            'position',
            new lang_string('position', 'tool_organisation'),
            'tool_organisation_position'
        ))
            ->add_join('left join {tool_organisation_position} p ON p.id = j.positionid')
            ->add_field('p.name', 'position')
            ->add_field('j.positionid')
            ->set_is_default(true);
        // Argument passed to callback here is the type of permission and helps in rendering permission icons.
        $positioncolumn->add_callback([\tool_organisation\local\helpers\format::class, 'entityname_and_permissions'],
            'globalmanager');
        $this->add_column($positioncolumn);

        // Add department column.
        $departmentcolumn = (new report_column(
            'department',
            new lang_string('department', 'tool_organisation'),
            'tool_organisation_department'
        ))
            ->add_join('left join {tool_organisation_department} d ON d.id = j.departmentid')
            ->add_field('d.name', 'department')
            ->add_field('j.positionid')
            ->set_is_default(true);
        // Argument passed to callback here is the type of permission and helps in rendering permission icons.
        $departmentcolumn->add_callback([\tool_organisation\local\helpers\format::class, 'entityname_and_permissions'],
            'departmentmanager');
        $this->add_column($departmentcolumn);

        // Add date columns.
        foreach (['startdate', 'enddate'] as $field) {
            $newcolumn = (new report_column(
                $field,
                new lang_string($field, 'tool_organisation'),
                'tool_organisation_jobs'
            ))
                ->add_field($field)
                ->set_is_default(true)
                ->add_callback([\tool_organisation\local\helpers\format::class, 'jobdate']);
            $this->add_column($newcolumn);
        }

    }

    /**
     * Set the actions icons of the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function add_actions() {
        $url = new \moodle_url('#');

        $icon = new \pix_icon('i/settings', get_string('editjob', 'tool_organisation'), 'core');
        $action = new report_action($url, $icon, ['data-action' => 'editjob', 'data-id' => ':id',
            'data-userid' => ':userid', 'data-fullusername' => ':fullusername']);
        $action->add_callback(function($row) {
            $row->fullusername = format::fullname('', $row);
            return permission::can_edit_job(new job(0, $row));
        });
        $this->add_action($action);

        $icon = new \pix_icon('t/add', get_string('addjob', 'tool_organisation'), 'core');
        $action = new report_action($url, $icon, ['data-action' => 'addjob',
            'data-userid' => ':userid', 'data-fullusername' => ':fullusername']);
        $action->add_callback(function($row) {
            $row->fullusername = format::fullname('', $row);
            return permission::can_assign_job_to_user($row->userid);
        });
        $this->add_action($action);
    }

    /**
     * CSS class for the row
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        return ($row->enddate && $row->enddate < helper::round_time(time())) ? 'dimmed_text' : '';
    }
}
