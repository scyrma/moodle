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
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use tool_datastore\api;
use tool_datastore\action\course_completed;
use tool_reportbuilder\constants;
use tool_reportbuilder\local\entities\course as course_entity;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\local\filter\null_select;
use tool_reportbuilder\local\filter\text;
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
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_course_completion extends \tool_reportbuilder\datasource {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_main_table('tool_datastore_action', 'dsa');
        $this->add_base_condition_simple('dsa.action', 'course_completed');
        $this->add_base_join('LEFT JOIN {user} u ON u.id = dsa.relateduserid AND u.deleted = 0');
        $this->add_organisation_condition('u');

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

        // Our datastore entities.
        $this->annotate_entity('tool_datastore_course', new \lang_string('entitydatastorecourse', 'tool_reportbuilder'));
        $this->annotate_entity('tool_datastore_user', new \lang_string('entitydatastoreuser', 'tool_reportbuilder'));
        $this->annotate_entity('tool_datastore_course_completion',
            new \lang_string('entitydatastorecoursecompletion', 'tool_reportbuilder'));

        $maintablealias = $this->get_main_table_alias();

        // Add course/user entities (left joined because they may no longer exist).
        $coursejoin = "LEFT JOIN {course} c ON c.id = {$maintablealias}.originalcourseid";
        $this->add_entity(new course_entity($coursejoin, 'c'));

        $this->add_entity(new user_entity('', 'u'));

        // Datastore course columns.
        $coursefields = ['fullname', 'shortname', 'idnumber', 'format'];
        foreach ($coursefields as $field) {
            list($sql, $params) = api::get_datasource_field_sql('course', $field, $maintablealias, null, constants::DB_TYPE_TEXT);

            $newcolumn = (new report_column(
                $field,
                new \lang_string($field),
                'tool_datastore_course'
            ))
                ->add_field($sql, $field, $params)
                ->set_groupby_sql("{$maintablealias}.id")
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_callback([format::class, 'format_string']);

            if ($ismssql) {
                columns::disable_column_aggregation($newcolumn);
            }

            $this->add_column($newcolumn);
        }

        // Datastore user columns.
        $userfields = ['firstname', 'lastname', 'email', 'idnumber', 'username'];
        foreach ($userfields as $field) {
            list($sql, $params) = api::get_datasource_field_sql('user', $field, $maintablealias, 'relateduserid',
                constants::DB_TYPE_TEXT);

            $newcolumn = (new report_column(
                $field,
                new \lang_string($field),
                'tool_datastore_user'
            ))
                ->add_field($sql, $field, $params)
                ->set_groupby_sql("{$maintablealias}.id, {$maintablealias}.relateduserid")
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_callback([format::class, 'format_string']);

            if ($ismssql) {
                columns::disable_column_aggregation($newcolumn);
            }

            $this->add_column($newcolumn);
        }

        // Datastore course completion columns.
        $completionfields = course_completed::get_fields_to_index()['course_completion'];
        $completionfields = explode(',', $completionfields);
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
        $maintablealias = $this->get_main_table_alias();

        // Datasource course/user entity 'deleted' status (i.e. whether they still exist).
        $statusoptions = [
            0 => get_string('active'),
            1 => get_string('deleted'),
        ];

        // Datastore course filters.
        list($sql, $params) = api::get_datasource_field_sql('course', 'fullname', $maintablealias, null, constants::DB_TYPE_TEXT);
        $filters[] = (new report_filter(
            text::class,
            'tool_datastore_course_fullname',
            new \lang_string('conditiondatastorecoursefullname', 'tool_reportbuilder'),
            'tool_datastore_course',
            $sql,
            $params
        ));

        $filters[] = (new report_filter(
            null_select::class,
            'tool_datastore_course_status',
            new \lang_string('status'),
            'tool_datastore_course',
            'c.id'
        ))->set_options($statusoptions);

        // Datastore user filters.
        list($sql, $params) = api::get_datasource_field_sql('user', 'firstname', $maintablealias, 'relateduserid',
            constants::DB_TYPE_TEXT);

        $filters[] = (new report_filter(
            text::class,
            'tool_datastore_user_firstname_filter',
            new \lang_string('conditiondatastoreuserfirstname', 'tool_reportbuilder'),
            'tool_datastore_user',
            $sql,
            $params
        ));

        list($sql, $params) = api::get_datasource_field_sql('user', 'lastname', $maintablealias, 'relateduserid',
            constants::DB_TYPE_TEXT);

        $filters[] = (new report_filter(
            text::class,
            'tool_datastore_user_lastname',
            new \lang_string('conditiondatastoreuserlastname', 'tool_reportbuilder'),
            'tool_datastore_user',
            $sql,
            $params
        ));

        $filters[] = (new report_filter(
            null_select::class,
            'tool_datastore_user_status',
            new \lang_string('status'),
            'tool_datastore_user',
            'u.id'
        ))->set_options($statusoptions);

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