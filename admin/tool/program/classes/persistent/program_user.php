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
 * Program user class for tool_program
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\persistent;

use core\persistent;
use core_user;
use stdClass;
use tool_certification\certification;
use tool_program\constants;

/**
 * Class program_user
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_user extends persistent {
    /**
     * Database table.
     */
    public const TABLE = 'tool_program_users';

    /** @var program */
    protected $program;

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param stdClass $record If set will be passed to {@see self::from_record()}.
     */
    public function __construct(int $id = 0, stdClass $record = null) {
        if ($record) {
            $record = (object)array_intersect_key((array)$record, self::properties_definition());
        }
        if ($id && $record) {
            debugging('Either id or record need to be specified in the persistent constructor but not both',
                DEBUG_DEVELOPER);
        }
        parent::__construct($id, $record);
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'programid' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'certificationid' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'startdate' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'startdatelocked' => [
                'type' => PARAM_BOOL,
                'optional' => true,
                'default' => false,
            ],
            'duedate' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'duedatelocked' => [
                'type' => PARAM_BOOL,
                'optional' => true,
                'default' => false,
            ],
            'enddate' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'enddatelocked' => [
                'type' => PARAM_BOOL,
                'optional' => true,
                'default' => false,
            ],
            'status' => [
                'type' => PARAM_BOOL,
                'optional' => true,
                'default' => true,
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
     * Get program
     *
     * @return program|false
     */
    public function get_program() {
        if (!$this->program) {
            $this->program = program::get_record(['id' => $this->get('programid')]);
        }
        return $this->program;
    }

    /**
     * Sets the program to use in get_program()
     *
     * @param program $program
     */
    public function set_program(program $program) {
        $this->program = $program;
    }

    /**
     * Get user
     *
     * @param int $strictness
     * @return false|stdClass
     */
    public function get_user(int $strictness = IGNORE_MISSING) {
        return core_user::get_user($this->get('userid'), '*', $strictness);
    }

    /**
     * Get certification id
     *
     * @return certification|false
     */
    public function get_certification() {
        return certification::get_record(['id' => $this->get('certificationid')]);
    }

    /**
     * Wether the allocation source of this allocation was a certification.
     *
     * @return bool
     */
    public function is_certification_allocation(): bool {
        return 0 !== (int) $this->get('certificationid');
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
