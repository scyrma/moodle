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

namespace tool_catalogue;

use advanced_testcase;
use context_coursecat;
use moodle_exception;
use tool_program_generator;

/**
 * Permission class tests
 *
 * @covers     \tool_catalogue\permission
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission_test extends advanced_testcase {

    /**
     * Test for can_view_course_cover
     */
    public function test_can_view_course_cover(): void {
        global $DB;
        $this->resetAfterTest();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $course = self::getDataGenerator()->create_course();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // User is not enroled in the course but can browse course category.
        $this->assertTrue(permission::can_view_course_cover((int) $user->id, $course));

        // Prevent users viewing course lists in the category.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        $catcontext = context_coursecat::instance($course->category);
        assign_capability('moodle/category:viewcourselist', CAP_PREVENT, $userrole, $catcontext->id, true);

        // User is not enroled in the course and cannot browse course category.
        $this->assertFalse(permission::can_view_course_cover((int) $user->id, $course));

        // User is enroled directly in the course.
        self::getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->assertTrue(permission::can_view_course_cover((int) $user->id, $course));

        // Create a new user.
        $user2 = self::getDataGenerator()->create_user();
        $this->setUser($user2);

        // Allocate the user into a program that does not contain the course.
        $program1 = $programgenerator->generate_program();
        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $user2->id);

        $this->assertFalse(permission::can_view_course_cover((int) $user2->id, $course));

        // Allocate the user into a program that contains the course.
        $program2 = $programgenerator->generate_program();
        $programgenerator->add_course_to_set((int) $course->id, $program2->get_base_set()->get('id'));
        $programgenerator->allocate_user_to_program($program2->get('id'), (int) $user2->id);

        $this->assertTrue(permission::can_view_course_cover((int) $user2->id, $course));
    }

    /**
     * Test for require_can_view_course_cover
     */
    public function test_require_can_view_course_cover(): void {
        global $DB;
        $this->resetAfterTest();

        $course = self::getDataGenerator()->create_course();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // Prevent users viewing course lists in the category.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        $catcontext = context_coursecat::instance($course->category);
        assign_capability('moodle/category:viewcourselist', CAP_PREVENT, $userrole, $catcontext->id, true);

        // User is not enroled in the course and cannot browse course category.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('No permission to view course cover page');
        permission::require_can_view_course_cover((int) $user->id, $course);
    }

    /**
     * Test for can_edit_preference
     */
    public function test_can_edit_preference(): void {
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();

        $this->setAdminUser();
        $this->assertFalse(permission::can_edit_preference($user));

        $this->setUser($user);
        $this->assertTrue(permission::can_edit_preference($user));
    }
}
