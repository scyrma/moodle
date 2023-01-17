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

declare(strict_types=1);

namespace mod_appointment\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\custom_fields;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use mod_appointment\reportbuilder\local\formatters\appointment as appointment_formatter;
use stdClass;

/**
 * Appointment session entity class implementation
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session extends base {

    /** @var custom_fields */
    protected $customfields;

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'appointment_sessions' => 'aps',
            'appointment_sessions_dates' => 'sd',
        ];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('appointmentsession', 'mod_appointment');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = array_merge($this->get_all_columns(), $this->get_custom_fields()->get_columns());
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = array_merge($this->get_all_filters(), $this->get_custom_fields()->get_filters());
        foreach ($filters as $filter) {
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
        global $DB;

        $dbfamily = $DB->get_dbfamily();
        $session = $this->get_table_alias('appointment_sessions');

        // Column details.
        $detailsfieldsql = "{$session}.details";
        if ($dbfamily === 'oracle') {
            $detailsfieldsql = $DB->sql_order_by_text($detailsfieldsql, 1024);
        }
        $columns[] = (new column(
            'details',
            new lang_string('description'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {modules} m ON m.name = 'appointment'")
            ->add_join("
                LEFT JOIN {course_modules} cm
                       ON {$session}.appointment = cm.instance AND cm.course = c.id AND cm.module = m.id
            ")
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field($detailsfieldsql, 'details')
            ->add_field("{$session}.detailsformat")
            ->add_field("{$session}.appointment", 'appointmentid')
            ->add_field('cm.id')
            ->set_is_sortable(false)
            ->add_callback([appointment_formatter::class, 'description']);

        // Column capacity.
        $columns[] = (new column(
            'capacity',
            new lang_string('capacity', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$session}.capacity")
            ->set_is_sortable(true);

        // Column allow wait listing.
        $columns[] = (new column(
            'allowwaitlist',
            new lang_string('allowwaitlist', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$session}.allowwaitlist")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'boolean_as_text']);

        // Column allow cancellations.
        $columns[] = (new column(
            'allowcancellations',
            new lang_string('allowcancellations', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$session}.allowcancellations")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'boolean_as_text']);

        // Column seatsbooked.
        $column = (new column(
            'seatsbooked',
            new lang_string('seatsbooked', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->set_is_sortable(true)
            ->add_field("(SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = {$session}.id)", 'seatsbooked');

        // TODO: See WP-1397 - seats booked is already an aggregate function, so can't be grouped by in MSSQL/Oracle.
        if ($dbfamily === 'mssql' || $dbfamily === 'oracle') {
            $column->set_groupby_sql("{$session}.id");
        }
        // MSSQL can not aggregate columns with sub-query, so we'll disable all aggregation methods for them.
        if ($dbfamily === 'mssql') {
            $column->set_disabled_aggregation_all();
        }
        $columns[] = $column;

        // Column bookedvscapacity.
        $column = (new column(
            'bookedvscapacity',
            new lang_string('bookedvscapacity', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("(SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = {$session}.id)", 'seatsbooked')
            ->add_field("{$session}.capacity", 'capacity')
            ->set_is_sortable(true)
            ->add_callback(static function(string $value, stdClass $row): string {
                return $row->seatsbooked . ' / ' . $row->capacity;
            });

        // TODO: See WP-1397 - booked vs capacity contains an aggregate function, so can't be grouped by in MSSQL/Oracle.
        if ($dbfamily === 'mssql' || $dbfamily === 'oracle') {
            $column->set_groupby_sql("{$session}.id, {$session}.capacity");
        }
        // MSSQL can not aggregate columns with sub-query, so we'll disable all aggregation methods for them.
        if ($dbfamily === 'mssql') {
            $column->set_disabled_aggregation_all();
        }
        $columns[] = $column;

        // Column session status.
        $columns[] = (new column(
            'status',
            new lang_string('sessionstatus', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$session}.id", 'sessionid')
            ->set_is_sortable(true)
            ->add_callback([appointment_formatter::class, 'sessionstatus']);

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $session = $this->get_table_alias('appointment_sessions');

        // Filter capacity.
        $filters[] = (new filter(
            number::class,
            'capacity',
            new lang_string('capacity', 'mod_appointment'),
            $this->get_entity_name(),
            "{$session}.capacity"
        ))
            ->add_joins($this->get_joins());

        // Filter allowwaitlist.
        $filters[] = (new filter(
            boolean_select::class,
            'allowwaitlist',
            new lang_string('allowwaitlist', 'mod_appointment'),
            $this->get_entity_name(),
            "{$session}.allowwaitlist"
        ))
            ->add_joins($this->get_joins());

        // Filter allowcancellations.
        $filters[] = (new filter(
            boolean_select::class,
            'allowcancellations',
            new lang_string('allowcancellations', 'mod_appointment'),
            $this->get_entity_name(),
            "{$session}.allowcancellations"
        ))
            ->add_joins($this->get_joins());

        // Filter seatsbooked.
        $filters[] = (new filter(
            number::class,
            'seatsbooked',
            new lang_string('seatsbooked', 'mod_appointment'),
            $this->get_entity_name(),
            "(SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = {$session}.id)"
        ))
            ->add_joins($this->get_joins());

        // Filter sessionavailability.
        $filters[] = (new filter(
            select::class,
            'sessionavailability',
            new lang_string('sessionavailability', 'mod_appointment'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("CASE WHEN
                (SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = {$session}.id) >= {$session}.capacity
                THEN 1
                WHEN
                (SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = {$session}.id) = 0
                THEN 2
                WHEN
                ((SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = {$session}.id) > 0 AND
                (SELECT COUNT(id) FROM {appointment_signups} WHERE sessionid = {$session}.id) < {$session}.capacity)
                THEN 3
                ELSE 0
            END")
            ->set_options_callback([appointment_formatter::class, 'get_sessionavailability_statuses']);

        return $filters;
    }

    /**
     * Get the custom fields helper
     *
     * @return custom_fields
     */
    protected function get_custom_fields(): custom_fields {
        if ($this->customfields === null) {
            $session = $this->get_table_alias('appointment_sessions');
            $this->customfields = new custom_fields("{$session}.id", $this->get_entity_name(),
                'mod_appointment', 'appointment', 0);
            $this->customfields->add_joins($this->get_joins());
        }
        return $this->customfields;
    }
}
