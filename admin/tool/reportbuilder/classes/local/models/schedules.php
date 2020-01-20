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
 * Class for the schedules persistent
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\models;

defined('MOODLE_INTERNAL') || die();

use core\persistent;
use tool_reportbuilder\constants;
use tool_reportbuilder\manager;
use tool_reportbuilder\report_base;
use tool_reportbuilder\reportbuilder;
use \tool_reportbuilder\local\helpers\schedules as scheduleshelper;

/**
 * Class for the schedules persistent
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class schedules extends persistent {

    /** The table name. */
    const TABLE = 'tool_reportbuilder_scheduled';

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
            'scheduled' => array(
                'type' => PARAM_INT,
                'default' => function() {
                    return 1;
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
            'audience' => array(
                'type' => PARAM_RAW,
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
     * Returns the tenantid related to the report of the schedule.
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function get_tenantid() {
        return $this->get_report()->get_tenant_id();
    }
}