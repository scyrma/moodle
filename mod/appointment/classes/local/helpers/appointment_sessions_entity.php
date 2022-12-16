<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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
 * File for the class appointment_sessions_entity
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\local\helpers;

use lang_string;
use tool_reportbuilder\constants;
use tool_reportbuilder\convert_not_possible;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;

/**
 * Columns, filters and conditions that defines the appointment_sessions_entity and can be reused in any report datasource
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appointment_sessions_entity extends entity_base {

    /**
     * Which entity class from core reportbuilder should this entity be migrated to
     *
     * @return string name of the class extending {@see \core_reportbuilder\local\entities\entity_base}
     */
    public function convert_get_entity_class(): string {
        return \mod_appointment\reportbuilder\local\entities\session_date::class;
    }

    /**
     * The column name from the corresponding core reportbuilder entity
     *
     * @param report_column $oldcolumn
     * @param \core_reportbuilder\datasource $newsource
     * @return string
     */
    public function convert_get_column_name(report_column $oldcolumn, \core_reportbuilder\datasource $newsource): string {
        if ($oldcolumn->get_name() === 'sessionstartdate') {
            return 'datestart';
        } else if ($oldcolumn->get_name() === 'sessionstarttime') {
            return 'starttime';
        } else if ($oldcolumn->get_name() === 'sessionfinishtime') {
            return 'finishtime';
        } else if ($oldcolumn->get_name() === 'sessiondatetime') {
            // TODO WP-3789 implement the sessiondatetime field and remove this exception.
            $errorstring = get_string('columncannotbeconverted', 'tool_reportbuilder', $oldcolumn->get_unique_identifier());
            throw new convert_not_possible($errorstring);
        }

        return parent::convert_get_column_name($oldcolumn, $newsource);
    }

    /**
     * The filter name from the corresponding core reportbuilder entity
     *
     * Entities can override if some filters were renamed in the new core_reportbuilder entities
     *
     * @param \tool_reportbuilder\report_filter $oldfilter
     * @param \core_reportbuilder\datasource $newsource
     * @return string
     */
    public function convert_get_filter_name(report_filter $oldfilter, \core_reportbuilder\datasource $newsource): string {
        if ($oldfilter->get_name() === 'sessionstartdate') {
            return 'datestart';
        } else if ($oldfilter->get_name() === 'sessiondatetime') {
            $errorstring = get_string('filtercannotbeconverted', 'tool_reportbuilder', $oldfilter->get_unique_identifier());
            throw new convert_not_possible($errorstring);
        }
        return parent::convert_get_filter_name($oldfilter, $newsource);
    }

    /**
     * The condition name from the corresponding core reportbuilder entity
     *
     * Entities can override if some conditions were renamed in the new core_reportbuilder entities
     *
     * @param \tool_reportbuilder\report_filter $oldcondition $oldcolumn
     * @param \core_reportbuilder\datasource $newsource
     * @return string
     */
    public function convert_get_condition_name(report_filter $oldcondition, \core_reportbuilder\datasource $newsource): string {
        if ($oldcondition->get_name() === 'sessionstartdate') {
            return 'datestart';
        } else if ($oldcondition->get_name() === 'sessiondatetime') {
            $errorstring = get_string('conditioncannotbeconverted', 'tool_reportbuilder', $oldcondition->get_unique_identifier());
            throw new convert_not_possible($errorstring);
        }
        return parent::convert_get_condition_name($oldcondition, $newsource);
    }

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'appointment_sessions_dates' => 'apsd',
        ];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'appointment_sessions';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('appointmentsessions', 'mod_appointment');
    }

    /**
     * Executed when entity is added to the datasource or system report
     */
    public function add_to_report() {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }

        foreach ($this->get_filters_or_conditions(true) as $condition) {
            $this->add_condition($condition);
        }

        foreach ($this->get_filters_or_conditions(false) as $filter) {
            $this->add_filter($filter);
        }
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns(): array {
        $tablealias = $this->get_table_alias('appointment_sessions_dates');
        $columns = [];

        // Column sessionstartdate.
        $newcolumn = (new report_column(
            'sessionstartdate',
            new lang_string('sessionstartdate', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timestart")
            ->add_callback([format::class, 'userdate'], get_string('strftimedaydate'));
        $columns[] = $newcolumn;

        // Column sessionstarttime.
        $newcolumn = (new report_column(
            'sessionstarttime',
            new lang_string('sessionstarttime', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timestart")
            ->add_callback([format::class, 'userdate'], get_string('strftimetime'));
        $columns[] = $newcolumn;

        // Column sessionfinishtime.
        $newcolumn = (new report_column(
            'sessionfinishtime',
            new lang_string('sessionfinishtime', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timefinish")
            ->add_callback([format::class, 'userdate'], get_string('strftimetime'));
        $columns[] = $newcolumn;

        // Column sessiondatetime: date (timestart - timefinish).
        $newcolumn = (new report_column(
            'sessiondatetime',
            new lang_string('sessiondatetime', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.timestart")
            ->add_field("$tablealias.timefinish")
            ->add_callback([appointment_format::class, 'sessiondatetime'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');
        $columns[] = $newcolumn;

        return $columns;
    }

    /**
     * Filters/conditions for programs.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $tablealias = $this->get_table_alias('appointment_sessions_dates');
        $filters = [];

        // Filter sessionstartdate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'sessionstartdate',
            new lang_string('sessionstartdate', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timestart");

        // TODO Filter sessionstarttime after WP-1387 is done.
        // TODO Filter sessionfinishtime after WP-1387 is done.

        return $filters;
    }
}
