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

namespace tool_organisation\external;

use tool_organisation_generator;
use tool_tenant_generator;
use tool_organisation\local\helpers\user_manager;

/**
 * Tests for Organisation structure
 *
 * @covers     \tool_organisation\external\unassign_managers
 * @package    tool_organisation
 * @category   test
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class unassign_managers_test extends \advanced_testcase {

    /**
     * Tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_organisation_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Test unassign a specific manager for specific user
     */
    public function test_unassign_managers(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $tenantgenerator = $this->get_tenant_generator();

        $tenant = $tenantgenerator->create_tenant();
        $tenant1 = $tenantgenerator->create_tenant();

        // Create some new users.
        $employee1 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee1']);
        $employee2 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee2']);
        $employee3 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee3']);
        $employee4 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant1->id, 'username' => 'employee4']);
        $manager = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'manager']);
        $manager1 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant1->id, 'username' => 'manager1']);

        user_manager::add_assigned_manager($employee1->id, $manager);
        user_manager::add_assigned_manager($employee2->id, $employee1);
        user_manager::add_assigned_manager($employee3->id, $employee2);
        user_manager::add_assigned_manager($employee4->id, $manager1);

        // Let's unassign/delete employee1 manually assigned relation.
        $unassignemployee1 = unassign_managers::execute([['id' => $employee1->id]], [['id' => $manager->id]]);
        $this->assertEmpty($unassignemployee1['warnings']);
        $this->assertCount(1, $unassignemployee1['unassignedmanagers']);

        // Trying to unassign non-existing relation.
        $unassignemployee2 = unassign_managers::execute([['username' => $employee2->username]],
            [['username' => $manager->username]]);
        $this->assertEmpty($unassignemployee2['unassignedmanagers']);
        $this->assertCount(1, $unassignemployee2['warnings']);
        $this->assertEquals("User 'manager' is not a manager of the user 'employee2'",
            $unassignemployee2['warnings'][0]['message']);

        // Unassign by username.
        $unassignemployee3 = unassign_managers::execute([['username' => $employee2->username]],
            [['username' => $employee1->username]]);
        $this->assertEmpty($unassignemployee3['warnings']);
        $this->assertCount(1, $unassignemployee3['unassignedmanagers']);

        // Trying to unassign manager to user in different tenant.
        $unassignemployee4 = unassign_managers::execute([['id' => $employee4->id]], [['id' => $manager->id]]);
        $this->assertCount(1, $unassignemployee4['warnings']);
        $this->assertEquals("User and manager are not in the same tenant",
            reset($unassignemployee4['warnings'])['message']);

        // Let's execute the assignment using a user who not belong the same tenant as the affected user, should return a warning.
        user_manager::add_assigned_manager($employee1->id, $manager);
        $context = \context_system::instance();
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('tool/organisation:assignmanuallymgr', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $employee4->id, $context->id);
        $this->setUser($employee4);
        $unassignemployee1 = unassign_managers::execute([['id' => $employee1->id]], [['id' => $manager->id]]);

        $this->assertCount(2, $unassignemployee1['warnings']);
        $this->assertEquals("User not found", $unassignemployee1['warnings'][0]['message']);
        $this->assertEquals("User not found", $unassignemployee1['warnings'][1]['message']);

        // Now add some of the allowed capabilities to the user and try again.
        assign_capability('tool/tenant:manage', CAP_ALLOW, $roleid, $context->id);
        $unassignemployee1 = unassign_managers::execute([['id' => $employee1->id]], [['id' => $manager->id]]);
        $this->assertEmpty($unassignemployee1['warnings']);
    }

    /**
     * Test unassign all managers from a user
     */
    public function test_unassign_managers_all(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $tenantgenerator = $this->get_tenant_generator();

        $tenant = $tenantgenerator->create_tenant();

        // Create some new users.
        $employee1 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee1']);
        $employee2 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee2']);
        $employee3 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee3']);
        $manager = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'manager']);;

        user_manager::add_assigned_manager($employee1->id, $manager);
        user_manager::add_assigned_manager($employee2->id, $employee1);
        user_manager::add_assigned_manager($employee3->id, $employee2);

        // Unassign all managers from $employee1.
        $unassignemployee1 = unassign_managers::execute([['id' => $employee1->id]], [], true);
        $this->assertEmpty($unassignemployee1['warnings']);
        $this->assertCount(1, $unassignemployee1['unassignedmanagers']);
    }
}
