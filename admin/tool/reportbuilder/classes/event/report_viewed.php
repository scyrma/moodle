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
 * Viewed report event.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\event;

use tool_reportbuilder\reportbuilder;
use context_system;

defined('MOODLE_INTERNAL') || die();

/**
 * Report builder report viewed event class.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_viewed extends \core\event\base {

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = 'tool_reportbuilder';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Creates an instance from a category controller object
     *
     * @param reportbuilder $reportbuilder
     * @param string $source
     * @return report_viewed
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function create_from_object(reportbuilder $reportbuilder, string $source): report_viewed {
        global $USER;

        $eventparams = [
            'context'       => context_system::instance(),
            'objectid'      => $reportbuilder->get('id'),
            'relateduserid' => $USER->id,
            'other'         => [
                'source'   => $source
            ]
        ];
        $event = self::create($eventparams);
        $event->add_record_snapshot($event->objecttable, $reportbuilder->to_record());
        return $event;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('eventreportviewed', 'tool_reportbuilder');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $source = $this->other['source'];
        return "The user with id '$this->userid' viewed the reportbuilder with id '$this->objectid' from source '$source'.";
    }
}
