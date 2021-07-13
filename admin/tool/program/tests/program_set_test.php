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
 * Tests for program_set
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/course/lib.php');

/**
 * Class program_set_test
 *
 * @covers    \tool_program\persistent\program_set
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_set_testcase extends advanced_testcase {
    /**
     * @var tool_program_generator
     */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    /**
     * Test get program courses ids
     */
    public function test_get_program_courses_ids(): void {
        $program = $this->generator->generate_program();
        $course1 = self::getDataGenerator()->create_course(['category' => 1]);
        $course2 = self::getDataGenerator()->create_course(['category' => 1]);
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $programcourse1 = $this->generator->add_course_to_set($course1->id, $basesetid);
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $basesetid, 2);

        $coursesids = $baseset->get_program_courses_ids();

        // Check we can recover the ids of the courses within the program.
        $this->assertEmpty(array_diff([$programcourse1->get('id'), $programcourse2->get('id')], $coursesids));
    }

    /**
     * Test get courses count
     */
    public function test_get_courses_count(): void {
        $program = $this->generator->generate_program();
        $course1 = self::getDataGenerator()->create_course(['category' => 1]);
        $course2 = self::getDataGenerator()->create_course(['category' => 1]);
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $this->generator->add_course_to_set($course1->id, $basesetid);
        $this->generator->add_course_to_set($course2->id, $basesetid, 2);

        $totalcourses = $baseset->get_courses_count();

        // Check the count return the expected amount.
        $this->assertEquals(2, $totalcourses);

        // Check the count keeps incrementing properly.
        $course3 = self::getDataGenerator()->create_course(['category' => 1]);
        $this->generator->add_course_to_set($course3->id, $basesetid, 3);

        $totalcourses = $baseset->get_courses_count();

        $this->assertEquals(3, $totalcourses);
    }

    /**
     * Test get courses
     */
    public function test_get_courses(): void {
        $program = $this->generator->generate_program();
        $course1 = self::getDataGenerator()->create_course(['category' => 1]);
        $course2 = self::getDataGenerator()->create_course(['category' => 1]);
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $this->generator->add_course_to_set($course1->id, $basesetid);
        $this->generator->add_course_to_set($course2->id, $basesetid, 2);

        $courseslist = $baseset->get_courses();

        $this->assertCount(2, $courseslist);
        $this->assertArrayHasKey($course1->id, $courseslist);
        $this->assertArrayHasKey($course2->id, $courseslist);
    }

    /**
     * Test get program courses
     */
    public function test_get_program_courses(): void {
        $program = $this->generator->generate_program();
        $course1 = self::getDataGenerator()->create_course(['category' => 1]);
        $course2 = self::getDataGenerator()->create_course(['category' => 1]);
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $this->generator->add_course_to_set($course1->id, $basesetid);
        $this->generator->add_course_to_set($course2->id, $basesetid, 2);

        $programcourseslist = $baseset->get_program_courses();

        $this->assertCount(2, $programcourseslist);
        $this->assertContainsOnlyInstancesOf(program_course::class, $programcourseslist);
    }

    /**
     * Test get program.
     */
    public function test_get_program(): void {
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();

        $copyprogram = $baseset->get_program();
        $this->assertEquals($program->get('id'), $copyprogram->get('id'));
        $this->assertEquals($program->get('fullname'), $copyprogram->get('fullname'));
        $this->assertInstanceOf(program::class, $copyprogram);

        $newset = $this->generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => 0,
            'name' => 'A new set',
            'sortorder' => 2,
        ]);

        $copyprogram2 = $newset->get_program();
        $this->assertEquals($program->get('id'), $copyprogram2->get('id'));
        $this->assertEquals($program->get('fullname'), $copyprogram2->get('fullname'));
        $this->assertInstanceOf(program::class, $copyprogram2);
    }

    /**
     * Test get subsets ids.
     */
    public function test_get_subsets_ids(): void {
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();

        $subsetsids1 = $baseset->get_subsets_ids();
        $this->assertEmpty($subsetsids1);

        $newset1 = $this->generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'name' => 'A new set 1',
            'sortorder' => 1,
        ]);
        $newset2 = $this->generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $newset1->get('id'),
            'name' => 'A new set 2',
            'sortorder' => 1,
        ]);

        $subsetsids1 = $baseset->get_subsets_ids();
        $this->assertNotEmpty($subsetsids1);
        $this->assertCount(1, $subsetsids1);
        $this->assertContains($newset1->get('id'), $subsetsids1);
        $this->assertNotContains($newset2->get('id'), $subsetsids1);

        $subsetsids2 = $newset1->get_subsets_ids();
        $this->assertNotEmpty($subsetsids2);
        $this->assertCount(1, $subsetsids2);
        $this->assertContains($newset2->get('id'), $subsetsids2);
        $this->assertNotContains($baseset->get('id'), $subsetsids2);
    }

    /**
     * Test get subsets.
     */
    public function test_get_subsets(): void {
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();
        $this->generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'name' => 'A new set 1',
            'sortorder' => 2,
        ]);
        $this->generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'name' => 'A new set 2',
            'sortorder' => 1,
        ]);

        $subsets = $baseset->get_subsets();

        $this->assertNotEmpty($subsets);
        $this->assertContainsOnlyInstancesOf(program_set::class, $subsets);
        $this->assertCount(2, $subsets);
        $this->assertEquals('A new set 1', $subsets[0]->get('name'));
        $this->assertEquals('A new set 2', $subsets[1]->get('name'));
    }

    /**
     * Test get sorted children.
     */
    public function test_get_sorted_children(): void {
        $program = $this->generator->generate_program();
        $course1 = self::getDataGenerator()->create_course(['category' => 1]);
        $course2 = self::getDataGenerator()->create_course(['category' => 1]);
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $this->generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'name' => 'A new set 1',
            'sortorder' => 1,
        ]);
        $programcourse1 = $this->generator->add_course_to_set($course1->id, $basesetid, 2);
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $basesetid, 3);
        $sortedchildren = $baseset->get_sorted_children();

        $this->assertNotEmpty($sortedchildren);
        $this->assertCount(3, $sortedchildren);
        $this->assertEquals('A new set 1', $sortedchildren[0]->get('name'));
        $this->assertEquals(1, $sortedchildren[0]->get('sortorder'));
        $this->assertEquals($programcourse1->get('courseid'), $sortedchildren[1]->get('courseid'));
        $this->assertEquals($programcourse1->get('sortorder'), $sortedchildren[1]->get('sortorder'));
        $this->assertEquals($programcourse2->get('courseid'), $sortedchildren[2]->get('courseid'));
        $this->assertEquals($programcourse2->get('sortorder'), $sortedchildren[2]->get('sortorder'));
        $this->assertInstanceOf(program_set::class, $sortedchildren[0]);
        $this->assertInstanceOf(program_course::class, $sortedchildren[1]);
        $this->assertInstanceOf(program_course::class, $sortedchildren[2]);
    }

    /**
     * Test get next child sortorder.
     */
    public function test_get_next_child_sortorder(): void {
        $program = $this->generator->generate_program();
        $course1 = self::getDataGenerator()->create_course(['category' => 1]);
        $course2 = self::getDataGenerator()->create_course(['category' => 1]);
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $this->generator->add_course_to_set($course1->id, $basesetid, 1);
        $this->generator->add_course_to_set($course2->id, $basesetid, 2);

        $next = $baseset->get_next_child_sortorder();
        $this->assertEquals(3, $next);
    }
}
