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
 * Persistent class.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use core\persistent;
use tool_reportbuilder\event\report_deleted;
use tool_reportbuilder\local\models\audiences;
use tool_reportbuilder\local\models\reportbuilder_conditions as condition;
use tool_reportbuilder\local\report\reportbuilder_filter as filter;
use tool_reportbuilder\reportbuilder_column as column;
use tool_reportbuilder\local\models\schedule;

defined('MOODLE_INTERNAL') || die();

// TODO SP-399: move to models.

/**
 * Class for reportbuilder persistent
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
            ),
            'shared' => array(
                'type' => PARAM_INT,
                'default' => 0,
            ),
            'cardviewsettings' => array(
                'type' => PARAM_RAW,
                'default' => '{"showtitle":0,"visibility":1}',
            ),
        );
    }

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param \stdClass $record If set will be passed to {@see self::from_record()}.
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
     * Cascading delete. Each persistent should be deleted properly, as they may implement their own pre/post-delete logic.
     *
     * @return void
     */
    protected function before_delete() {
        $params = ['reportid' => $this->get('id')];

        // Columns.
        foreach (column::get_records($params) as $column) {
            $column->delete();
        }

        // Conditions.
        foreach (condition::get_records($params) as $condition) {
            $condition->delete();
        }

        // Filters.
        foreach (filter::get_records($params) as $filter) {
            $filter->delete();
        }

        // Audience.
        foreach (audiences::get_records($params) as $audience) {
            $audience->delete();
        }

        // Schedules.
        foreach (schedule::get_records($params) as $schedule) {
            $schedule->delete();
        }
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
