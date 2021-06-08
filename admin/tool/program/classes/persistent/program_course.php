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
 * Program course
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\persistent;

use core\persistent;
use lang_string;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Class program_course
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_course extends persistent {
    /**
     * Database table.
     */
    public const TABLE = 'tool_program_courses';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'setid' => [
                'type' => PARAM_INT,
            ],
            'courseid' => [
                'type' => PARAM_INT,
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Validate the set ID.
     *
     * @param  int $value
     * @return true|lang_string
     */
    protected function validate_setid($value) {
        global $DB;

        // Check set exists.
        if (!$this->get('id') && !$DB->record_exists(program_set::TABLE, ['id' => $value])) {
            return new lang_string('invaliddata', 'error');
        }

        return true;
    }

    /**
     * Validate the course ID.
     *
     * @param  int $value
     * @return true|lang_string
     */
    protected function validate_courseid($value) {
        global $DB;

        // Check course exists.
        if (!$this->get('id') && !$DB->record_exists('course', ['id' => $value])) {
            return new lang_string('invaliddata', 'error');
        }

        return true;
    }

    /**
     * Get program
     *
     * @return program|false
     */
    public function get_program() {
        global $DB;

        $sql = 'SELECT p.*
                  FROM {' . program::TABLE . '} p
            INNER JOIN {' . program_set::TABLE . '} s
                    ON s.programid = p.id
                 WHERE s.id = :setid ';
        if (!$programrecord = $DB->get_record_sql($sql, ['setid' => $this->get('setid')])) {
            return false;
        }

        return new program(0, $programrecord);
    }

    /**
     * Get set
     *
     * @return program_set|false
     */
    public function get_set() {
        return program_set::get_record(['id' => $this->get('setid')]);
    }

    /**
     * Get course
     *
     * @return stdClass
     */
    public function get_course(): stdClass {
        return get_course($this->get('courseid'));
    }
}
