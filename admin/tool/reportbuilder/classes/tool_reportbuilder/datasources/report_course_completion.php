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
 * Class report_course_completion
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use tool_datastore\action_factory;
use tool_datastore\api;
use tool_datastore\local\entities\course as datastore_course_entity;
use tool_datastore\local\entities\user as datastore_user_entity;
use tool_reportbuilder\constants;
use tool_reportbuilder\local\entities\course as course_entity;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class report_course_completion
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_course_completion extends \tool_reportbuilder\datasource {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_main_table('tool_datastore_action', 'dsa');
        $this->add_base_condition_simple('dsa.action', 'course_completed');
        $this->add_base_join('LEFT JOIN {user} u ON u.id = dsa.relateduserid AND u.deleted = 0');

        // We add a tenancy limit on non-admins, so they can see deleted users as well as all tenants.
        if (!is_siteadmin()) {
            list($join, $where, $params) = tenancy::get_users_sql('u');
            $this->add_base_join($join);
            $this->add_base_condition_sql($where, $params);
        }

        $this->set_columns();
        $this->set_conditions();
        $this->set_filters();

        // Set default columns/sorting.
        $this->get_column('tool_datastore_user:firstname')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true);
        $this->get_column('tool_datastore_user:lastname')
            ->set_is_default(true, 2)
            ->set_is_sortable(true, true);
        $this->get_column('tool_datastore_course:fullname')
            ->set_is_default(true, 3)
            ->set_is_sortable(true, true);
        $this->get_column('tool_datastore_course_completion:timecompleted')
            ->set_is_default(true, 4)
            ->set_is_sortable(true, true);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('reportcoursecompletion', 'tool_reportbuilder');
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

        // MSSQL can not aggregate columns with sub-query, so we'll disable all aggregation methods for them.
        $ismssql = $DB->get_dbfamily() === 'mssql';

        $maintablealias = $this->get_main_table_alias();

        // Course may no longer exist, so left joined.
        $coursejoin = "LEFT JOIN {course} c ON c.id = {$maintablealias}.originalcourseid";

        // Add datastore course/user entities.
        $this->add_entity((new datastore_course_entity())
            ->add_course_join($coursejoin)
            ->set_table_alias('tool_datastore_action', $maintablealias));

        $this->add_entity((new datastore_user_entity())
            ->set_table_alias('tool_datastore_action', $maintablealias));

        // Our datastore entities.
        $this->annotate_entity('tool_datastore_course_completion',
            new \lang_string('entitydatastorecoursecompletion', 'tool_reportbuilder'));

        // Add course/user entities.
        $this->add_entity((new course_entity())
            ->add_join($coursejoin));

        $this->add_entity(new user_entity());

        // Datastore course completion columns.
        /** @var \tool_datastore\local\action\course_completed $actionclass */
        $actionclass = action_factory::get_action_class('course_completed');

        $completionfields = $actionclass::get_fields_to_index()['course_completion'];
        foreach ($completionfields as $field) {
            list($sql, $params) = api::get_datasource_field_sql('course_completion', $field, $maintablealias, null,
                constants::DB_TYPE_TIMESTAMP);

            $newcolumn = (new report_column(
                $field,
                new \lang_string("course_completion_{$field}", 'tool_reportbuilder'),
                'tool_datastore_course_completion'
            ))
                ->add_field($sql, $field, $params)
                ->set_groupby_sql("{$maintablealias}.id")
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->add_callback([format::class, 'userdate']);

            if ($ismssql) {
                columns::disable_column_aggregation($newcolumn);
            }

            $this->add_column($newcolumn);
        }
    }

    /**
     * Return common filters/conditions of the datasource
     *
     * @param bool $iscondition
     * @return report_filter[]
     */
    protected function get_filters_or_conditions(bool $iscondition) : array {
        $filters = [];

        return $filters;
    }

    /**
     * Set the available conditions of the datasource
     *
     * @return void
     */
    public function set_conditions() : void {
        foreach ($this->get_filters_or_conditions(true) as $condition) {
            $this->add_condition($condition);
        }
    }

    /**
     * Set the available filters of the datasource
     *
     * @return void
     */
    public function set_filters() : void {
        foreach ($this->get_filters_or_conditions(false) as $filter) {
            $this->add_filter($filter);
        }
    }
}