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

namespace block_myteams;

use advanced_testcase;
use completion_completion;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/blocks/myteams/lib.php');
require_once($CFG->libdir . '/externallib.php');

/**
 * Tests for the block_myteams lib class.
 *
 * @package    block_myteams
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class lib_test extends advanced_testcase {

    /**
     * Test callback block_myteams_block_myteams_user_section()
     *
     * @covers ::block_myteams_block_myteams_user_section
     */
    public function test_section_block_myteams(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course 1', 'enablecompletion' => 1]);
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Course 2', 'enablecompletion' => 1]);

        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Complete course1 progress for user2.
        $this->complete_course($course->id, $user2->id);

        // User courses.
        $user1courses = block_myteams_block_myteams_user_section($user1->id)[0]->get_items();
        $user2courses = block_myteams_block_myteams_user_section($user2->id)[0]->get_items();

        $this->assertCount(1, $user1courses);
        $this->assertCount(2, $user2courses);

        $this->assertEquals('Course 1', $user1courses[0]->get_title());
        $this->assertEquals('0% completed', $user1courses[0]->get_subtitle());

        $this->assertEquals('Course 2', $user2courses[0]->get_title());
        $this->assertEquals('0% completed', $user2courses[0]->get_subtitle());

        $this->assertEquals('Course 1', $user2courses[1]->get_title());
        $this->assertEquals('100% completed', $user2courses[1]->get_subtitle());

        // Complete course1 progress for user1.
        $this->complete_course($course->id, $user1->id);
        $user1courses = block_myteams_block_myteams_user_section($user1->id)[0]->get_items();
        $this->assertEquals('Course 1', $user1courses[0]->get_title());
        $this->assertEquals('100% completed', $user1courses[0]->get_subtitle());
    }

    /**
     * Complete course for user.
     *
     * @param int $courseid
     * @param int $userid
     */
    private function complete_course(int $courseid, int $userid): void {
        $ccompletion = new completion_completion(['course' => $courseid, 'userid' => $userid]);
        $ccompletion->mark_complete();
    }
}
