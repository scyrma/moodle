<?php
// This file is part of Moodle - http://moodle.org/
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
 * File for class relative_dates
 *
 * @package   tool_reportbuilder
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\helpers;

use DateTime;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Class relative_dates
 *
 * @package   tool_reportbuilder
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class relative_dates {

    /**
     * Returns the timestamp for the begining of the first day and the end of the last day of the requested period of time.
     *
     * Eg. get_start_and_end_timestamp_for('current', 'year');
     *
     * @param string $time
     * @param string $period
     * @return array
     * @throws \coding_exception
     */
    public static function get_start_and_end_timestamp_for(string $time, string $period): ?array {
        $time = strtolower($time);
        $period = strtolower($period);
        $validtimes = ['current', 'upcoming', 'previous'];
        $validperiods = ['day', 'week', 'month', 'quarter', 'year'];

        if (!in_array($time, $validtimes, true)) {
            throw new \coding_exception('Invalid time');
        }
        if (!in_array($period, $validperiods, true)) {
            throw new \coding_exception('Invalid period');
        }

        switch ($period) {
            case 'day':
                if ($time === 'current') {
                    $start = strtotime('midnight');
                    $end   = strtotime('tomorrow', $start) - 1;
                } else if ($time === 'previous') {
                    $start = strtotime('midnight', strtotime('-1 day'));
                    $end   = strtotime('midnight') - 1;
                } else if ($time === 'upcoming') {
                    $start = strtotime('midnight', strtotime('+1 day'));
                    $end   = strtotime('tomorrow', strtotime('+1 day')) - 1;
                }
                return [$start, $end];
                break;
            case 'week':
                [$firstday, $lastday] = self::get_week_dates($time);
                $start = strtotime('midnight', $firstday->getTimestamp());
                $end = strtotime('tomorrow', $lastday->getTimestamp()) - 1;
                return [$start, $end];
                break;
            case 'month':
                $timeparam = null;
                if ($time === 'previous') {
                    $timeparam = new DateTime('previous month');
                } else if ($time === 'upcoming') {
                    $timeparam = new DateTime('next month');
                }

                $start = self::first_day_of('month', $timeparam);
                $end = self::last_day_of('month', $timeparam);
                return [$start, $end];
                break;
            case 'quarter':
                $timeparam = null;
                if ($time === 'previous') {
                    $timeparam = new DateTime('3 months ago');
                } else if ($time === 'upcoming') {
                    $timeparam = new DateTime('+3 months');
                }

                $start = self::first_day_of('quarter', $timeparam);
                $end = self::last_day_of('quarter', $timeparam);
                return [$start, $end];
                break;
            case 'year':
                $timeparam = null;
                if ($time === 'previous') {
                    $timeparam = new DateTime('-1 year');
                } else if ($time === 'upcoming') {
                    $timeparam = new DateTime('+1 year');
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
     * @return array
     * @throws \Exception
     */
    private static function get_week_dates(string $time): array {
        $firstweekday = (int)calendar_get_starting_weekday();

        if ($time === 'current') {
            if ($firstweekday === 1) {
                $firstday = new DateTime('Monday this week');
                $lastday = new DateTime('Sunday this week');
            } else if ($firstweekday === 7) {
                $firstday = new DateTime('Sunday last week');
                $lastday = new DateTime('Saturday this week');
            }
        } else if ($time === 'previous') {
            if ($firstweekday === 1) {
                $firstday = new DateTime('Monday last week');
                $lastday = new DateTime('Sunday last week');
            } else if ($firstweekday === 7) {
                $firstday = new DateTime('Sunday 1 week ago');
                $lastday = new DateTime('Saturday last week');
            }
        } else if ($time === 'upcoming') {
            if ($firstweekday === 1) {
                $firstday = new DateTime('Monday next week');
                $lastday = new DateTime('Sunday next week');
            } else if ($firstweekday === 7) {
                $firstday = new DateTime('Sunday this week');
                $lastday = new DateTime('Saturday next week');
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
     * @param DateTime|null $date
     * @return int
     * @throws \coding_exception
     */
    private static function first_day_of(string $period, DateTime $date = null): int {
        $period = strtolower($period);
        $validperiods = array('month', 'quarter', 'year');

        if (!in_array($period, $validperiods, true)) {
            throw new \coding_exception('Invalid period of time');
        }

        $newdate = ($date === null) ? new DateTime() : clone $date;
        $format = $newdate->format('Y-m-d 00:00:00');

        switch ($period) {
            case 'month':
                $newdate->modify('first day of this month' . $format);
                break;
            case 'year':
                $newdate->modify('first day of january ' . $format);
                break;
            case 'quarter':
                $month = $newdate->format('n');

                if ($month < 4) {
                    $newdate->modify('first day of january ' . $format);
                } else if ($month > 3 && $month < 7) {
                    $newdate->modify('first day of april ' . $format);
                } else if ($month > 6 && $month < 10) {
                    $newdate->modify('first day of july ' . $format);
                } else if ($month > 9) {
                    $newdate->modify('first day of october ' . $format);
                }
                break;
        }

        return $newdate->getTimestamp();
    }

    /**
     * Returns the timestamp for the end of the last day of the requested period of time.
     *
     * Accepted periods are month, quarter and year.
     *
     * @param string $period
     * @param DateTime|null $date
     * @return int
     * @throws \coding_exception
     */
    private static function last_day_of(string $period, DateTime $date = null): int {
        $period = strtolower($period);
        $validperiods = array('year', 'quarter', 'month');

        if (!in_array($period, $validperiods, true)) {
            throw new \coding_exception('Invalid period of time');
        }

        $newdate = ($date === null) ? new DateTime() : clone $date;
        $format = $newdate->format('Y-m-d 23:59:59');

        switch ($period) {
            case 'year':
                $newdate->modify('last day of december ' . $format);
                break;
            case 'quarter':
                $month = $newdate->format('n');

                if ($month < 4) {
                    $newdate->modify('last day of march ' . $format);
                } else if ($month > 3 && $month < 7) {
                    $newdate->modify('last day of june ' . $format);
                } else if ($month > 6 && $month < 10) {
                    $newdate->modify('last day of september ' . $format);
                } else if ($month > 9) {
                    $newdate->modify('last day of december ' . $format);
                }
                break;
            case 'month':
                $newdate->modify('last day of this month' . $format);
                break;
        }

        return $newdate->getTimestamp();
    }
}