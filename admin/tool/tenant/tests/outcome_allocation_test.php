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

namespace tool_tenant;

use advanced_testcase;
use component_generator_base;
use context_system;
use tool_dynamicrule_generator;
use tool_tenant_generator;
use tool_tenant\tool_dynamicrule\outcome\allocation;
use tool_dynamicrule\api;

/**
 * Unit tests for outcome\allocation class.
 *
 * @package    tool_tenant
 * @group      tool_tenant
 * @covers     \tool_tenant\tool_dynamicrule\outcome\allocation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Sumit Negi
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class outcome_allocation_test extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->generate_tenants();
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
        }
    }

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        /** @var tool_dynamicrule_generator $dynamicrulegenerator */
        $dynamicrulegenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        return $dynamicrulegenerator;
    }

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator|component_generator_base
     */
    public function get_tenant_generator(): tool_tenant_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     *  Generate tenants
     */
    public function generate_tenants() {
        $tenantgenerator = $this->get_tenant_generator();
        $this->tenant1 = $tenantgenerator->create_tenant((object)['name' => 'Tenant 1']);
        $this->tenant2 = $tenantgenerator->create_tenant((object)['name' => 'Tenant 2']);
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $outcome = allocation::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test apply_to_user and setup_for_applying using non-current tenant.
     */
    public function test_apply_to_user_in_other_tenant(): void {
        global $DB;
        // Create users and allocate to tenant1.
        $user1 = self::getDataGenerator()->create_user(['timecreated' => strtotime('-1 day')]);
        $user2 = self::getDataGenerator()->create_user(['timecreated' => strtotime('-1 day')]);
        $user3 = self::getDataGenerator()->create_user(['timecreated' => strtotime('-1 day')]);
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($user1->id, $this->tenant1->id);
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($user2->id, $this->tenant1->id);
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($user3->id, $this->tenant1->id);

        // Rule.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $this->tenant1->id]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        // Create tenant allocation outcome.
        $configdata = ['tenantid' => $this->tenant2->id];
        allocation::create($rule0->id, $configdata);
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);

        // Process the rule.
        api::process_rule($ruleinstance);

        // Get the allocated users in tenant2.
        $users = $DB->get_records('tool_tenant_user', ['tenantid' => $this->tenant2->id], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id], array_column($users, 'userid'));
        $this->assertCount(3, $users);

        // Check users should not be in the tenant1.
        $users = $DB->get_records('tool_tenant_user', ['tenantid' => $this->tenant1->id], '', 'userid');
        $this->assertNotEqualsCanonicalizing([$user1->id, $user2->id, $user3->id], array_column($users, 'userid'));
    }


    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $this->resetAfterTest();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = [
            'tenantid' => $this->tenant1->id
        ];
        $outcome1 = allocation::create($rule1->id, $configdata);

        $this->assertEqualsCanonicalizing(get_string('outcomeallocationdescription', 'tool_tenant',
            $this->tenant1->name), $outcome1->get_description());

        $rule2 = $this->get_generator()->create_rule();
        $configdata = [
            'tenantid' => $this->tenant2->id
        ];
        $outcome1 = allocation::create($rule2->id, $configdata);
        $expected = get_string('outcomeallocationdescription', 'tool_tenant', $this->tenant2->name);
        $this->assertEqualsCanonicalizing($expected, $outcome1->get_description());
    }

    /**
     * Test broken description
     */
    public function test_broken_description(): void {
        $this->resetAfterTest();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = [
            'tenantid' => $this->tenant1->id
        ];
        $outcome = allocation::create($rule1->id, $configdata);
        $tenantmanager = new \tool_tenant\manager();
        $tenantmanager->archive_tenant($this->tenant1->id);
        $this->assertNotEmpty($outcome->get_broken_description());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $context = context_system::instance();
        // Create and set user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        // Create outcome.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['tenantid' => $this->tenant1->id];
        $outcome = allocation::create($rule1->id, $configdata);
        // Check user should not able to edit the outcome.
        $this->assertFalse($outcome->user_can_edit($configdata));
        // Allocate user to tenant.
        $this->get_tenant_generator()->allocate_user($user->id, $this->tenant1->id);
        // Assign capabilities to current user.
        $roleid = create_role('manage users role', 'manageusersrole', 'Role description');
        assign_capability('tool/tenant:manageusers', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        // Check user should be able to edit outcome.
        $this->assertTrue($outcome->user_can_edit($configdata));
        // Move user to other tenant.
        $this->get_tenant_generator()->allocate_user($user->id, $this->tenant2->id);
        // Check user should not be able to edit outcome.
        $this->assertFalse($outcome->user_can_edit($configdata));

    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $context = context_system::instance();
        // Create and set user.
        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        $this->get_tenant_generator()->allocate_user($user->id, $this->tenant1->id);
        self::setUser($user);
        // Create rule.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['tenantid' => $this->tenant1->id];
        allocation::create($rule1->id, $configdata);
        // Check user should not able to add outcome.
        $this->assertFalse(allocation::instance()->user_can_add());
        // Allocate user to tenant and assign capability.
        $this->get_tenant_generator()->allocate_user($user->id, $this->tenant1->id);
        $roleid = create_role('manage users role', 'manageusersrole', 'Role description');
        assign_capability('tool/tenant:manage', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        // Check user should be able to add outcome.
        $this->assertTrue(allocation::instance()->user_can_add());

    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['tenantid' => $this->tenant1->id];
        $outcome = allocation::create($rule1->id, $configdata);
        // Check the configuration is valid or not.
        $this->assertTrue($outcome->is_configuration_valid());
        // Archive the tenant.
        $manager = new \tool_tenant\manager();
        $manager->update_tenant($this->tenant1->id, (object)['archived' => 1]);
        // Configuration should be invalid.
        $this->assertFalse($outcome->is_configuration_valid());
    }
}
