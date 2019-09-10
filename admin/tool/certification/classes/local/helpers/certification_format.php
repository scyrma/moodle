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
 * File for class certification_format
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\helpers;

use coding_exception;
use core_tag_tag;
use core_text;
use html_writer;
use stdClass;
use tool_certification\constants;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certification_format
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certification_format {

    /**
     * Displays columns Certification Start Date on report.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     * @throws \moodle_exception
     */
    public static function startdate(?string $value, stdClass $row): ?string {
        switch ($row->startdatetype) {
            case constants::DATE_ABSOLUTE:
                return userdate($row->startdateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_USER_ALLOCATION_DATE:
                return get_string('allocationdate', 'tool_certification');
                break;
            case constants::DATE_RELATIVE_TO_ALLOCATION_DATE:
                $str = get_string('afterallocationdate', 'tool_certification');
                return $row->startdaterelative . ' ' . $str;
                break;
            default:
                return get_string('notset', 'tool_certification');
                break;
        }
    }

    /**
     * Displays columns Certification Due Date on report.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function duedate(?string $value, stdClass $row): string {
        if (isset($row->startdatetype) && (int) $row->startdatetype === constants::DATE_ABSOLUTE) {
            $duedate = strtotime('+' . $row->duedaterelative, $row->startdateabsolute);
            return userdate($duedate, get_string('strftimedatefullshort'));
        }
        $str = get_string('afterstartdate', 'tool_certification');
        return $row->duedaterelative . ' ' . core_text::strtolower($str);
    }

    /**
     * Displays columns Certification Expiry Date on report.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     * @throws \moodle_exception
     */
    public static function expirydate(?string $value, stdClass $row): ?string {
        switch ($row->expirydatetype) {
            case constants::DATE_NEVER:
                return get_string('never', 'tool_certification');
                break;
            case constants::DATE_ABSOLUTE:
                return userdate($row->expirydateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_AFTER_COMPLETION:
                $str = get_string('aftercompletion', 'tool_certification');
                return $row->expirydaterelative . ' ' . core_text::strtolower($str);
                break;
            case constants::DATE_AFTER_ALLOCATION_DATE:
                $str = get_string('afterallocationdate', 'tool_certification');
                return $row->expirydaterelative . ' ' . core_text::strtolower($str);
                break;
            case constants::DATE_AFTER_DUE_DATE:
                if ((int) $row->startdatetype === constants::DATE_ABSOLUTE) {
                    $duedate = strtotime('+' . $row->duedaterelative, $row->startdateabsolute);
                    $expirydate = strtotime('+' . $row->expirydaterelative, $duedate);
                    return userdate($expirydate, get_string('strftimedatefullshort'));
                }

                $str = get_string('afterduedate', 'tool_certification');
                return $row->expirydaterelative . ' ' . core_text::strtolower($str);
                break;
            default:
                return get_string('notset', 'tool_certification');
                break;
        }
    }
}