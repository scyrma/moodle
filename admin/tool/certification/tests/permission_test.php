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
 * Tests for the tool_certification permission class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use tool_certification\permission;
use tool_certification\constants;
use tool_certification\certification;

/**
 * Permission tests.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_permission_testcase extends advanced_testcase {

    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_organisation_generator */
    protected $orggenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->orggenerator = self::getDataGenerator()->get_plugin_generator('tool_organisation');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Assign a job with allocate permission.
     *
     * @param int $userid
     * @throws moodle_exception
     */
    protected function assign_job_with_allocate_permission(int $userid) : void {
        $tenant = $this->tenantgenerator->create_tenant();

        $pf = $this->orggenerator->create_position(['tenantid' => $tenant->id]);
        // Global permission to allocate users.
        $pa = $this->orggenerator->create_position(['parentid' => $pf->id, 'globalmanager' => 1, 'globalpermissions' => 1]);
        $df = $this->orggenerator->create_department(['tenantid' => $tenant->id]);
        $da = $this->orggenerator->create_department(['parentid' => $df->id]);

        $this->tenantgenerator->allocate_user($userid, $tenant->id);

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

    public function test_check_belongs_same_tenant(): void {
        // We setup 2 tenants.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenantid = $this->tenantgenerator->create_tenant()->id;
        // Create one user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        // We allocate user into othertenant.
        $this->assertEquals($defaulttenantid, \tool_tenant\tenancy::get_tenant_id());
        $this->tenantgenerator->allocate_user($user->id, $othertenantid);
        $this->assertEquals($othertenantid, \tool_tenant\tenancy::get_tenant_id());

        // We generate certification with default tenant and check function.
        $certification = $this->generator->generate_certification(['tenantid' => $defaulttenantid]);

        $return = permission::check_belongs_same_tenant($certification);
        $this->assertFalse($return);

        // We generate certification with othertenant and check function again.
        $certification2 = $this->generator->generate_certification(['tenantid' => $othertenantid]);

        $return = permission::check_belongs_same_tenant($certification2);
        $this->assertTrue($return);

        // This is for test_require_check_belongs_same_tenant.
        $str = get_string('errorusernotinsametenant', 'tool_certification');
        $this->expectExceptionMessage($str);
        permission::require_check_belongs_same_tenant($certification);
    }

    public function test_has_edit_capability(): void {
        $context = context_system::instance();
        // Create one user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Check without capability.
        $result = permission::has_edit_capability($context);
        $this->assertFalse($result);

        // We assign capability.
        $this->generator->assign_edit_capability($user->id, $context);

        // Check with capability.
        $result = permission::has_edit_capability($context);
        $this->assertTrue($result);
    }

    public function test_can_edit_details(): void {
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);
        $this->assertEquals($data->defaulttenantid, \tool_tenant\tenancy::get_tenant_id());

        // We generate certification with default tenant and check function.
        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 1]);

        // We check can_edit_certification.
        $this->assertInstanceOf(certification::class, $certification);
        $return = permission::can_edit_details($certification);
        $this->assertFalse($return);

        // We assign capability to user.
        $this->generator->assign_edit_capability($data->user->id, $context);

        $this->assertTrue(permission::check_belongs_same_tenant($certification));
        $this->assertFalse(permission::can_edit_details($certification));

        $str = get_string('errornopermissionmanagecertifications', 'tool_certification');
        $this->expectExceptionMessage($str);
        permission::require_can_edit_details($certification);

        $certification->set('archived', '0');
        $certification->update();

        $this->assertTrue(permission::can_edit_details($certification));
    }

    public function test_require_can_edit_details(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $certification = $this->generator->generate_certification();

        $str = get_string('errornopermissionmanagecertifications', 'tool_certification');
        $this->expectExceptionMessage($str);
        permission::require_can_edit_details($certification);
    }

    public function test_can_archive(): void {
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);

        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 0]);

        // We check with no capability.
        $this->assertFalse(permission::can_archive($certification));

        // We assign capability to user.
        $this->generator->assign_edit_capability($data->user->id, $context);
        $this->assertTrue(permission::can_archive($certification));

        // We check with different tenantid.
        $certification->set('tenantid', $data->othertenantid);
        $certification->update();
        $this->assertFalse(permission::can_archive($certification));

        // We check with certification already archived.
        $certification->set('tenantid', $data->defaulttenantid);
        $certification->set('archived', 1);
        $certification->update();
        $this->assertFalse(permission::can_archive($certification));
    }

    public function test_require_can_archive(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $certification = $this->generator->generate_certification();

        $str = get_string('errornopermissionmanagecertifications', 'tool_certification');
        $this->expectExceptionMessage($str);
        permission::require_can_archive($certification);
    }

    public function test_can_restore(): void {
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);

        // We set certification archived and with default tenant id.
        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 1]);

        // We check without capability.
        $this->assertFalse(permission::can_restore($certification));

        // We assign capability to user.
        $this->generator->assign_edit_capability($data->user->id, $context);
        $this->assertTrue(permission::can_restore($certification));

        // We check with different tenantid.
        $certification->set('tenantid', $data->othertenantid);
        $certification->update();
        $this->assertFalse(permission::can_restore($certification));

        // We check with certification not archived.
        $certification->set('tenantid', $data->defaulttenantid);
        $certification->set('archived', 0);
        $certification->update();
        $this->assertFalse(permission::can_restore($certification));
    }

    public function test_require_can_restore(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $certification = $this->generator->generate_certification();

        $str = get_string('errorcantrestorecertification', 'tool_certification');
        $this->expectExceptionMessage($str);
        permission::require_can_restore($certification);
    }

    public function test_can_delete(): void {
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);

        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 1]);

        // We check with no capability.
        $this->assertFalse(permission::can_delete($certification));

        // We assign capability to user.
        $this->generator->assign_edit_capability($data->user->id, $context);
        $this->assertTrue(permission::can_delete($certification));

        // We check with different tenant id.
        $certification->set('tenantid', $data->othertenantid);
        $certification->update();
        $this->assertFalse(permission::can_delete($certification));

        // We check with certification not archived.
        $certification->set('tenantid', $data->defaulttenantid);
        $certification->set('archived', 0);
        $certification->update();
        $this->assertFalse(permission::can_delete($certification));
    }

    public function test_require_can_delete(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $certification = $this->generator->generate_certification();

        $str = get_string('errorcantdeletecertification', 'tool_certification');
        $this->expectExceptionMessage($str);
        permission::require_can_delete($certification);
    }

    public function test_can_allocate(): void {
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);

        // We assign capability to user.
        $this->generator->assign_allocateuser_capability($data->user->id, $context);

        $certification = $this->generator->generate_certification();
        $certification->set('archived', 0);
        $certification->set('tenantid', $data->defaulttenantid);
        $onedayless = strtotime(' -1 day');
        $twodaysmore = strtotime(' +2 day');
        $certification->set('allocationstartdatetype', constants::ALLOCATION_SET);
        $certification->set('allocationstartdateabsolute', $onedayless);
        $certification->set('allocationenddatetype', constants::ALLOCATION_SET);
        $certification->set('allocationenddateabsolute', $twodaysmore);
        $certification->update();

        // We can allocate user with these conditions set.
        $this->assertTrue(permission::can_allocate_anybody($certification));

        // We archive certification.
        $certification->set('archived', 1);
        $certification->update();
        $this->assertFalse(permission::can_allocate_anybody($certification));

        // We modify allocation window certification.
        $certification->set('archived', 0);
        $certification->set('allocationstartdateabsolute', $twodaysmore);
        $certification->update();
        $this->assertFalse(permission::can_allocate_anybody($certification));

        // We modify tenant id on certification.
        $certification->set('allocationstartdateabsolute', $onedayless);
        $certification->set('tenantid', $data->othertenantid);
        $certification->update();
        $this->assertFalse(permission::can_allocate_anybody($certification));

        // We restore good values.
        $certification->set('tenantid', $data->defaulttenantid);
        $certification->update();
        $this->assertTrue(permission::can_allocate_anybody($certification));
    }

    public function test_require_can_allocate(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $certification = $this->generator->generate_certification();

        $str = get_string('errorcantmanageusers', 'tool_certification');
        $this->expectExceptionMessage($str);
        permission::require_can_allocate_anybody($certification);
    }

    public function test_can_deallocate(): void {
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);

        $user2 = $this->getDataGenerator()->create_user();

        // We assign capability to user.
        $this->generator->assign_allocateuser_capability($data->user->id, $context);

        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 0]);

        // We can allocate user with these conditions set.
        $this->assertTrue(permission::can_allocate_user($certification, $user2->id));

        // We archive certification.
        $certification->set('archived', 1);
        $certification->update();
        $this->assertFalse(permission::can_allocate_user($certification, $user2->id));

        // We modify tenant id on certification.
        $certification->set('archived', 0);
        $certification->set('tenantid', $data->othertenantid);
        $certification->update();
        $this->assertFalse(permission::can_allocate_user($certification, $user2->id));

        // We restore good values.
        $certification->set('tenantid', $data->defaulttenantid);
        $certification->update();
        $this->assertTrue(permission::can_allocate_user($certification, $user2->id));
    }

    public function test_require_can_deallocate(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $user2 = $this->getDataGenerator()->create_user();
        $certification = $this->generator->generate_certification();

        $str = get_string('errorcantmanageusers', 'tool_certification');
        $this->expectExceptionMessage($str);
        permission::require_can_edit_user_allocation(null);
    }

    public function test_can_create(): void {
        $context = context_system::instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Check without capability.
        $result = permission::can_create($context);
        $this->assertFalse($result);

        $this->generator->assign_edit_capability($user->id, $context);

        // Check with capability.
        $result = permission::can_create($context);
        $this->assertTrue($result);
    }

    public function test_require_can_create(): void {
        $str = get_string('errornopermissionmanagecertifications', 'tool_certification');
        $this->expectExceptionMessage($str);
        permission::require_can_create(context_system::instance());
    }

    public function test_can_view_list(): void {
        $context = context_system::instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Check without capability.
        $result = permission::can_view_list($context);
        $this->assertFalse($result);

        $this->generator->assign_edit_capability($user->id, $context);

        // Check with capability.
        $result = permission::can_view_list($context);
        $this->assertTrue($result);

        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user2);

        // Check without capabilities.
        $result = permission::can_view_list($context);
        $this->assertFalse($result);

        // Assign user allocation capability.
        $this->generator->assign_allocateuser_capability($user2->id, $context);

        // Check with capability.
        $result = permission::can_view_list($context);
        $this->assertTrue($result);

        $user3 = $this->getDataGenerator()->create_user();
        $this->setUser($user3);

        // Check without capabilities.
        $result = permission::can_view_list($context);
        $this->assertFalse($result);

        // Assign organisation structure.
        $this->assign_job_with_allocate_permission($user3->id);

        // Check with capability.
        $result = permission::can_view_list($context);
        $this->assertTrue($result);
    }

    public function test_require_can_view_list(): void {
        $str = get_string('errornopermissionmanagecertifications', 'tool_certification');
        $this->expectExceptionMessage($str);
        permission::require_can_view_list(context_system::instance());
    }

    public function test_require_can_manage_users_list(): void {
        $str = get_string('errorcantmanageusers', 'tool_certification');
        $certification = $this->generator->generate_certification();

        $this->expectExceptionMessage($str);
        permission::require_can_view_allocated_users($certification);
    }

    public function test_can_view_reports_as_organisation_manager(): void {
        $tenant = $this->tenantgenerator->create_tenant();

        $manager = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($manager->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);

        $this->setUser($manager);
        $canview = permission::can_view_reports_as_organisation_manager($user->id);
        $this->assertFalse($canview);

        $this->generate_manager_user_structure($manager->id, $user->id, $tenant->id);

        $canview = permission::can_view_reports_as_organisation_manager($user->id);
        $this->assertTrue($canview);
    }

    public function test_can_view_reports(): void {
        $tenant = $this->tenantgenerator->create_tenant();

        $manager = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($manager->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);

        $this->setUser($manager);

        // Manager can view its own reports.
        $canview = permission::can_view_user_progress($manager->id);
        $this->assertTrue($canview);

        // Check no permission to view.
        $canview = permission::can_view_user_progress($user->id);
        $this->assertFalse($canview);

        $this->generate_manager_user_structure($manager->id, $user->id, $tenant->id);

        // Check permission to view.
        $canview = permission::can_view_user_progress($user->id);
        $this->assertTrue($canview);
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
     * Test that manager role is created on installation
     */
    public function test_roles() {
        $allroles = get_all_roles();
        $roles = array_combine(array_keys($allroles), array_column($allroles, 'shortname'));
        $this->assertContains('tool_certification_manager', $roles);
        $fliproles = array_flip($roles);

        $user = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        role_assign($fliproles['tool_tenant_admin'], $user->id, $context->id);
        $this->setUser($user);

        // Tenant admin can assign tool_certification_manager in the system context.
        $assignableroles = get_assignable_roles($context);
        $this->assertTrue(array_key_exists($fliproles['tool_certification_manager'], $assignableroles));

        // The manager role has two capabilities, they are valid and belong to this project (plus doclinks).
        $caps = get_capabilities_from_role_on_context((object)['id' => $fliproles['tool_certification_manager']], $context);
        $this->assertEquals(3, count($caps));
        foreach ($caps as $cap) {
            if (!preg_match('|^tool/certification:|', $cap->capability) && $cap->capability != 'moodle/site:doclinks') {
                $this->fail('Capability ' . $cap->capability . ' does not belong to this plugin');
            }
            get_capability_info($cap->capability);
        }
    }
}
