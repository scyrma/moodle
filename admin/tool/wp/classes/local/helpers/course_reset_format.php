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
 * File for course_reset_format
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\helpers;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use tool_wp\course_reset_api;

/**
 * Class course_reset_format
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_reset_format {

    /**
     * Displays column reset info.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function reset_info(string $value, stdClass $row): string {
        if (!$row->resetinfo) {
            return '';
        }

        $resetinfo = json_decode($row->resetinfo);
        $output = '';
        foreach ($resetinfo as $reset) {
            if (isset($reset->plugin) && isset($reset->status)) {
                $output .= $reset->plugin . ': ' . course_reset_api::decode_statuses((int)$reset->status) . '<br>';
            }
        }
        return $output;
    }

    /**
     * Displays column reset status.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function reset_status($value, stdClass $row): string {
        if (!$row->resetstatus) {
            return '';
        }
        return course_reset_api::decode_statuses((int)$row->resetstatus);
    }
}
