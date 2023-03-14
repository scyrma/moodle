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
 * Tests for the tool_catalogue get_user_catalogue external class.
 *
 * @covers     \tool_catalogue\external\get_user_catalogue
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_user_catalogue_test extends externallib_advanced_testcase {

    /**
     * Test execute
     */
    public function test_execute(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('showcataloguecoursecategory', '1', 'tool_catalogue');

        // User has no allocations.
        $result = get_user_catalogue::execute((int) $USER->id, '', 'name');
        $cleanresult = external_api::clean_returnvalue(get_user_catalogue::execute_returns(), $result);
        $this->assertEmpty($cleanresult['catalogue']['listitems']);

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        // Generate a program and allocate user.
        $duedate = time() + (DAYSECS * 7) + HOURSECS;
        $program = $programgenerator->generate_program((object) [
            'fullname' => 'My program 1',
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => $duedate,
        ]);
        $programgenerator->allocate_user_to_program($program->get('id'), (int) $USER->id);

        // Generate Course1, allocate user and set course as completed for the user.
        $category = self::getDataGenerator()->create_category(['name' => 'Cat 1']);
        $params = (object)['category' => $category->id, 'fullname' => 'My course 1'];
        $course = $programgenerator->generate_course_with_completion_self($params);
        $this->getDataGenerator()->enrol_user((int) $USER->id, $course->id, 'student');
        $programgenerator->complete_courses([$course->id], (int) $USER->id);

        $result = get_user_catalogue::execute((int) $USER->id, '', 'name');
        $cleanresult = external_api::clean_returnvalue(get_user_catalogue::execute_returns(), $result);

        $data = $cleanresult['catalogue']['listitems'];
        $this->assertCount(2, $data);

        // First item in the list should be Course1.
        $this->assertNotEmpty($data[0]['image']);
        $this->assertEquals($course->id, $data[0]['itemid']);
        $this->assertEquals('My course 1', $data[0]['fullname']);
        $this->assertEquals(constants::MAX_DATE, $data[0]['duedate']);
        $this->assertEquals('', $data[0]['duedatebadgestr']);
        $this->assertEquals('100', $data[0]['progress']);
        $this->assertEquals('Cat 1', $data[0]['categoryname']);
        $this->assertEquals(0, $data[0]['lastaccess']);
        $this->assertEquals(router::build_course_url((int) $course->id), $data[0]['url']);
        $this->assertFalse($data[0]['isprogram']);

        // Second item in the list should be Program1.
        $this->assertEquals('My program 1', $data[1]['fullname']);
        $this->assertEquals($program->get('id'), $data[1]['itemid']);
        $this->assertNotEmpty($data[1]['image']);
        $this->assertEquals('0.0', $data[1]['progress']);
        $this->assertEquals(0, $data[1]['numcourses']);
        $this->assertEquals(0, $data[1]['lastaccess']);
        $this->assertEquals($duedate, $data[1]['duedate']);
        $this->assertEquals('Due in 7 days', $data[1]['duedatebadgestr']);
        $this->assertEquals(router::build_program_url($program->get('id')), $data[1]['url']);
        $this->assertTrue($data[1]['isprogram']);
    }

    /**
     * Test execute passing parameter userid
     */
    public function test_execute_with_userid_parameter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        // Generate program and allocate user.
        $duedate = time() + (DAYSECS * 7) + HOURSECS;
        $program = $programgenerator->generate_program((object) [
            'fullname' => 'My program 1',
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => $duedate,
        ]);
        $programgenerator->allocate_user_to_program($program->get('id'), (int) $user->id);

        $result = get_user_catalogue::execute((int) $user->id);
        $cleanresult = external_api::clean_returnvalue(get_user_catalogue::execute_returns(), $result);

        $cleanresult = $cleanresult['catalogue'];
        $this->assertCount(1, $cleanresult['listitems']);

        $data = $cleanresult['listitems'][0];
        $this->assertEquals('My program 1', $data['fullname']);
        $this->assertEquals($program->get('id'), $data['itemid']);
        $this->assertNotEmpty($data['image']);
        $this->assertEquals('0.0', $data['progress']);
        $this->assertEquals(0, $data['numcourses']);
        $this->assertEquals(0, $data['lastaccess']);
        $this->assertEquals($duedate, $data['duedate']);
        $this->assertEquals('Due in 7 days', $data['duedatebadgestr']);
        $this->assertEquals(router::build_program_url($program->get('id')), $data['url']);
        $this->assertTrue($data['isprogram']);
    }

    /**
     * Test execute passing parameters userid, filter, sort and search
     */
    public function test_execute_with_parameters(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        // Generate program and allocate user.
        $program1 = $programgenerator->generate_program((object) ['fullname' => 'My program 1']);
        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $user->id);

        $program2 = $programgenerator->generate_program((object) ['fullname' => 'Our program 2']);
        $programgenerator->allocate_user_to_program($program2->get('id'), (int) $user->id);

        $program3 = $programgenerator->generate_program((object) ['fullname' => 'My program 3']);
        $programgenerator->allocate_user_to_program($program3->get('id'), (int) $user->id);

        // Generate Course1, allocate user and set course as completed for the user.
        $category = self::getDataGenerator()->create_category(['name' => 'Cat 1']);
        $params = (object)['category' => $category->id, 'fullname' => 'My course 1'];
        $course = $programgenerator->generate_course_with_completion_self($params);
        $this->getDataGenerator()->enrol_user((int) $user->id, $course->id, 'student');
        $programgenerator->complete_courses([$course->id], (int) $user->id);

        $result = get_user_catalogue::execute((int) $user->id, 'programs', 'name', 'My');
        $cleanresult = external_api::clean_returnvalue(get_user_catalogue::execute_returns(), $result);

        $data = $cleanresult['catalogue']['listitems'];

        // Only 'My program 1' and "My program 3' should be returned in this order.
        $this->assertCount(2, $data);

        // First item in the list should be Program 1.
        $this->assertEquals('My program 1', $data[0]['fullname']);
        $this->assertTrue($data[0]['isprogram']);

        // Second item in the list should be Program 3.
        $this->assertEquals('My program 3', $data[1]['fullname']);
        $this->assertTrue($data[1]['isprogram']);
    }

    /**
     * Test execute when requested userid does not exist
     */
    public function test_execute_exception_invalid(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(moodle_exception::class);
        get_user_catalogue::execute(1234);
    }

    /**
     * Test execute when requested userid is suspended
     */
    public function test_execute_exception_suspended(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $this->setUser($user);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Suspended account');
        get_user_catalogue::execute($user->id);
    }
}
