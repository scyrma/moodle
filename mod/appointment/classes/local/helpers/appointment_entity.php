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
 * File for the class appointment_entity
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
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\number;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\local\helpers\customfields;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the appointment_entity and can be reused in any report datasource
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appointment_entity extends entity_base {
    /** @var customfields */
    protected $customfields;

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'appointment_sessions' => 'aps',
            'appointment_sessions_dates' => 'apsd',
        ];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'appointment';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return \lang_string
     */
    protected function get_default_entity_title(): \lang_string {
        return new \lang_string('appointment', 'mod_appointment');
    }

    /**
     * Executed when entity is added to the datasource or system report
     */
    public function add_to_report() {
        $columns = array_merge($this->get_all_columns(), $this->get_custom_fields()->get_columns());
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $conditions = array_merge($this->get_filters_or_conditions(true), $this->get_custom_fields()->get_conditions());
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        $filters = array_merge($this->get_filters_or_conditions(false), $this->get_custom_fields()->get_filters());
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }
    }

    /**
     * Get the custom fields helper
     *
     * @return customfields
     */
    protected function get_custom_fields(): customfields {
        if ($this->customfields === null) {
            $tablealias = $this->get_table_alias('appointment_sessions');
            $this->customfields = new customfields($tablealias . '.id', $this->get_entity_name(),
                'mod_appointment', 'appointment', 0);
            $this->customfields->add_joins($this->get_joins());
        }
        return $this->customfields;
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns(): array {
        global $DB;

        $dbfamily = $DB->get_dbfamily();

        $tablealias = $this->get_table_alias('appointment_sessions');
        $columns = [];

        // Column details.
        $newcolumn = (new report_column(
            'details',
            new lang_string('description'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {modules} m ON m.name = 'appointment'")
            ->add_join("LEFT JOIN {course_modules} cm ON $tablealias.appointment = cm.instance
            AND cm.course = c.id AND cm.module = m.id")
            ->set_type(constants::DB_TYPE_LONGTEXT)
            ->add_field("$tablealias.details")
            ->add_field("$tablealias.detailsformat")
            ->add_field("$tablealias.appointment", 'appointmentid')
            ->add_field('cm.id')
            ->add_callback([appointment_format::class, 'description']);
        $columns[] = $newcolumn;

        // Column capacity.
        $newcolumn = (new report_column(
            'capacity',
            new lang_string('capacity', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_NUMBER)
            ->add_field("$tablealias.capacity");
        $columns[] = $newcolumn;

        // Column allowwaitlist.
        $newcolumn = (new report_column(
            'allowwaitlist',
            new lang_string('allowwaitlist', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$tablealias.allowwaitlist")
            ->add_callback([format::class, 'checkbox_as_text']);
        $columns[] = $newcolumn;

        // Column allowcancellations.
        $newcolumn = (new report_column(
            'allowcancellations',
            new lang_string('allowcancellations', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$tablealias.allowcancellations")
            ->add_callback([format::class, 'checkbox_as_text']);
        $columns[] = $newcolumn;

        // Column seatsbooked.
        $newcolumn = (new report_column(
            'seatsbooked',
            new lang_string('seatsbooked', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_NUMBER)
            ->add_field("(SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = $tablealias.id)", 'seatsbooked');

        // TODO: See WP-1397 - seats booked is already an aggregate function, so can't be grouped by in MSSQL/Oracle.
        if ($dbfamily === 'mssql' || $dbfamily === 'oracle') {
            $newcolumn->set_groupby_sql("{$tablealias}.id");
        }
        if ($dbfamily === 'mssql') {
            columns::disable_column_aggregation($newcolumn);
        }
        $columns[] = $newcolumn;

        // Column bookedvscapacity.
        $newcolumn = (new report_column(
            'bookedvscapacity',
            new lang_string('bookedvscapacity', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("(SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = $tablealias.id)", 'seatsbooked')
            ->add_field("$tablealias.capacity", 'capacity')
            ->add_callback([appointment_format::class, 'bookedvscapacity']);

        // TODO: See WP-1397 - booked vs capacity contains an aggregate function, so can't be grouped by in MSSQL/Oracle.
        if ($dbfamily === 'mssql' || $dbfamily === 'oracle') {
            $newcolumn->set_groupby_sql("{$tablealias}.id, {$tablealias}.capacity");
        }
        if ($dbfamily === 'mssql') {
            columns::disable_column_aggregation($newcolumn);
        }
        $columns[] = $newcolumn;

        // Column appointment status.
        $newcolumn = (new report_column(
            'status',
            new lang_string('status', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.id", 'sessionid')
            ->add_callback([appointment_format::class, 'sessionstatus']);
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
        $tablealias = $this->get_table_alias('appointment_sessions');
        $filters = [];

        // Filter capacity.
        $filters[] = (new report_filter(
            number::class,
            'capacity',
            new lang_string('capacity', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.capacity");

        // Filter allowwaitlist.
        $filters[] = (new report_filter(
            checkbox::class,
            'allowwaitlist',
            new lang_string('allowwaitlist', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.allowwaitlist");

        // Filter allowcancellations.
        $filters[] = (new report_filter(
            checkbox::class,
            'allowcancellations',
            new lang_string('allowcancellations', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.allowcancellations");

        // Filter seatsbooked.
        $filters[] = (new report_filter(
            number::class,
            'seatsbooked',
            new lang_string('seatsbooked', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("(SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = $tablealias.id)");

        // Filter sessionavailability.
        $filters[] = (new report_filter(
            select::class,
            'sessionavailability',
            new lang_string('sessionavailability', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("CASE WHEN
                (SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = $tablealias.id) >= $tablealias.capacity
                THEN 1
                WHEN
                (SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = $tablealias.id) = 0
                THEN 2
                WHEN
                ((SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = $tablealias.id) > 0 AND
                (SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = $tablealias.id) < $tablealias.capacity)
                THEN 3
                ELSE 0
            END")
            ->set_options_callback([static::class, 'get_sessionavailability_statuses']);

        return $filters;
    }

    /**
     * Returns session availability statuses for filter
     *
     * @return array
     */
    public static function get_sessionavailability_statuses(): array {
        return [
            1 => get_string('fullfilter', 'mod_appointment'),
            2 => get_string('empty', 'mod_appointment'),
            3 => get_string('partiallyfull', 'mod_appointment'),
        ];
    }
}
