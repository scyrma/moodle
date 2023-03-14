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

namespace block_myinprogress;

use advanced_testcase;
use completion_completion;

/**
 * Unit tests for manager class
 *
 * @package     block_myinprogress
 * @covers      \block_myinprogress\manager
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager_test extends advanced_testcase {

    /**
     * Test test_get_inprogress_courses
     */
    public function test_get_inprogress_courses(): void {
        global $CFG;

        $this->resetAfterTest();

        // Enable compeltion globally.
        $CFG->enablecompletion = true;

        // Generate user and courses with completion enabled (except course5).
        $user = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $course3 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $course4 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $course5 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);
        $this->getDataGenerator()->enrol_user($user->id, $course3->id);
        $this->getDataGenerator()->enrol_user($user->id, $course4->id);
        $this->getDataGenerator()->enrol_user($user->id, $course5->id);

        // Generate user lastaccess for all courses except course4.
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course1, strtotime('- 1 days'));
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course2, strtotime('- 2 days'));
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course3, strtotime('- 3 days'));
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course5, strtotime('- 1 days'));

        $this->setUser($user);

        $inprogresscourses = manager::get_inprogress_courses();

        // Check inprogress courses contents.
        $this->assertCount(3, $inprogresscourses);
        $this->assertContainsOnly(\stdClass::class, $inprogresscourses);
        $this->assertArrayHasKey($course1->id, $inprogresscourses);
        $this->assertArrayHasKey($course2->id, $inprogresscourses);
        $this->assertArrayHasKey($course3->id, $inprogresscourses);
        // Course 4 is not returned because user has no access to it.
        // Course 5 is not returned because it has completion disabled.

        // Check that is orderer by lastaccess.
        $inprogresscourses = array_values($inprogresscourses);
        $this->assertEquals($course1->id, $inprogresscourses[0]->id);
        $this->assertEquals($course2->id, $inprogresscourses[1]->id);
        $this->assertEquals($course3->id, $inprogresscourses[2]->id);

        // Mark the course3 completed by the user and check it is not returned now.
        $ccompletion = new completion_completion(['course' => $course3->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();
        $inprogresscourses = manager::get_inprogress_courses();
        $this->assertCount(2, $inprogresscourses);
        $this->assertArrayNotHasKey($course3->id, $inprogresscourses);

        // Access course4 and check again.
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course4, strtotime('- 1 days'));
        $inprogresscourses = manager::get_inprogress_courses();
        $this->assertCount(3, $inprogresscourses);
        $this->assertArrayHasKey($course4->id, $inprogresscourses);
    }

    /**
     * Test test_get_inprogress_courses including courses without completion
     */
    public function test_get_inprogress_courses_include_withoutcompletion(): void {
        global $CFG;

        $this->resetAfterTest();

        // Enable compeltion globally.
        $CFG->enablecompletion = true;

        // Generate user and courses.
        $user = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);
        $this->getDataGenerator()->enrol_user($user->id, $course3->id);
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course1, strtotime('- 1 days'));
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course2, strtotime('- 1 days'));
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course3, strtotime('- 1 days'));

        $this->setUser($user);

        $inprogresscourses = manager::get_inprogress_courses((int) $user->id, false);

        // Check course2 and course3 (both without completion enabled) are also returned.
        $this->assertCount(3, $inprogresscourses);
        $this->assertArrayHasKey($course3->id, $inprogresscourses);
    }

    /**
     * Test test_get_inprogress_courses with completion disabled globally
     */
    public function test_get_inprogress_courses_disabled_global_completion(): void {
        global $CFG;

        $this->resetAfterTest();

        // Disable compeltion globally.
        $CFG->enablecompletion = false;

        // Generate a user and some courses.
        $user = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course1, strtotime('- 1 days'));
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course2, strtotime('- 1 days'));

        $this->setUser($user);

        $inprogresscourses = manager::get_inprogress_courses();

        // Check return in empty with completion disabled globally.
        $this->assertEmpty($inprogresscourses);
    }

    /**
     * Test test_get_inprogress_courses for a user that is not the current one
     */
    public function test_get_inprogress_courses_different_user(): void {
        global $CFG;

        $this->resetAfterTest();

        // Enable compeltion globally.
        $CFG->enablecompletion = true;

        // Generate user and courses.
        $user = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course1, strtotime('- 1 days'));
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course2, strtotime('- 1 days'));

        $this->setAdminUser();

        // Get inprogress courses for a different user.
        $inprogresscourses = manager::get_inprogress_courses((int) $user->id);

        // Check course1 and course2 are returned.
        $this->assertCount(2, $inprogresscourses);
        $this->assertArrayHasKey($course1->id, $inprogresscourses);
        $this->assertArrayHasKey($course2->id, $inprogresscourses);
    }
}
