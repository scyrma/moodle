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
 * Tests for the tool_tenant external class.
 *
 * @package   tool_tenant
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_tenant external class.
 *
 * @package    tool_tenant
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_tenant_external_testcase extends advanced_testcase {

    /**
     * Returns the tenant generator
     * @return tool_tenant_generator
     */
    protected function get_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test for funciton get_tenants()
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
     * Test for funciton allocate_users()
     */
    public function test_allocate_users() {

        $this->resetAfterTest();

        $this->setAdminUser();

        \tool_tenant\tenancy::get_default_tenant_id();
        $tenant1 = $this->get_generator()->create_tenant();
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

        $category = $this->getDataGenerator()->create_category();

        $manager = new \tool_tenant\manager();
        $manager->update_tenant($tenant1->id, (object) ['categoryid' => $category->id]);

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
}
