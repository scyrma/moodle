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
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use tool_datastore\api;
use tool_datastore\action\course_completed;
use tool_reportbuilder\constants;
use tool_reportbuilder\local\entities\course as course_entity;
use tool_reportbuilder\local\entities\user as user_entity;
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
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
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
            list($sql, $params) = api::get_datasource_field_sql('course', $field, $maintablealias);

            $newcolumn = (new report_column(
                $field,
                new \lang_string($field),
                'tool_datastore_course'
            ))
                ->add_field($DB->sql_compare_text($sql, 255), $field, $params)
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
            list($sql, $params) = api::get_datasource_field_sql('user', $field, $maintablealias, 'relateduserid');

            $newcolumn = (new report_column(
                $field,
                new \lang_string($field),
                'tool_datastore_user'
            ))
                ->add_field($DB->sql_compare_text($sql, 255), $field, $params)
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
            list($sql, $params) = api::get_datasource_field_sql('course_completion', $field, $maintablealias);

            $newcolumn = (new report_column(
                $field,
                new \lang_string("course_completion_{$field}", 'tool_reportbuilder'),
                'tool_datastore_course_completion'
            ))
                // To ensure all the numeric fields work with aggregation, cast the field to int.
                ->add_field($DB->sql_cast_char2int($sql, true), $field, $params)
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
     * Generate field SQL appropriate for filters. Work around current limitation of them not accepting $params by moving them
     * into the returned SQL snippet
     *
     * @param string $entitytype
     * @param string $fieldname
     * @param string $maintablealias
     * @param string|null $maintablefield
     * @param int|null $type type of the field/filter, by default constants::DB_TYPE_LONGTEXT
     * @return string
     */
    private static function get_filter_field_sql(string $entitytype, string $fieldname, string $maintablealias,
            ?string $maintablefield = null, ?int $type = null) : string {
        global $DB;

        $type = $type !== null ? $type : constants::DB_TYPE_LONGTEXT;
        list($sql, $params) = api::get_datasource_field_sql($entitytype, $fieldname, $maintablealias, $maintablefield);

        // Match everything that looks like a parameter (:foo) and replace with actual param value.
        $sql = preg_replace_callback('/\s:(?<param>[a-z0-9_]+)\s?/i', function ($matches) use ($params) {
            return "'" .  preg_replace("/'/", '', $params[$matches['param']]) . "'";
        }, $sql);

        if ($type == constants::DB_TYPE_NUMBER || $type == constants::DB_TYPE_DATETIME
                || $type == constants::DB_TYPE_TIMESTAMP) {
            return $DB->sql_cast_char2int($sql, true);
        } else if ($type == constants::DB_TYPE_TEXT) {
            return $DB->sql_compare_text($sql, 255);
        }
        return $sql;
    }

    /**
     * Set the available conditions of the datasource
     *
     * @return void
     */
    protected function set_conditions() : void {
        $this->add_condition(new report_filter(
            text::class,
            'tool_datastore_course_fullname_condition',
            new \lang_string('conditiondatastorecoursefullname', 'tool_reportbuilder'),
            'tool_datastore_course',
            self::get_filter_field_sql('course', 'fullname', $this->get_main_table_alias(),
                null, constants::DB_TYPE_TEXT)
        ));
    }

    /**
     * Set the available filters of the datasource
     *
     * @return void
     */
    protected function set_filters() : void {
        $maintablealias = $this->get_main_table_alias();

        // Datastore course filters.
        $this->add_filter(new report_filter(
            text::class,
            'tool_datastore_course_fullname_filter',
            new \lang_string('conditiondatastorecoursefullname', 'tool_reportbuilder'),
            'tool_datastore_course',
            self::get_filter_field_sql('course', 'fullname', $maintablealias,
                null, constants::DB_TYPE_TEXT)
        ));

        // Datastore user filters.
        $this->add_filter(new report_filter(
            text::class,
            'tool_datastore_user_firstname_filter',
            new \lang_string('conditiondatastoreuserfirstname', 'tool_reportbuilder'),
            'tool_datastore_user',
            self::get_filter_field_sql('user', 'firstname', $maintablealias, 'relateduserid',
                constants::DB_TYPE_TEXT)
        ));

        $this->add_filter(new report_filter(
            text::class,
            'tool_datastore_user_lastname_filter',
            new \lang_string('conditiondatastoreuserlastname', 'tool_reportbuilder'),
            'tool_datastore_user',
            self::get_filter_field_sql('user', 'lastname', $maintablealias, 'relateduserid',
                constants::DB_TYPE_TEXT)
        ));
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