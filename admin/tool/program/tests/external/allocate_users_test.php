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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.
declare(strict_types=1);
namespace tool_program\external;

use core_external\external_api;
use stdClass;
use tool_program\constants;
use tool_program\persistent\program_user;
use tool_program_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_program allocate_users external class.
 *
 * @covers     \tool_program\external\allocate_users
 * @package    tool_program
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Mohamed A. Shehata <mohamed.shehata@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class allocate_users_test extends \externallib_advanced_testcase {

    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var stdClass[] $users1 users created for each tenant */
    private $users1;
    /** @var stdClass[] $users2 users created for each tenant */
    private $users2;
    /** @var stdClass $tenant1 tenant1 */
    private $tenant1;
    /** @var stdClass $tenant2 tenant2 */
    private $tenant2;
    /** @var stdClass $program1 program for tenant 1 */
    private $program1;
    /** @var stdClass $program2 program for tenant 2 */
    private $program2;
    /** @var stdClass $sharedprogram program shared tenant */
    private $sharedprogram;
    /** @var int $sharedtenantid shared tenant id */
    private $sharedtenantid;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        $this->initialize_tenants_programs_users();
        $this->resetAfterTest();
    }

    /**
     * Test if tenant administrator can allocate tenant's users.
     */
    public function test_tenant_administrator_can_allocate_users(): void {
        $usersids = array_column($this->users1, 'id');
        // Add failing 1 user to check mixed input.
        $result = external_api::clean_returnvalue(
            allocate_users::execute_returns(),
            allocate_users::execute(
                $this->program1->get('id'),
                array_merge($usersids, [$this->users2[0]->id])
            )
        );
        // Assert WS output.
        $this->assertEqualsCanonicalizing($usersids, $result['result']);
        $this->assertEqualsCanonicalizing([
            [
                'item' => $this->users2[0]->id,
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
        ], $result['warnings']);
        $this->assertEquals(1, $result['summary']['failed']);
        $this->assertEquals(3, $result['summary']['success']);

        // Asserting inserted in DB.
        $program1user1 = program_user::get_record([
            'programid' => $this->program1->get('id'),
            'userid' => $this->users1[0]->id,
        ]);
        $this->assertInstanceOf(program_user::class, $program1user1);

        // Trying to double allocated users to same program.
        $result = external_api::clean_returnvalue(
            allocate_users::execute_returns(),
            allocate_users::execute($this->program1->get('id'), $usersids)
        );
        // Assert WS output.
        $this->assertEqualsCanonicalizing([
            [
                'item' => $usersids[0],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
            [
                'item' => $usersids[1],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
            [
                'item' => $usersids[2],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
        ], $result['warnings']);
        $this->assertEquals(3, $result['summary']['failed']);
        $this->assertEquals(0, $result['summary']['success']);
    }

    /**
     * Test if tenant administrator can't allocate other tenant's users.
     */
    public function test_tenant_administrator_cannot_allocate_other_tenant_users(): void {
        $usersids = array_column($this->users2, 'id');
        $result = external_api::clean_returnvalue(
            allocate_users::execute_returns(),
            allocate_users::execute($this->program1->get('id'), $usersids)
        );
        // Assert WS output.
        $this->assertEqualsCanonicalizing([
            [
                'item' => $usersids[0],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
            [
                'item' => $usersids[1],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
        ], $result['warnings']);
        $this->assertEquals(2, $result['summary']['failed']);
        $this->assertEquals(0, $result['summary']['success']);
        // Asserting not inserted in DB.
        $program1user1 = program_user::get_record(['programid' => $this->program1->get('id'), 'userid' => $this->users1[0]->id]);
        $this->assertNotInstanceOf(program_user::class, $program1user1);
    }

    /**
     * Test allocating invalid program id.
     */
    public function test_allocate_invalid_program(): void {
        $usersids = array_column($this->users1, 'id');
        $result = external_api::clean_returnvalue(
            allocate_users::execute_returns(),
            allocate_users::execute(0, $usersids)
        );
        // Assert WS output.
        $this->assertEqualsCanonicalizing([
            [
                'item' => $usersids[0],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
            [
                'item' => $usersids[1],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
            [
                'item' => $usersids[2],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
        ], $result['warnings']);
        $this->assertEquals(3, $result['summary']['failed']);
        $this->assertEquals(0, $result['summary']['success']);
    }

    /**
     * Test if tenant administrator can't allocate other tenant's users.
     */
    public function test_tenant_administrator_cannot_allocate_already_allocated_dynamically(): void {
        // Dynamically allocation for user1.
        $this->programgenerator->allocate_user_to_program(
            $this->program1->get('id'),
            (int)$this->users1[0]->id,
            0,
            ['allocationtype' => constants::ALLOCATION_DYNAMIC]
        );
        $usersids = array_column($this->users1, 'id');
        // Add failing 1 user to check mixed input.
        $result = external_api::clean_returnvalue(
            allocate_users::execute_returns(),
            allocate_users::execute($this->program1->get('id'), $usersids)
        );
        // Assert WS output.
        $this->assertEqualsCanonicalizing([
            [
                'item' => $usersids[0],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
        ], $result['warnings']);
        $this->assertEquals(1, $result['summary']['failed']);
        $this->assertEquals(2, $result['summary']['success']);
    }

    /**
     * Test if tenant administrator can't allocate other tenant's program.
     */
    public function test_tenant_administrator_cannot_allocate_other_tenant_program(): void {
        $usersids = array_column($this->users1, 'id');
        $result = external_api::clean_returnvalue(
            allocate_users::execute_returns(),
            allocate_users::execute($this->program2->get('id'), $usersids)
        );
        // Assert WS output.
        $this->assertEqualsCanonicalizing([
            [
                'item' => $usersids[0],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
            [
                'item' => $usersids[1],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
            [
                'item' => $usersids[2],
                'warningcode' => 'nopermissions',
                'message' => get_string('errornopermissionallocateusers', 'tool_program'),
            ],
        ], $result['warnings']);
        $this->assertEquals(3, $result['summary']['failed']);
        $this->assertEquals(0, $result['summary']['success']);
        // Asserting not inserted in DB.
        $program1user1 = program_user::get_record(['programid' => $this->program2->get('id'), 'userid' => $this->users1[0]->id]);
        $this->assertNotInstanceOf(program_user::class, $program1user1);
    }

    /**
     * Test if global administrator can allocate users to shared program.
     */
    public function test_global_administrator_can_allocated_to_shared_program(): void {
        self::setAdminUser();
        $usersids = array_column(array_merge($this->users1, $this->users2), 'id');
        $result = external_api::clean_returnvalue(
            allocate_users::execute_returns(),
            allocate_users::execute($this->sharedprogram->get('id'), $usersids)
        );
        // Assert WS output.
        $this->assertEqualsCanonicalizing($result['result'], $usersids);
        $this->assertEmpty($result['warnings']);
        $this->assertEquals(0, $result['summary']['failed']);
        $this->assertEquals(5, $result['summary']['success']);
        // Asserting inserted in DB.
        $sharedprogramuser1t1 = program_user::get_record([
            'programid' => $this->sharedprogram->get('id'),
            'userid' => $this->users1[0]->id,
        ]);
        $this->assertInstanceOf(program_user::class, $sharedprogramuser1t1);

        $sharedprogramuser2t1 = program_user::get_record([
            'programid' => $this->sharedprogram->get('id'),
            'userid' => $this->users1[1]->id,
        ]);
        $this->assertInstanceOf(program_user::class, $sharedprogramuser2t1);

        $sharedprogramuser1t2 = program_user::get_record([
            'programid' => $this->sharedprogram->get('id'),
            'userid' => $this->users2[0]->id,
        ]);
        $this->assertInstanceOf(program_user::class, $sharedprogramuser1t2);

        $sharedprogramuser2t2 = program_user::get_record([
            'programid' => $this->sharedprogram->get('id'),
            'userid' => $this->users2[1]->id,
        ]);
        $this->assertInstanceOf(program_user::class, $sharedprogramuser2t2);
    }

    /**
     * Function to initialize all properties used in tests.
     */
    private function initialize_tenants_programs_users(): void {
        // Initialize properties.
        $tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        [$this->tenant1, $this->users1] = $tenantgenerator->create_tenant_and_users(3);
        [$this->tenant2, $this->users2] = $tenantgenerator->create_tenant_and_users(2);
        $this->sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();

        // Initialize programs for each tenant.
        $this->program1 = $this->programgenerator->generate_program((object)[
            'fullname' => 'Program number 1T1',
            'tenantid' => $this->tenant1->id,
        ]);
        $this->program2 = $this->programgenerator->generate_program((object)[
            'fullname' => 'Program number 2T2',
            'tenantid' => $this->tenant2->id,
        ]);
        $this->sharedprogram = $this->programgenerator->generate_program((object)[
            'fullname' => 'Shared Program',
            'tenantid' => $this->sharedtenantid,
        ]);

        // Make user1[0] as tenant administrator.
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$this->users1[0]->id], $this->tenant1->id);
        // Login as tenant1 administrator.
        self::setUser($this->users1[0]->id);
    }
}
