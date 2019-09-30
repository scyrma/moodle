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
 * Persistent class.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */

namespace tool_reportbuilder;

use core\persistent;
use tool_reportbuilder\event\report_deleted;

defined('MOODLE_INTERNAL') || die();

// TODO SP-399: move to models.

/**
 * Class for reportbuilder persistent
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */
class reportbuilder extends persistent {

    /** main table */
    const TABLE = 'tool_reportbuilder';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return array(
            'name' => array(
                'type' => PARAM_TEXT,
            ),
            'idnumber' => array(
                'type' => PARAM_RAW,
            ),
            'shortname' => array(
                // Short name is currently not used.
                'type' => PARAM_RAW,
            ),
            'description' => array(
                // Description is currently not used.
                'type' => PARAM_RAW,
            ),
            'source' => array(
                'type' => PARAM_RAW
            ),
            'usercreated' => array(
                'type' => PARAM_INT
            ),
            'tenantid' => array(
                'type' => PARAM_INT
            ),
            'type' => array(
                'type' => PARAM_INT
            ),
            'conditions' => array(
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
            )
        );
    }

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param \stdClass $record If set will be passed to {@link self::from_record()}.
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
     * Cascading delete.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function before_delete() {
        global $DB;
        $DB->delete_records('tool_reportbuilder_column', ['reportid' => $this->get('id')]);
        $DB->delete_records('tool_reportbuilder_cond', ['reportid' => $this->get('id')]);
        $DB->delete_records('tool_reportbuilder_filter', ['reportid' => $this->get('id')]);
        $DB->delete_records('tool_reportbuilder_scheduled', ['reportid' => $this->get('id')]);
    }

    /**
     * Trigger report deleted event after successful deletion
     *
     * @param bool $result
     * @return void
     */
    protected function after_delete($result) {
        if ($result) {
            report_deleted::create_from_object($this)->trigger();
        }
    }
}