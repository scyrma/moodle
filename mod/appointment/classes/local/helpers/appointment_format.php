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
 * File for class appointment_format
 *
 * @package    mod_appointment
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\local\helpers;

use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot. '/mod/appointment/lib.php');

/**
 * Class appointment_format
 *
 * @package    mod_appointment
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appointment_format {

    /**
     * Returns status of the atendee.
     *
     * @param string|null $value
     * @param stdClass|null $row
     * @return string
     * @throws \coding_exception
     */
    public static function status(?string $value, ?stdClass $row): string {
        $statuses = appointment_statuses();

        // Check code exists.
        if (!isset($statuses[$value])) {
            return '-';
        }
        return get_string('status_' . $statuses[$value], 'appointment');
    }

    /**
     * Returns booked vs capacity
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function bookedvscapacity(string $value, stdClass $row): string {
        return $row->seatsbooked . ' / ' . $row->capacity;
    }

    /**
     * Returns Session date and times
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function sessiondatetime(string $value, stdClass $row): string {
        $formatdate = get_string('strftimedaydate', 'langconfig');
        $formattime = get_string('strftimetime', 'langconfig');
        $dateobj = [];
        $dateobj['startdate'] = userdate($row->timestart, $formatdate);
        $dateobj['starttime'] = userdate($row->timestart, $formattime);
        $dateobj['endtime'] = userdate($row->timefinish, $formattime);

        return get_string('sessionstartdateandtimewithouttimezone', 'mod_appointment', $dateobj);
    }

    /**
     * returns session status
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function sessionstatus(?string $value, stdClass $row): string {
        // TODO avoid extra db calls.
        if (!isset($row->sessionid)) {
            return '';
        }
        $session = appointment_get_session($row->sessionid);
        return appointment_get_session_info($session)['status'];
    }

    /**
     * Returns formatted description
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function description(?string $value, stdClass $row): string {
        if (empty($row->id)) {
            return '';
        }
        $contextid = \context_module::instance($row->id)->id;
        $description = file_rewrite_pluginfile_urls($row->details, 'pluginfile.php', $contextid,
            'mod_appointment', 'session', $row->appointmentid);
        return format_text($description, $row->detailsformat);
    }
}
