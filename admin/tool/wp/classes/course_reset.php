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

namespace tool_wp;

use core\persistent;

/**
 * Class course_reset
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
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
