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
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;

defined('MOODLE_INTERNAL') || die();

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
