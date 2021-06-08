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
 * Class tool_certification\certification_user
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use core\persistent;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certification_user
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_user extends persistent
{
    /**
     * Database table.
     */
    public const TABLE = 'tool_certification_users';

    /** @var certification */
    protected $certification;

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
            'certificationid' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
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
            'currentprogramid' => [
                'type' => PARAM_INT,
                'optional' => true,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'isrecertification' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'graceperiodends' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'graceperiodendslocked' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'nextstartdate' => [
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
        if (!$this->certification) {
            $this->certification = certification::get_record(['id' => $this->get('certificationid')]);
        }
        return $this->certification;
    }

    /**
     * Sets certification that can be retrieved by calling get_certification() method
     *
     * @param certification $certification
     */
    public function set_certification(certification $certification) {
        $this->certification = $certification;
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
