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

namespace block_myteams;

use advanced_testcase;

/**
 * Tests for the api class methods.
 *
 * @package    block_myteams
 * @covers     \block_myteams\api
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class api_test extends advanced_testcase {

    /**
     * Test get_user_enrolled_courses() api method.
     *
     */
    public function test_get_user_enrolled_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course 1']);
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Course 2']);

        // Enrol user1 in 'Course 1' with manual method.
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        // Enrol user2 in 'Course 1' with program method and in 'Course 2' with manual method.
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, null, 'program');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // User courses.
        $user1courses = api::get_user_enrolled_courses((int) $user1->id);
        $user2courses = api::get_user_enrolled_courses((int) $user2->id);

        // Assert both user have only one course.
        $this->assertCount(1, $user1courses);
        $this->assertCount(1, $user2courses);

        // Assert user1 & user2 return only course with direct enrolment method.
        $this->assertEquals('Course 1', reset($user1courses)->fullname);
        $this->assertEquals('Course 2', reset($user2courses)->fullname);
    }
}
