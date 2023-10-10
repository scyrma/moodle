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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_wp\event;

use tool_wp\exporter_base;

/**
 * The event that's triggered when an export is created
 *
 * @since       Moodle 3.8
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_created extends export_base {

    /**
     * Set the event data properties
     *
     * @return void
     */
    protected function init(): void {
        parent::init();

        $this->data['crud'] = 'c';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventexportcreated', 'tool_wp');
    }

    /**
     * Return non-localised event description
     *
     * @uses \tool_wp\exporter_base::get_name()
     *
     * @return string
     */
    public function get_description(): string {
        $description = "The user with id '{$this->relateduserid}' created the export with id '{$this->objectid}'";

        $exporterclass = $this->other['exporter'];
        if (class_exists($exporterclass) && is_subclass_of($exporterclass, exporter_base::class)) {
            $description .= ' using the \'' . $exporterclass . '\' exporter';
        }

        return $description;
    }
}
