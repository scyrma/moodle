<?php
// This file is part of Moodle - https://moodle.org/
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
 * Class schedules
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\helpers;

use tool_reportbuilder\constants;
use tool_reportbuilder\event\schedule_created;
use tool_reportbuilder\event\schedule_deleted;
use tool_reportbuilder\permission;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class schedules
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedules {

    /** @var int $scheduleid The schedule ID */
    protected $scheduleid;

    /**
     * schedules constructor.
     * @param int $scheduleid
     */
    public function __construct(int $scheduleid) {
        $this->scheduleid = $scheduleid;
    }

    /**
     * Create a new schedule.
     *
     * @param \stdClass $data The schedule data to store
     *
     * @return \tool_reportbuilder\local\models\schedules
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public static function add_schedule(\stdClass $data) {
        $persistent = new \tool_reportbuilder\local\models\schedules(0, $data);
        $persistent->create();

        // Trigger schedule created event.
        $event = schedule_created::create_from_object($persistent);
        $event->trigger();

        return $persistent;
    }

    /**
     * Delete a schedule.
     *
     * @param int $scheduleid The id of the schedule to delete.
     *
     * @return bool
     * @throws \coding_exception
     */
    public static function delete_schedule(int $scheduleid) : bool {
        $persistent = new \tool_reportbuilder\local\models\schedules($scheduleid);
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
     *
     * @return \tool_reportbuilder\local\models\schedules
     */
    public static function get_schedule(int $scheduleid) : \tool_reportbuilder\local\models\schedules {
        return new \tool_reportbuilder\local\models\schedules($scheduleid);
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
     * @return \core\persistent[]
     */
    public static function get_schedules() {
        return \tool_reportbuilder\local\models\schedules::get_records();
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
     * @return bool
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     */
    public function send(): bool {
        if (!$this->needs_to_be_sent()) {
            return true;
        }

        $sendhelper = new send($this->scheduleid);
        return $sendhelper->sendemail();
    }

    /**
     * Check if the schedule needs to be sent
     *
     * @return bool
     * @throws \coding_exception
     */
    public function needs_to_be_sent() : bool {
        $schedule = new \tool_reportbuilder\local\models\schedules($this->scheduleid);

        // Calculate current time down to previous minute.
        $datenow = time();
        $datenow -= ($datenow % MINSECS);

        // If we haven't reached the initial scheduled date, we can return early.
        if ($datenow < $schedule->get('scheduled')) {
            return false;
        }

        // If schedule hasn't ever been sent, it can be sent now.
        $datelastsent = $schedule->get('lastsenton');
        if ($datelastsent == 0) {
            return true;
        }

        // If no recurrence set, exit here.
        $recurrence = $schedule->get('recurrence');
        if ($recurrence == constants::RECURRENCE_NONE) {
            return false;
        }

        // If current time is greater than or equal to next send time, it can be sent.
        return ($datenow >= self::calculate_next_send_time($recurrence, $datelastsent));
    }

    /**
     * Calculate the next time I schedule should be sent, based on it's recurrence and the last time
     * it was sent
     *
     * @param int $recurrence
     * @param int $datelastsent
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private static function calculate_next_send_time(int $recurrence, int $datelastsent) : int {
        global $CFG;

        // Extract attributes from date (year, month, day, hours, minutes).
        $datelastsentarray = usergetdate($datelastsent, $CFG->timezone);
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

        return make_timestamp($year, $month, $day, $hours, $minutes, 0, $CFG->timezone);
    }
}