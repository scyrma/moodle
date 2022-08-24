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
use tool_certification_generator;
use tool_program\constants;
use tool_program\persistent\program_user;
use tool_program_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_catalogue get_user_catalogue_program external class.
 *
 * @covers     \tool_catalogue\external\get_user_catalogue_program
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_user_catalogue_program_test extends externallib_advanced_testcase {

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
        $now = time();
        $duedate1 = $now + (DAYSECS * 7) + HOURSECS;
        $enddate = $now + YEARSECS;
        $program = $programgenerator->generate_program((object) [
            'fullname' => 'My program 1',
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => $duedate1,
            'enddatetype' => constants::DATE_ABSOLUTE,
            'enddateabsolute' => $enddate,
        ]);
        $programuser = $programgenerator->allocate_user_to_program($program->get('id'), (int) $USER->id);
        $programgenerator->add_course_to_set((int) $course->id, $program->get_base_set()->get('id'), 1);

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');

        $expirydate = $now + YEARSECS;
        $certification1 = $certificationgenerator->generate_certification([
            'fullname' => 'Certification 1',
            'program' => $program->get('id'),
            'duedatetype' => \tool_certification\constants::DATE_AFTER_START_DATE,
            'duedaterelative' => '1 month',
            'expirydatetype' => \tool_certification\constants::DATE_ABSOLUTE,
            'expirydateabsolute' => $expirydate,
        ]);
        $certificationgenerator->allocate_user($USER->id, $certification1->get('id'));

        $result = get_user_catalogue_program::execute($program->get('id'), (int) $USER->id);
        $cleanresult = external_api::clean_returnvalue(get_user_catalogue_program::execute_returns(), $result);

        $programstructure = $cleanresult['programstructure'];
        $catalogueprogram = $cleanresult['program'];

        $this->assertEquals($program->get('fullname'), $catalogueprogram['fullname']);
        $this->assertEquals($program->get('description'), $catalogueprogram['description']);
        $this->assertNotEmpty($catalogueprogram['image']);
        $this->assertEqualsCanonicalizing(['hello', 'world'], $catalogueprogram['tags']);
        $this->assertEmpty($catalogueprogram['customfields']);
        $this->assertEquals(1, $catalogueprogram['numcourses']);
        $this->assertEquals(0, $catalogueprogram['lastaccess']);
        $params = [
            'name' => $certification1->get_formatted_name(),
            'date' => userdate($expirydate, get_string('strftimedatefullshort', 'langconfig'))
        ];
        $allocation1 = program_user::get_record([
            'certificationid' => $certification1->get('id'),
            'userid' => $USER->id,
            'programid' => $program->get('id'),
        ]);
        $this->assertEquals([[
            'message' => get_string('certificationstatuscertifiedwithdate', 'tool_catalogue', $params),
            'type' => 'success',
            'hash' => md5('certificationstatuscertifiedwithdate' . $allocation1->get('id')),
        ]], $catalogueprogram['certifications']);
        $this->assertEquals(100, $catalogueprogram['progress']);
        $this->assertEquals('Complete all in order', $catalogueprogram['basesetcriteria']);

        $this->assertEquals($programuser->get('startdate'), $catalogueprogram['startdate']);
        $this->assertEquals(userdate($programuser->get('startdate'),
            get_string('strftimedatefullshort', 'langconfig')), $catalogueprogram['startdatestr']);
        $this->assertEquals($duedate1, $catalogueprogram['duedate']);
        $this->assertEquals(userdate($duedate1, get_string('strftimedatefullshort', 'langconfig')),
            $catalogueprogram['duedatestr']);
        $this->assertEquals('warning', $catalogueprogram['duedatebadgetype']);
        $this->assertEquals('Due in 7 days', $catalogueprogram['duedatebadgestr']);
        $this->assertEquals($enddate, $catalogueprogram['enddate']);
        $this->assertEquals(userdate($enddate, get_string('strftimedatefullshort', 'langconfig')),
            $catalogueprogram['enddatestr']);

        $baseset = $programstructure['sets'][0];
        $this->assertTrue($baseset['isset']);
        $this->assertEquals('Complete all in order', $baseset['setcriteriastr']);
        $this->assertEquals(1, $baseset['completeditems']);
        $this->assertEquals(1, $baseset['numcourses']);
        $this->assertTrue($baseset['iscompleted']);
        $this->assertEquals(100, $baseset['progress']);
        $this->assertEquals(1, $baseset['totalitems']);
        $this->assertFalse($baseset['islocked']);
        $this->assertEquals(router::build_program_set_url($program->get('id'), $baseset['setid']), $baseset['url']);

        $programcourse = $programstructure['courses'][0];
        $this->assertFalse($programcourse['isset']);
        $this->assertEquals($program->get_base_set()->get('id'), $programcourse['setid']);
        $this->assertEquals($course->id, $programcourse['courseid']);
        $this->assertEquals(1, $programcourse['sortorder']);
        $this->assertEquals($course->fullname, $programcourse['fullname']);
        $this->assertTrue($programcourse['iscompleted']);
        $this->assertEquals(100, $programcourse['progress']);
        $this->assertFalse($programcourse['islocked']);
        $this->assertFalse($programcourse['ishidden']);
    }

    /**
     * Test execute when requested userid does not exist
     */
    public function test_execute_exception_invalid(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $program = $programgenerator->generate_program();

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Invalid user');
        get_user_catalogue_program::execute($program->get('id'), 1234);
    }

    /**
     * Test execute when requested program does not exist
     */
    public function test_execute_exception_invalid2(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Can\'t find data record in database table tool_program');
        get_user_catalogue_program::execute(1234, (int) $user->id);
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
        get_user_catalogue_program::execute(1234, $user->id);
    }
}
