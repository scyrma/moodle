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
 * File containing tests for export/import course mapper class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\mapper;

use context_coursecat;
use tool_tenant\manager;
use tool_wp\local\exportimport\helper;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\mapper\course
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_mapper_testcase extends \advanced_testcase {

    /**
     * Test mapper returns mapping data correctly for given course
     */
    public function test_get_mapping_data_for_workplace_export() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'My course',
            'shortname' => 'mycourse',
            'idnumber' => 'course101',
        ]);

        $mapper = helper::find_mapper_for_entity('course', helper::get_all_mappers());
        $this->assertInstanceOf(course::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($course->id);
        $this->assertEquals([
            'id' => $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'idnumber' => $course->idnumber,
        ], $data);
    }

    /**
     * Data provider for testing matching courses
     *
     * @see test_locate_mapping_success
     *
     * @return array
     */
    public function locate_mapping_success_provider(): array {
        return [
            ['mycourse', 'course101', ['shortname' => 'mycourse']],
            ['mycourse', 'course101', ['idnumber' => 'course101']],
        ];
    }

    /**
     * Test the mapper class successfully locates existing courses
     *
     * @param string $shortname
     * @param string $idnumber
     * @param array $identifier
     *
     * @dataProvider locate_mapping_success_provider
     */
    public function test_locate_mapping_success(string $shortname, string $idnumber, array $identifier): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course([
            'shortname' => $shortname,
            'idnumber' => $idnumber
        ]);

        $mapping = $this->get_plugin_generator()->locate_mapping('course', $identifier);
        $this->assertEquals([$course->id, [], [], true], $mapping);
    }

    /**
     * Test the mapper class successfully locates existing courses where a matching course also exists in a category
     * that the user cannot access
     */
    public function test_locate_mapping_success_duplicate_identifier(): void {
        global $DB;

        $this->resetAfterTest();

        // Remove category course browsing (enabled by default).
        unassign_capability('moodle/category:viewcourselist', $DB->get_field('role', 'id', ['shortname' => 'user']));
        unassign_capability('moodle/category:viewcourselist', $DB->get_field('role', 'id', ['shortname' => 'guest']));

        // Create a course that the user cannot access.
        $othercourse = $this->getDataGenerator()->create_course([
            'shortname' => 'mycourse',
        ]);

        // Create a category & course that the user can access.
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = context_coursecat::instance($category->id);
        $course = $this->getDataGenerator()->create_course([
            'idnumber' => 'course101',
            'category' => $category->id,
        ]);

        // Create user, assign them manager role in the course category so they can browse courses.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $managerrole = manager::get_tenant_manager_role();
        $this->getDataGenerator()->role_assign($managerrole, $user->id, $categorycontext->id);

        // Locate mapping using $othercourse shortname (which user can't access) and $course idnumber (which user can access).
        $this->assertEquals($course->id, $this->get_plugin_generator()->locate_mapping('course', [
            'shortname' => $othercourse->shortname,
            'idnumber' => $course->idnumber,
        ])[0]);
    }

    /**
     * Test mapper creates missing courses when conflict resolution is set as such
     */
    public function test_locate_mapping_success_create_missing(): void {
        global $DB;

        $this->resetAfterTest();

        // Remove category course browsing (enabled by default).
        unassign_capability('moodle/category:viewcourselist', $DB->get_field('role', 'id', ['shortname' => 'user']));
        unassign_capability('moodle/category:viewcourselist', $DB->get_field('role', 'id', ['shortname' => 'guest']));

        // Create a course that the user cannot access.
        $existingcourse = $this->getDataGenerator()->create_course(['shortname' => 'mycourse1']);

        // Create user, assign them manager role in a course category so they can create courses.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $category = $this->getDataGenerator()->create_category(['name' => 'My category']);

        $managerrole = manager::get_tenant_manager_role();
        $this->getDataGenerator()->role_assign($managerrole, $user->id, context_coursecat::instance($category->id)->id);

        // Conflict resolution setting to create a missing course.
        list($courseid, $notices, $errors, $conflicts, $validated) =
            $this->get_plugin_generator()->locate_mapping_with_conflict_resolution('course', [
                'fullname' => 'My course',
                'shortname' => $existingcourse->shortname,
            ], [
                'action' => 'create',
                'catid' => $category->id,
            ]);

        $this->assertNotNull($courseid);
        $this->assertCount(1, $notices);

        $course = get_course($courseid);
        $this->assertNotEquals($existingcourse->id, $course->id);
        $this->assertEquals($category->id, $course->category);
        $this->assertEquals('My course', $course->fullname);
        $this->assertEquals('mycourse2', $course->shortname);

        $courseurl = course_get_url($course)->out();
        $this->assertEquals("Empty course <a href=\"{$courseurl}\">My course</a> was created", reset($notices));

        $this->assertCount(1, $conflicts);
        $this->assertEquals([
            'Some courses do not exist',
            "Create empty course in category '{$category->name}'",
        ], reset($conflicts));

        $this->assertEmpty($errors);
        $this->assertTrue($validated);

        // Conflict resolution setting to create a missing course (again), no fullname specified.
        list($courseid, $notices, $errors, $conflicts, $validated) =
            $this->get_plugin_generator()->locate_mapping_with_conflict_resolution('course', [
                'shortname' => $existingcourse->shortname,
            ], [
                'action' => 'create',
                'catid' => $category->id,
            ]);

        $this->assertNotNull($courseid);
        $this->assertCount(1, $notices);

        $course = get_course($courseid);
        $this->assertNotEquals($existingcourse->id, $course->id);
        $this->assertEquals($category->id, $course->category);
        $this->assertEquals('mycourse1', $course->fullname);
        $this->assertEquals('mycourse3', $course->shortname);

        $courseurl = course_get_url($course)->out();
        $this->assertEquals("Empty course <a href=\"{$courseurl}\">mycourse1</a> was created", reset($notices));

        $this->assertCount(1, $conflicts);
        $this->assertEquals([
            'Some courses do not exist',
            "Create empty course in category '{$category->name}'",
        ], reset($conflicts));

        $this->assertEmpty($errors);
        $this->assertTrue($validated);
    }

    /**
     * Tests the mapper class returns appropriate notice when locating a course by idnumber, which has a different shortname
     */
    public function test_locate_mapping_notice() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course([
            'shortname' => 'mycourse',
            'idnumber' => 'course101',
        ]);

        list($courseid, $notices, $errors, $validated) = $this->get_plugin_generator()->locate_mapping('course', [
            'shortname' => 'myothercourse',
            'idnumber' => $course->idnumber,
        ]);
        $this->assertEquals($course->id, $courseid);
        $this->assertCount(1, $notices);

        $courseurl = course_get_url($course)->out();
        $this->assertEquals("A course with short name 'myothercourse' was not found. <a href=\"{$courseurl}\">Another course</a>" .
            " with the idnumber '{$course->idnumber}' was found, but this course has a different short name", reset($notices));

        $this->assertEmpty($errors);
        $this->assertTrue($validated);
    }

    /**
     * Data provider for testing non-matching courses
     *
     * @see test_locate_mapping_error
     *
     * @return array
     */
    public function locate_mapping_error_provider(): array {
        return [
            ['mycourse', 'course101', ['shortname' => 'myothercourse']],
            ['mycourse', 'course101', ['shortname' => 'myothercourse', 'idnumber' => 'course202']],
            ['mycourse', 'course101', ['idnumber' => 'course202']],
        ];
    }

    /**
     * Test mapper returns errors for non-matching courses
     *
     * @param string $shortname
     * @param string $idnumber
     * @param array $identifier
     *
     * @dataProvider locate_mapping_error_provider
     */
    public function test_locate_mapping_error(string $shortname, string $idnumber, array $identifier): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_course([
            'shortname' => $shortname,
            'idnumber' => $idnumber
        ]);

        list($courseid, $notices, $errors, $validated) = $this->get_plugin_generator()->locate_mapping('course', $identifier);
        $this->assertNull($courseid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);

        if (!empty($identifier['shortname']) && !empty($identifier['idnumber'])) {
            $identifierstring = "'{$identifier['shortname']}' ('{$identifier['idnumber']}')";
        } else {
            $identifierstring = !empty($identifier['shortname']) ? "'{$identifier['shortname']}'" : "'{$identifier['idnumber']}'";
        }
        $this->assertEquals("Course {$identifierstring} was not found", reset($errors));

        $this->assertFalse($validated);
    }

    /**
     * Returns the plugin generator
     *
     * @return \tool_wp_generator
     */
    protected function get_plugin_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }
}
