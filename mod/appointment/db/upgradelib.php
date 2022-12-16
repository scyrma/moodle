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
 * Appointment module upgrade helpers.
 *
 * @package    mod_appointment
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove orphaned user events following WP-3695.
 */
function mod_appointment_upgrade_remove_orphaned_user_events() {
    global $DB;

    // Delete orphaned user events.
    if ($appointmentids = $DB->get_fieldset_select('appointment', 'id', '')) {
        [$insql, $params] = $DB->get_in_or_equal($appointmentids, SQL_PARAMS_QM, 'param', false);
        $whereclause = "eventtype = 'appointmentbooking' AND instance $insql";
        $DB->delete_records_select('event', $whereclause, $params);
    } else {
        // No existing appointments, just clean up all appointmentbooking events.
        $whereclause = "eventtype = 'appointmentbooking'";
        $DB->delete_records_select('event', $whereclause, []);
    }
}
