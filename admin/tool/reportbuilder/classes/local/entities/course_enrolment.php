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
 * Class containing course enrolment entity
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\entities;

use core_user\output\status_field;
use lang_string;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;

defined('MOODLE_INTERNAL') || die();

/**
 * Datasource class
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_enrolment extends entity_base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['user_enrolments' => 'ue', 'enrol' => 'e'];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'course_enrolment';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return \lang_string
     */
    protected function get_default_entity_title(): \lang_string {
        return new \lang_string('entitycourseenrolment', 'tool_reportbuilder');
    }

    /**
     * Executed when entity is added to the datasource or system report
     */
    public function add_to_report() {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $conditions = $this->get_filters_or_conditions(true);
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        $filters = $this->get_filters_or_conditions(false);
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }
    }

    /**
     * Generate SQL snippet suitable for returning enrolment status field
     *
     * @return string
     */
    private function get_status_field_sql() : string {
        $time = round(time(), -2); // Rounding helps caching in DB.
        $tablealias = $this->get_table_alias('user_enrolments');
        $tablealiasenrol = $this->get_table_alias('enrol');

        return  "
            CASE WHEN {$tablealias}.status = " . ENROL_USER_ACTIVE . "
                 THEN CASE WHEN ({$tablealias}.timestart > {$time})
                             OR ({$tablealias}.timeend > 0 AND {$tablealias}.timeend < {$time})
                             OR ({$tablealiasenrol}.status = " . ENROL_INSTANCE_DISABLED . ")
                           THEN " . status_field::STATUS_NOT_CURRENT . "
                           ELSE " . status_field::STATUS_ACTIVE . "
                      END
                 ELSE " . status_field::STATUS_SUSPENDED . "
            END";
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns() : array {
        $tablealias = $this->get_table_alias('user_enrolments');
        $tablealiasenrol = $this->get_table_alias('enrol');

        // Enrolment method.
        $columns[] = (new report_column(
            'method',
            new lang_string('enrolmentmethod', 'enrol'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$tablealiasenrol}.enrol")
            ->add_field("{$tablealias}.enrolid")
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'enrolment_name']);

        // Enrolment time created.
        $columns[] = (new report_column(
            'timecreated',
            new lang_string('enroltimecreated', 'enrol'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$tablealias}.timecreated")
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Enrolment time started.
        $columns[] = (new report_column(
            'timestarted',
            new lang_string('course_enrolment_timestarted', 'tool_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("CASE WHEN {$tablealias}.timestart = 0
                              THEN {$tablealias}.timecreated
                              ELSE {$tablealias}.timestart
                         END", 'timestarted')
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Enrolment time ended.
        $columns[] = (new report_column(
            'timeended',
            new lang_string('course_enrolment_timeended', 'tool_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$tablealias}.timeend")
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Enrolment status.
        $columns[] = (new report_column(
            'status',
            new lang_string('course_enrolment_status', 'tool_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field($this->get_status_field_sql(), 'status')
            ->set_type(constants::DB_TYPE_NUMBER)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'enrolment_status']);

        return $columns;
    }

    /**
     * Return available filters/conditions
     *
     * @param bool $iscondition
     * @return report_filter[]
     */
    protected function get_filters_or_conditions(bool $iscondition) : array {
        $tablealias = $this->get_table_alias('user_enrolments');
        $tablealiasenrol = $this->get_table_alias('enrol');

        // Enrolment method.
        $enrolmentmethods = array_map(function(\enrol_plugin $plugin) {
            return get_string('pluginname', 'enrol_' . $plugin->get_name());
        }, enrol_get_plugins(true));

        $filters[] = (new report_filter(
            select::class,
            'method_filter',
            new lang_string('enrolmentmethod', 'enrol'),
            $this->get_entity_name(),
            "{$tablealiasenrol}.enrol"
        ))
            ->add_joins($this->get_joins())
            ->set_options($enrolmentmethods);

        // Enrolment time created.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecreated_filter',
            new lang_string('enroltimecreated', 'enrol'),
            $this->get_entity_name(),
            "{$tablealias}.timecreated"
        ))
            ->add_joins($this->get_joins());

        // Enrolment time started.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timestarted_filter',
            new lang_string('course_enrolment_timestarted', 'tool_reportbuilder'),
            $this->get_entity_name(),
            "CASE WHEN {$tablealias}.timestart = 0
                  THEN {$tablealias}.timecreated
                  ELSE {$tablealias}.timestart
             END"
        ))
            ->add_joins($this->get_joins());

        // Enrolment time ended.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timeended_filter',
            new lang_string('course_enrolment_timeended', 'tool_reportbuilder'),
            $this->get_entity_name(),
            "{$tablealias}.timeend"
        ))
            ->add_joins($this->get_joins());

        // Enrolment status.
        $enrolmentstatuses = [
            status_field::STATUS_ACTIVE => get_string('participationactive', 'enrol'),
            status_field::STATUS_SUSPENDED => get_string('participationsuspended', 'enrol'),
            status_field::STATUS_NOT_CURRENT => get_string('participationnotcurrent', 'enrol'),
        ];

        $filters[] = (new report_filter(
            select::class,
            'status_filter',
            new lang_string('course_enrolment_status', 'tool_reportbuilder'),
            $this->get_entity_name(),
            $this->get_status_field_sql()
        ))
            ->add_joins($this->get_joins())
            ->set_options($enrolmentstatuses);

        return $filters;
    }
}