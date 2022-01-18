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
 * Event observers for datastore.
 *
 * @package    tool_datastore
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_datastore;

use core\event\base;
use core\event\course_restored;
use tool_datastore\task\migrate_course_completion;

/**
 * Event observer for datastore.
 *
 * @package    tool_datastore
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class observer {

    /**
     * Observe all events to determine whether we have an appropriate action class to store the data
     *
     * @param base $event
     * @return void
     */
    public static function observe_all(base $event) {
        $actionclass = action_factory::create($event);
        if ($actionclass) {
            $actionclass->save_action();
        }
    }

    /**
     * Course restored event
     *
     * @param course_restored $event
     * @return void
     */
    public static function course_restored(course_restored $event) {
        $course = $event->get_record_snapshot('course', $event->objectid);

        // Schedule ad-hoc task to migrate completion data from restored course.
        $task = new migrate_course_completion();
        $task->set_custom_data(['courseid' => $course->id]);

        \core\task\manager::queue_adhoc_task($task, true);
    }
}
