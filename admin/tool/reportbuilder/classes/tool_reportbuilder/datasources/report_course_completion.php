<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Class report_course_completion
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
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
use tool_reportbuilder\permission;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_tenant\tenancy;

/**
 * Class report_course_completion
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_course_completion extends \tool_reportbuilder\datasource {

    /**
     * Initialise report
     */
    protected function initialise() {
        // Set main table. We want a custom tenant filter, so disable automatic one.
        $this->set_main_table('tool_datastore_action', 'dsa', false);
        $this->add_base_condition_simple('dsa.action', 'course_completed');
        $this->add_base_join('LEFT JOIN {user} u ON u.id = dsa.relateduserid AND u.deleted = 0');
        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        $canviewinactiveusers = \tool_tenant\permission::can_view_inactive_users($this->get_tenant_id());
        if (!$canviewinactiveusers) {
            $this->add_confirmed_user_condition();
        }

        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, 'u.id', 0, true));

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

        $canshowtenantcolumn = \tool_reportbuilder\permission::can_show_tenant_column($this->get_tenant_id());
        if ($column = $this->get_column('user:tenant')) {
            $column->set_is_default(true, 5)
                ->set_is_sortable(true, true)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($filter = $this->get_filter('user:tenant')) {
            $filter->set_is_default(true)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($condition = $this->get_condition('user:tenant')) {
            $condition->set_is_available($canshowtenantcolumn);
        }

        // Set suspended/confirmed availability in condition/filter based on 'can_view_inactive_users' permission.
        $this->get_conditions()['user:suspended']->set_is_available($canviewinactiveusers);
        $this->get_conditions()['user:confirmed']->set_is_available($canviewinactiveusers);
        $this->get_filters()['user:suspended']->set_is_available($canviewinactiveusers);
        $this->get_filters()['user:confirmed']->set_is_available($canviewinactiveusers);
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

        $allowtenant = permission::can_show_tenant_column(tenancy::get_tenant_id());
        $this->add_entity((new user_entity())->set_allow_tenant_columns($allowtenant));

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
