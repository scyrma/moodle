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
 * Delete schedule event.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\event;

use tool_reportbuilder\local\models\schedules;
use context_system;

defined('MOODLE_INTERNAL') || die();

/**
 * Report builder schedule deleted event class.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule_deleted extends \core\event\base {

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = 'tool_reportbuilder_scheduled';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Creates an instance from a category controller object
     *
     * @param schedules $schedule
     * @return schedule_deleted
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function create_from_object(schedules $schedule): schedule_deleted {
        $eventparams = [
            'context'  => context_system::instance(),
            'objectid' => $schedule->get('id'),
            'other'    => [
                'reportid' => $schedule->get('reportid'),
            ]
        ];
        $event = self::create($eventparams);
        $event->add_record_snapshot($event->objecttable, $schedule->to_record());
        return $event;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('eventreportscheduledeleted', 'tool_reportbuilder');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' deleted a schedule in reportbuilder with id '$this->objectid'.";
    }
}
