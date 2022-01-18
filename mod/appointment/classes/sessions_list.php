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
 * Class sessions_list
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment;

use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use tool_wp\db;

/**
 * Class sessions_list
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessions_list extends system_report {
    /** @var \stdClass current session */
    protected $currentsession = null;

    /** @var array|bool booking submission for the current user */
    protected $usersubmission = false;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('appointment_sessions', 's');
        $appointmentid = $this->get_parameter('appointmentid', 0, PARAM_INT);
        $this->add_base_condition_simple('s.appointment', $appointmentid);
        $this->add_base_join('LEFT JOIN (
            SELECT sessionid, MIN(timestart) AS timestart
              FROM {appointment_sessions_dates}
          GROUP BY sessionid) sd ON (s.id = sd.sessionid)');
        $this->add_base_fields('s.id');
        $this->set_downloadable(false);
        $this->set_attributes(['class' => 'sessions-list']);

        // Prepare data.
        $this->set_usersubmission($appointmentid);

        // Add columns.
        $this->annotate_entity('session', new \lang_string('entitiysession', 'mod_appointment'));

        $showcheckboxes = false; // TODO: Prmissions check.
        if ($showcheckboxes) {
            $this->add_column((new report_column(
                'check',
                null,
                'session'
            ))
                ->add_fields('s.id')
                ->add_attributes(['class' => 'bg-white border-0'])
                ->set_is_default(true, 0)
                ->add_callback([$this, 'col_checkbox']));
        }

        $this->add_column((new report_column(
            'container',
            null,
            'session'
        ))
            ->add_fields('s.id, sd.timestart')
            ->set_is_default(true)
            ->set_is_sortable(true, true, null, null, ['sd.timestart'])
            ->add_attributes(['class' => 'bg-white w-100 border-0 pb-0'])
            ->add_callback([$this, 'col_container']));
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
     * Takes a column and creates a checkbox element with it.
     *
     * @param  int $value
     * @param  \stdClass $row
     * @return string The checkbox element.
     */
    public function col_checkbox(int $value, \stdClass $row) : string {
        $id = 'selectsession' . $value;
        $checkbox = \html_writer::checkbox('sessions[' . $value . ']', $value, false, null,
            ['id' => $id, 'data-bulksessionid' => $value]);
        $label = get_string('selectuser', 'tool_tenant', fullname($row));
        return $checkbox . \html_writer::tag('label', $label,
                ['for' => $id, 'class' => 'accesshide']);
    }

    /**
     * Populates session container.
     *
     * @param  int $value
     * @param  \stdClass $row
     * @return string
     */
    public function col_container(int $value, \stdClass $row) : string {
        global $OUTPUT, $PAGE;
        $context = new \mod_appointment\output\sessions_list_item($this->currentsession, $this->usersubmission, $PAGE->context);
        return $OUTPUT->render_from_template('mod_appointment/sessions_list_item',
            $context->export_for_template($OUTPUT));
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * This is necessary to implement here and not on the page that embeds the system report
     * because second and consequtive pages of the report are rendered via web services.
     *
     * To retrieve parameters values call $this->get_parameter()
     */
    public function can_view(): bool {
        global $PAGE;
        return \mod_appointment\permission::can_view_appointment($PAGE->context);
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
     * Report name
     *
     * @return string
     */
    public static function get_name() {
        return get_string('scheduledsessions', 'mod_appointment');
    }
}
