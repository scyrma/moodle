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

namespace block_myavailable\external;

use external_api;
use externallib_advanced_testcase;
use moodle_exception;
use tool_program\persistent\program_set;
use tool_program_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Unit tests for get_available_courses_test external class
 *
 * @package     block_myavailable
 * @covers      \block_myavailable\external\get_available_courses
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_available_courses_test extends externallib_advanced_testcase {
    /** @var tool_program_generator */
    protected $programgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
    }

    /**
     * Test execute
     */
    public function test_execute(): void {
        $this->resetAfterTest();

        // Create student user.
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);

        // Create all courses.
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'My course #1']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'My course #2']);
        $course3 = $this->getDataGenerator()->create_course(['fullname' => 'My course #3']);
        $course4 = $this->getDataGenerator()->create_course(['fullname' => 'My course #4']);
        $course5 = $this->getDataGenerator()->create_course(['fullname' => 'My course #5']);
        $course6 = $this->getDataGenerator()->create_course(['fullname' => 'My course #6']);

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
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
        ]);

        $this->programgenerator->add_course_to_set((int) $course3->id, $baseset->get('id'));
        $this->programgenerator->add_course_to_set((int) $course4->id, $baseset->get('id'), 2);
        $this->programgenerator->add_course_to_set((int) $course5->id, $set1->get('id'));
        $this->programgenerator->add_course_to_set((int) $course6->id, $set1->get('id'), 2);

        // Enrol student in courses.
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($student->id, $course2->id, 'student');

        // Make user access the course2.
        $timeaccess = time();
        $this->getDataGenerator()->create_user_course_lastaccess($student, $course2, $timeaccess);

        // Allocate user to the program.
        $this->programgenerator->allocate_user_to_program($program->get('id'), (int) $student->id);

        // Make sure get_available_courses returns courses where user can access and has not accessed yet.
        $coursestoshow = [
            'My course #1',
            'My course #3',
            'My course #4',
            'My course #5',
        ];
        $availablecoursesresult = get_available_courses::execute();
        $availablecourses = external_api::clean_returnvalue(get_available_courses::execute_returns(), $availablecoursesresult);
        $returnedcourses = array_column($availablecourses['data']['courses'], 'fullnamedisplay');
        $this->assertEmpty($availablecourses['warnings']);
        $this->assertEquals($coursestoshow, $returnedcourses);
    }
}
