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

namespace tool_wp\event;

use context_system;
use core\event\base;
use moodle_url;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;

/**
 * Abstract class definition
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - string exporter: name of the exporter class.
 *      - string entrypoint: name of the entry point.
 *      - int entrypointid: id of the entry point.
 *      - int status: status of the export.
 * }
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class export_base extends base {

    /**
     * Helper method for creating event from persistent instance
     *
     * @param export_persistent $persistent
     * @return base
     */
    public static function create_from_persistent(export_persistent $persistent): base {
        $event = self::create([
            'objectid' => $persistent->get('id'),
            'relateduserid' => $persistent->get('createdby'),
            'other' => [
                'exporter' => $persistent->get('exporter'),
                'entrypoint' => $persistent->get('entrypoint'),
                'entrypointid' => $persistent->get('entrypointid'),
                'status' => $persistent->get('status'),
            ],
        ]);

        $event->add_record_snapshot(export_persistent::TABLE, $persistent->to_record());

        return $event;
    }

    /**
     * Set the event data properties
     *
     * @return void
     */
    protected function init(): void {
        $this->context = context_system::instance();

        $this->data['objecttable'] = export_persistent::TABLE;
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns URL relevant to event
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return helper::export_url($this->objectid);
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the objectid to it's new value in the new course.
     *
     * @return int
     */
    public static function get_objectid_mapping() {
        return base::NOT_MAPPED;
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the information in 'other' to it's new value in the new course.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
