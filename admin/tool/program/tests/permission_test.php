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
 * File for permission tests.
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\api;
use tool_program\constants;
use tool_program\permission;

defined('MOODLE_INTERNAL') || die();

/**
 * Permission tests.
 *
 * @covers     \tool_program\permission
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_permission_testcase extends advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_organisation_generator */
    protected $orggenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->orggenerator = self::getDataGenerator()->get_plugin_generator('tool_organisation');
        $this->resetAfterTest();
    }

    /**
     * Creates a user in default tenant and additional tenant
     *
     * @return stdClass
     */
    protected function create_tenant_and_user() {
        return (object)[
            'user' => self::getDataGenerator()->create_user(),
            'defaulttenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
            'othertenantid' => $this->tenantgenerator->create_tenant()->id,
        ];
    }

    /**
     * Assign a job with allocate permission.
     *
     * @param int $userid
     * @throws moodle_exception
     */
    protected function assign_job_with_allocate_permission(int $userid) : void {
        $tenantid = \tool_tenant\tenancy::get_tenant_id($userid);

        $pf = $this->orggenerator->create_position(['tenantid' => $tenantid]);
        // Global permission to allocate users.
        $pa = $this->orggenerator->create_position(['parentid' => $pf->id, 'globalmanager' => 1, 'globalpermissions' => 1]);
        $df = $this->orggenerator->create_department(['tenantid' => $tenantid]);
        $da = $this->orggenerator->create_department(['parentid' => $df->id]);

        $this->tenantgenerator->allocate_user($userid, $tenantid);

        $manager = new \tool_organisation\job_manager();
        $manager->create_job((object)['userid' => $userid,
            'positionid' => $pa->id, 'departmentid' => $da->id, 'startdate' => 1262304000]);
    }

    /**
     * Generates a manager with a user to manage.
     *
     * @param int $managerid
     * @param int $userid
     * @param int $tenantid
     */
    protected function generate_manager_user_structure(int $managerid, int $userid, int $tenantid) {
        $dep = $this->orggenerator->create_department(['tenantid' => $tenantid]);
        $pf1 = $this->orggenerator->create_position(['tenantid' => $tenantid]);

        $pa = $this->orggenerator->create_position(['parentid' => $pf1->id, 'globalmanager' => 1, 'globalpermissions' => 3]);
        $pb = $this->orggenerator->create_position(['parentid' => $pa->id]);

        $this->orggenerator->assign_job(['userid' => $managerid, 'positionid' => $pa->id, 'departmentid' => $dep->id]);
        $this->orggenerator->assign_job(['userid' => $userid, 'positionid' => $pb->id, 'departmentid' => $dep->id]);
    }

    /**
     * Test if user has edit capability
     */
    public function test_has_edit_capability(): void {
        $context = context_system::instance();
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $result = permission::has_edit_capability($context);
        $this->assertFalse($result);

        // We assign capability.
        $this->generator->assign_edit_capability($user->id, $context);

        $result = permission::has_edit_capability($context);
        $this->assertTrue($result);
    }

    /**
     * Test if user has allocateuser capability
     */
    public function test_has_allocateuser_capability(): void {
        $context = context_system::instance();
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $result = permission::has_allocateuser_capability($context);
        $this->assertFalse($result);

        // We assign capability.
        $this->generator->assign_allocateuser_capability($user->id, $context);

        $result = permission::has_allocateuser_capability($context);
        $this->assertTrue($result);
    }

    /**
     * Test if user can view a program
     */
    public function test_can_view_program(): void {
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);

        $program = $this->generator->generate_program((object)[
            'tenantid' => $data->defaulttenantid,
            'archived' => 0,
            'visible' => 1,
        ]);

        $user = self::getDataGenerator()->create_user();
        $userid = $user->id;
        $userdata = (object) [
            'userid' => $userid,
            'certificationid' => 0,
        ];
        api::allocate_user($program, $userdata);
        self::setUser($user);

        $canview = permission::can_view_program($program, $userid);
        $this->assertTrue($canview);

        // We archive program.
        api::archive_program($program);
        $canview = permission::can_view_program($program, $userid);
        $this->assertFalse($canview);

        api::restore_program($program);
        $canview = permission::can_view_program($program, $userid);
        $this->assertTrue($canview);

        // We hide program.
        api::update_program_visibility($program, 0);
        $canview = permission::can_view_program($program, $userid);
        $this->assertFalse($canview);

        api::update_program_visibility($program, 1);
        $canview = permission::can_view_program($program, $userid);
        $this->assertTrue($canview);
    }

    /**
     * Test if user can view a program
     */
    public function test_require_can_view_program(): void {
        $context = context_system::instance();
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $program = $this->generator->generate_program();

        $str = get_string('errornopermissionviewprograms', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_view_program($program, $context, $user->id);
    }

    /**
     * Test if user can allocate user to a program
     */
    public function test_can_allocate(): void {
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);

        // We assign capability to user.
        $this->generator->assign_allocateuser_capability($data->user->id, $context);
        $program = $this->generator->generate_program((object)['tenantid' => $data->defaulttenantid, 'archived' => 0]);

        $onedayless = strtotime(' -1 day');
        $twodaysmore = strtotime(' +2 day');
        $program->set('allocationstartdatetype', constants::DATE_ABSOLUTE);
        $program->set('allocationstartdateabsolute', $onedayless);
        $program->set('allocationenddatetype', constants::DATE_ABSOLUTE);
        $program->set('allocationenddateabsolute', $twodaysmore);
        $program->update();

        // We can allocate user with these conditions set.
        $this->assertTrue(permission::can_allocate_anybody($program));

        // We archive program.
        $program->set('archived', 1);
        $program->update();
        $this->assertFalse(permission::can_allocate_anybody($program));

        // We modify allocation window program.
        $program->set('archived', 0);
        $program->set('allocationstartdateabsolute', $twodaysmore);
        $program->update();
        $this->assertFalse(permission::can_allocate_anybody($program));
        // Passing arguments to false should avoid checking allocation window and direct allocation settings.
        $this->assertTrue(permission::can_allocate_anybody($program, false, false));
        $program->set('allocationstartdateabsolute', $onedayless);
        $program->set('allowdirectallocation', 0);
        $program->update();
        $this->assertFalse(permission::can_allocate_anybody($program));
        // Passing arguments to false should avoid checking allocation window and direct allocation settings.
        $this->assertTrue(permission::can_allocate_anybody($program, false, false));

        // We modify tenant id on certification.
        $program->set('allowdirectallocation', 1);
        $program->set('tenantid', $data->othertenantid);
        $program->update();
        $this->assertFalse(permission::can_allocate_anybody($program));

        // We restore good values.
        $program->set('tenantid', $data->defaulttenantid);
        $program->update();
        $this->assertTrue(permission::can_allocate_anybody($program));

        // We hide program.
        $program->set('visible', 0);
        $program->update();
        $this->assertTrue(permission::can_allocate_anybody($program));
    }

    /**
     * Test if user can allocate user to a program
     */
    public function test_require_can_allocate(): void {
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $program = $this->generator->generate_program();

        $str = get_string('errorcantallocateusers', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_allocate_anybody($program);
    }

    /**
     * Test if user can edit program details
     */
    public function test_can_edit_details(): void {
        // We generate default tenant and user.
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        $this->assertEquals($data->defaulttenantid, \tool_tenant\tenancy::get_tenant_id());

        $program = $this->generator->generate_program((object)['tenantid' => $data->defaulttenantid]);

        $return = permission::can_edit_details($program);
        $this->assertFalse($return);

        // We assign capability to user.
        $this->generator->assign_edit_capability($data->user->id, context_system::instance());

        $program->set('archived', '0');
        $program->update();
        $this->assertTrue(permission::can_edit_details($program));

        $program->set('tenantid', '0');
        $program->update();
        $this->assertFalse(permission::can_edit_details($program));

        $str = get_string('errornopermissionmanageprograms', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_edit_details($program);
    }

    /**
     * Test if user can archive a program
     */
    public function test_can_archive(): void {
        // We generate default tenant and user.
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        $program = $this->generator->generate_program((object)['tenantid' => $data->defaulttenantid, 'archived' => 0]);

        // We check with no capability.
        $this->assertFalse(permission::can_archive($program));

        // We assign capability to user.
        $this->generator->assign_edit_capability($data->user->id, context_system::instance());
        $this->assertTrue(permission::can_archive($program));

        // We assert passing manually the value for $skipcheckcertification and $belongstocertification.
        $this->assertTrue(permission::can_archive($program, false, false));
        $this->assertTrue(permission::can_archive($program, true, false));
        $this->assertFalse(permission::can_archive($program, true, true));

        // We check with different tenantid.
        $program->set('tenantid', $data->othertenantid);
        $program->update();
        $this->assertFalse(permission::can_archive($program));

        // We check with program already archived.
        $program->set('tenantid', $data->defaulttenantid);
        $program->set('archived', 1);
        $program->update();
        $this->assertFalse(permission::can_archive($program));
    }

    /**
     * Test if user can archive a program
     */
    public function test_require_can_archive(): void {
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $program = $this->generator->generate_program();

        $str = get_string('errornopermissionmanageprograms', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_archive($program);
    }

    /**
     * Test if user can restore a program
     */
    public function test_can_restore(): void {
        // We generate default tenant and user.
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        // We set certification archived and with default tenant id.
        $program = $this->generator->generate_program((object)['tenantid' => $data->defaulttenantid, 'archived' => 1]);

        // We check without capability.
        $this->assertFalse(permission::can_restore($program));

        // We assign capability to user.
        $this->generator->assign_edit_capability($data->user->id, context_system::instance());
        $this->assertTrue(permission::can_restore($program));

        // We check with different tenantid.
        $program->set('tenantid', $data->othertenantid);
        $program->update();
        $this->assertFalse(permission::can_restore($program));

        // We check with program not archived.
        $program->set('tenantid', $data->defaulttenantid);
        $program->set('archived', 0);
        $program->update();
        $this->assertFalse(permission::can_restore($program));
    }

    /**
     * Test if user can restore a program
     */
    public function test_require_can_restore(): void {
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $program = $this->generator->generate_program();

        $str = get_string('errornopermissionmanageprograms', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_restore($program);
    }

    /**
     * Test if user can delete a program
     */
    public function test_can_delete(): void {
        // We generate default tenant and user.
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        $program = $this->generator->generate_program((object)['tenantid' => $data->defaulttenantid, 'archived' => 1]);

        // We check with no capability.
        $this->assertFalse(permission::can_delete($program));

        // We assign capability to user.
        $this->generator->assign_edit_capability($data->user->id, context_system::instance());
        $this->assertTrue(permission::can_delete($program));

        // We check with different tenant id.
        $program->set('tenantid', $data->othertenantid);
        $program->update();
        $this->assertFalse(permission::can_delete($program));

        // We check with program not archived.
        $program->set('tenantid', $data->defaulttenantid);
        $program->set('archived', 0);
        $program->update();
        $this->assertFalse(permission::can_delete($program));
    }

    /**
     * Test if user can delete a program
     */
    public function test_require_can_delete(): void {
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $program = $this->generator->generate_program();

        $str = get_string('errornopermissionmanageprograms', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_delete($program);
    }

    /**
     * Test if user can create a program
     */
    public function test_require_can_create(): void {
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $str = get_string('errornopermissionmanageprograms', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_create( context_system::instance());
    }

    /**
     * Test if user can view a program list
     */
    public function test_can_view_list(): void {
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $canview = permission::can_view_list(context_system::instance());
        $this->assertFalse($canview);
    }

    /**
     * Test if user can view a program list
     */
    public function test_can_be_allocated(): void {
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        $program = $this->generator->generate_program((object)['tenantid' => $data->defaulttenantid]);
        $this->assertFalse(permission::can_allocate_user($program, $data->user->id));

        // We assign capability to user.
        $this->generator->assign_allocateuser_capability($data->user->id, context_system::instance());
        $this->assertTrue(permission::can_allocate_user($program, $data->user->id));

        // Other tenant id.
        $program->set('tenantid', $data->othertenantid);
        $program->update();
        $this->assertFalse(permission::can_allocate_user($program, $data->user->id));

        // Same tenant id.
        $program->set('tenantid', $data->defaulttenantid);
        $program->update();
        $this->assertTrue(permission::can_allocate_user($program, $data->user->id));

        // Allocation window closed.
        $program->set('allocationstartdateabsolute', strtotime(' +1 day'));
        $program->set('allocationstartdatetype', constants::DATE_ABSOLUTE);
        $program->update();
        $this->assertFalse(permission::can_allocate_user($program, $data->user->id));
        // Passing arguments to false should avoid checking allocation window and direct allocation settings.
        $this->assertTrue(permission::can_allocate_user($program, $data->user->id, false, false));

        $program->set('allocationstartdateabsolute', strtotime(' -1 day'));
        $program->set('allowdirectallocation', 0);
        $program->update();
        $this->assertFalse(permission::can_allocate_user($program, $data->user->id));
        // Passing arguments to false should avoid checking allocation window and direct allocation settings.
        $this->assertTrue(permission::can_allocate_user($program, $data->user->id, false, false));

        // Already allocated and can not be allocated more than once to same program directly.
        $program->set('allowdirectallocation', 1);
        $program->set('allocationstartdatetype', constants::DATE_ABSOLUTE);
        $program->update();
        $userdata = (object) [
            'userid' => $data->user->id,
            'certificationid' => 0,
        ];
        api::allocate_user($program, $userdata);
        $this->assertFalse(permission::can_allocate_user($program, $data->user->id));
    }

    public function test_check_access(): void {
        $tenant = $this->tenantgenerator->create_tenant();

        $manager = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($manager->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);

        $this->setUser($manager);

        $access = permission::check_access();
        $this->assertFalse($access);

        $this->assign_job_with_allocate_permission($manager->id);

        $access = permission::check_access();
        $this->assertTrue($access);
    }

    /**
     * Check if user can see programs progress of another user
     */
    public function test_can_view_user_programs_progress(): void {
        $tenant = $this->tenantgenerator->create_tenant();

        $manager = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($manager->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);

        $this->setUser($manager);

        // Manager can view its own reports.
        $canview = permission::can_view_user_programs_progress($manager->id);
        $this->assertTrue($canview);

        // Check no permission to view.
        $canview = permission::can_view_user_programs_progress($user->id);
        $this->assertFalse($canview);

        $this->generate_manager_user_structure($manager->id, $user->id, $tenant->id);

        // Check permission to view.
        $canview = permission::can_view_user_programs_progress($user->id);
        $this->assertTrue($canview);
    }

    /**
     * Check if user can see program progress of another user
     */
    public function test_can_view_user_program_progress(): void {
        $tenant = $this->tenantgenerator->create_tenant();

        $manager = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($manager->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->generate_manager_user_structure($manager->id, $user->id, $tenant->id);

        $program = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        // Manager can see programs progress report on the given user but can not see
        // report on particular program (because user is not allocated to it).
        $this->setUser($manager);
        $canview = permission::can_view_user_programs_progress($user->id);
        $this->assertTrue($canview);
        $canview = permission::can_view_user_programs_progress($user->id, $program);
        $this->assertFalse($canview);

        $userdata = (object) [
            'userid' => $user->id,
            'certificationid' => 0,
        ];
        api::allocate_user($program, $userdata);

        $canview = permission::can_view_user_programs_progress($user->id, $program);
        $this->assertTrue($canview);
    }

    /**
     * Check if user can see program progress of another user
     */
    public function test_can_view_user_program_progress_self(): void {
        $tenant = $this->tenantgenerator->create_tenant();

        $user = $this->getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);

        $program = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $this->setUser($user->id);
        // User can not see their progress on a program they are not allocated to.
        $canview = permission::can_view_user_programs_progress($user->id, $program);
        $this->assertFalse($canview);

        $userdata = (object) [
            'userid' => $user->id,
            'certificationid' => 0,
        ];
        api::allocate_user($program, $userdata);
        // User can see their progress on a program they are allocated to.
        $canview = permission::can_view_user_programs_progress($user->id, $program);
        $this->assertTrue($canview);

        $program->set('visible', false);
        $program->update();

        // User can not see their progress on a program they are allocated to if the program is hidden.
        $canview = permission::can_view_user_programs_progress($user->id, $program);
        $this->assertFalse($canview);
    }

    public function test_require_can_view_list(): void {
        $str = get_string('errornopermissionviewprograms', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_view_list(context_system::instance());
    }

    public function test_require_can_manage_users_list(): void {
        $str = get_string('errornopermissionmanageusers', 'tool_program');
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        $program = $this->generator->generate_program();

        $this->expectExceptionMessage($str);
        permission::require_can_view_allocated_users($program);
    }

    public function test_can_view_allocated_users(): void {
        // We generate default tenant and user.
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        // We assign capability to user.
        $this->generator->assign_allocateuser_capability($data->user->id, context_system::instance());

        $programdata = (object)[
            'archived' => 0,
            'tenantid' => $data->defaulttenantid,
            'allocationstartdatetype' => constants::DATE_ABSOLUTE,
            'allocationstartdateabsolute' => strtotime(' -1 day'),
            'allocationenddatetype' => constants::DATE_ABSOLUTE,
            'allocationenddateabsolute' => strtotime(' +2 day'),
        ];
        $program = $this->generator->generate_program($programdata);

        $canview = permission::can_view_allocated_users($program);
        $this->assertTrue($canview);

        $program->set('archived', 1);
        $program->update();
        $canview = permission::can_view_allocated_users($program);
        $this->assertFalse($canview);

        $program->set('archived', 0);
        $program->set('tenantid', $data->othertenantid);
        $program->update();
        $canview = permission::can_view_allocated_users($program);
        $this->assertFalse($canview);

        $program->set('tenantid', $data->defaulttenantid);
        $program->set('allocationstartdatetype', constants::DATE_ABSOLUTE);
        $program->set('allocationstartdateabsolute', strtotime('+1 day'));
        $program->update();
        $canview = permission::can_view_allocated_users($program);
        $this->assertTrue($canview);
    }

    public function test_can_manage_user_allocation(): void {
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        $program = $this->generator->generate_program();

        $userdata = (object) [
            'userid' => $data->user->id,
            'certificationid' => 0,
        ];
        $programuser = api::allocate_user($program, $userdata);

        $canmanage = permission::can_edit_user_allocation($programuser, $context);
        $this->assertFalse($canmanage);

        $this->generator->assign_allocateuser_capability($data->user->id, $context);
        $canmanage = permission::can_edit_user_allocation($programuser, $context);
        $this->assertTrue($canmanage);

        $programuser->set('certificationid', 99);
        $programuser->update();
        $canmanage = permission::can_edit_user_allocation($programuser, $context);
        $this->assertFalse($canmanage);

        $programuser->set('certificationid', 0);
        $programuser->update();

        $user2 = $this->getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user2->id, $data->defaulttenantid);
        self::setUser($user2);
        $canmanage = permission::can_edit_user_allocation($programuser, $context);
        $this->assertFalse($canmanage);

        $this->generate_manager_user_structure($user2->id, $data->user->id, $data->defaulttenantid);
        $canmanage = permission::can_edit_user_allocation($programuser, $context);
        $this->assertTrue($canmanage);
    }

    public function test_require_can_manage_user_allocation(): void {
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        $program = $this->generator->generate_program();

        $userdata = (object) [
            'userid' => $data->user->id,
            'certificationid' => 0,
        ];
        $programuser = api::allocate_user($program, $userdata);

        $str = get_string('errornopermissionmanageusers', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_edit_user_allocation($programuser);
    }

    public function test_require_can_be_allocated(): void {
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        $program = $this->generator->generate_program();

        $str = get_string('errorusercantbeallocated', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_allocate_user($program, $data->user->id);
    }

    public function test_can_reset_progress(): void {
        $data = $this->create_tenant_and_user();
        $manager = $this->getDataGenerator()->create_user();
        self::setUser($manager);

        $program = $this->generator->generate_program((object)['tenantid' => $data->defaulttenantid]);
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $data->user->id);
        $programuser->set_program($program);

        $canreset = permission::can_reset_progress($programuser);
        $this->assertFalse($canreset);

        $this->generator->assign_allocateuser_capability($manager->id, context_system::instance());
        $canreset = permission::can_reset_progress($programuser);
        $this->assertFalse($canreset);

        // Only admin has capability to reset courses.
        self::setAdminUser();
        $canreset = permission::can_reset_progress($programuser);
        $this->assertTrue($canreset);

        $program->set('tenantid', $data->othertenantid);
        $program->update();
        $canreset = permission::can_reset_progress($programuser);
        $this->assertFalse($canreset);

        $program->set('tenantid', $data->defaulttenantid);
        $program->set('archived', 1);
        $program->update();
        $canreset = permission::can_reset_progress($programuser);
        $this->assertFalse($canreset);
    }

    public function test_require_can_reset_progress(): void {
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        $program = $this->generator->generate_program();
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $data->user->id);

        $str = get_string('errorcannotresetprogram', 'tool_program');
        $this->expectExceptionMessage($str);
        permission::require_can_reset_progress($programuser);
    }

    public function test_require_can_self_enrol_to_course(): void {
        $data = $this->create_tenant_and_user();
        self::setUser($data->user);
        $course = self::getDataGenerator()->create_course();

        $program = $this->generator->generate_program((object)['tenantid' => $data->defaulttenantid]);

        $str = get_string('errorcantselfenrol', 'tool_program');

        try {
            permission::require_can_self_enrol_to_course($course->id, $program);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $this->assertStringContainsString($str, $e->getMessage());
        }

        $userdata = (object) [
            'userid' => $data->user->id,
            'certificationid' => 0,
        ];
        api::allocate_user($program, $userdata);

        try {
            permission::require_can_self_enrol_to_course($course->id, $program);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $this->assertStringContainsString($str, $e->getMessage());
        }

        api::add_course_to_base_set($program->get('id'), $course->id);

        $program->set('tenantid', $data->othertenantid);
        $program->update();

        try {
            permission::require_can_self_enrol_to_course($course->id, $program);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $this->assertStringContainsString($str, $e->getMessage());
        }

        $course2 = self::getDataGenerator()->create_course();
        api::add_course_to_base_set($program->get('id'), $course2->id);
        $program->set('tenantid', $data->defaulttenantid);
        $program->update();

        try {
            permission::require_can_self_enrol_to_course($course2->id, $program);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $this->assertStringContainsString($str, $e->getMessage());
        }
    }

    public function test_can_view_reports_as_organisation_manager(): void {
        $tenant = $this->tenantgenerator->create_tenant();

        $manager = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($manager->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);

        $this->setUser($manager);
        $canview = permission::can_view_user_programs_progress_as_organisation_manager($user->id);
        $this->assertFalse($canview);

        $this->generate_manager_user_structure($manager->id, $user->id, $tenant->id);

        $canview = permission::can_view_user_programs_progress_as_organisation_manager($user->id);
        $this->assertTrue($canview);
    }

    /**
     * Test that manager role is created on installation
     */
    public function test_roles() {
        $allroles = get_all_roles();
        $roles = array_combine(array_keys($allroles), array_column($allroles, 'shortname'));
        $this->assertContains('tool_program_manager', $roles);
        $fliproles = array_flip($roles);

        $user = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        role_assign($fliproles['tool_tenant_admin'], $user->id, $context->id);
        $this->setUser($user);

        // Tenant admin can assign tool_program_manager in the system context.
        $assignableroles = get_assignable_roles($context);
        $this->assertTrue(array_key_exists($fliproles['tool_program_manager'], $assignableroles));

        // The manager role has two capabilities, they are valid and belong to this project (plus doclinks).
        $caps = get_capabilities_from_role_on_context((object)['id' => $fliproles['tool_program_manager']], $context);
        $this->assertEquals(3, count($caps));
        foreach ($caps as $cap) {
            if (!preg_match('|^tool/program:|', $cap->capability) && $cap->capability != 'moodle/site:doclinks') {
                $this->fail('Capability ' . $cap->capability . ' does not belong to this plugin');
            }
            get_capability_info($cap->capability);
        }
    }
}
