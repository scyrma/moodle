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
 * File for class relative_dates
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use core_date;
use DateTime;
use DateTimeZone;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Class relative_dates
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class relative_dates {

    /**
     * Returns the timestamp for the begining of the first day and the end of the last day of the requested period of time.
     *
     * Eg. get_start_and_end_timestamp_for('current', 'year');
     *
     * @param string $time
     * @param string $period
     * @param int $timenow Specify base time, defaults to current time
     * @return array
     * @throws \coding_exception
     */
    public static function get_start_and_end_timestamp_for(string $time, string $period, int $timenow = 0) : array {
        $timenow = $timenow ?: time();

        $time = strtolower($time);
        $period = strtolower($period);

        $validtimes = ['current', 'upcoming', 'previous'];
        if (!in_array($time, $validtimes, true)) {
            throw new \coding_exception('Invalid time', $time);
        }

        $validperiods = ['day', 'week', 'month', 'quarter', 'year'];
        if (!in_array($period, $validperiods, true)) {
            throw new \coding_exception('Invalid period', $period);
        }

        $usertimezone = core_date::get_user_timezone();

        switch ($period) {
            case 'day':
                if ($time === 'current') {
                    $start = strtotime('midnight', $timenow);
                    $end   = strtotime('tomorrow', $start) - 1;
                } else if ($time === 'previous') {
                    $start = strtotime('midnight', strtotime('-1 day', $timenow));
                    $end   = strtotime('midnight', $timenow) - 1;
                } else if ($time === 'upcoming') {
                    $start = strtotime('midnight', strtotime('+1 day', $timenow));
                    $end   = strtotime('tomorrow', strtotime('+1 day', $timenow)) - 1;
                }
                return [$start, $end];
                break;
            case 'week':
                [$firstday, $lastday] = self::get_week_dates($time, $timenow);
                $start = strtotime('midnight', $firstday->getTimestamp());
                $end = strtotime('tomorrow', $lastday->getTimestamp()) - 1;
                return [$start, $end];
                break;
            case 'month':
                $timeparam = DateTime::createFromFormat('U', $timenow);
                $timeparam->setTimezone(new DateTimeZone($usertimezone));
                [$year, $month] = explode('-', $timeparam->format('Y-m'));
                if ($time === 'previous') {
                    $timeparam->setDate($year, $month - 1, 1);
                } else if ($time === 'upcoming') {
                    $timeparam->setDate($year, $month + 1, 1);
                }
                $start = self::first_day_of('month', $timeparam);
                $end = self::last_day_of('month', $timeparam);
                return [$start, $end];
                break;
            case 'quarter':
                $timeparam = DateTime::createFromFormat('U', $timenow);
                $timeparam->setTimezone(new DateTimeZone($usertimezone))->modify('first day of this month');
                if ($time === 'previous') {
                    $timeparam->modify('-85 days');
                } else if ($time === 'upcoming') {
                    $timeparam->modify('+95 days');
                }

                $start = self::first_day_of('quarter', $timeparam);
                $end = self::last_day_of('quarter', $timeparam);
                return [$start, $end];
                break;
            case 'year':
                $timeparam = DateTime::createFromFormat('U', $timenow);;
                $timeparam->setTimezone(new DateTimeZone($usertimezone));
                if ($time === 'previous') {
                    $timeparam->modify('-1 year');
                } else if ($time === 'upcoming') {
                    $timeparam->modify('+1 year');
                }

                $start = self::first_day_of('year', $timeparam);
                $end = self::last_day_of('year', $timeparam);
                return [$start, $end];
                break;
            default:
                return [];
                break;
        }
    }

    /**
     * Calculate week dates.
     *
     * @param string $time
     * @param int $timenow
     * @return DateTime[]
     */
    private static function get_week_dates(string $time, int $timenow) : array {
        $firstweekday = (int)calendar_get_starting_weekday();

        $firstday = DateTime::createFromFormat('U', $timenow);
        $firstday->setTimezone(new \DateTimeZone(core_date::get_user_timezone()));
        $lastday = clone $firstday;

        if ($time === 'current') {
            if ($firstweekday === 1) {
                $firstday->modify('Monday this week');
                $lastday->modify('Sunday this week');
            } else if ($firstweekday === 7) {
                $firstday->modify('Sunday last week');
                $lastday->modify('Saturday this week');
            }
        } else if ($time === 'previous') {
            if ($firstweekday === 1) {
                $firstday->modify('Monday last week');
                $lastday->modify('Sunday last week');
            } else if ($firstweekday === 7) {
                $firstday->modify('Sunday 1 week ago');
                $lastday->modify('Saturday last week');
            }
        } else if ($time === 'upcoming') {
            if ($firstweekday === 1) {
                $firstday->modify('Monday next week');
                $lastday->modify('Sunday next week');
            } else if ($firstweekday === 7) {
                $firstday->modify('Sunday this week');
                $lastday->modify('Saturday next week');
            }
        }

        return [$firstday, $lastday];
    }

    /**
     * Returns the timestamp for the begining of the first day of the requested period of time.
     *
     * Accepted periods are month, quarter and year.
     *
     * @param string $period
     * @param DateTime $date
     * @return int
     * @throws \coding_exception
     */
    private static function first_day_of(string $period, DateTime $date) : int {
        $period = strtolower($period);
        $validperiods = array('month', 'quarter', 'year');

        if (!in_array($period, $validperiods, true)) {
            throw new \coding_exception('Invalid period of time');
        }

        switch ($period) {
            case 'month':
                $date->modify('first day of this month');
                break;
            case 'year':
                $date->modify('first day of january');
                break;
            case 'quarter':
                $month = $date->format('n');

                if ($month < 4) {
                    $date->modify('first day of january');
                } else if ($month > 3 && $month < 7) {
                    $date->modify('first day of april');
                } else if ($month > 6 && $month < 10) {
                    $date->modify('first day of july');
                } else if ($month > 9) {
                    $date->modify('first day of october');
                }
                break;
        }

        return strtotime('midnight', $date->getTimestamp());
    }

    /**
     * Returns the timestamp for the end of the last day of the requested period of time.
     *
     * Accepted periods are month, quarter and year.
     *
     * @param string $period
     * @param DateTime $date
     * @return int
     * @throws \coding_exception
     */
    private static function last_day_of(string $period, DateTime $date): int {
        $period = strtolower($period);
        $validperiods = array('year', 'quarter', 'month');

        if (!in_array($period, $validperiods, true)) {
            throw new \coding_exception('Invalid period of time');
        }

        switch ($period) {
            case 'year':
                $date->modify('last day of december');
                break;
            case 'quarter':
                $month = $date->format('n');

                if ($month < 4) {
                    $date->modify('last day of march');
                } else if ($month > 3 && $month < 7) {
                    $date->modify('last day of june');
                } else if ($month > 6 && $month < 10) {
                    $date->modify('last day of september');
                } else if ($month > 9) {
                    $date->modify('last day of december');
                }
                break;
            case 'month':
                $date->modify('last day of this month');
                break;
        }

        return strtotime('tomorrow', $date->getTimestamp()) - 1;
    }
}
