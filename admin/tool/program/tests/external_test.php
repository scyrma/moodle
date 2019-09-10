<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Tests for the tool_program external class.
 *
 * @package   tool_program
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use tool_program\external;
use tool_program\constants;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_program\api;
use tool_program\persistent\program_course;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_program external class.
 *
 * @covers     \tool_program\external
 * @package    tool_program
 * @copyright  2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_program_external_testcase extends externallib_advanced_testcase {
    /**
     * @var tool_program_generator $generator
     */
    private $generator;
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
    public function setUp() {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');

        $data = $this->generator->create_tenant_and_user();
        self::setUser($data->user);
        $this->user = $data->user;
        $this->defaulttenantid = $data->defaulttenantid;
        $this->othertenantid = $data->othertenantid;

        $this->resetAfterTest();
    }

    public function test_archive_program(): void {
        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
        ]);
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $result = external::archive_program($program->get('id'));
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);

        $program = $program->read();
        $this->assertEquals(1, $program->get('archived'));

        $cleanresult = external_api::clean_returnvalue(external::archive_program_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_restore_program(): void {
        $program = $this->generator->generate_program((object) [
            'tenantid' => $this->defaulttenantid,
            'archived' => 1,
        ]);

        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $result = external::restore_program($program->get('id'));

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);

        $program = $program->read();
        $this->assertEquals(0, $program->get('archived'));

        $cleanresult = external_api::clean_returnvalue(external::restore_program_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_delete_program(): void {
        global $DB;
        $program = $this->generator->generate_program_with_base_set((object) [
            'tenantid' => $this->defaulttenantid,
            'archived' => 1,
        ]);
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $result = external::delete_program($program->get('id'));

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);

        $record = $DB->record_exists('tool_program', ['id' => $program->get('id')]);
        $this->assertEmpty($record);

        $cleanresult = external_api::clean_returnvalue(external::delete_program_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_deallocate_user(): void {
        global $DB;
        $program = $this->generator->generate_program_with_base_set((object) [
            'tenantid' => $this->defaulttenantid,
        ]);
        $programid = $program->get('id');

        $userdata = [
            'userid' => $this->user->id,
            'certificationid' => 0,
            'programid' => $programid
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

        $this->assertTrue($result['result']);
        $record = $DB->record_exists(program_user::TABLE, $userdata);
        $this->assertFalse($record);

        $cleanresult = external_api::clean_returnvalue(external::deallocate_user_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_get_user_programs(): void {
        $program1 = $this->generator->generate_program_with_base_set((object) [
            'fullname' => 'Program number 1',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program2 = $this->generator->generate_program_with_base_set((object) [
            'fullname' => 'Program number 2',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program3 = $this->generator->generate_program_with_base_set((object) [
            'fullname' => 'Program number 3',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program4 = $this->generator->generate_program_with_base_set((object) [
            'fullname' => 'Program number 4',
            'tenantid' => $this->defaulttenantid,
        ]);

        $result = external::get_user_programs();

        $this->assertTrue($result['status']);
        $this->assertEmpty($result['programs']);

        $cleanresult = external_api::clean_returnvalue(external::get_user_programs_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $userdata = (object) [
            'userid' => $this->user->id,
            'certificationid' => 0,
        ];
        api::allocate_user($program1, $userdata);
        $programuser2 = api::allocate_user($program2, $userdata);
        api::allocate_user($program4, $userdata);

        $result = external::get_user_programs();

        $this->assertNotEmpty($result);
        $this->assertCount(3, $result['programs']);
        $fullnames = array_map(static function($program) {
            return $program->fullname;
        }, $result['programs']);
        $this->assertContains($program1->get('fullname'), $fullnames);
        $this->assertContains($program2->get('fullname'), $fullnames);
        $this->assertContains($program4->get('fullname'), $fullnames);

        $cleanresult = external_api::clean_returnvalue(external::get_user_programs_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $userdata = (object) [
            'userid' => $this->user->id,
            'certificationid' => 0,
        ];
        $programuser3 = api::allocate_user($program3, $userdata);

        $result = external::get_user_programs();

        $this->assertNotEmpty($result);
        $this->assertCount(4, $result['programs']);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('programs', $result);
        $this->assertArrayHasKey('onlyenrolprogramcourseids', $result);

        $p = $result['programs'][0];
        $this->assertEquals($this->defaulttenantid, $p->tenantid);
        $this->assertObjectHasAttribute('programcourses', $p);
        $this->assertObjectHasAttribute('programsets', $p);
        $this->assertObjectHasAttribute('allocations', $p);
        $this->assertObjectHasAttribute('certifications', $p);

        $cleanresult = external_api::clean_returnvalue(external::get_user_programs_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $programuser3->delete();
        $programuser2->delete();

        $result = external::get_user_programs();

        $this->assertNotEmpty($result);
        $this->assertCount(2, $result['programs']);

        $cleanresult = external_api::clean_returnvalue(external::get_user_programs_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_duplicate_program(): void {
        $program = $this->generator->generate_program_with_base_set((object) [
            'tenantid' => $this->defaulttenantid,
        ]);

        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $result = external::duplicate_program($program->get('id'));

        $this->assertNotEmpty($result);
        $this->assertTrue($result['result']);
        $duplicatedprogramid = $result['duplicatedprogramid'];
        $programcopy = new program($duplicatedprogramid);
        $this->assertInstanceOf(program::class, $programcopy);
        $this->assertContains($program->get('fullname'), $programcopy->get('fullname'));
        $this->assertEquals($program->get('tenantid'), $programcopy->get('tenantid'));
        $this->assertNotEquals($program->get('id'), $programcopy->get('id'));

        $cleanresult = external_api::clean_returnvalue(external::duplicate_program_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_update_program_visibility(): void {
        global $DB;
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());
        $program = $this->generator->generate_program_with_base_set((object) [
            'tenantid' => $this->defaulttenantid,
            'visible' => constants::VISIBILITY_AVAILABLE,
        ]);
        $programid = $program->get('id');

        $result = external::update_program_visibility($programid, constants::VISIBILITY_HIDDEN);

        $this->assertTrue($result['result']);
        $record = $DB->get_record('tool_program', ['id' => $programid]);
        $this->assertEquals(constants::VISIBILITY_HIDDEN, $record->visible);

        $cleanresult = external_api::clean_returnvalue(external::update_program_visibility_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $result = external::update_program_visibility($programid, constants::VISIBILITY_AVAILABLE);

        $this->assertTrue($result['result']);
        $record = $DB->get_record('tool_program', ['id' => $programid]);
        $this->assertEquals(constants::VISIBILITY_AVAILABLE, $record->visible);

        $cleanresult = external_api::clean_returnvalue(external::update_program_visibility_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_delete_set(): void {
        global $DB;
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $program = $this->generator->generate_program_with_base_set((object) [
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

        $this->assertTrue($result['result']);

        // Check parent set deleted.
        $this->assertFalse($DB->record_exists('tool_program_sets', ['id' => $parentsetid]));
        // Check child set deleted.
        $this->assertFalse($DB->record_exists('tool_program_sets', ['id' => $childsetid]));
        // Check courses of parent set (course1) deleted.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourse1id]));
        // Check courses of child set (course2) deleted.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourse2id]));

        $cleanresult = external_api::clean_returnvalue(external::delete_set_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_delete_course(): void {
        global $DB;
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $program = $this->generator->generate_program_with_base_set((object) [
            'tenantid' => $this->defaulttenantid,
        ]);
        $basesetid = $program->get_base_set()->get('id');
        $course = self::getDataGenerator()->create_course();
        $programcourse = $this->generator->add_course_to_set($course->id, $basesetid);
        $this->generator->enable_program_enrol_instance($programcourse);

        // Check course exists.
        $this->assertTrue($DB->record_exists('tool_program_courses', ['id' => $programcourse->get('id')]));

        $result = external::delete_course($programcourse->get('id'));

        $this->assertTrue($result['result']);

        // Check course does not exist anymore.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourse->get('id')]));

        $cleanresult = external_api::clean_returnvalue(external::delete_course_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_potential_courses_program_selector(): void {
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $search = 'two';

        $result = external::potential_courses_program_selector($search);

        $this->assertEmpty($result);

        $course1 = self::getDataGenerator()->create_course(['fullname' => 'Course number one']);
        $course2 = self::getDataGenerator()->create_course(['fullname' => 'Course # two']);
        $course3 = self::getDataGenerator()->create_course(['fullname' => 'Course number three']);

        $cleanresult = external_api::clean_returnvalue(external::potential_courses_program_selector_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $result = external::potential_courses_program_selector($search);

        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey($course1->id, $result);
        $this->assertArrayHasKey($course2->id, $result);
        $this->assertArrayNotHasKey($course3->id, $result);

        $cleanresult = external_api::clean_returnvalue(external::potential_courses_program_selector_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $search = 'number';

        $result = external::potential_courses_program_selector($search);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey($course1->id, $result);
        $this->assertArrayNotHasKey($course2->id, $result);
        $this->assertArrayHasKey($course3->id, $result);

        $cleanresult = external_api::clean_returnvalue(external::potential_courses_program_selector_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $search = 'dog';

        $result = external::potential_courses_program_selector($search);

        $this->assertEmpty($result);

        $cleanresult = external_api::clean_returnvalue(external::potential_courses_program_selector_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_submit_edit_program_set_completion_form(): void {
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());
        $context = context_system::instance();
        $program = $this->generator->generate_program_with_base_set((object) ['tenantid' => $this->defaulttenantid]);
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

        $baseset = $baseset->read();
        $this->assertEquals(program_set::COMPLETION_AT_LEAST, $baseset->get('completioncriteria'));
        $this->assertEquals(15, $baseset->get('completionatleast'));

        $cleanresult = external_api::clean_returnvalue(external::submit_edit_program_set_completion_form_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_enrol_user_to_course(): void {
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $course = self::getDataGenerator()->create_course();
        $program = $this->generator->generate_program_with_base_set((object) [
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

        $this->assertTrue($result['status']);
        $this->assertContains($course->id, $result['redirecturl']);

        $cleanresult = external_api::clean_returnvalue(external::enrol_user_to_course_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_move_program_item(): void {
        global $DB;
        $this->generator->assign_edit_capability($this->user->id, context_system::instance());

        $program = $this->generator->generate_program_with_base_set((object) [
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

        $this->assertArrayHasKey('result', $result);
        $this->assertcount(3, $result['result']);
        $obj = $result['result'][0];
        $this->assertObjectHasAttribute('programid', $obj);
        $this->assertObjectHasAttribute('parent', $obj);
        $this->assertObjectHasAttribute('name', $obj);
        $this->assertObjectHasAttribute('sortorder', $obj);
        $this->assertObjectHasAttribute('completioncriteria', $obj);
        $this->assertObjectHasAttribute('completionatleast', $obj);
        $this->assertObjectHasAttribute('id', $obj);
        $this->assertEquals($programid, $obj->programid);

        $programcourse11record = $DB->get_record(program_course::TABLE, ['id' => $programcourse11id], '*', MUST_EXIST);
        $this->assertEquals($childset12id, $programcourse11record->setid);
        $this->assertEquals(1, $programcourse11record->sortorder);

        $cleanresult = external_api::clean_returnvalue(external::move_program_item_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }

    public function test_reset_program_progress(): void {
        $data = $this->generator->generate_program_filled_with_user_completion();
        $manager = $this->getDataGenerator()->create_user();
        self::setUser($manager);
        $this->generator->assign_allocateuser_capability($manager->id, context_system::instance());

        $programuser = $this->generator->allocate_user_to_program($data->program->get('id'), $data->user->id);

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

        $this->assertTrue($result['result']);

        $cleanresult = external_api::clean_returnvalue(external::reset_program_progress_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);
    }
}
