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
 * File for the class appointment_signups_entity
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace mod_appointment\local\helpers;

use lang_string;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot. '/mod/appointment/lib.php');

/**
 * Columns, filters and conditions that defines the appointment_signups_entity and can be reused in any report datasource
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class appointment_signups_entity extends entity_base {
    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'appointment_signups' => 'apsg',
            'appointment_signups_status' => 'apsgst',
            'appointment_session_dates' => 'apsd',
        ];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'appointment_signups';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return \lang_string
     */
    protected function get_default_entity_title(): \lang_string {
        return new \lang_string('attendees', 'mod_appointment');
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
        $columns = [];
        $tablealiasstatus = $this->get_table_alias('appointment_signups_status');
        $tablealiasdates = $this->get_table_alias('appointment_session_dates');

        // Column status.
        $newcolumn = (new report_column(
            'status',
            new lang_string('status', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealiasstatus.statuscode")
            ->add_callback([appointment_format::class, 'status']);
        $columns[] = $newcolumn;

        // Column timerequested.
        $newcolumn = (new report_column(
            'timerequested',
            new lang_string('timerequested', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealiasdates.timestart")
            ->add_callback([format::class, 'userdate'], get_string('strftimetime'));
        $columns[] = $newcolumn;

        // TODO Attendance.

        return $columns;
    }

    /**
     * Filters/conditions for programs.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];
        $tablealiasstatus = $this->get_table_alias('appointment_signups_status');
        $tablealiasdates = $this->get_table_alias('appointment_session_dates');

        // Filter timerequested.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timerequested',
            new lang_string('timerequested', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealiasdates.timestart");

        // Filter status.
        $filters[] = (new report_filter(
            select::class,
            'status',
            new lang_string('status', 'mod_appointment'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealiasstatus.statuscode")
            ->set_options($this->get_appointment_statuses());

        return $filters;
    }

    /**
     * Returns list of appointment statuses
     *
     * @return array
     * @throws \coding_exception
     */
    private function get_appointment_statuses(): array {
        $statuslist = [];
        foreach (appointment_statuses() as $key => $status) {
            $statuslist[$key] = get_string('status_' . $status, 'appointment');
        }
        return $statuslist;
    }
}