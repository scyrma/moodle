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

namespace block_myavailable;

use advanced_testcase;
use tool_program\persistent\program_set;
use tool_program_generator;

/**
 * Unit tests for manager class
 *
 * @package     block_myavailable
 * @covers      \block_myavailable\manager
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager_test extends advanced_testcase {
    /** @var tool_program_generator */
    protected $programgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    /**
     * Test get_available_courses
     */
    public function test_get_available_courses(): void {
        global $DB;
        $this->setAdminUser();

        // Create student user.
        $student = $this->getDataGenerator()->create_user();

        // Create all courses.
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();
        $course4 = $this->getDataGenerator()->create_course();
        $course5 = $this->getDataGenerator()->create_course();
        $course6 = $this->getDataGenerator()->create_course();
        $course7 = $this->getDataGenerator()->create_course();
        $course8 = $this->getDataGenerator()->create_course(['visible' => false]);
        $course9 = $this->getDataGenerator()->create_course();
        $course10 = $this->getDataGenerator()->create_course();

        // Create program with structure.
        $program = $this->programgenerator->generate_program();
        $baseset = $program->get_base_set();
        $baseset->set('completioncriteria', program_set::COMPLETION_ALL_IN_ANY_ORDER);
        $baseset->update();
        $set1 = $this->programgenerator->generate_set((object) [
            'name' => 'Set1',
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'sortorder' => 3,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ANY_ORDER,
        ]);
        $set1a = $this->programgenerator->generate_set((object) [
            'name' => 'Set1-A',
            'programid' => $program->get('id'),
            'parent' => $set1->get('id'),
            'sortorder' => 2,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
        ]);
        $set2 = $this->programgenerator->generate_set((object) [
            'name' => 'Set2',
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'sortorder' => 4,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
        ]);
        $set2a = $this->programgenerator->generate_set((object) [
            'name' => 'Set2-A',
            'programid' => $program->get('id'),
            'parent' => $set2->get('id'),
            'sortorder' => 2,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
        ]);

        $this->programgenerator->add_course_to_set((int) $course3->id, $baseset->get('id'), 1);
        $this->programgenerator->add_course_to_set((int) $course4->id, $baseset->get('id'), 2);
        $this->programgenerator->add_course_to_set((int) $course5->id, $set1->get('id'), 1);
        $this->programgenerator->add_course_to_set((int) $course6->id, $set1a->get('id'), 1);
        $this->programgenerator->add_course_to_set((int) $course7->id, $set1a->get('id'), 2);
        $this->programgenerator->add_course_to_set((int) $course8->id, $set2->get('id'), 1);
        $this->programgenerator->add_course_to_set((int) $course9->id, $set2a->get('id'), 1);
        $this->programgenerator->add_course_to_set((int) $course10->id, $set2a->get('id'), 2);

        // Enroll student in courses.
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($student->id, $course2->id, 'student');

        // Make sure user sees only courses he is enrolled in.
        $availablecourses = manager::get_available_courses((int) $student->id);
        $returnedcourseids = array_column($availablecourses, 'id');
        $this->assertEquals([$course1->id, $course2->id], $returnedcourseids);

        // Make user access the course2.
        $timeaccess = time();
        $DB->insert_record('user_lastaccess', ['userid' => $student->id, 'courseid' => $course2->id, 'timeaccess' => $timeaccess]);

        // Make sure user sees only courses he is enrolled in and has not accessed.
        $availablecourses = manager::get_available_courses((int) $student->id);
        $returnedcourseids = array_column($availablecourses, 'id');
        $this->assertEquals([$course1->id], $returnedcourseids);

        // Allocate user to the program.
        $this->programgenerator->allocate_user_to_program($program->get('id'), (int) $student->id);

        // Make sure get_available_courses returns different content with and without user id when logged in with different user.
        $availablecoursescurrentuser = manager::get_available_courses();
        $availablecourseswithuserid = manager::get_available_courses((int) $student->id);
        $this->assertNotEquals($availablecoursescurrentuser, $availablecourseswithuserid);

        // Make sure get_available_courses returns same content with and without user id when logged in with same user.
        $this->setUser($student);
        $availablecoursescurrentuser = manager::get_available_courses();
        $availablecourseswithuserid = manager::get_available_courses((int) $student->id);
        $this->assertEquals($availablecoursescurrentuser, $availablecourseswithuserid);

        // Make sure get_available_courses returns visible courses where user can access and has not accessed yet.
        $courseidstoshow = [
            $course1->id,
            $course3->id,
            $course4->id,
            $course5->id,
            $course6->id,
        ];
        $returnedcourseids = array_column($availablecoursescurrentuser, 'id');
        $this->assertEquals($courseidstoshow, $returnedcourseids);
    }
}
