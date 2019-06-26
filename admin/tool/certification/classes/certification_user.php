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
 * Class tool_certification\certification_user
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;

use core\persistent;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certification_user
 * @package tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certification_user extends persistent
{
    /**
     * Database table.
     */
    public const TABLE = 'tool_certification_users';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'certificationid' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'startdate' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'startdatelocked' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'duedate' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'duedatelocked' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'enddate' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'enddatelocked' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'expirydate' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'expirydatelocked' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'status' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'timesuspended' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'allocationtype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
        ];
    }

    /**
     * Gets certification.
     *
     * @return certification|false
     */
    public function get_certification() {
        return certification::get_record(['id' => $this->get('certificationid')]);
    }

    /**
     * Wether this allocation is suspended.
     *
     * @return bool
     */
    public function is_suspended(): bool {
        return constants::STATUS_OVERRIDE_SUSPENDED === (int) $this->get('status');
    }
}
