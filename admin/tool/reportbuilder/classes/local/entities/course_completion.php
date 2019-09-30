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
 */
class course_completion extends entity_base {

    /** @var string  */
    protected $join = '';

    /** @var string  */
    protected $tablealias = 'cc';

    /** @var array  */
    protected $excludefields = [];

    /** @var string  */
    protected $tablealiascourse = 'c';

    /** @var string  */
    protected $lastenroldatefield;

    /**
     * Constructor
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludefields
     * @param string $tablealiascourse
     * @param string $lastenroldatefield SQL for the field that represents the start of the last enrolment
     */
    public function __construct(string $join, string $tablealias = 'cc', array $excludefields = [],
            string $tablealiascourse = 'c', string $lastenroldatefield = null) {
        $this->join = $join;
        $this->tablealias = $tablealias;
        $this->excludefields = array_combine($excludefields, $excludefields);
        $this->tablealiascourse = $tablealiascourse;
        $this->lastenroldatefield = $lastenroldatefield;
    }

    /**
     * Entity name
     *
     * @return string
     */
    public function get_entity_name() : string {
        return 'course_completion';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    public function get_entity_title() : lang_string {
        return new lang_string('entitycoursecompletion', 'tool_reportbuilder');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns() : array {
        // Completed status.
        $columns[] = (new report_column(
            'completed',
            new lang_string('completed', 'completion'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->add_field("CASE WHEN {$this->tablealias}.timecompleted > 0 THEN 1 ELSE 0 END", 'completed')
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'checkbox_as_text']);

        // Progress.
        $column = (new report_column(
            'progress',
            new lang_string('course_completion_progress', 'tool_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->add_field("{$this->tablealiascourse}.id", 'courseid')
            ->add_field("{$this->tablealiascourse}.enablecompletion")
            ->add_field("{$this->tablealias}.userid")
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
            ->add_join($this->join)
            ->add_field("{$this->tablealiascourse}.id", 'courseid')
            ->add_field("{$this->tablealiascourse}.enablecompletion")
            ->add_field("{$this->tablealias}.userid")
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
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->add_field("{$this->tablealias}.{$column}")
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
            ->add_join($this->join)
            ->add_field("(
                CASE
                    WHEN {$this->tablealias}.timecompleted > 0 THEN
                        {$this->tablealias}.timecompleted
                    ELSE
                        {$currenttime}
                END - {$this->tablealiascourse}.startdate) / " . DAYSECS, 'dayscourse')
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
                ->add_join($this->join)
                ->add_field("(
                CASE
                    WHEN {$this->tablealias}.timecompleted > 0 THEN
                        {$this->tablealias}.timecompleted
                    ELSE
                        {$currenttime}
                END - {$this->lastenroldatefield}) / " . DAYSECS, 'daysenrolled')
                ->set_type(constants::DB_TYPE_NUMBER)
                ->set_is_sortable(true)
                ->add_callback([format::class, 'days']);

            return $columns;
        }
    }

    /**
     * Return available filters/conditions
     *
     * @param bool $iscondition
     * @return report_filter[]
     */
    protected function get_filters_or_conditions(bool $iscondition) : array {
        // Completed status.
        $filters[] = (new report_filter(
            checkbox::class,
            'completed_filter',
            new lang_string('completed', 'completion'),
            $this->get_entity_name(),
            "CASE WHEN {$this->tablealias}.timecompleted > 0 THEN 1 ELSE 0 END"
        ))->add_join($this->join);

        // Time completed.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecompleted_filter',
            new lang_string('course_completion_timecompleted', 'tool_reportbuilder'),
            $this->get_entity_name(),
            "{$this->tablealias}.timecompleted"
        ))->add_join($this->join);

        return $filters;
    }

    /**
     * Returns all available conditions
     *
     * @return report_filter[]
     */
    public function get_conditions() : array {
        return $this->get_filters_or_conditions(true);
    }

    /**
     * Returns all available filters
     *
     * @return report_filter[]
     */
    public function get_filters() : array {
        return $this->get_filters_or_conditions(false);
    }
}