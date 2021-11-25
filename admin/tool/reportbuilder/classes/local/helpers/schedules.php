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
 * Class schedules
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use tool_reportbuilder\constants;
use tool_reportbuilder\datasource;
use tool_reportbuilder\event\schedule_created;
use tool_reportbuilder\event\schedule_deleted;
use tool_reportbuilder\local\models\schedule;
use tool_reportbuilder\manager;
use tool_reportbuilder\reportbuilder;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class schedules
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class schedules {

    /**
     * Create a new schedule, calculate when it should be next sent
     *
     * @param \stdClass $data The schedule data to store
     * @return schedule
     */
    public static function add_schedule(\stdClass $data): schedule {
        $persistent = (new schedule(0, $data))->create();
        $persistent->set('nextsend', self::calculate_next_send_time(
            $persistent->get('recurrence'),
            $persistent->get('scheduled')
        ))->update();

        // Trigger schedule created event.
        $event = schedule_created::create_from_object($persistent);
        $event->trigger();

        return $persistent;
    }

    /**
     * Delete a schedule.
     *
     * @param int $scheduleid The id of the schedule to delete.
     * @return bool
     */
    public static function delete_schedule(int $scheduleid) : bool {
        $persistent = self::get_schedule($scheduleid);
        $event = schedule_deleted::create_from_object($persistent);

        if ($persistent->delete()) {
            $event->trigger();
            return true;
        }
        return false;
    }

    /**
     * Get a schedule persistent.
     *
     * @param int $scheduleid
     * @return schedule
     */
    public static function get_schedule(int $scheduleid): schedule {
        return new schedule($scheduleid);
    }

    /**
     * Get the formats to send a report.
     *
     * @param bool $onlyenabled
     * @return array
     * @throws \coding_exception
     */
    public static function get_formats($onlyenabled = true) : array {
        $formats = \core_plugin_manager::instance()->get_plugins_of_type('dataformat');
        $options = array();
        foreach ($formats as $format) {
            if ($format->is_enabled() || !$onlyenabled) {
                $options[$format->name] = get_string('dataformat', $format->component);
            }
        }

        return $options;
    }

    /**
     * Get the formats to send a report.
     *
     * @return array
     * @throws \coding_exception
     */
    public static function get_recurrences() : array {
        return [
            constants::RECURRENCE_NONE => get_string('recurrencedonorepeat', 'tool_reportbuilder'),
            constants::RECURRENCE_DAILY => get_string('recurrencedaily', 'tool_reportbuilder'),
            constants::RECURRENCE_DAILY_WEEKDAY => get_string('recurrencedailyweekday', 'tool_reportbuilder'),
            constants::RECURRENCE_WEEKLY => get_string('recurrenceweekly', 'tool_reportbuilder'),
            constants::RECURRENCE_MONTHLY => get_string('recurrencemonthly', 'tool_reportbuilder'),
            constants::RECURRENCE_ANNUALLY => get_string('recurrenceannualy', 'tool_reportbuilder'),
        ];
    }

    /**
     * Get the schedules
     *
     * @return schedule[]
     */
    public static function get_schedules() {
        return schedule::get_records();
    }

    /**
     * Get the visible name of the given format.
     *
     * @param string $value
     * @param array $row
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_format(string $value, $row = []) : string {
        $formats = self::get_formats(false);
        return $formats[$value];
    }

    /**
     * Send the schedule
     *
     * @param int $scheduleid
     * @param bool $forcesendnow Force the schedule to be sent, regardless of recurrence (e.g. from Ad-hoc task)
     * @return bool
     */
    public static function send(int $scheduleid, bool $forcesendnow = false): bool {
        global $USER;

        $schedule = self::get_schedule($scheduleid);
        $userid = $schedule->get('usercreated');

        // Return early if schedule doesn't need sending yet.
        if (!$forcesendnow && !self::needs_to_be_sent($schedule)) {
            return false;
        }

        // Pre-empt failures due to invalid report source. TODO: re-factor send helper and move this validation there.
        $reportpersistent = new reportbuilder($schedule->get('reportid'));

        // If report belongs to shared space and is not shared don't send schedule.
        if (sharedspace::is_shared_space($reportpersistent->get('tenantid')) && !$reportpersistent->get('shared')) {
            return false;
        }

        $originaluser = $USER;
        // We need to set the current user to that set in the "View report data as" field.
        $user = \core_user::get_user($userid);
        cron_setup_user($user);

        // AFTER setting the user we need to switch to the tenant of the schedule.
        if (!array_key_exists($reportpersistent->get('tenantid'), tenancy::get_tenants())) {
            throw new \moodle_exception('tenantnotfound', 'tool_tenant');
        }
        // TODO WP-2347 There is no validation that "view report as" user in schedule can access the report.
        tenancy::set_switched_tenant_id($reportpersistent->get('tenantid'));

        if (!manager::report_source_valid($reportpersistent->get('source'), datasource::class)) {
            return false;
        }

        if ($success = (new send($schedule))->sendemail()) {
            if (!$forcesendnow) {
                // The schedule was sent naturally (non-forced), so calculate when it should be next sent.
                $schedule->set('nextsend', self::calculate_next_send_time(
                    $schedule->get('recurrence'),
                    $schedule->get('scheduled')
                ));
            }

            $schedule->set('lastsenton', time());
            $schedule->update();
        }

        cron_setup_user($originaluser);

        return $success;
    }

    /**
     * Check if the schedule needs to be sent
     *
     * @param schedule $schedule
     * @return bool
     */
    public static function needs_to_be_sent(schedule $schedule): bool {
        // Disabled schedules don't need sending.
        if (!$schedule->get('enabled')) {
            return false;
        }

        $datenow = time();

        // If we haven't reached the initial scheduled date, we can return early.
        $datescheduled = $schedule->get('scheduled');
        if ($datenow < $datescheduled) {
            return false;
        }

        $datelastsent = $schedule->get('lastsenton');
        $recurrence = $schedule->get('recurrence');

        // If schedule has no recurrence and has never been sent, it can be sent now. Otherwise defer to next send time.
        if ($recurrence == constants::RECURRENCE_NONE) {
            return $datelastsent == -1;
        } else {
            return $datenow >= $schedule->get('nextsend');
        }
    }

    /**
     * Calculate the next time a schedule should be sent, based on it's recurrence and when it was initially scheduled. Ensures
     * returned value is greater than current date
     *
     * @param int $recurrence
     * @param int $scheduled
     * @param int $datenow Date to use for calculation (defaults to current date)
     * @return int
     */
    public static function calculate_next_send_time(int $recurrence, int $scheduled, int $datenow = 0): int {
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
            return self::calculate_next_send_time($recurrence, $timestamp, $datenow);
        } else {
            return $timestamp;
        }
    }
}
