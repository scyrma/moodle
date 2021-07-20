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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Plugin upgrade methods
 *
 * @package     tool_reportbuilder
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
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

/**
 * Create instance of one of the new report audience types
 *
 * @param int $reportid
 * @param string $classname
 * @param array $configdata
 * @param int $usermodified
 * @return int ID of the new audience type record
 */
function tool_reportbuilder_upgrade_create_audience_type(int $reportid, string $classname, array $configdata,
        int $usermodified): int {

    global $DB;

    return $DB->insert_record('tool_reportbuilder_audiences', (object) [
        'reportid' => $reportid,
        'classname' => $classname,
        'configdata' => json_encode($configdata),
        'usermodified' => $usermodified,
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
}

/**
 * Create instance of one of the new schedule types, creating appropriate audiences along the way
 *
 * @param stdClass $schedule The original schedule record
 * @return int ID of the new schedule type record
 */
function tool_reportbuilder_upgrade_create_schedule_with_audiences(stdClass $schedule): int {
    global $DB;

    $audiences = [];

    // Create matching "Job" audience for the department, with "Any" position.
    if ($schedule->departmentid > 0) {
        $audienceclass = \tool_organisation\tool_reportbuilder\audiences\job::class;
        $audienceconfig = [
            'department' => ['id' => $schedule->departmentid],
            'position' => ['id' => 0],
        ];

        $audiences[] = tool_reportbuilder_upgrade_create_audience_type($schedule->reportid, $audienceclass, $audienceconfig,
            $schedule->usermodified);
    }

    // Create matching "Job" audience for the position, with "Any" department.
    if ($schedule->positionid > 0) {
        $audienceclass = \tool_organisation\tool_reportbuilder\audiences\job::class;
        $audienceconfig = [
            'position' => ['id' => $schedule->positionid],
            'department' => ['id' => 0],
        ];

        $audiences[] = tool_reportbuilder_upgrade_create_audience_type($schedule->reportid, $audienceclass, $audienceconfig,
            $schedule->usermodified);
    }

    // Create matching "Manual" audience for manually added user recipients.
    $recipients = json_decode($schedule->recipients);
    if (!empty($recipients->users)) {
        $audienceclass = \tool_reportbuilder\tool_reportbuilder\audiences\manual::class;
        $audienceconfig = ['users' => $recipients->users];

        $audiences[] = tool_reportbuilder_upgrade_create_audience_type($schedule->reportid, $audienceclass, $audienceconfig,
            $schedule->usermodified);
    }

    // We no longer allow manually added emails for schedules, so let the user who created this schedule know.
    $usercreated = \core_user::get_user($schedule->usercreated);
    if ($usercreated && !empty($recipients->emails)) {
        $task = new \tool_reportbuilder\task\notify_schedule_upgrade();
        $task->set_component('tool_reportbuilder');
        $task->set_userid($usercreated->id);
        $task->set_custom_data([
            'reportid' => $schedule->reportid,
            'schedulename' => $schedule->name,
            'emails' => $recipients->emails,
        ]);

        try {
            \core\task\manager::queue_adhoc_task($task);
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (moodle_exception $ex) {
            // Queueing an adhoc task for a user considered "non active" will throw an exception.
        }
    }

    // Remove old data from the schedule, replace with the new audiences we've created.
    unset($schedule->departmentid, $schedule->positionid, $schedule->recipients);
    $schedule->audiences = json_encode($audiences);

    return $DB->insert_record('tool_reportbuilder_schedule', $schedule);
}
