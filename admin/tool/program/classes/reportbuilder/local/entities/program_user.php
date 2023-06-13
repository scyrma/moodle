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

declare(strict_types=1);

namespace tool_program\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use tool_program\api;
use tool_program\constants;
use tool_program\reportbuilder\local\formatters\program_user as program_user_formatter;

/**
 * Program user entity class implementation
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 David Matamoros <davidmc@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_user extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_program_users' => 'tpu'];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityprogramusers', 'tool_program');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {

        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }

        foreach ($this->get_all_filters() as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $columns = [];
        $tablealias = $this->get_table_alias('tool_program_users');
        $dateformat = get_string('strftimedatefullshort', 'core_langconfig');

        // Start date column.
        $columns[] = (new column(
            'startdate',
            new lang_string('startdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.startdate, {$tablealias}.startdatelocked")
            ->set_is_sortable(true, ['startdate', 'startdatelocked'])
            ->add_callback([program_user_formatter::class, 'startdate']);

        // Due date column.
        $columns[] = (new column(
            'duedate',
            new lang_string('duedate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.duedate, {$tablealias}.duedatelocked")
            ->set_is_sortable(true, ['duedate', 'duedatelocked'])
            ->add_callback([program_user_formatter::class, 'duedate']);

        // End date column.
        $columns[] = (new column(
            'enddate',
            new lang_string('enddate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.enddate, {$tablealias}.enddatelocked")
            ->set_is_sortable(true, ['enddate', 'enddatelocked'])
            ->set_disabled_aggregation(['groupconcat', 'groupconcatdistinct'])
            ->add_callback([program_user_formatter::class, 'enddate']);

        // Column programstatus.
        $programset = database::generate_alias();
        $programcompletion = database::generate_alias();
        $columns[] = (new column(
            'programstatus',
            new lang_string('programstatus', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_joins($this->get_program_status_joins($programset, $programcompletion))
            ->set_type(column::TYPE_TEXT)
            ->add_field(api::get_status_sql_cases(0, $tablealias, $programcompletion), 'status')
            ->add_field("{$tablealias}.programid")
            ->set_is_sortable(true)
            ->add_callback([program_user_formatter::class, 'programstatus']);

        // Column programprogress.
        $columns[] = (new column(
            'programprogress',
            new lang_string('programprogress', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.programid, {$tablealias}.userid")
            ->add_callback([program_user_formatter::class, 'programprogress']);

        // Column programprogresswithoverview.
        $columns[] = (new column(
            'programprogresswithoverview',
            new lang_string('programprogresswithreportlinks', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.id, {$tablealias}.programid, {$tablealias}.userid, {$tablealias}.certificationid")
            ->add_callback([program_user_formatter::class, 'programprogressoverviewlink']);

        // Column suspended.
        $columns[] = (new column(
            'suspended',
            new lang_string('suspended', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_fields("{$tablealias}.status")
            ->add_callback([format::class, 'boolean_as_text']);

        // Column timesuspended.
        $columns[] = (new column(
            'timesuspended',
            new lang_string('timesuspended', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.timesuspended")
            ->add_callback([format::class, 'userdate'], $dateformat);

        // Column allocationtype.
        $columns[] = (new column(
            'allocationtype',
            new lang_string('allocationsource', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("{$tablealias}.allocationtype")
            ->set_is_sortable(true)
            ->add_callback([program_user_formatter::class, 'allocationtype']);

        // Column associated certification (if any).
        $columns[] = (new column(
            'associatedcertification',
            new lang_string('associatedcertifications', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.certificationid")
            ->add_callback([program_user_formatter::class, 'associatedcertification']);

        // Column timecreated.
        $columns[] = (new column(
            'timecreated',
            new lang_string('allocationdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.timecreated")
            ->add_callback([format::class, 'userdate'], $dateformat);

        // Column timemodified.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.timemodified")
            ->add_callback([format::class, 'userdate'], $dateformat);

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $filters = [];
        $tablealias = $this->get_table_alias('tool_program_users');

        // Allocationtype filter.
        $filters[] = (new filter(
            select::class,
            'allocationtype',
            new lang_string('allocationsource', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.allocationtype"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback([program_user_formatter::class, 'get_allocation_sources']);

        // Filter startdate.
        $filters[] = (new filter(
            date::class,
            'startdate',
            new lang_string('startdate', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.startdate"
        ))
            ->add_joins($this->get_joins());

        // Filter duedate.
        $filters[] = (new filter(
            date::class,
            'duedate',
            new lang_string('duedate', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.duedate"
        ))
            ->add_joins($this->get_joins());

        // Filter enddate.
        $filters[] = (new filter(
            date::class,
            'enddate',
            new lang_string('enddate', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.enddate"
        ))
            ->add_joins($this->get_joins());

        // Filter suspended.
        $filters[] = (new filter(
            boolean_select::class,
            'suspended',
            new lang_string('suspended', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.status"
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("CASE WHEN {$tablealias}.status = " . constants::STATUS_OVERRIDE_SUSPENDED .
                " THEN 1 ELSE 0 END");

        // Filter timesuspended.
        $filters[] = (new filter(
            date::class,
            'timesuspended',
            new lang_string('timesuspended', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.timesuspended"
        ))
            ->add_joins($this->get_joins());

        // Filter timecreated.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('allocationdate', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.timecreated"
        ))
            ->add_joins($this->get_joins());

        // Filter timemodified.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.timemodified"
        ))
            ->add_joins($this->get_joins());

        // Filter by status. Use generated alias to avoid possible conflicts with other table joins.
        $programset = database::generate_alias();
        $programcompletion = database::generate_alias();
        $filters[] = (new filter(
            select::class,
            'filterablestatus',
            new lang_string('programstatus', 'tool_program'),
            $this->get_entity_name(),
            api::get_status_sql_cases(0, $tablealias, $programcompletion)
        ))
            ->add_joins($this->get_joins())
            ->add_joins($this->get_program_status_joins($programset, $programcompletion))
            ->set_options_callback([api::class, 'get_program_statuses_fieldset']);

        return $filters;
    }

    /**
     * Get joins for program status
     *
     * @param string $programset table alias
     * @param string $programcompletion table alias
     * @return array
     */
    protected function get_program_status_joins(string $programset, string $programcompletion): array {
        $tablealias = $this->get_table_alias('tool_program_users');
        return [
            "LEFT JOIN {tool_program_sets} {$programset}
                       ON {$programset}.programid = {$tablealias}.programid AND {$programset}.parent = 0",
            "LEFT JOIN {tool_program_set_completion} {$programcompletion}
                       ON {$programcompletion}.setid = {$programset}.id AND {$programcompletion}.userid = {$tablealias}.userid"
        ];
    }
}
