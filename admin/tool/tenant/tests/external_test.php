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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Tests for the tool_tenant external class.
 *
 * @package   tool_tenant
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_tenant\external\confirm_users;
use tool_tenant\external\resend_confirmation_email;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_tenant external class.
 *
 * @package    tool_tenant
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_tenant_external_testcase extends advanced_testcase {

    /**
     * Returns the tenant generator
     * @return tool_tenant_generator
     */
    protected function get_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Load required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->libdir}/externallib.php");
    }
    /**
     * Test for function get_tenants()
     */
    public function test_get_tenants() {
        $this->resetAfterTest();

        $this->setAdminUser();

        \tool_tenant\tenancy::get_default_tenant_id();
        $tenant1 = $this->get_generator()->create_tenant();
        $tenant2 = $this->get_generator()->create_tenant();
        $tenant3 = $this->get_generator()->create_tenant();

        $tenants = tool_tenant_external::get_tenants();
        $tenants = external_api::clean_returnvalue(tool_tenant_external::get_tenants_returns(), $tenants);

        $this->assertEquals('Default tenant', $tenants[0]['name']);
        $this->assertEquals('New tenant 1', $tenants[1]['name']);
        $this->assertEquals('New tenant 2', $tenants[2]['name']);
        $this->assertEquals('New tenant 3', $tenants[3]['name']);
    }

    /**
     * Test for function allocate_users()
     */
    public function test_allocate_users() {

        $this->resetAfterTest();

        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();

        \tool_tenant\tenancy::get_default_tenant_id();
        $tenant1 = $this->get_generator()->create_tenant((object)['categoryid' => $category->id]);
        $tenant2 = $this->get_generator()->create_tenant();
        $tenant3 = $this->get_generator()->create_tenant();

        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user();

        $allocations = [
            ['tenantid' => $tenant1->id, 'userid' => $user1->id],
            ['tenantid' => $tenant1->id, 'userid' => $user2->id],
            ['tenantid' => $tenant2->id, 'userid' => $user3->id],
            ['tenantid' => $tenant3->id, 'userid' => $user4->id]
        ];

        $tenants = tool_tenant_external::allocate_users($allocations);

        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_tenant_id($user1->id));
        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_tenant_id($user2->id));
        $this->assertEquals($tenant2->id, \tool_tenant\tenancy::get_tenant_id($user3->id));
        $this->assertEquals($tenant3->id, \tool_tenant\tenancy::get_tenant_id($user4->id));

        $manager = new \tool_tenant\manager();
        $manager->allocate_user($user0->id, $tenant1->id, 'tool_tenant', 'testing');
        $manager->assign_tenant_admin_role($tenant1->id, [$user0->id]);

        $this->setUser($user0);

        $allocations = [
            ['tenantid' => $tenant2->id, 'userid' => $user1->id],
        ];

        $this->expectException(\moodle_exception::class);
        tool_tenant_external::allocate_users($allocations);

        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_tenant_id($user1->id));

        $allocations = [
            ['tenantid' => $tenant1->id, 'userid' => $user1->id],
            ['tenantid' => $tenant1->id, 'userid' => $user5->id],
        ];

        tool_tenant_external::allocate_users($allocations);
        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_tenant_id($user1->id));
        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_tenant_id($user5->id));
    }

    /**
     * Test for function test_confirm_users()
     *
     */
    public function test_confirm_users() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        \tool_tenant\tenancy::get_default_tenant_id();
        $tenant1 = $this->get_generator()->create_tenant((object)['categoryid' => $category->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->get_generator()->allocate_user($user->id, $tenant1->id);
        $user->confirmed = 0;
        user_update_user($user, false, true);
        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_tenant_id($user->id));
        $confirmed = external_api::clean_returnvalue(
            confirm_users::execute_returns(),
            confirm_users::execute([$user->id])
        );
        $confirmeduser = \core_user::get_user($user->id)->confirmed;
        $this->assertEquals(1, $confirmeduser);
        $this->assertEquals(1, $confirmed['successcount']);
    }

    /**
     * Tests for scenarios that cannot confirm users.
     */
    public function test_cannot_confirm_users() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $tenant1 = $this->get_generator()->create_tenant();

        // User who has already been confirmed.
        $user2 = $this->getDataGenerator()->create_user();
        $this->get_generator()->allocate_user($user2->id, $tenant1->id);
        $confirmed = external_api::clean_returnvalue(
            confirm_users::execute_returns(),
            confirm_users::execute([$user2->id])
        );
        $this->assertEquals(0, $confirmed['failcount']);
        $this->assertEquals(1, $confirmed['skippedcount']);
        $this->assertEquals(0, $confirmed['successcount']);
        // User with no confirmation method in auth.
        $user3 = $this->getDataGenerator()->create_user(['confirmed' => 0, 'auth' => 'nologin']);
        $confirmed = external_api::clean_returnvalue(
            confirm_users::execute_returns(),
            confirm_users::execute([$user3->id])
        );
        $this->assertEquals(1, $confirmed['failcount']);
        $this->assertEquals(0, $confirmed['skippedcount']);
        $this->assertEquals(0, $confirmed['successcount']);
        // User that does not exist.
        $confirmed = external_api::clean_returnvalue(
            confirm_users::execute_returns(),
            confirm_users::execute([221])
        );
        $this->assertEquals(1, $confirmed['failcount']);
        $this->assertEquals(0, $confirmed['skippedcount']);
        $this->assertEquals(0, $confirmed['successcount']);

        // Tenant admin from a different tenant cannot confirm user of a different tenant.
        [$tenant2, $users2] = $this->get_generator()->create_tenant_and_users(2);
        $manager = new \tool_tenant\manager();
        $manager->assign_tenant_admin_role($tenant2->id, [$users2[0]->id]);
        $this->setUser($user2->id);
        \tool_tenant\tenancy::set_switched_tenant_id($tenant2->id);
        // Try to confirm a user from a different tenant.
        try {
            $confirmed = external_api::clean_returnvalue(
                confirm_users::execute_returns(),
                confirm_users::execute([$user2->id])
            );
        } catch (Exception $e) {
            $this->assertInstanceOf('required_capability_exception', $e);
        }
        $this->assertEquals(1, $confirmed['failcount']);
        $this->assertEquals(0, $confirmed['skippedcount']);
        $this->assertEquals(0, $confirmed['successcount']);
    }
    /**
     * Test for function test_resend_email_users()
     *
     */
    public function test_resend_email_users() {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$tenant1, $users1] = $this->get_generator()->create_tenant_and_users(4,
            ['sitename' => 'SITENAME1', 'siteshortname' => 'SN']);
        $users1[0]->confirmed = 0;
        user_update_user($users1[0], false, true);
        $notconfirmeduser = \core_user::get_user($users1[0]->id);
        $this->assertEquals(0, $notconfirmeduser->confirmed);
        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_tenant_id($notconfirmeduser->id));
        $anotheruser = self::getDataGenerator()->create_user(['confirmed' => 0]);
        unset_config('noemailever');
        $sink = $this->redirectEmails();
        $emails = external_api::clean_returnvalue(
            resend_confirmation_email::execute_returns(),
            resend_confirmation_email::execute(
                array_merge(array_column($users1, 'id'), [$anotheruser->id, $users1[0]->id + 1000]))
        );
        // 2 users were confirmed, 3 users skipped and 1 user was not found.
        $messages = $sink->get_messages();
        $this->assertEquals(2, count($messages));
        $this->assertEquals(2, $emails['successcount']);
        // Users from different tenants have different site name in the subject.
        $this->assertStringStartsWith("SITENAME1:", $messages[0]->subject);
        $this->assertStringStartsWith("PHPUnit test site:", $messages[1]->subject);
        $this->assertEquals(1, $emails['failcount']);
        $this->assertEquals(3, $emails['skippedcount']);
    }

    /**
     * Test for function test_assign_tenant_admin_role()
     */
    public function test_assign_tenant_admin_role() {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$tenant1, $users1] = $this->get_generator()->create_tenant_and_users(4);
        $tenantadminroleassigned = external_api::clean_returnvalue(
            tool_tenant_external::assign_tenant_admin_roles_returns(),
            tool_tenant_external::assign_tenant_admin_roles([$users1[0]->id])
        );
        $this->assertEquals(0, $tenantadminroleassigned['failcount']);
        $this->assertEquals(0, $tenantadminroleassigned['skippedcount']);
        $this->assertEquals(1, $tenantadminroleassigned['successcount']);

        // Re-assign the same role, skippedcount should be 1.
        $tenantadminroleassigned = external_api::clean_returnvalue(
            tool_tenant_external::assign_tenant_admin_roles_returns(),
            tool_tenant_external::assign_tenant_admin_roles([$users1[0]->id])
        );
        $this->assertEquals(0, $tenantadminroleassigned['failcount']);
        $this->assertEquals(1, $tenantadminroleassigned['skippedcount']);
        $this->assertEquals(0, $tenantadminroleassigned['successcount']);
    }

    /**
     * Test for function test_unassign_tenant_admin_role()
     */
    public function test_unassign_tenant_admin_role() {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$tenant1, $users1] = $this->get_generator()->create_tenant_and_users(4);
        $tenantadminroleassigned = external_api::clean_returnvalue(
            tool_tenant_external::assign_tenant_admin_roles_returns(),
            tool_tenant_external::assign_tenant_admin_roles([$users1[0]->id])
        );
        $this->assertEquals(0, $tenantadminroleassigned['failcount']);
        $this->assertEquals(0, $tenantadminroleassigned['skippedcount']);
        $this->assertEquals(1, $tenantadminroleassigned['successcount']);
        $tenantadminroleunassigned = external_api::clean_returnvalue(
            tool_tenant_external::unassign_tenant_admin_roles_returns(),
            tool_tenant_external::unassign_tenant_admin_roles([$users1[0]->id])
        );

        $this->assertEquals(0, $tenantadminroleunassigned['failcount']);
        $this->assertEquals(0, $tenantadminroleunassigned['skippedcount']);
        $this->assertEquals(1, $tenantadminroleunassigned['successcount']);

        // Re-unassign the same role, skippedcount should be 1.
        $tenantadminroleunassigned = external_api::clean_returnvalue(
            tool_tenant_external::unassign_tenant_admin_roles_returns(),
            tool_tenant_external::unassign_tenant_admin_roles([$users1[0]->id])
        );
        $this->assertEquals(0, $tenantadminroleunassigned['failcount']);
        $this->assertEquals(1, $tenantadminroleunassigned['skippedcount']);
        $this->assertEquals(0, $tenantadminroleunassigned['successcount']);
    }
}
