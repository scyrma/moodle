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

namespace enrol_program\external;

use external_api;
use externallib_advanced_testcase;
use moodle_exception;
use tool_program_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the enrol_program get_instance_info external class.
 *
 * @covers     \enrol_program\external\get_instance_info
 * @package    enrol_program
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_instance_info_test extends externallib_advanced_testcase {

    /**
     * Test execute
     */
    public function test_execute(): void {
        global $USER, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        // Generate a program and allocate user.
        $program = $programgenerator->generate_program((object) ['fullname' => 'My program 1']);
        $programgenerator->allocate_user_to_program($program->get('id'), (int) $USER->id);

        // Generate Course1, allocate user and set course as completed for the user.
        $category = self::getDataGenerator()->create_category(['name' => 'Cat 1']);
        $params = (object)['category' => $category->id, 'fullname' => 'My course 1'];
        $course1 = $programgenerator->generate_course_with_completion_self($params);
        $course2 = $programgenerator->generate_course_with_completion_self($params);
        $baseset = $program->get_base_set(); // By default is set to 'Complete all in order'.
        $programgenerator->add_course_to_set($course1->id, $baseset->get('id'), 1);
        $programgenerator->add_course_to_set($course2->id, $baseset->get('id'), 2);

        $enrolinstance = $DB->get_record('enrol', ['courseid' => $course1->id, 'enrol' => 'program'], '*', MUST_EXIST);

        $result = get_instance_info::execute((int) $enrolinstance->id);
        $cleanresult = external_api::clean_returnvalue(get_instance_info::execute_returns(), $result);

        // User is able to enrol into course1 because is the first course in the program.
        $expected = [
            'id' => (int) $enrolinstance->id,
            'courseid' => (int) $course1->id,
            'programid' => $program->get('id'),
            'type' => 'program',
            'name' => 'Program enrolment (My program 1)',
            'canselfenrol' => true,
        ];
        $this->assertEquals($expected, $cleanresult['instanceinfo']);

        $enrolinstance = $DB->get_record('enrol', ['courseid' => $course2->id, 'enrol' => 'program'], '*', MUST_EXIST);

        $result = get_instance_info::execute((int) $enrolinstance->id);
        $cleanresult = external_api::clean_returnvalue(get_instance_info::execute_returns(), $result);

        // User can not get enrolled into course2 yet because has to complete course1 first.
        $expected = [
            'id' => (int) $enrolinstance->id,
            'courseid' => (int) $course2->id,
            'programid' => $program->get('id'),
            'type' => 'program',
            'name' => 'Program enrolment (My program 1)',
            'canselfenrol' => false,
        ];
        $this->assertEquals($expected, $cleanresult['instanceinfo']);

        // Let user complete the first course in the program.
        $programgenerator->complete_courses([$course1->id], $USER->id);

        $result = get_instance_info::execute((int) $enrolinstance->id);
        $cleanresult = external_api::clean_returnvalue(get_instance_info::execute_returns(), $result);

        // Now user can get enrolled into course2 because course1 has been completed.
        $expected = [
            'id' => (int) $enrolinstance->id,
            'courseid' => (int) $course2->id,
            'programid' => $program->get('id'),
            'type' => 'program',
            'name' => 'Program enrolment (My program 1)',
            'canselfenrol' => true,
        ];
        $this->assertEquals($expected, $cleanresult['instanceinfo']);
    }

    /**
     * Test execute when requested instanceid does not exist
     */
    public function test_execute_exception_invalid(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(moodle_exception::class);
        get_instance_info::execute(1234);
    }
}
