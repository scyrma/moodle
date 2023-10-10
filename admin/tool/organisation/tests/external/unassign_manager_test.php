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

use tool_organisation\local\helpers\user_manager;
use tool_organisation\local\persistent\user_manager as user_manager_model;
use tool_organisation_generator;
use tool_tenant\manager;
use tool_tenant\tenancy;
use tool_tenant_generator;

/**
 * Tests for unassign_manager WS
 *
 * @covers     \tool_organisation\external\unassign_manager
 *
 * @package    tool_organisation
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class unassign_manager_test extends \advanced_testcase {

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
     * Test unassign_manager for user with/without permissions.
     */
    public function test_unassign_manager(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $tenantgenerator = $this->get_tenant_generator();

        $tenant = $tenantgenerator->create_tenant();

        // Create some new users.
        $employee1 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee1']);
        $employee2 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee2']);
        $employee3 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee3']);
        $manager = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'manager']);
        $extrauser = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'extrauser']);

        user_manager::add_assigned_manager($employee1->id, $manager);
        user_manager::add_assigned_manager($employee2->id, $employee1);
        user_manager::add_assigned_manager($employee3->id, $employee2);

        tenancy::set_switched_tenant_id($tenant->id);

        // Let's unassign/delete employee1 manually assigned relation.
        $unassignemployee1 = unassign_manager::execute($employee1->id, $manager->id);
        $this->assertNull($unassignemployee1);
        $this->assertFalse(user_manager_model::get_record(['userid' => $employee1->id, 'managerid' => $manager->id]));

        // Let's unassign/delete employee2 manually assigned relation.
        $unassignemployee2 = unassign_manager::execute($employee2->id, $employee1->id);
        $this->assertNull($unassignemployee2);
        $this->assertFalse(user_manager_model::get_record(['userid' => $employee2->id, 'managerid' => $employee1->id]));

        $this->setUser($extrauser);

        // Let's unassign/delete employee3 manually assigned relation but with user without permissions,
        // so an exception is raise.
        $this->expectException(\required_capability_exception::class);
        unassign_manager::execute($employee3->id, $employee2->id);
    }

    /**
     * Test unassign_manager for tenant admin.
     */
    public function test_unassign_manager_as_tenant_admin(): void {
        $this->resetAfterTest();

        $tenantgenerator = $this->get_tenant_generator();
        $tenant = $tenantgenerator->create_tenant();
        $tenantadmin = $this->getDataGenerator()->create_user();
        $tenantgenerator->allocate_user($tenantadmin->id, $tenant->id);
        $manager = new manager();
        $manager->assign_tenant_admin_role($tenant->id, [$tenantadmin->id]);

        $this->setUser($tenantadmin);

        // Create some new users.
        $employee1 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee1']);
        $employee2 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee2']);
        $employee3 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee3']);
        $manager = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'manager']);
        $extrauser = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'extrauser']);

        user_manager::add_assigned_manager($employee1->id, $manager);
        user_manager::add_assigned_manager($employee2->id, $employee1);
        user_manager::add_assigned_manager($employee3->id, $employee2);

        tenancy::set_switched_tenant_id($tenant->id);

        // Let's unassign/delete employee1 manually assigned relation.
        $unassignemployee1 = unassign_manager::execute($employee1->id, $manager->id);
        $this->assertNull($unassignemployee1);
        $this->assertFalse(user_manager_model::get_record(['userid' => $employee1->id, 'managerid' => $manager->id]));

        // Let's unassign/delete employee2 manually assigned relation.
        $unassignemployee2 = unassign_manager::execute($employee2->id, $employee1->id);
        $this->assertNull($unassignemployee2);
        $this->assertFalse(user_manager_model::get_record(['userid' => $employee2->id, 'managerid' => $employee1->id]));

        // Let's unassign/delete employee3 manually assigned relation.
        $unassignemployee2 = unassign_manager::execute($employee3->id, $employee2->id);
        $this->assertNull($unassignemployee2);
        $this->assertFalse(user_manager_model::get_record(['userid' => $employee3->id, 'managerid' => $employee2->id]));
    }
}
