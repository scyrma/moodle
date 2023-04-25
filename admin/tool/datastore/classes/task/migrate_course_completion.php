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
 * Datastore ad-hoc task for migrating existing course completion data
 *
 * @package     tool_datastore
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_datastore\task;

use core\event\course_completed;
use core\task\adhoc_task;
use tool_datastore\action_factory;
use tool_datastore\local\models\action;

/**
 * Ad-hoc task class
 *
 * @package     tool_datastore
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class migrate_course_completion extends adhoc_task {

    /**
     * Return course ID from custom task data, or null if not specified
     *
     * @return int|null
     */
    private function get_courseid(): ?int {
        return ($data = $this->get_custom_data()) ? $data->courseid : null;
    }

    /**
     * Execute the task
     *
     * @return void
     */
    public function execute() {
        global $DB;

        // Select records about completion that have not been migrated yet.
        $select = 'c.timecompleted IS NOT NULL and NOT EXISTS(
                SELECT 1 FROM {tool_datastore_action} a, {tool_datastore_entity} e
                WHERE e.actionid = a.id AND e.type = :cctype AND a.originalcourseid = c.course AND e.originalid = c.id
                      AND a.action = :action AND a.relateduserid = c.userid
            )';
        $params = ['cctype' => 'course_completion', 'action' => 'course_completed'];

        // Check whether a course ID was specified in custom data.
        if ($courseid = $this->get_courseid()) {
            $select .= ' AND c.course = :course';
            $params['course'] = $courseid;
        }

        $completions = $DB->get_records_sql('SELECT c.* FROM {course_completions} c
            JOIN {user} u ON u.id = c.userid AND u.deleted = 0
            WHERE ' . $select, $params);
        foreach ($completions as $completion) {

            // We need to create an instance of the course_completed event, and pass it to the action factory.
            $event = course_completed::create_from_completion($completion);
            action_factory::create($event)->save_action();
            unset($event);
        }
    }

    /**
     * Add completion migration tasks for each course that has completions
     */
    public static function queue() {
        global $DB;

        $sql = "SELECT DISTINCT course FROM {course_completions} WHERE timecompleted IS NOT NULL";
        $courses = $DB->get_recordset_sql($sql);
        foreach ($courses as $course) {
            $task = new self();
            $task->set_custom_data(['courseid' => $course->course]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
        $courses->close();
    }
}
