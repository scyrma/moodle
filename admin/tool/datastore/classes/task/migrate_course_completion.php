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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Datastore ad-hoc task for migrating existing course completion data
 *
 * @package     tool_datastore
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_datastore\task;

use core\event\course_completed;
use core\task\adhoc_task;
use tool_datastore\action_factory;
use tool_datastore\local\models\action;

defined('MOODLE_INTERNAL') || die;

/**
 * Ad-hoc task class
 *
 * @package     tool_datastore
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        $select = 'timecompleted IS NOT NULL';
        $params = [];

        // Check whether a course ID was specified in custom data.
        if ($courseid = $this->get_courseid()) {
            $select .= ' AND course = :course';
            $params['course'] = $courseid;
        }

        $completions = $DB->get_records_select('course_completions', $select, $params);
        foreach ($completions as $completion) {

            // If we already have this action recorded, we can skip it.
            if (action::record_exists_select('action = ? AND originalcourseid = ? AND relateduserid = ?',
                    ['course_completed', $completion->course, $completion->userid])) {

                continue;
            }

            // We need to create an instance of the course_completed event, and pass it to the action factory.
            $event = course_completed::create_from_completion($completion);
            action_factory::create($event)->save_action();
            unset($event);
        }
    }
}
