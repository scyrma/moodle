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
 * File for class programcompletion_format
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\local\helpers;

use DateTime;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Class programcompletion_format
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programcompletion_format {

    /**
     * Displays column completed.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function completed(?string $value, stdClass $row): string {
        return format::yesno(
            0 < (int) $value,
            get_string('completed', 'tool_program'),
            get_string('notcompleted', 'tool_program')
        );
    }

    /**
     * Displays column completeddate.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function completeddate(?string $value, stdClass $row): string {
        return format::date((int) $row->completeddate);
    }

    /**
     * Returns formatted time modified
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function timemodified(?string $value, stdClass $row): string {
        return format::date((int) $row->timemodified);
    }

    /**
     * Returns formatted time created
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function timecreated(?string $value, stdClass $row): string {
        return format::date((int) $row->timecreated);
    }

    /**
     * Returns formatted daystakingprogram
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function daystakingprogram(string $value, stdClass $row): string {
        $firsttimestamp = (int) $row->startdate;
        if (!(0 < $firsttimestamp)) {
            // If there is no start date set use allocation date.
            $firsttimestamp = (int) $row->timecreated;
        }
        $secondtimestamp = (int) $row->completeddate;
        if (!(0 < $secondtimestamp)) {
            $secondtimestamp = time();
        }
        $firstdate = new DateTime('@' . $firsttimestamp);
        $seconddate = new DateTime('@' . $secondtimestamp);

        $daysint = $seconddate->diff($firstdate)->format("%a");
        return ($daysint > 0) ? $daysint : get_string('lessthanaday', 'tool_program');
    }

    /**
     * Returns formatted dayssincelastallocation
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function dayssinceallocation(string $value, stdClass $row): string {
        $firsttimestamp = (int) $row->timecreated;
        if (!(0 < $firsttimestamp)) {
            return '';
        }
        $secondtimestamp = (int) $row->completeddate;
        if (!(0 < $secondtimestamp)) {
            $secondtimestamp = time();
        }
        $firstdate = new DateTime('@' . $firsttimestamp);
        $seconddate = new DateTime('@' . $secondtimestamp);

        $daysint = $seconddate->diff($firstdate)->format("%a");
        return ($daysint > 0) ? $daysint : get_string('lessthanaday', 'tool_program');
    }
}
