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
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use mod_appointment\reportbuilder\local\formatters\appointment as appointment_formatter;

/**
 * Appointment atendee class implementation
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendee extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'appointment_signups' => 'apsg',
            'appointment_signups_status' => 'apsgst',
            'appointment_session_dates' => 'sd',
        ];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('attendees', 'mod_appointment');
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
        $tablealias = $this->get_table_alias('appointment_signups_status');

        // Column status.
        $columns[] = (new column(
            'status',
            new lang_string('status', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.statuscode")
            ->set_is_sortable(true)
            ->add_callback([appointment_formatter::class, 'status']);

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $tablealias = $this->get_table_alias('appointment_signups_status');

        // Filter status.
        $filters[] = (new filter(
            select::class,
            'status',
            new lang_string('status', 'mod_appointment'),
            $this->get_entity_name(),
            "{$tablealias}.statuscode"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback([appointment_formatter::class, 'get_appointment_statuses']);

        return $filters;
    }
}
