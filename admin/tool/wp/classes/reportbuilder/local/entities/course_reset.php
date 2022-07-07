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

namespace tool_wp\reportbuilder\local\entities;

use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\helpers\format;
use tool_wp\reportbuilder\local\formatters\course_reset as course_reset_format;

/**
 * Course reset entity class implementation
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_reset extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_wp_course_reset' => 'twpcr'];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entitycoursereset', 'tool_wp');
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

        $coursereset = $this->get_table_alias('tool_wp_course_reset');

        // Column reason for course reset.
        $columns[] = (new column(
            'reason',
            new lang_string('reason', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$coursereset}.reason")
            ->add_callback([course_reset_format::class, 'format_string']);

        // Column time requested.
        $columns[] = (new column(
            'timerequested',
            new lang_string('timerequested', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$coursereset}.timerequested")
            ->add_callback([format::class, 'userdate']);

        // Column was completed.
        $columns[] = (new column(
            'wascompleted',
            new lang_string('wascompleted', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$coursereset}.wascompleted")
            ->add_callback([format::class, 'boolean_as_text']);

        // Column grade.
        $columns[] = (new column(
            'grade',
            new lang_string('grade', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$coursereset}.grade")
            ->add_callback([course_reset_format::class, 'format_string']);

        // Column resetstatus.
        $columns[] = (new column(
            'resetstatus',
            new lang_string('resetstatus', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$coursereset}.resetstatus")
            ->add_callback([course_reset_format::class, 'reset_status'])
            ->set_disabled_aggregation_all();

        // Column resetinfo.
        $columns[] = (new column(
            'resetinfo',
            new lang_string('resetinfo', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$coursereset}.resetinfo")
            ->add_callback([course_reset_format::class, 'reset_info'])
            ->set_disabled_aggregation_all();

        // Column time reset.
        $columns[] = (new column(
            'timereseted',
            new lang_string('timereseted', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$coursereset}.timecreated", 'timereseted')
            ->add_callback([format::class, 'userdate']);

        return $columns;
    }

    /**
     * Return list of all available filters.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $filters = [];
        $coursereset = $this->get_table_alias('tool_wp_course_reset');

        // Filter for timereseted.
        $filters[] = (new filter(
            date::class,
            'timereseted',
            new lang_string('timereseted', 'tool_wp'),
            $this->get_entity_name(),
            "{$coursereset}.timecreated"
        ))
            ->add_joins($this->get_joins());

        // Filter Was completed.
        $filters[] = (new filter(
            boolean_select::class,
            'wascompleted',
            new lang_string('wascompleted', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("(CASE WHEN ({$coursereset}.wascompleted > 0) THEN 1 ELSE 0 END)");

        return $filters;
    }
}
