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
 * Class course_reset
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

use core\persistent;

/**
 * Class course_reset
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_reset extends persistent {
    /**
     * Database table.
     */
    public const TABLE = 'tool_wp_course_reset';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'courseid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'userid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'programid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'optional' => true,
            ],
            'certificationid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'optional' => true,
            ],
            'reason' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'timerequested' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'userrequested' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'wascompleted' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'grade' => [
                'type' => PARAM_FLOAT,
                'optional' => true,
                'default' => 0,
            ],
            'resetstatus' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'resetinfo' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
        ];
    }
}