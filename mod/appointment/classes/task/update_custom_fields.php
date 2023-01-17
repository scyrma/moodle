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
 * Appointment ad-hoc task for adding necessary custom fields for emails.
 *
 * @package     mod_appointment
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Odei Alba
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\task;

/**
 * Ad-hoc task class
 *
 * @package     mod_appointment
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Odei Alba
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_custom_fields extends \core\task\adhoc_task {

    /**
     * Execute the task
     *
     * @return void
     */
    public function execute() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/appointment/lib.php');

        $appointments = $DB->get_records('appointment');
        $defaultcolumns = \mod_appointment\form\messages::get_defaults();
        $allmatches = [];
        foreach ($appointments as $appointment) {
            $appointmentmatches = [];
            $appointmentclean = array_intersect_key((array) $appointment, array_flip(array_keys($defaultcolumns)));
            $appointmentsstring = implode(' ', $appointmentclean);
            preg_match_all("/\[session\:(.*?)\]/m", $appointmentsstring, $appointmentmatches);
            $allmatches = array_merge($allmatches, $appointmentmatches[1]);
        }
        $fields = array_unique($allmatches);

        if (empty($fields)) {
            return;
        }

        appointment_create_customfields($fields);
    }
}
