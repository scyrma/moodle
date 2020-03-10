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
 * Methods for tool_uploaduser
 *
 * @package     tool_datastore
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_datastore;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_uploaduser
 *
 * This implements methods to be use on uploading users CSV files.
 *
 * @package     tool_datastore
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_uploaduser {

    /**
     * Callback for tool_uploaduser that process updated user.
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     * @param array $ccache the course cache
     */
    public static function process_user_after_enrol($user, $filecolumns, $upt, &$ccache) {
        global $DB;
        foreach ($filecolumns as $column) {
            if (preg_match('/^coursecompleted(\d+)$/', $column, $matches)) {
                $i = $matches[1];
                if (empty($user->{'coursecompleted'.$i})) {
                    continue;
                }
                $coursecompleted = $user->{'coursecompleted'.$i};
                if (!array_key_exists($coursecompleted, $ccache)) {
                    if (!$course = $DB->get_record('course', ['shortname' => $coursecompleted], 'id, shortname')) {
                        $upt->track('enrolments', get_string('unknowncourse', 'error', s($coursecompleted)), 'error');
                        return;
                    }
                    $ccache[$coursecompleted] = $course;
                    $ccache[$coursecompleted]->groups = null;
                }
                if (\tool_datastore\permission::can_upload_course_completion($user, $ccache[$coursecompleted])) {
                    if (empty($user->{'coursecompleteddate'.$i})) {
                        $coursecompleteddate = time();
                    } else {
                        $coursecompleteddate = strtotime($user->{'coursecompleteddate'.$i});
                    }
                    \tool_datastore\helper::add_course_completion($user->id, $ccache[$coursecompleted]->id, $coursecompleteddate);
                }
            }
        }
    }
}
