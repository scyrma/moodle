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

/**
 * Test for program
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\constants;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_program\api;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/course/lib.php');

/**
 * Class tool_program_program_testcase
 *
 * @covers    \tool_program\persistent\program
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_program_testcase extends advanced_testcase {
    /** @var tool_program_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var stdClass */
    protected $course1;
    /** @var stdClass */
    protected $course2;
    /** @var program */
    protected $program1;
    /** @var program_set */
    protected $baseset;
    /** @var stdClass */
    protected $user1;
    /** @var stdClass */
    protected $user2;
    /** @var program_course */
    protected $programcourse1;
    /** @var program_course */
    protected $programcourse2;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();

        $this->course1 = self::getDataGenerator()->create_course(); // Empty course.
        $this->course2 = self::getDataGenerator()->create_course(); // Empty course.
        $this->program1 = $this->generator->generate_program($this->generator->get_dummy_program_data(false));
        $this->baseset = $this->program1->get_base_set();

        $this->programcourse1 = $this->generator->add_course_to_set($this->course1->id, $this->baseset->get('id'));
        $this->programcourse2 = $this->generator->add_course_to_set($this->course2->id, $this->baseset->get('id'));

        $this->user1 = self::getDataGenerator()->create_user();
        $this->user2 = self::getDataGenerator()->create_user();
    }

    /**
     * Test get sets
     */
    public function test_get_sets(): void {
        global $DB;

        // We create set for this program.
        api::add_set_to_base_set($this->program1->get('id'), 'Set 1');

        // We check base set exists.
        $baseset = $DB->record_exists(program_set::TABLE, ['parent' => '0', 'programid' => $this->program1->get('id')]);
        $this->assertTrue($baseset);

        // We check that we have the base set and the new set that we just created.
        $count = $DB->count_records(program_set::TABLE, ['programid' => $this->program1->get('id')]);
        $this->assertEquals('2', $count);

        $setslist = $this->program1->get_sets();
        foreach ($setslist as $set) {
            $this->assertEquals($this->program1->get('id'), $set->get('programid'));
        }
    }

    /**
     * Test get sets count
     */
    public function test_get_sets_count(): void {
        // We check that we have the base set.
        $setscount = $this->program1->get_sets_count();
        $this->assertEquals('1', $setscount);

        // We create set for this program.
        api::add_set_to_base_set($this->program1->get('id'), 'Set 2');

        // We check that we have the base set and the new set that we just created.
        $setscount = $this->program1->get_sets_count();
        $this->assertEquals('2', $setscount);

        // We create set for this program.
        api::add_set_to_base_set($this->program1->get('id'), 'Set 3');

        // We check that we have the base set and the new set that we just created.
        $setscount = $this->program1->get_sets_count();
        $this->assertEquals('3', $setscount);
    }

    /**
     * Test get courses
     */
    public function test_get_courses(): void {
        $courseslist = $this->program1->get_courses();

        // Program has already 2 courses.
        $this->assertNotEmpty($courseslist);
        $this->assertCount(2, $courseslist);

        foreach ($courseslist as $course) {
            $this->assertObjectHasAttribute('shortname', $course);
            $this->assertObjectHasAttribute('fullname', $course);
            $this->assertObjectHasAttribute('summary', $course);
            $this->assertObjectHasAttribute('idnumber', $course);
            $this->assertObjectHasAttribute('category', $course);
        }
    }

    /**
     * Test get courses count
     */
    public function test_get_courses_count(): void {
        // Program has already 2 courses.
        $coursescount = $this->program1->get_courses_count();
        $this->assertEquals('2', $coursescount);

        // We create another program with no courses.
        $newprogram = new program(0, (object) [
            'fullname' => 'A program fullname',
            'idnumber' => '15',
        ]);
        $newprogram->create();
        $coursescount = $newprogram->get_courses_count();
        $this->assertEquals('0', $coursescount);
    }

    /**
     * Test get users count
     */
    public function test_get_users_count(): void {
        global $DB;

        // We should no users allocated to this program.
        $userscount = $this->program1->get_users_count();
        $this->assertEquals('0', $userscount);

        // We allocate a new user.
        $userdata = [
            'programid' => $this->program1->get('id'),
            'userid' => $this->user1->id,
        ];
        $newuser = new program_user(0, (object) $userdata);
        $newuser->create();

        // We should have 1 user allocated to this program.
        $userscount = $this->program1->get_users_count();
        $this->assertEquals('1', $userscount);

        // We allocate a new user.
        $userdata = [
            'programid' => $this->program1->get('id'),
            'userid' => $this->user2->id,
        ];
        $newuser = new program_user(0, (object) $userdata);
        $newuser->create();

        // We should have 2 users allocated to this program.
        $userscount = $this->program1->get_users_count();
        $this->assertEquals('2', $userscount);

        // We remove one user.
        $record = $DB->get_record(program_user::TABLE, ['userid' => $this->user2->id, 'programid' => $this->program1->get('id')]);
        $user = new program_user($record->id);
        $user->delete();

        // We should have 1 user allocated to this program.
        $userscount = $this->program1->get_users_count();
        $this->assertEquals('1', $userscount);
    }

    /**
     * Test get users
     */
    public function test_get_users(): void {

        // We should no users allocated to this program.
        $userscount = $this->program1->get_users_count();
        $this->assertEquals('0', $userscount);

        // We should get an empty array because there are no users allocated in this program.
        $programusers = $this->program1->get_users();
        $this->assertEmpty($programusers);

        // We allocate a new user1.
        $userdata = [
            'programid' => $this->program1->get('id'),
            'userid' => $this->user1->id,
        ];
        $newuser = new program_user(0, (object) $userdata);
        $newuser->create();

        // We count users returned by function.
        $programusers = $this->program1->get_users();
        $this->assertCount(1, $programusers);
        $usertest = $programusers[$this->user1->id];
        $this->assertEquals($this->user1->deleted, $usertest->deleted);
        $this->assertEquals($this->user1->firstname, $usertest->firstname);
        $this->assertEquals($this->user1->lastname, $usertest->lastname);
        $this->assertEquals($this->user1->email, $usertest->email);

        // We allocate a new user2.
        $userdata = [
            'programid' => $this->program1->get('id'),
            'userid' => $this->user2->id,
        ];
        $newuser = new program_user(0, (object) $userdata);
        $newuser->create();

        $programusers = $this->program1->get_users();

        // We count users returned by function.
        $this->assertCount(2, $programusers);
    }

    // TODO SP-85: write test for test_get_image method.

    // TODO SP-85: write test for test_get_image_url method.

    /**
     * Test get base set
     */
    public function test_get_base_set(): void {
        global $DB;

        // We check if base set exists for this program.
        $set = $DB->record_exists(program_set::TABLE, ['parent' => '0', 'programid' => $this->program1->get('id')]);
        $this->assertTrue($set);

        // We check if base set exists.
        $baseset = $this->program1->get_base_set();
        $this->assertEquals('0', $baseset->get('parent'));
        $this->assertEquals($this->baseset->get('programid'), $baseset->get('programid'));
        $this->assertEquals($this->baseset->get('id'), $baseset->get('id'));
    }

    /**
     * Test if program is archived
     */
    public function test_is_archived(): void {
        // By default should not be archived.
        $this->assertFalse($this->program1->is_archived());

        // We change archived value and check again.
        $this->program1->set('archived', '1');
        $this->program1->update();
        $this->assertTrue($this->program1->is_archived());
    }

    /**
     * Test if program is hidden.
     */
    public function test_is_hidden(): void {
        $this->assertFalse($this->program1->is_hidden());

        // We change hidden value and check again.
        $this->program1->set('visible', '0');
        $this->program1->update();
        $this->assertTrue($this->program1->is_hidden());
    }

    /**
     * Test get active programs by user id.
     */
    public function test_get_active_programs_by_userid(): void {
        // We create default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->tenantgenerator->allocate_user($user->id, $defaulttenantid);

        $activeprogs = api::get_programs_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertEmpty($activeprogs);

        $this->program1->set('tenantid', $defaulttenantid);
        $this->program1->update();

        $onedayless = strtotime(' -1 day');
        $onedaymore = strtotime(' +1 day');
        $twodaysmore = strtotime(' +2 day');

        $data = (object) [
            'userid' => $user->id,
            'certificationid' => 0,
            'startdate' => $onedaymore,
            'startdatelocked' => 1,
            'duedate' => $twodaysmore,
            'duedatelocked' => 1,
            'status' => 1,
        ];

        $proguser = api::allocate_user($this->program1, $data);
        $activeprogs = api::get_programs_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertEmpty($activeprogs);

        $proguser->set('startdate', $onedayless);
        $proguser->update();
        $activeprogs = api::get_programs_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertCount(1, $activeprogs);

        $proguser->set('duedate', $onedayless);
        $proguser->update();
        $activeprogs = api::get_programs_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertEmpty($activeprogs);

        $proguser->set('duedate', $onedaymore);
        $proguser->update();
        $activeprogs = api::get_programs_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertCount(1, $activeprogs);
        $this->assertArrayHasKey($this->program1->get('id'), $activeprogs);

        // Check program completed.
        $baseset = $this->program1->get_base_set();
        $datacompletion = (object)[
            'setid' => $baseset->get('id'),
            'userid' => $user->id,
        ];
        $progcompletion = new \tool_program\persistent\program_set_completion(0, $datacompletion);
        $progcompletion->create();

        $activeprogs = api::get_programs_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertEmpty($activeprogs);
    }

    /**
     * Test get overdue programs by user id.
     */
    public function test_get_overdue_programs_by_userid(): void {
        // We generate default tenant and user.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->tenantgenerator->allocate_user($user->id, $defaulttenantid);

        $overdueprogs = api::get_programs_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertEmpty($overdueprogs);

        $this->program1->set('tenantid', $defaulttenantid);
        $this->program1->update();

        $twodayless = strtotime(' -2 day');
        $onedayless = strtotime(' -1 day');
        $onedaymore = strtotime(' +1 day');

        $data = (object) [
            'userid' => $user->id,
            'certificationid' => 0,
            'startdate' => $onedaymore,
            'startdatelocked' => 1,
            'duedate' => $onedaymore,
            'duedatelocked' => 1,
            'status' => 1,
        ];
        $proguser = api::allocate_user($this->program1, $data);
        $overdueprogs = api::get_programs_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertEmpty($overdueprogs);

        $proguser->set('startdate', $twodayless);
        $proguser->set('duedate', $onedayless);
        $proguser->update();

        $overdueprogs = api::get_programs_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertCount(1, $overdueprogs);

        $proguser->set('startdate', $onedaymore);
        $proguser->update();
        $overdueprogs = api::get_programs_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertEmpty($overdueprogs);

        $proguser->set('startdate', $twodayless);
        $proguser->update();
        $overdueprogs = api::get_programs_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertNotEmpty($overdueprogs);
        $this->assertCount(1, $overdueprogs);
        $this->assertArrayHasKey($this->program1->get('id'), $overdueprogs);

        // Check program completed.
        $baseset = $this->program1->get_base_set();
        $datacompletion = (object)[
            'setid' => $baseset->get('id'),
            'userid' => $user->id,
        ];
        $progcompletion = new \tool_program\persistent\program_set_completion(0, $datacompletion);
        $progcompletion->create();

        $overdueprogs = api::get_programs_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertEmpty($overdueprogs);
    }
}
