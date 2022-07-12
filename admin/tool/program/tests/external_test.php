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

namespace tool_program;

use context_system;
use Exception;
use external_api;
use externallib_advanced_testcase;
use phpunit_util;
use stdClass;
use tool_program_generator;
use tool_tenant_generator;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_program\persistent\program_course;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_program external class.
 *
 * @covers     \tool_program\external
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external_test extends externallib_advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;
    /**
     * @var stdClass $user Currently logged user
     */
    private $user;
    /**
     * @var int $defaulttenantid Default tenant id (also the tenant id of $this->loggeduser)
     */
    private $defaulttenantid;
    /**
     * @var int $othertenantid Other tenant id (different from $this->defaulttenantid)
     */
    private $othertenantid;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');

        $this->user = self::getDataGenerator()->create_user();
        self::setUser($this->user);
        $this->defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $this->othertenantid = $tenantgenerator->create_tenant()->id;

        $this->resetAfterTest();
    }

    public function test_archive_program(): void {
        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
        ]);
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $result = external::archive_program($program->get('id'));
        $cleanresult = external_api::clean_returnvalue(external::archive_program_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertArrayHasKey('result', $cleanresult);
        $this->assertTrue($cleanresult['result']);

        $program = $program->read();
        $this->assertTrue($program->get('archived'));
    }

    public function test_restore_program(): void {
        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
            'archived' => 1,
        ]);

        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $result = external::restore_program($program->get('id'));
        $cleanresult = external_api::clean_returnvalue(external::restore_program_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertArrayHasKey('result', $cleanresult);
        $this->assertTrue($cleanresult['result']);

        $program = $program->read();
        $this->assertFalse($program->get('archived'));
    }

    public function test_delete_program(): void {
        global $DB;
        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
            'archived' => 1,
        ]);
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $result = external::delete_program($program->get('id'));
        $cleanresult = external_api::clean_returnvalue(external::delete_program_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertArrayHasKey('result', $cleanresult);
        $this->assertTrue($cleanresult['result']);

        $record = $DB->record_exists('tool_program', ['id' => $program->get('id')]);
        $this->assertEmpty($record);
    }

    public function test_deallocate_user(): void {
        global $DB;
        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
        ]);
        $programid = $program->get('id');

        $userdata = [
            'userid' => $this->user->id,
            'certificationid' => 0,
            'programid' => $programid,
        ];
        api::allocate_user($program, (object) $userdata);
        $record = $DB->get_record(program_user::TABLE, $userdata);
        $this->assertNotFalse($record);

        try {
            external::deallocate_user($programid, $this->user->id);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        $this->generator->assign_allocateuser_capability($this->user->id, context_system::instance());

        $result = external::deallocate_user($programid, $this->user->id);
        $cleanresult = external_api::clean_returnvalue(external::deallocate_user_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertTrue($cleanresult['result']);
        $this->assertFalse($DB->record_exists(program_user::TABLE, $userdata));
    }

    public function test_get_user_programs(): void {
        global $DB;
        $program1 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 1',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program2 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 2',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program3 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 3',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program4 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 4',
            'tenantid' => $this->defaulttenantid,
        ]);

        $result = external::get_user_programs();
        $cleanresult = external_api::clean_returnvalue(external::get_user_programs_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        // User is not enroled to any program or course.
        $this->assertTrue($cleanresult['status']);
        $this->assertEmpty($cleanresult['programs']);
        $this->assertEmpty($cleanresult['courses']);

        // Add course1 to program1.
        $course1 = $this->getDataGenerator()->create_course();
        $programcourse = $this->generator->add_course_to_set($course1->id, $program1->get_base_set()->get('id'));

        // Allocate user into program1, program2 and program4.
        $userdata = (object) [
            'userid' => $this->user->id,
            'certificationid' => 0,
        ];
        $programuser1 = api::allocate_user($program1, $userdata);
        $programuser2 = api::allocate_user($program2, $userdata);
        api::allocate_user($program4, $userdata);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);

        // Set a lastaccess time for the user for course1 (User has accessed the course at least this one time).
        // Lastaccess value is used on the UI for ordering courses and programs.
        $timeaccess1 = time();
        $DB->insert_record('user_lastaccess',
            ['userid' => $this->user->id, 'courseid' => $course1->id, 'timeaccess' => $timeaccess1]);

        // Allocate user into a separate course that does not belong to a program and also add a different lastaccess time.
        $course2 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->user->id, $course2->id);
        $timeaccess2 = time() + DAYSECS;
        $DB->insert_record('user_lastaccess',
            ['userid' => $this->user->id, 'courseid' => $course2->id, 'timeaccess' => $timeaccess2]);

        $result = external::get_user_programs();
        $cleanresult = external_api::clean_returnvalue(external::get_user_programs_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        // Check that WS returns that user is enroled into 3 programs and 1 separate course.
        $this->assertNotEmpty($cleanresult);
        $this->assertCount(3, $cleanresult['programs']);
        $fullnames = array_map(static function($program) {
            return $program->fullname;
        }, $result['programs']);
        $this->assertContains($program1->get_formatted_name(), $fullnames);
        $this->assertContains($program2->get_formatted_name(), $fullnames);
        $this->assertContains($program4->get_formatted_name(), $fullnames);
        // Check lastaccess values. One program has been accessed at least once and the other two have never been accessed.
        $this->assertEqualsCanonicalizing([$timeaccess1, 0, 0], array_column($cleanresult['programs'], 'lastaccess'));
        $programswithtimeaccess = array_filter($cleanresult['programs'], function($program) {
            return $program['lastaccess'] > 0;
        });
        $this->assertEquals($program1->get('id'), reset($programswithtimeaccess)['id']);
        // Check suser is enroled to course2 using manual enrolment and lastaccess value is correct.
        $this->assertCount(1, $cleanresult['courses']);
        $this->assertEquals($course2->id, $cleanresult['courses'][0]['id']);
        $this->assertEquals($timeaccess2, $cleanresult['courses'][0]['lastaccess']);

        // Allocate user into program3.
        $userdata = (object) [
            'userid' => $this->user->id,
            'certificationid' => 0,
        ];
        $programuser3 = api::allocate_user($program3, $userdata);

        // Create a new allocation to a certification that uses program1.
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $certification = $certificationgenerator->generate_certification([
            'tenantid' => $this->defaulttenantid,
            'program' => $program1->get('id'),
        ]);
        $certificationallocation = $certificationgenerator->allocate_user($this->user->id, $certification->get('id'));

        $result = external::get_user_programs();
        $cleanresult = external_api::clean_returnvalue(external::get_user_programs_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        // Check that now WS returns that user is enroled into 4 programs, 1 certification allocation and 1 separate course.
        $this->assertNotEmpty($cleanresult);
        $this->assertCount(4, $cleanresult['programs']);
        $this->assertCount(1, $cleanresult['courses']);
        $this->assertCount(1, $cleanresult['origins']);
        $this->assertArrayHasKey('status', $cleanresult);
        $this->assertArrayHasKey('programs', $cleanresult);
        $this->assertArrayHasKey('onlyenrolprogramcourseids', $cleanresult);

        $p = $cleanresult['programs'][0];
        $this->assertEquals($this->defaulttenantid, $p['tenantid']);
        $this->assertArrayHasKey('programcourses', $p);
        $this->assertArrayHasKey('programsets', $p);
        $this->assertArrayHasKey('allocations', $p);
        $this->assertArrayHasKey('certifications', $p);

        $this->assertCount(1, $cleanresult['courses']);
        $this->assertEquals($course2->fullname, $cleanresult['courses'][0]['fullname']);
        $this->assertEquals($course2->id, $cleanresult['courses'][0]['id']);
        $this->assertEquals($timeaccess2, $cleanresult['courses'][0]['lastaccess']);

        $this->assertEquals($program1->get('id'), $cleanresult['origins'][0]['programid']);
        $this->assertEquals($certification->get('id'), $cleanresult['origins'][0]['certificationid']);
        $this->assertEquals('certificationmsgactive', $cleanresult['origins'][0]['stringid']);

        $programuser3->delete();
        $programuser2->delete();

        // Check that now WS returns that user is enroled into 2 programs and 1 separate course.
        $result = external::get_user_programs();
        $cleanresult = external_api::clean_returnvalue(external::get_user_programs_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertCount(2, $cleanresult['programs']);
        $this->assertCount(1, $cleanresult['courses']);
    }

    public function test_duplicate_program(): void {
        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
        ]);

        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $result = external::duplicate_program($program->get('id'));
        $cleanresult = external_api::clean_returnvalue(external::duplicate_program_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertTrue($cleanresult['result']);
        $duplicatedprogramid = $cleanresult['duplicatedprogramid'];
        $programcopy = new program($duplicatedprogramid);
        $this->assertInstanceOf(program::class, $programcopy);
        $this->assertStringContainsString($program->get('fullname'), $programcopy->get('fullname'));
        $this->assertEquals($program->get('tenantid'), $programcopy->get('tenantid'));
        $this->assertNotEquals($program->get('id'), $programcopy->get('id'));
    }

    public function test_update_program_visibility(): void {
        global $DB;
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());
        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
            'visible' => constants::VISIBILITY_AVAILABLE,
        ]);
        $programid = $program->get('id');

        $result = external::update_program_visibility($programid, constants::VISIBILITY_HIDDEN);
        $cleanresult = external_api::clean_returnvalue(external::update_program_visibility_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertTrue($cleanresult['result']);
        $record = $DB->get_record('tool_program', ['id' => $programid]);
        $this->assertEquals(constants::VISIBILITY_HIDDEN, $record->visible);

        $result = external::update_program_visibility($programid, constants::VISIBILITY_AVAILABLE);
        $cleanresult = external_api::clean_returnvalue(external::update_program_visibility_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertTrue($cleanresult['result']);
        $record = $DB->get_record('tool_program', ['id' => $programid]);
        $this->assertEquals(constants::VISIBILITY_AVAILABLE, $record->visible);
    }

    public function test_delete_set(): void {
        global $DB;
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
        ]);
        $programid = $program->get('id');
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $parentset = $this->generator->generate_set((object) ['programid' => $programid, 'parent' => $basesetid]);
        $parentsetid = $parentset->get('id');
        $course1 = self::getDataGenerator()->create_course();
        $course1id = $course1->id;
        $programcourse1 = $this->generator->add_course_to_set($course1id, $parentsetid);
        $programcourse1id = $programcourse1->get('id');
        $childset = $this->generator->generate_set((object) ['programid' => $programid, 'parent' => $parentsetid]);
        $childsetid = $childset->get('id');
        $course2 = self::getDataGenerator()->create_course();
        $course2id = $course2->id;
        $programcourse2 = $this->generator->add_course_to_set($course2id, $childsetid);
        $programcourse2id = $programcourse2->get('id');

        $result = external::delete_set($parentsetid);
        $cleanresult = external_api::clean_returnvalue(external::delete_set_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertTrue($cleanresult['result']);

        // Check parent set deleted.
        $this->assertFalse($DB->record_exists('tool_program_sets', ['id' => $parentsetid]));
        // Check child set deleted.
        $this->assertFalse($DB->record_exists('tool_program_sets', ['id' => $childsetid]));
        // Check courses of parent set (course1) deleted.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourse1id]));
        // Check courses of child set (course2) deleted.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourse2id]));
    }

    public function test_delete_course(): void {
        global $DB;
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
        ]);
        $basesetid = $program->get_base_set()->get('id');
        $course = self::getDataGenerator()->create_course();
        $programcourse = $this->generator->add_course_to_set($course->id, $basesetid);

        // Check course exists.
        $this->assertTrue($DB->record_exists('tool_program_courses', ['id' => $programcourse->get('id')]));

        $result = external::delete_course($programcourse->get('id'));
        $cleanresult = external_api::clean_returnvalue(external::delete_course_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertTrue($cleanresult['result']);

        // Check course does not exist anymore.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourse->get('id')]));
    }

    public function test_potential_courses_program_selector(): void {
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $search = 'two';

        $result = external::potential_courses_program_selector($search);
        $cleanresult = external_api::clean_returnvalue(external::potential_courses_program_selector_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertEmpty($cleanresult);

        $course1 = self::getDataGenerator()->create_course(['fullname' => 'Course number one']);
        $course2 = self::getDataGenerator()->create_course(['fullname' => 'Course # two']);
        $course3 = self::getDataGenerator()->create_course(['fullname' => 'Course number three']);

        $result = external::potential_courses_program_selector($search);
        $cleanresult = external_api::clean_returnvalue(external::potential_courses_program_selector_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertCount(1, $cleanresult);
        $this->assertEquals($course2->id, $cleanresult[0]['id']);

        $search = 'number';

        $result = external::potential_courses_program_selector($search);
        $cleanresult = external_api::clean_returnvalue(external::potential_courses_program_selector_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertCount(2, $cleanresult);
        $this->assertEqualsCanonicalizing([$course1->id, $course3->id], array_column($cleanresult, 'id'));

        $search = 'dog';

        $result = external::potential_courses_program_selector($search);
        $cleanresult = external_api::clean_returnvalue(external::potential_courses_program_selector_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertEmpty($cleanresult);
    }

    public function test_submit_edit_program_set_completion_form(): void {
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());
        $context = context_system::instance();
        $program = $this->generator->generate_program((object) ['tenantid' => $this->defaulttenantid]);
        $baseset = $program->get_base_set();

        $this->assertNotEquals(program_set::COMPLETION_AT_LEAST, $baseset->get('completioncriteria'));
        $this->assertNotEquals(15, $baseset->get('completionatleast'));

        $_POST['sesskey'] = sesskey();
        $formdata = http_build_query([
            '_qf__set_completion_form_' . $baseset->get('id') => true,
            'setid' => $baseset->get('id'),
            'completioncriteria' => program_set::COMPLETION_AT_LEAST,
            'completionatleast' => 15,
        ], null, '&');

        $result = external::submit_edit_program_set_completion_form($context->id, '"' . $formdata . '"');
        $cleanresult = external_api::clean_returnvalue(external::submit_edit_program_set_completion_form_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $baseset = $baseset->read();
        $this->assertEquals(program_set::COMPLETION_AT_LEAST, $baseset->get('completioncriteria'));
        $this->assertEquals(15, $baseset->get('completionatleast'));
    }

    public function test_enrol_user_to_course(): void {
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $course = self::getDataGenerator()->create_course();
        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
        ]);
        api::add_course_to_base_set($program->get('id'), $course->id);

        try {
            external::enrol_user_to_course($course->id, $program->get('id'));
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        $this->generator->allocate_user_to_program($program->get('id'), $this->user->id);

        $result = external::enrol_user_to_course($course->id, $program->get('id'));
        $cleanresult = external_api::clean_returnvalue(external::enrol_user_to_course_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertTrue($cleanresult['status']);
        $this->assertStringContainsString($course->id, $cleanresult['redirecturl']);
    }

    public function test_move_program_item(): void {
        global $DB;
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
        ]);
        $programid = $program->get('id');
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        // Items within the base set.
        $course11 = self::getDataGenerator()->create_course();
        $course11id = $course11->id;
        $programcourse11 = $this->generator->add_course_to_set($course11id, $basesetid);
        $programcourse11id = $programcourse11->get('id');
        $childset12 = $this->generator->generate_set((object) [
            'programid' => $programid,
            'parent' => $basesetid,
            'sortorder' => 2,
        ]);
        $childset12id = $childset12->get('id');
        // Items within a child set.
        $childset21 = $this->generator->generate_set((object) [
            'programid' => $programid,
            'parent' => $childset12id,
            'sortorder' => 1,
        ]);
        $childset21id = $childset21->get('id');

        /*
         * Initial situation:
         *
         * 1 Base set
         *      - 1 course11
         *      - 2 childset12
         *          - 1 childset21
         */

        $programcourse11record = $DB->get_record(program_course::TABLE, ['id' => $programcourse11id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $programcourse11record->setid);
        $this->assertEquals(1, $programcourse11record->sortorder);

        // Move course11 item into childset12 set.
        $result = external::move_program_item($programid, $programcourse11id, false, $basesetid, $childset12id,
            $childset21id, true);
        $cleanresult = external_api::clean_returnvalue(external::move_program_item_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertcount(3, $cleanresult['result']);
        $array = $cleanresult['result'][0];
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('sortorder', $array);
        $this->assertArrayHasKey('isset', $array);

        $programcourse11record = $DB->get_record(program_course::TABLE, ['id' => $programcourse11id], '*', MUST_EXIST);
        $this->assertEquals($childset12id, $programcourse11record->setid);
        $this->assertEquals(1, $programcourse11record->sortorder);
    }

    public function test_reset_program_progress(): void {
        // Generate a program, allocate user to it and complete it as a user.
        $program = $this->generator->generate_program_with_course();
        $user = self::getDataGenerator()->create_user();
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $user->id);
        $this->generator->complete_program($program, $user->id);

        // Create a user who can allocate to the program only.
        $manager = $this->getDataGenerator()->create_user();
        self::setUser($manager);
        $this->generator->assign_allocateuser_capability($manager->id, context_system::instance());

        // Organisation managers can not reset courses.
        try {
            external::reset_program_progress($programuser->get('id'));
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // Admin has capability to reset courses.
        self::setAdminUser();
        $result = external::reset_program_progress($programuser->get('id'));
        $cleanresult = external_api::clean_returnvalue(external::reset_program_progress_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertTrue($cleanresult['result']);
    }

    public function test_potential_program_selector() {
        // We retrieve default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        // Create one user, he will be allocated to default tenant.
        $user = self::getDataGenerator()->create_user();
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = phpunit_util::get_data_generator()->get_plugin_generator('tool_tenant');
        // Create one more tenant.
        $othertenant = $tenantgenerator->create_tenant([]);
        $this->generator->assign_edit_capability($user->id, context_system::instance());
        self::setUser($user);

        // We create a dummy program.
        $params = (object)['fullname' => 'A program fullname', 'tenantid' => $defaulttenantid];
        $program1 = $this->generator->generate_program($params);
        $programdata = $program1->to_record();
        unset($programdata->id);

        // We create another dummy program.
        $programdata->fullname = 'A program number two';
        $program2 = $this->generator->generate_program($programdata);

        // We create another dummy program with different tenantid.
        $programdata->fullname = 'A program number three';
        $programdata->tenantid = $othertenant->id;
        $program3 = $this->generator->generate_program($programdata);

        $progs = external::potential_program_selector('program');
        $progs = external::clean_returnvalue(external::potential_program_selector_returns(), $progs);
        $this->assertCount(2, $progs);
        $this->assertEqualsCanonicalizing([$program1->get('id'), $program2->get('id')], array_column($progs, 'id'));
        $this->assertEqualsCanonicalizing([$program1->get('fullname'), $program2->get('fullname')],
            array_column($progs, 'fullname'));

        $progs = external::potential_program_selector('number');
        $progs = external::clean_returnvalue(external::potential_program_selector_returns(), $progs);
        $this->assertCount(1, $progs);
        $this->assertEqualsCanonicalizing([$program2->get('id')], array_column($progs, 'id'));
        $this->assertEqualsCanonicalizing([$program2->get('fullname')], array_column($progs, 'fullname'));

        $progs = external::potential_program_selector('dogs');
        $this->assertEmpty($progs);
    }

    public function test_get_user_learning_statuses(): void {
        // Create some programs.
        $program1 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 1',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program2 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 2',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program3 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 3',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program4 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 4',
            'tenantid' => $this->defaulttenantid,
        ]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // Allocate user1 to 3 programs.
        $userdata = (object) [
            'userid' => $user1->id,
            'certificationid' => 0,
        ];
        api::allocate_user($program1, $userdata);
        $programuser2 = api::allocate_user($program2, $userdata);
        api::allocate_user($program4, $userdata);

        // Generate a small organisation structure.
        $orggenerator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $df = $orggenerator->create_department(['tenantid' => $this->defaulttenantid]);
        $da = $orggenerator->create_department(['parentid' => $df->id]);
        $pf = $orggenerator->create_position(['tenantid' => $this->defaulttenantid]);
        $pa = $orggenerator->create_position(['parentid' => $pf->id, 'globalmanager' => 1,
            'globalpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS |
                \tool_organisation\organisation::PERM_VIEW_REPORTS]);
        $pa1 = $orggenerator->create_position(['parentid' => $pa->id]);

        // Assign user2 as manager of user1.
        $orggenerator->assign_job((object)[
            'userid' => $user1->id,
            'positionid' => $pa1->id,
            'departmentid' => $da->id,
        ]);
        $orggenerator->assign_job((object)[
            'userid' => $user2->id,
            'positionid' => $pa->id,
            'departmentid' => $da->id,
        ]);

        // Call the WS as manager.
        $this->setUser($user2);

        $result = external::get_user_learning_statuses($user1->id);
        $cleanresult = external_api::clean_returnvalue(external::get_user_learning_statuses_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
        $this->assertNotEmpty($cleanresult);

        $this->assertCount(3, $cleanresult['programs']);
        $expected = [
            $program1->get_formatted_name(),
            $program2->get_formatted_name(),
            $program4->get_formatted_name(),
        ];
        $this->assertEqualsCanonicalizing($expected, array_column($cleanresult['programs'], 'fullname'));

        $this->assertEmpty($cleanresult['certifications']);
        $this->assertEquals(0, $cleanresult['hasexpiredcertifications']);

        // Call the WS as a regular user not in the organisation.
        $this->setUser($user3);
        try {
            $result = external::get_user_learning_statuses($user1->id);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }
    }

    public function test_bulk_deallocate_user(): void {
        global $DB;
        $program = $this->generator->generate_program((object) [
            'fullname' => 'Program number 1',
            'tenantid' => $this->defaulttenantid,
        ]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user();
        $user6 = $this->getDataGenerator()->create_user();
        $programusers = $this->generator->allocate_users_to_program($program->get('id'),
            [$user1->id, $user2->id, $user3->id, $user4->id, $user5->id, $user6->id]);

        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $certification = $certificationgenerator->generate_certification([
            'tenantid' => $this->defaulttenantid,
            'program' => $program->get('id'),
        ]);
        $programusers[$user4->id]->set('certificationid', $certification->get('id'));
        $programusers[$user4->id]->update();

        $this->assertEquals(6, program_user::count_records());

        // Set allocation type dynamic for user6.
        $DB->set_field('tool_program_users', 'allocationtype', constants::ALLOCATION_DYNAMIC,
            ['userid' => $user6->id, 'programid' => $program->get('id')]);

        $this->setAdminUser();
        $result = external::bulk_deallocate_user([
            $programusers[$user1->id]->get('id'),
            $programusers[$user3->id]->get('id'),
            $programusers[$user4->id]->get('id'),
            $programusers[$user6->id]->get('id'),
        ]);
        $cleanresult = external_api::clean_returnvalue(external::bulk_deallocate_user_returns(), $result);

        $this->assertEquals(2, $cleanresult['successcount']);
        // Certification (user4) and dynamic (user6) allocations can not be modified.
        $this->assertEquals(2, $cleanresult['skippedcount']);
        // Only user2, user4, user5 and user6 should still be allocated into the program.
        $allocateduserids = array_map(static function($puser) {
            return $puser->get('userid');
        }, program_user::get_records());
        $this->assertEqualsCanonicalizing([$user2->id, $user4->id, $user5->id, $user6->id], $allocateduserids);

        // Set a current user with no permission to deallocate.
        $this->setUser($user1);
        $result = external::bulk_deallocate_user([
            $programusers[$user2->id]->get('id'),
            $programusers[$user5->id]->get('id'),
        ]);
        $cleanresult = external_api::clean_returnvalue(external::bulk_deallocate_user_returns(), $result);

        $this->assertEquals(0, $cleanresult['successcount']);
        // Certification allocations can not be modified.
        $this->assertEquals(2, $cleanresult['skippedcount']);

        $allocateduserids = array_map(static function($puser) {
            return $puser->get('userid');
        }, program_user::get_records());
        $this->assertEqualsCanonicalizing([$user2->id, $user4->id, $user5->id, $user6->id], $allocateduserids);
    }

    public function test_bulk_reset_program_progress(): void {
        // Generate a program, allocate user to it and complete it as a user.
        $program = $this->generator->generate_program_with_course();
        $user1 = self::getDataGenerator()->create_user();
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $this->generator->complete_program($program, $user1->id);
        $user2 = self::getDataGenerator()->create_user();
        $programuser2 = $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $this->generator->complete_program($program, $user2->id);

        $this->setAdminUser();
        $result = external::bulk_reset_program_progress([$programuser1->get('id'), $programuser2->get('id')]);
        $cleanresult = external_api::clean_returnvalue(external::bulk_reset_program_progress_returns(), $result);

        $this->assertEquals(2, $cleanresult['successcount']);
        $this->assertEquals(0, $cleanresult['skippedcount']);

        // Set a current user with no permission to reset program.
        $this->setUser($user1);
        $result = external::bulk_reset_program_progress([$programuser1->get('id'), $programuser2->get('id')]);
        $cleanresult = external_api::clean_returnvalue(external::bulk_reset_program_progress_returns(), $result);

        $this->assertEquals(0, $cleanresult['successcount']);
        $this->assertEquals(2, $cleanresult['skippedcount']);
    }

    /**
     * Ensure that strings necessary for the mobile app are present
     *
     * This unittest should prevent accidental removal of these strings in the strings clean-up issues
     *
     * @return void
     */
    public function test_mobile_strings(): void {
        $this->assertNotEmpty(get_string('notenrolledprograms_mobile', 'tool_program'));
        $this->assertNotEmpty(get_string('programsoverview_mobile', 'tool_program'));
        $this->assertNotEmpty(get_string('errorloadingprogram_mobile', 'tool_program'));
        $this->assertNotEmpty(get_string('seecontent_mobile', 'tool_program'));
        $this->assertNotEmpty(get_string('setsandcoursesnotfound_mobile', 'tool_program'));
    }
}
