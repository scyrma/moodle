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
 * Delete report event.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Toni Barberà <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */

namespace tool_reportbuilder\event;

use tool_reportbuilder\reportbuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Report builder report deleted event class.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Toni Barberà <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_updated extends \core\event\base {

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = 'tool_reportbuilder';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Creates an instance from a category controller object
     *
     * @param reportbuilder $reportbuilder
     * @return report_updated
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function create_from_object(reportbuilder $reportbuilder): report_updated {
        $eventparams = [
                'context'  => \context_system::instance(),
                'objectid' => $reportbuilder->get('id'),
                'other' => [
                        'name'     => $reportbuilder->get('name'),
                        'source'   => $reportbuilder->get('source')
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
        return get_string('eventreportupdated', 'tool_reportbuilder');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' updated the reportbuilder with id '$this->objectid'.";
    }
}
