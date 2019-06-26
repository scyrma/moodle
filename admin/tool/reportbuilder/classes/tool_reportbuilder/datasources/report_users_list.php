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
 * Class report_users_list
 *
 * @package   tool_reportbuilder
 * @copyright 2019, Toni Barbera <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use tool_organisation\organisation;
use tool_organisation\tool_reportbuilder\filter\department_select;
use tool_organisation\tool_reportbuilder\filter\position_select;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_users_list
 *
 * @package   tool_reportbuilder
 * @copyright 2019, Toni Barbera <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_users_list extends \tool_reportbuilder\datasource {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_main_table('user', 'u');
        list($join, $where, $params) = tenancy::get_users_sql('u');
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);
        $this->add_organisation_condition('u');

        $this->set_columns();
        $this->set_filters();
        $this->set_conditions();

        $this->get_column('user:fullname')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 1);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('reportuserslist', 'tool_reportbuilder');
    }


    /**
     * Additional join for jobs
     *
     * @return string
     */
    protected function get_jobs_join() {
        return 'LEFT JOIN {tool_organisation_job} toj ON u.id = toj.userid ' .
               'LEFT JOIN {tool_organisation_position} topos ON toj.positionid = topos.id ' .
               'LEFT JOIN {tool_organisation_department} tod ON toj.departmentid = tod.id';
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function set_columns() {
        global $DB;
        $this->add_entity(new user_entity('', 'u'));
        $this->annotate_entity('tool_organisation_jobs', new \lang_string('entityjob', 'tool_organisation'));
        $this->annotate_entity('user_custom_field', new \lang_string('profilefields', 'admin'));

        $newcolumn = (new report_column(
                'position',
                new \lang_string('position', 'tool_organisation'),
                'tool_organisation_jobs'
        ))
            ->add_join($this->get_jobs_join())
            ->add_field('topos.name')
            ->add_callback([format::class, 'format_string']);
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            'department',
            new \lang_string('department', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->get_jobs_join())
            ->add_field('tod.name')
            ->add_callback([format::class, 'format_string']);
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            'positiondepartment',
            new \lang_string('jobpositiondepartment', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->get_jobs_join())
            ->add_field($DB->sql_concat('topos.name', "' '", 'tod.name'), 'positiondepartment')
            ->add_callback([format::class, 'format_string']);
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
                'startdate',
                new \lang_string('startdate', 'tool_organisation'),
                'tool_organisation_jobs'
        ))
            ->add_join($this->get_jobs_join())
            ->add_field('toj.startdate')
            ->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
                'enddate',
                new \lang_string('enddate', 'tool_organisation'),
                'tool_organisation_jobs'
        ))
            ->add_join($this->get_jobs_join())
            ->add_field('toj.enddate');
        $newcolumn->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);
    }

    /**
     * Set the filters of the report
     *
     * @throws \coding_exception
     */
    protected function set_filters() {
        foreach ($this->get_filters_or_conditions(false) as $filter) {
            $this->add_filter($filter);
        }
    }

    /**
     * Available conditions to be selected in the report.
     *
     * @throws \coding_exception
     */
    protected function set_conditions() {
        foreach ($this->get_filters_or_conditions(true) as $condition) {
            $this->add_condition($condition);
        }
    }

    /**
     * Filters/conditions for jobs
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition) {

        $filters = [];

        // Position select filter.
        $filters[] = (new report_filter(
            position_select::class,
            'position',
            new \lang_string('position', 'tool_organisation'),
            'tool_organisation_jobs',
            'u.id'
        ))
            ->add_join($this->get_jobs_join())
            ->set_options(organisation::get_all_positions_menu(
                ['' => get_string('anyposition', 'tool_organisation')]));

        // Department select filter.
        $filters[] = (new report_filter(
            department_select::class,
            'department',
            new \lang_string('department', 'tool_organisation'),
            'tool_organisation_jobs',
            'u.id'
        ))
            ->set_options(organisation::get_all_departments_menu(
                ['' => get_string('anydepartment', 'tool_organisation')]));

        return $filters;
    }

    /**
     * This report is available to organisation managers with the permission to view reports
     *
     * Only users who are managed by the current user will be displayed
     *
     * @return bool
     */
    public static function supports_organisation_filter(): bool {
        return true;
    }
}