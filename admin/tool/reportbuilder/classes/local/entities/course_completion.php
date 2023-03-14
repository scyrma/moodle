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
 * Class containing course completion entity
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
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
global $CFG;
require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
/**
 * Datasource class
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_completion extends entity_base {

    /** @var string $lastenroldatefield */
    protected $lastenroldatefield;

    /** @var string $lastenroldatejoin */
    protected $lastenroldatejoin;


    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'course_completion' => 'cc',
            'course' => 'c',
            'grade_grades' => 'gg',
            'grade_items' => "gi",
            "course_completion_criteria" => "ccc"
        ];
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
     * @param string|null $lastenroldatejoin If the lastenroldatefield needs an extra join, pass it here
     * @return self
     */
    public function set_last_enroldate_field_sql(string $lastenroldatefield, ?string $lastenroldatejoin = null): self {
        $this->lastenroldatefield = $lastenroldatefield;
        $this->lastenroldatejoin = (string) $lastenroldatejoin;

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
        $tablealiasgrade = $this->get_table_alias('grade_grades');
        $tablealiasgradeitem = $this->get_table_alias('grade_items');
        $tablealiascompletioncriteria = $this->get_table_alias('course_completion_criteria');

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
                new lang_string("course_completion_{$column}", 'tool_reportbuilder'),
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
                ->add_join($this->lastenroldatejoin)
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

        // Student course grade.
        $columns[] = (new report_column(
            'grade',
            new lang_string('gradenoun'),
            $this->get_entity_name()
        ))
            ->add_join("LEFT JOIN {grade_items} $tablealiasgradeitem ON ($tablealiasgradeitem.itemtype = 'course'
                 AND $tablealiascourse.id = $tablealiasgradeitem.courseid )")
            ->add_join("LEFT JOIN {grade_grades} $tablealiasgrade ON ( u.id = $tablealiasgrade.userid
                 AND $tablealiasgradeitem.id = $tablealiasgrade.itemid )")
            ->add_joins($this->get_joins())
            ->add_fields("$tablealiasgrade.finalgrade")
            ->set_type(constants::DB_TYPE_NUMBER)
            ->set_is_sortable(true)
            ->add_callback(function ($value) {
                if (!$value) {
                    return '';
                }
                return format_float($value, 2);
            });

        // Required grade.
        $criteriatype = COMPLETION_CRITERIA_TYPE_GRADE;
        $columns[] = (new report_column(
            'requiredgrade',
            new lang_string('graderequired', 'completion'),
            $this->get_entity_name()
        ))
            ->add_join("LEFT JOIN {course_completion_criteria} $tablealiascompletioncriteria
                         ON ($tablealiascourse.id = $tablealiascompletioncriteria.course
                         AND $tablealiascompletioncriteria.criteriatype = $criteriatype
                         AND $tablealiascompletioncriteria.module IS NULL)")
            ->add_joins($this->get_joins())
            ->add_field("$tablealiascompletioncriteria.gradepass")
            ->set_type(constants::DB_TYPE_NUMBER)
            ->set_is_sortable(true)
            ->add_callback(function ($value) {
                if (!$value) {
                    return '';
                }
                return format_float($value, 2);
            });

        return $columns;
    }

    /**
     * Return available filters/conditions
     *
     * @param bool $iscondition
     *
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
