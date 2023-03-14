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

declare(strict_types=1);

namespace tool_tenant\tool_dynamicrule\condition;

use advanced_testcase;
use context_system;
use tool_dynamicrule\api;
use tool_dynamicrule\rule;
use tool_tenant\manager;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;

/**
 * Unit tests for condition user_not_allocated  class.
 *
 * @package    tool_tenant
 * @group      tool_tenant
 * @covers     \tool_tenant\tool_dynamicrule\condition\user_not_allocated
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_not_allocated_test extends advanced_testcase {

    /** @var \tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     *
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Get dynamic rule generator
     *
     * @return \tool_dynamicrule_generator
     */
    protected function get_generator(): \tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Get workplace generator
     *
     * @return \tool_wp_generator
     */
    protected function get_workplace_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Test supports_rule_types
     *
     */
    public function test_supports_rule_types(): void {
        $condition = user_not_allocated::instance();
        $this->assertEquals(rule::TYPE_SHARED, $condition->supports_rule_types());
    }

    /**
     * Test get_title
     *
     */
    public function test_get_title(): void {
        $condition = user_not_allocated::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     *
     */
    public function test_get_category(): void {
        $condition = user_not_allocated::instance();
        $this->assertEquals(get_string('pluginname', 'tool_tenant'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     *
     */
    public function test_validate_config_form(): void {
        $condition = user_not_allocated::instance();
        $user1 = $this->generator->create_user();

        $this->setUser($user1);

        // User can't view tenant list.
        $this->assertArrayHasKey('tenantid', $condition->validate_config_form([]));

        // Create role with some of allowed capabilities to edit DR conditions, and assign this role to user1.
        $roleid = create_role('manage users role', 'manageusersrole', 'Role description');
        $context = context_system::instance();
        assign_capability('tool/tenant:manage', CAP_ALLOW, $roleid, $context->id);
        $this->getDataGenerator()->role_assign($roleid, $user1->id);

        // User now can view tenant list.
        $this->assertArrayNotHasKey('tenantid', $condition->validate_config_form([]));
    }

    /**
     * Test user_can_add
     *
     */
    public function test_user_can_add(): void {
        [$tenant1, $users] = $this->generator->create_tenant_and_users(2);
        $rule = $this->get_generator()->create_rule();

        // In defaulttenant.
        $configdata = ['tenantid' => $tenant1->id];
        $condition = user_not_allocated::create($rule->id, $configdata);

        // Can't add this rule since is only allowed when rule tenant has subtenant.
        self::setUser($users[0]);
        $this->assertFalse($condition->user_can_add());

        // Enable and switch to shared space.
        self::setAdminUser();
        $sharedspaceid = sharedspace::enable_shared_space();
        tenancy::set_switched_tenant_id($sharedspaceid);
        $rule = $this->get_generator()->create_rule();

        // In sharedspace.
        $configdata = ['tenantid' => $sharedspaceid];
        $condition = user_not_allocated::create($rule->id, $configdata);

        // Can add condition in shared space.
        $this->assertTrue($condition->user_can_add());
    }

    /**
     * Test user_can_edit
     *
     */
    public function test_user_can_edit(): void {
        [$tenant1, $users] = $this->generator->create_tenant_and_users(2);
        $configform = ['tenantid' => $tenant1->id];
        $condition = user_not_allocated::instance();

        // Admin user.
        $this->setAdminUser();
        $this->assertTrue($condition->user_can_edit($configform));

        // Non-privileged user.
        self::setUser($users[0]);
        $this->assertFalse($condition->user_can_edit($configform));

        // Create role with some of allowed capabilities to edit DR conditions, and assign this role to user1.
        $roleid = create_role('manage users role', 'manageusersrole', 'Role description');
        $context = context_system::instance();
        assign_capability('tool/tenant:allocate', CAP_ALLOW, $roleid, $context->id);
        $this->getDataGenerator()->role_assign($roleid, $users[0]->id);
        $this->assertTrue($condition->user_can_edit($configform));
    }

    /**
     * Test condition matching user
     *
     */
    public function test_get_matching_users(): void {
        $this->setAdminUser();
        $sharedspaceid = sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->generator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->generator->create_tenant_and_users(3);

        tenancy::set_switched_tenant_id($sharedspaceid);
        $rule1 = $this->get_generator()->create_rule();

        // Create condition for users not allocated in tenant1.
        $configdata = ['tenantid' => $tenant1->id];
        user_not_allocated::create($rule1->id, $configdata);

        // Check only users in tenant2 plus admin in defaulttenant is recovered.
        $usersexpected = array_column($users2, 'id');
        $usersexpected[] = get_admin()->id;
        $this->assertEquals(count($usersexpected), api::count_matching_users($rule1->id));
        $usersmatched = api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing($usersexpected, array_column($usersmatched, 'id'));
    }

    /**
     * Test get_description
     *
     */
    public function test_get_description(): void {
        $this->setAdminUser();
        $sharedspaceid = sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->generator->create_tenant_and_users(3);

        tenancy::set_switched_tenant_id($sharedspaceid);
        $rule1 = $this->get_generator()->create_rule();

        // Create condition for users not allocated in tenant1.
        $configdata = ['tenantid' => $tenant1->id];
        $condition = user_not_allocated::create($rule1->id, $configdata);

        $this->assertEquals(get_string('conditionusernotallocateddescription', 'tool_tenant', $tenant1->name),
            $condition->get_description());
    }

    /**
     * Test is_configuration_valid
     *
     */
    public function test_is_configuration_valid(): void {
        $this->setAdminUser();
        $sharedspaceid = sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->generator->create_tenant_and_users(3);

        tenancy::set_switched_tenant_id($sharedspaceid);
        $rule1 = $this->get_generator()->create_rule();

        // Create condition for users not allocated in tenant1.
        $configdata = ['tenantid' => $tenant1->id];
        $condition = user_not_allocated::create($rule1->id, $configdata);

        // Valid condition.
        $this->assertTrue($condition->is_configuration_valid());

        // Deleted tenant, invalid condition.
        $manager = new manager();
        $manager->archive_tenant($tenant1->id);
        $this->assertFalse($condition->is_configuration_valid());
    }

    /**
     * Test tenant_user_created event is triggering rule processing.
     *
     */
    public function test_tenant_user_created_trigger_rule_processing(): void {
        $sharedspaceid = sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->generator->create_tenant_and_users(3);

        $rule1 = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid, 'enabled' => 1]);

        // Create condition for users not allocated in tenant1.
        $configdata = ['tenantid' => $tenant1->id];
        user_not_allocated::create($rule1->id, $configdata);

        // Create users into default tenant, they should match immediately.
        $newuser1 = $this->generator->create_user();
        $this->get_generator()->assert_user_matched_rule($this, (int)$newuser1->id, $rule1->id);
        $newuser2 = $this->generator->create_user();
        $this->get_generator()->assert_user_matched_rule($this, (int)$newuser2->id, $rule1->id);
    }

    /**
     * Test tenant_user_updated event is triggering rule processing.
     */
    public function test_tenant_user_updated_rule_processing(): void {
        $sharedspaceid = sharedspace::enable_shared_space();
        $tenant1 = $this->generator->create_tenant();
        $tenant2 = $this->generator->create_tenant();

        $rule1 = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid, 'enabled' => 1]);

        // Create condition for users not allocated to tenant2.
        $configdata = ['tenantid' => $tenant2->id];
        user_not_allocated::create($rule1->id, $configdata);

        // Create a user in tenant2, they should not match.
        $newuser1 = $this->generator->create_user(['tenantid' => $tenant2->id]);
        $this->get_generator()->assert_user_did_not_match_rule($this, (int)$newuser1->id, $rule1->id);

        // Move user to tenant 1, they should match now.
        $this->generator->allocate_user((int)$newuser1->id, $tenant1->id);
        $this->get_generator()->assert_user_matched_rule($this, (int)$newuser1->id, $rule1->id);

        // Create a user in default tenant, they should match.
        $newuser2 = $this->generator->create_user();
        $this->get_generator()->assert_user_matched_rule($this, (int)$newuser2->id, $rule1->id);

        // Move user to tenant 2, they should not match now.
        $this->generator->allocate_user((int)$newuser2->id, $tenant2->id);
        $this->get_generator()->assert_user_did_not_match_rule($this, (int)$newuser2->id, $rule1->id, 1);
    }
}
