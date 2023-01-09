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

declare(strict_types=1);

namespace tool_catalogue\output;

use advanced_testcase;
use completion_info;
use tool_catalogue\router;
use tool_program_generator;

/**
 * Unit tests for program content
 *
 * The program content class returns the program content (sets and courses) with all
 * the information needed.
 *
 * @package     tool_catalogue
 * @covers      \tool_catalogue\output\program_content
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_content_test extends advanced_testcase {

    /**
     * Test program export_for_template
     */
    public function test_export_for_template(): void {
        global $PAGE, $DB, $OUTPUT;
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = self::getDataGenerator()->create_user();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        // Generate program and allocate user.
        $program = $programgenerator->generate_program((object) ['fullname' => 'My program']);
        $programgenerator->allocate_user_to_program($program->get('id'), (int) $user->id);

        // Generate Course1 and allocate user.
        $category = self::getDataGenerator()->create_category(['name' => 'Cat 1']);
        $course = self::getDataGenerator()->create_course(
            (object)['category' => $category->id, 'fullname' => 'My course 1', 'enablecompletion' => true]);
        self::getDataGenerator()->enrol_user((int) $user->id, $course->id, 'student');

        $programgenerator->add_course_to_set((int) $course->id, $program->get_base_set()->get('id'));

        // Add an assign module to the course.
        $assign1 = self::getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);
        $assign2 = self::getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);
        $assign3 = self::getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);

        $completion = new completion_info($course);
        $cmassign1 = get_coursemodule_from_id('assign', $assign1->cmid);
        $cmassign2 = get_coursemodule_from_id('assign', $assign2->cmid);
        $completion->update_state($cmassign1, COMPLETION_COMPLETE, $user->id);
        $completion->update_state($cmassign2, COMPLETION_COMPLETE, $user->id);

        // Test program_content without recently accessed courses.
        $programcontent = new program_content($program);
        $renderer = $PAGE->get_renderer('tool_catalogue');
        $programcontentdata = $programcontent->export_for_template($renderer);

        $this->assertEmpty($programcontentdata->recentlyaccessedcourses);
        $this->assertObjectNotHasAttribute('pagesectionrecentlyaccessedcourses', $programcontentdata);

        // Test now with one recently accessed course.
        $DB->insert_record('user_lastaccess',
            ['userid' => $user->id, 'courseid' => $course->id, 'timeaccess' => time() - DAYSECS]);

        // Set the user preference to indicate that the recently accessed courses should be collapsed.
        set_user_preference('tool_catalogue_collapse_recently_accessed_courses', true, $user);

        $this->setUser($user->id);

        $programcontent = new program_content($program);
        $renderer = $PAGE->get_renderer('tool_catalogue');
        $programcontentdata = $programcontent->export_for_template($renderer);

        $this->assertCount(1, $programcontentdata->recentlyaccessedcourses);
        $recentlyaccessedcourse = $programcontentdata->recentlyaccessedcourses[0];
        $this->assertEquals(66, $recentlyaccessedcourse['progress']);
        $this->assertEquals($course->fullname, $recentlyaccessedcourse['name']);
        $courseurl = router::build_course_url((int) $course->id);
        $this->assertEquals($courseurl, $recentlyaccessedcourse['viewurl']);
        $this->assertEquals(1, $recentlyaccessedcourse['visible']);

        $this->assertCount(1, $programcontentdata->listitems);
        $listitem = $programcontentdata->listitems[0];
        $this->assertEquals($course->fullname, $listitem->fullname);
        $this->assertEquals($course->id, $listitem->courseid);
        $this->assertFalse($listitem->iscompleted);
        $this->assertEquals($courseurl->out(), $listitem->url);
        $this->assertEquals(66, $listitem->progress);

        $completionprogressstr = get_string('progresscompleted', 'tool_catalogue', ['completed' => 0, 'total' => 1]);
        $this->assertEquals($completionprogressstr, $programcontentdata->completionprogress);
        $completioncriteriastr = get_string('completeallinorder', 'tool_program');
        $this->assertEquals($completioncriteriastr, $programcontentdata->completioncriteria);
        $this->assertEquals('My program', $programcontentdata->fullname);
        $this->assertEquals($OUTPUT->image_url('all-in-order', 'tool_catalogue')->out(false), $programcontentdata->completionicon);
        $this->assertEquals($program->get('id'), $programcontentdata->programid);
        $this->assertTrue($programcontentdata->pagesectionrecentlyaccessedcourses->collapse);
    }
}
