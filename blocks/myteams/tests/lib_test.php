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
use context_system;
use tool_tenant\tenancy;

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

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // Create position with global manager permission.
        $params = ['name' => 'Team managers', 'shared' => 0, 'tenantid' => tenancy::get_default_tenant_id()];
        $departmentframework1 = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $params2 = ['name' => 'Manager', 'shared' => 0, 'globalmanager' => 1, 'tenantid' => tenancy::get_default_tenant_id()];
        $positionframework1 = (new \tool_organisation\position_manager())->create_position((object)$params2, false);
        self::getDataGenerator()->get_plugin_generator('tool_organisation')->assign_job([
            'userid' => $user3->id,
            'departmentid' => $departmentframework1->get('id'),
            'positionid' => $positionframework1->get('id'),
        ]);
        role_assign(1, $user3->id, context_system::instance()->id);

        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Course 1', 'enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'Course 2', 'enablecompletion' => 1]);
        $course3 = $this->getDataGenerator()->create_course(['fullname' => 'Course 3', 'enablecompletion' => 1]);

        // Enrol user1 with two different method not related with enrol_program in course1 (course1 should be included).
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, null, 'self');

        // Enrol user2 with manual enrolment method in course1 (course1 should be included).
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Enrol user2 with program enrolment method in course2 (course2 should be excluded).
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id, null, 'program');

        // Enrol user4 with two program enrolment method in course2 (course2 should be excluded).
        $this->getDataGenerator()->enrol_user($user4->id, $course2->id, null, 'program');
        $this->getDataGenerator()->enrol_user($user4->id, $course2->id, null, 'program');

        // Enrol user4 with program and manual enrolment method in course3 (course3 should be included).
        $this->getDataGenerator()->enrol_user($user4->id, $course3->id, null, 'program');
        $this->getDataGenerator()->enrol_user($user4->id, $course3->id);

        // Complete course1 progress for user2.
        $this->complete_course($course1->id, $user2->id);

        // User courses.
        $this->setUser($user3);
        $user1courses = block_myteams_block_myteams_user_section($user1->id)[0]->get_items();
        $user2courses = block_myteams_block_myteams_user_section($user2->id)[0]->get_items();
        $user4courses = block_myteams_block_myteams_user_section($user4->id)[0]->get_items();

        $this->assertCount(1, $user1courses);
        $this->assertCount(1, $user2courses);
        $this->assertCount(1, $user4courses);

        $this->assertEquals('Course 1', $user1courses[0]->get_title());
        $this->assertEquals('0% completed', $user1courses[0]->get_subtitle());

        $this->assertEquals('Course 1', $user2courses[0]->get_title());
        $this->assertEquals('100% completed', $user2courses[0]->get_subtitle());

        $this->assertEquals('Course 3', $user4courses[0]->get_title());
        $this->assertEquals('0% completed', $user4courses[0]->get_subtitle());

        // Complete course1 progress for user1.
        $this->complete_course($course1->id, $user1->id);
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
