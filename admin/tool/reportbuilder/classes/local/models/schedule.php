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
 * Class for the schedules persistent
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\models;

use core\persistent;
use tool_reportbuilder\manager;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\report_base;

/**
 * Class for the schedules persistent
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class schedule extends persistent {

    /** The table name. */
    const TABLE = 'tool_reportbuilder_schedule';

    /** @var report_base */
    protected $report;

    /**
     * Get report
     *
     * @return report_base
     */
    public function get_report(): report_base {
        if (!$this->report) {
            $this->report = manager::get_report($this->get('reportid'));
        }
        return $this->report;
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'reportid' => array(
                'type' => PARAM_INT,
            ),
            'name' => array(
                'type' => PARAM_TEXT,
            ),
            'enabled' => array(
                'type' => PARAM_INT,
                'default' => 1,
            ),
            'scheduled' => array(
                'type' => PARAM_INT,
                'default' => function() {
                    return time();
                }
            ),
            'lastsenton' => array(
                'type' => PARAM_RAW,
                'default' => function() {
                    return -1;
                }
            ),
            'format' => array(
                'type' => PARAM_TEXT,
            ),
            'subject' => array(
                'type' => PARAM_TEXT
            ),
            'message' => array(
                'type' => PARAM_RAW
            ),
            'usercreated' => array(
                'type' => PARAM_INT,
            ),
            'audiences' => array(
                'type' => PARAM_RAW,
                'default' => '[]',
            ),
            'recurrence' => array(
                'type' => PARAM_INT,
            ),
            'nextsend' => array(
                'type' => PARAM_INT,
                'default' => function() {
                    return -1;
                }
            )
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
     * Returns the tenantid related to the report of the schedule.
     *
     * @return int
     */
    public function get_tenantid() {
        $reportbuilder = new reportbuilder($this->get('reportid'));

        return $reportbuilder->get('tenantid');
    }

    /**
     * Get the schedule filename
     *
     * @return string
     */
    public function get_filename(): string {
        return clean_filename($this->get('name'));
    }
}
