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
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\models;

defined('MOODLE_INTERNAL') || die();

use core\persistent;
use tool_reportbuilder\constants;
use tool_reportbuilder\reportbuilder;

/**
 * Class for the schedules persistent
 *
 * @package   tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedules extends persistent {

    /** The table name. */
    const TABLE = 'tool_reportbuilder_scheduled';

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
                'type' => PARAM_INT,
                'default' => constants::FORMAT_EXCEL,
                'choices' => array(
                    constants::FORMAT_EXCEL,
                    constants::FORMAT_CSV,
                    constants::FORMAT_PDF,
                    constants::FORMAT_JSON,
                    constants::FORMAT_HTML,
                    constants::FORMAT_ODS
                )
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
     * Returns the tenantid related to the report of the schedule.
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function get_tenantid() {
        $report = new reportbuilder($this->get('reportid'));
        return $report->get('tenantid');
    }
}