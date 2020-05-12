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
 * Class containing course completion entity
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\entities;

use lang_string;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;

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
class course_completion extends entity_base {

    /** @var string  */
    protected $lastenroldatefield;

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['course_completion' => 'cc', 'course' => 'c'];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'course_completion';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return \lang_string
     */
    protected function get_default_entity_title(): \lang_string {
        return new \lang_string('entitycoursecompletion', 'tool_reportbuilder');
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
     * Sets the SQL expression for the last enroldate (available outside of this join)
     *
     * @param string $lastenroldatefield
     * @return course_completion
     */
    public function set_last_enroldate_field_sql(string $lastenroldatefield): self {
        $this->lastenroldatefield = $lastenroldatefield;
        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns() : array {
        $tablealias = $this->get_table_alias('course_completion');
        $tablealiascourse = $this->get_table_alias('course');

        // Completed status.
        $columns[] = (new report_column(
            'completed',
            new lang_string('completed', 'completion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("CASE WHEN {$tablealias}.timecompleted > 0 THEN 1 ELSE 0 END", 'completed')
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'checkbox_as_text']);

        // Progress.
        $column = (new report_column(
            'progress',
            new lang_string('course_completion_progress', 'tool_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$tablealiascourse}.id", 'courseid')
            ->add_field("{$tablealiascourse}.enablecompletion")
            ->add_field("{$tablealias}.userid")
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_callback([format::class, 'completion_progress']);

        columns::disable_column_aggregation($column);
        $columns[] = $column;

        // Progress (percentage).
        $column = (new report_column(
            'progresspercent',
            new lang_string('course_completion_progress_percent', 'tool_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$tablealiascourse}.id", 'courseid')
            ->add_field("{$tablealiascourse}.enablecompletion")
            ->add_field("{$tablealias}.userid")
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_callback([format::class, 'completion_progress'], true);

        columns::disable_column_aggregation($column);
        $columns[] = $column;

        // Time enrolled/started/completed/reaggregated.
        $allcolumns = ['timeenrolled', 'timestarted', 'timecompleted', 'reaggregate'];
        foreach ($allcolumns as $column) {
            $columns[] = (new report_column(
                $column,
                new lang_string("course_completion_${column}", 'tool_reportbuilder'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->add_field("{$tablealias}.{$column}")
                ->set_is_sortable(true)
                ->add_callback([format::class, 'userdate']);
        }

        $currenttime = time();

        // Days taking course (days since course start date until completion or until current date if not completed).
        $columns[] = (new report_column(
            'dayscourse',
            new lang_string('course_completion_days_course', 'tool_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("(
                CASE
                    WHEN {$tablealias}.timecompleted > 0 THEN
                        {$tablealias}.timecompleted
                    ELSE
                        {$currenttime}
                END - {$tablealiascourse}.startdate) / " . DAYSECS, 'dayscourse')
            ->set_type(constants::DB_TYPE_NUMBER)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'days']);

        // Days since last enrolment (days since last enrolment date until completion or until current date if not completed).
        if ($this->lastenroldatefield) {
            $columns[] = (new report_column(
                'daysenrolled',
                new lang_string('course_completion_days_enrolled', 'tool_reportbuilder'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->add_field("(
                CASE
                    WHEN {$tablealias}.timecompleted > 0 THEN
                        {$tablealias}.timecompleted
                    ELSE
                        {$currenttime}
                END - {$this->lastenroldatefield}) / " . DAYSECS, 'daysenrolled')
                ->set_type(constants::DB_TYPE_NUMBER)
                ->set_is_sortable(true)
                ->add_callback([format::class, 'days']);

        }
        return $columns;
    }

    /**
     * Return available filters/conditions
     *
     * @param bool $iscondition
     * @return report_filter[]
     */
    protected function get_filters_or_conditions(bool $iscondition) : array {
        $tablealias = $this->get_table_alias('course_completion');
        // Completed status.
        $filters[] = (new report_filter(
            checkbox::class,
            'completed_filter',
            new lang_string('completed', 'completion'),
            $this->get_entity_name(),
            "CASE WHEN {$tablealias}.timecompleted > 0 THEN 1 ELSE 0 END"
        ))->add_joins($this->get_joins());

        // Time completed.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecompleted_filter',
            new lang_string('course_completion_timecompleted', 'tool_reportbuilder'),
            $this->get_entity_name(),
            "{$tablealias}.timecompleted"
        ))->add_joins($this->get_joins());

        return $filters;
    }
}