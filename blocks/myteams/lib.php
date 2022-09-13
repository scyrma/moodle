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
 *  Callbacks for plugin block_myteams
 *
 * @package    block_myteams
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use block_myteams\userinfo_section;
use block_myteams\userinfo_section_item;
use block_myteams\api;
use core_completion\progress;

/**
 * Callback for block_myteams, return list of user sections to be added to the MyTeams block report.
 *
 * @param int $userid
 * @return userinfo_section[]
 */
function block_myteams_block_myteams_user_section(int $userid): array {
    // Generate the "Courses" userinfo section.
    $sectionname = get_string('courses');
    $sectionlink = new moodle_url('#'); // TODO add course progress general when WP-3453 will done.
    $coursessection = new userinfo_section($sectionname, $sectionlink, 30);

    // Get enrolled courses for the given user.
    $courses = api::get_user_enrolled_courses($userid);

    // Get courses completion for the given user.
    $coursescompletion = api::get_user_courses_completion($courses, $userid);

    // Feed the sections items.
    foreach ($courses as $course) {
        $progresspercentage = progress::get_course_progress_percentage($course, $userid) ?? 0;

        // Adjust progress to maximum 95% if course is not completed.
        if (is_null($coursescompletion[$course->id]->timecompleted)) {
            $progresspercentage = min($progresspercentage, 95);
        }

        $progress = get_string('progress', 'block_myteams', (int) $progresspercentage);

        $item = new userinfo_section_item($course->fullname, $progress);

        $coursessection->add_item($item);
    }

    return [$coursessection];
}
