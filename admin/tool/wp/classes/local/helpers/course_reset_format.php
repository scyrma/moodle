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
 * File for course_reset_format
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
}
