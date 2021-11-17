<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Plugin upgrade methods
 *
 * @package     tool_reportbuilder
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_reportbuilder\constants;

defined('MOODLE_INTERNAL') || die();

/**
 * Calculate the next time a schedule should be sent
 *
 * @see \tool_reportbuilder\local\helpers\schedules::calculate_next_send_time
 *
 * @param int $recurrence
 * @param int $scheduled
 * @param int $datenow Date to use for calculation (defaults to current date)
 * @return int
 */
function tool_reportbuilder_upgrade_calculate_next_send_time(int $recurrence, int $scheduled, int $datenow = 0): int {
    global $CFG;

    $datenow = $datenow ?: time();

    // If no recurrence is set or we haven't reached last sent date, return early.
    if ($recurrence == constants::RECURRENCE_NONE || $scheduled > $datenow) {
        return $scheduled;
    }

    // Extract attributes from date (year, month, day, hours, minutes).
    $datelastsentarray = usergetdate($scheduled, $CFG->timezone);
    $year = $datelastsentarray['year'];
    $month = $datelastsentarray['mon'];
    $day = $datelastsentarray['mday'];
    $hours = $datelastsentarray['hours'];
    $minutes = $datelastsentarray['minutes'];

    switch ($recurrence) {
        case constants::RECURRENCE_DAILY:
            $day += 1;

            break;
        case constants::RECURRENCE_DAILY_WEEKDAY:
            $day += 1;

            $calendar = \core_calendar\type_factory::get_calendar_instance();
            $weekend = get_config('core', 'calendar_weekend');

            // Increment day until dayofweek falls on a weekday.
            $dayofweek = $datelastsentarray['wday'];
            while ((bool) ($weekend & (1 << (++$dayofweek % $calendar->get_num_weekdays())))) {
                $day++;
            }

            break;
        case constants::RECURRENCE_WEEKLY:
            $day += 7;

            break;
        case constants::RECURRENCE_MONTHLY:
            $month += 1;

            break;
        case constants::RECURRENCE_ANNUALLY:
            $year += 1;

            break;
    }

    // We need to recursively increment the timestamp until we get one after $datenow.
    $timestamp = make_timestamp($year, $month, $day, $hours, $minutes, 0, $CFG->timezone);
    if ($timestamp < $datenow) {
        return tool_reportbuilder_upgrade_calculate_next_send_time($recurrence, $timestamp, $datenow);
    } else {
        return $timestamp;
    }
}
