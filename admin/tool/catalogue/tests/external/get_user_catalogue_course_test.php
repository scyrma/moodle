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

namespace tool_catalogue\external;

use external_api;
use externallib_advanced_testcase;
use moodle_exception;
use tool_catalogue\router;
use tool_program\constants;
use tool_program_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_catalogue get_user_catalogue_course external class.
 *
 * @covers     \tool_catalogue\external\get_user_catalogue_course
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_user_catalogue_course_test extends externallib_advanced_testcase {

    /**
     * Test execute
     */
    public function test_execute(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        // Generate Course1, allocate user and set course as completed for the user.
        $category = self::getDataGenerator()->create_category(['name' => 'Cat 1']);
        $params = (object)['category' => $category->id, 'fullname' => 'My course 1'];
        $course = $programgenerator->generate_course_with_completion_self($params);
        $this->getDataGenerator()->enrol_user((int) $USER->id, $course->id, 'student');
        $programgenerator->complete_courses([$course->id], (int) $USER->id);

        // Generate program and allocate user.
        $duedate1 = time() + (DAYSECS * 7) + HOURSECS;
        $program1 = $programgenerator->generate_program((object) [
            'fullname' => 'My program 1',
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => $duedate1,
        ]);
        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $USER->id);
        $programgenerator->add_course_to_set((int) $course->id, $program1->get_base_set()->get('id'), 1);

        $result = get_user_catalogue_course::execute((int) $course->id, (int) $USER->id);
        $cleanresult = external_api::clean_returnvalue(get_user_catalogue_course::execute_returns(), $result);

        $cleanresult = $cleanresult['course'];
        $this->assertEquals($course->id, $cleanresult['course']['id']);
        $this->assertEquals($course->fullname, $cleanresult['course']['fullname']);
        $this->assertEquals([], $cleanresult['coursefiles']);

        $this->assertEquals([[
            'name' => "My program 1",
            'url' => router::build_program_url($program1->get('id')),
        ]], $cleanresult['linkedprograms']);
        $this->assertEmpty($cleanresult['trainers']);
        $this->assertEmpty($cleanresult['restrictions']);
        $this->assertEquals('', $cleanresult['duedatebadgestr']);
    }

    /**
     * Test execute when requested userid does not exist
     */
    public function test_execute_exception_invalid(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(moodle_exception::class);
        get_user_catalogue_course::execute(1234, 1234);
    }

    /**
     * Test execute when requested course does not exist
     */
    public function test_execute_exception_invalid2(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(moodle_exception::class);
        get_user_catalogue_course::execute(1234, (int) $user->id);
    }

    /**
     * Test execute when requested userid is suspended
     */
    public function test_execute_exception_suspended(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $this->setUser($user);

        $course = self::getDataGenerator()->create_course();
        self::getDataGenerator()->enrol_user($user->id, $course->id);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Suspended account');
        get_user_catalogue_course::execute((int) $course->id, $user->id);
    }
}
