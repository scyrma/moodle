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

namespace mod_appointment\reportbuilder\local\systemreports;

use context_module;
use lang_string;
use stdClass;
use core_reportbuilder\system_report;
use core_reportbuilder\local\report\filter;
use mod_appointment\output\sessions_list_item;
use mod_appointment\reportbuilder\local\entities\session_date;
use mod_appointment\reportbuilder\local\filters\datestart;

/**
 * System report for showing session of a given appointment activity
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessions extends system_report {

    /** @var \stdClass current session */
    protected $currentsession = null;

    /** @var array|bool booking submission for the current user */
    protected $usersubmission = false;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $appointmentid = $this->get_parameter('appointmentid', 0, PARAM_INT);
        [$course, $coursemodule] = get_course_and_cm_from_instance($appointmentid, 'appointment');

        $this->set_main_table('appointment_sessions', 's');

        $this->add_base_condition_simple('s.appointment', $appointmentid);

        // Add required fields for actions/callbacks.
        $this->add_base_fields('s.id');

        // Prepare data.
        $this->set_usersubmission($appointmentid);

        // Add session date entity.
        $sessiondateentity = new session_date();
        $sessiondatealias = $sessiondateentity->get_table_alias('appointment_sessions_dates');

        $this->add_entity($sessiondateentity
            ->add_join("LEFT JOIN (
                    SELECT sessionid, MIN(timestart) AS timestart, MAX(timefinish) AS timefinish
                      FROM {appointment_sessions_dates}
                  GROUP BY sessionid
              ) {$sessiondatealias} ON {$sessiondatealias}.sessionid = s.id"));

        $this->add_columns($coursemodule->context);
        $this->add_filters($sessiondateentity);

        $this->set_downloadable(false);
    }

    /**
     * Override default paging value (TODO: re-factor once MDL-73184 lands)
     *
     * @return int
     */
    public function get_default_per_page(): int {
        return 10;
    }

    /**
     * Sets current user booking submission.
     *
     * @param  int $appointmentid
     */
    private function set_usersubmission($appointmentid) {
        global $USER;
        if ($submissions = appointment_get_user_submissions($appointmentid, $USER->id)) {
            $this->usersubmission = array_shift($submissions);
        }
    }

    /**
     * Populates session container.
     *
     * @param int $timestart
     * @param stdClass $row
     * @param context_module $contextmodule
     * @return string
     */
    public function col_container(?int $timestart, stdClass $row, context_module $contextmodule): string {
        global $OUTPUT, $PAGE;

        $context = new sessions_list_item($this->currentsession, $this->usersubmission, $contextmodule);
        $renderer = $PAGE->get_renderer('core');

        return $OUTPUT->render_from_template('mod_appointment/session_item/main', $context->export_for_template($renderer));
    }

    /**
     * Validates access to view this report with the given parameters
     */
    public function can_view(): bool {
        $appointmentid = $this->get_parameter('appointmentid', 0, PARAM_INT);
        [$course, $coursemodule] = get_course_and_cm_from_instance($appointmentid, 'appointment');

        return \mod_appointment\permission::can_view_appointment($coursemodule->context);
    }

    /**
     * Adds report columns
     *
     * @param context_module $context
     */
    protected function add_columns(context_module $context): void {
        $this->add_column_from_entity('session_date:datestart')
            ->set_callback([$this, 'col_container'], $context);

        $this->set_initial_sort_column('session_date:datestart', SORT_ASC);
    }

    /**
     * Adds report filters
     *
     * @param session_date $sessiondateentity
     */
    protected function add_filters(session_date $sessiondateentity): void {

        // Date start.
        $this->add_filter((new filter(
            datestart::class,
            'datestart',
            new lang_string('sessionstartdate', 'mod_appointment'),
            $sessiondateentity->get_entity_name(),
        ))
            ->set_options([
                'sessionfieldsql' => 's.id',
            ])
            ->set_limited_operators([
                datestart::DATE_ANY,
                datestart::DATE_RANGE,
                datestart::DATE_CURRENT,
                datestart::DATE_LAST,
                datestart::DATE_NEXT,
            ])
        );

        // Filter session status.
        $this->add_filter($sessiondateentity->get_filter('status'));
    }

    /**
     * Get data prior to row rendering.
     *
     * @param \stdClass $row
     */
    public function row_callback(\stdClass $row): void {
        $this->currentsession = appointment_get_session($row->id);
    }

    /**
     * Remove the table-stripped and table-hover effects.
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        return 'bg-white session-row';
    }
}
