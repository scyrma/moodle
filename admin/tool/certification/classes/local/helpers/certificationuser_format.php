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
 * File for class certificationuser_format
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\helpers;

use coding_exception;
use html_writer;
use stdClass;
use tool_certification\api;
use tool_certification\constants;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certificationuser_format
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificationuser_format {
    /**
     * Displays allocation source.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function allocationtype(?string $value, stdClass $row): ?string {
        switch ((int)$row->allocationtype) {
            case constants::ALLOCATION_MANUAL:
                return get_string('manual', 'tool_certification');
                break;
            case constants::ALLOCATION_DYNAMIC:
                return get_string('dynamic', 'tool_certification');
                break;
            default:
                throw new \moodle_exception('errorallocationsourcenotfound', 'tool_certification');
                break;
        }
    }

    /**
     * Displays column startdate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function startdate(?string $value, stdClass $row): string {
        global $OUTPUT;
        $icon = '';
        if (1 === (int)$row->startdatelocked) {
            $icon = $OUTPUT->pix_icon('req', get_string('dateoverrided', 'tool_certification'));
        }
        if (0 === (int)$value) {
            return get_string('notset', 'tool_certification');
        }
        return userdate($row->startdate, get_string('strftimedatefullshort')) . ' ' . $icon;
    }

    /**
     * Displays column duedate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function duedate(?string $value, stdClass $row): string {
        global $OUTPUT;
        $icon = '';
        if (1 === (int)$row->duedatelocked) {
            $icon = $OUTPUT->pix_icon('req', get_string('dateoverrided', 'tool_certification'));
        }
        if (0 === (int)$value) {
            return get_string('notset', 'tool_certification');
        }
        return userdate($row->duedate, get_string('strftimedatefullshort')) . ' ' . $icon;
    }

    /**
     * Displays column expirydate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function expirydate(?string $value, stdClass $row): string {
        global $OUTPUT;
        if (!isset($row->userid) && !isset($row->certificationid)) {
            return '';
        }
        if (api::is_user_certified($row->userid, $row->certificationid)) {
            $icon = '';
            if (1 === (int)$row->expirydatelocked) {
                $icon = $OUTPUT->pix_icon('req', get_string('dateoverrided', 'tool_certification'));
            }
            if (0 === (int)$row->expirydate) {
                return get_string('never', 'tool_certification') . $icon;
            }
            return userdate($row->expirydate, get_string('strftimedatefullshort')) . $icon;
        }
        return '';
    }

    /**
     * Displays column status.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function status(?string $value, stdClass $row): string {
        $statuses = api::get_user_allocation_status($row->certificationid, $row->userid);
        $statuseshtml = [];
        foreach ($statuses as $status) {
            $statuseshtml[] = html_writer::span($status['statusstr'], $status['status']);
        }
        return implode(' ', $statuseshtml);
    }
}