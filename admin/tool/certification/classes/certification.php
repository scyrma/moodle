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
 * Class tool_certification\certification
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use core\persistent;
use tool_program\persistent\program;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certification
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification extends persistent {
    /**
     * Database table.
     */
    public const TABLE = 'tool_certification';

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param \stdClass $record If set will be passed to {$see self::from_record()}.
     */
    public function __construct(int $id = 0, \stdClass $record = null) {
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
            'tenantid' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'program' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'fullname' => [
                'type' => PARAM_TEXT,
            ],
            'idnumber' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'description' => [
                'type' => PARAM_RAW,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED
            ],
            'descriptionformat' => [
                'type' => PARAM_INT,
                'default' => FORMAT_MOODLE,
            ],
            'startdatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'startdateabsolute' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'startdaterelative' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'duedatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'duedateabsolute' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'duedaterelative' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'expirydatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'expirydateabsolute' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'expirydaterelative' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'allocationstartdatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'allocationstartdateabsolute' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'allocationenddatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'allocationenddateabsolute' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'autocreategroups' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => \tool_program\api::GROUPS_AS_IN_PROGRAMS,
                'null' => NULL_NOT_ALLOWED,
            ],
            'archived' => [
                'type' => PARAM_BOOL,
                'optional' => true,
                'default' => false,
            ],
            'timearchived' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'requirerecertification' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'recertdifferentprogram' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'recertificationprogram' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'recertstartdaterelative' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'recertgraceperiod' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'recertexpirydatetype' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => constants::DATE_NONE,
            ],
            'recertexpirydaterelative' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'shared' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
        ];
    }

    /**
     * Checks if certification is archived.
     *
     * @return bool
     */
    public function is_archived(): bool {
        return (true === (bool) $this->get('archived'));
    }

    /**
     * Gets certification users
     *
     * @return certification_user[]
     */
    public function get_certification_users(): array {
        return certification_user::get_records(['certificationid' => $this->get('id')]);
    }

    /**
     * Gets certification completions
     *
     * @return certification_completion[]
     */
    public function get_certification_completions(): array {
        return certification_completion::get_records(['certificationid' => $this->get('id')]);
    }

    /**
     * Return related program.
     *
     * @return program|false
     */
    public function get_certification_program() {
        return program::get_record(['id' => $this->get('program')]);
    }

    /**
     * Certification context
     *
     * @return \context
     */
    public function get_context(): \context {
        return \context_system::instance();
    }

    /**
     * Formatted certification name
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_formatted_name(): string {
        return format_string($this->get('fullname'), true, ['context' => $this->get_context(), 'escape' => false]);
    }
}
