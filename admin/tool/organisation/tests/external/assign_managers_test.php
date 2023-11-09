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

use required_capability_exception;
use tool_organisation_generator;
use tool_tenant_generator;

/**
 * Tests for Organisation structure
 *
 * @covers     \tool_organisation\external\assign_managers
 * @package    tool_organisation
 * @category   test
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class assign_managers_test extends \advanced_testcase {

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
     * Test assign a manager to the users
     */
    public function test_assign_managers(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $tenantgenerator = $this->get_tenant_generator();

        $tenant = $tenantgenerator->create_tenant();
        $tenant1 = $tenantgenerator->create_tenant();

        // Create some new users.
        $employee1 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee1']);
        $employee2 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee2']);
        $employee3 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'employee3']);
        $manager = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'manager']);
        $extrauser = $this->get_tenant_generator()->create_user(['tenantid' => $tenant1->id, 'username' => 'extrauser']);

        $userwithoutpermission = $this->get_tenant_generator()->create_user(['tenantid' => $tenant1->id, 'username' => 'user1']);

        $result = assign_managers::execute(
            [
                ['id' => $employee1->id],
                ['id' => $employee2->id],
                ['id' => $employee3->id],
            ],
            [
                ['id' => $manager->id],
            ],
            [
                'allocateprograms' => true,
            ],
        );
        $this->assertEmpty($result['warnings']);
        $this->assertCount(3, $result['assignedmanagers']);

        // Trying to assign manager to user in different tenant.
        $assigndifferenttenant = assign_managers::execute([['id' => $employee1->id]], [['id' => $extrauser->id]]);
        $this->assertEmpty($assigndifferenttenant['assignedmanagers']);
        $this->assertCount(1, $assigndifferenttenant['warnings']);
        $this->assertEquals("User and manager are not in the same tenant",
            reset($assigndifferenttenant['warnings'])['message']);

        // Trying to assign manager over same user.
        $assignsameuserandmanager = assign_managers::execute([['id' => $employee1->id]], [['id' => $employee1->id]]);
        $this->assertEmpty($assignsameuserandmanager['assignedmanagers']);
        $this->assertCount(1, $assignsameuserandmanager['warnings']);
        $this->assertEquals("User and manager cannot be the same person",
            reset($assignsameuserandmanager['warnings'])['message']);

        // Trying to assign manager twice over same user.
        $assignmanagertwice = assign_managers::execute(
            [
                ['id' => $employee1->id],
                ['id' => $employee2->id],
            ],
            [
                ['id' => $manager->id],
                ['id' => $manager->id],
            ],
            [],
            true,
        );

        $this->assertCount(2, $assignmanagertwice['assignedmanagers']);
        $this->assertCount(2, $assignmanagertwice['warnings']);
        $this->assertEquals("User and manager are already added",
            reset($assignmanagertwice['warnings'])['message']);

        // Trying to assign manager to user that already have assigned.
        $assigndifferenttenant = assign_managers::execute([['id' => $employee1->id]], [['id' => $manager->id]]);
        $this->assertEmpty($assigndifferenttenant['assignedmanagers']);
        $this->assertCount(1, $assigndifferenttenant['warnings']);
        $this->assertEquals(get_string('usermanagednotallowed', 'tool_organisation'),
            reset($assigndifferenttenant['warnings'])['message']);

        // Trying to assign manager with user without permission.
        $this->setUser($userwithoutpermission);
        $this->expectException(required_capability_exception::class);
        assign_managers::execute([['id' => $employee1->id]], [['id' => $manager->id]], [], true);

        // Let's execute the assignment using a user who not belong the same tenant as the affected user, should return a warning.
        $context = \context_system::instance();
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('tool/organisation:assignmanuallymgr', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $extrauser->id, $context->id);
        $this->setUser($extrauser);
        $assignuser = assign_managers::execute([['id' => $employee3->id]], [['id' => $employee2->id]]);
        $this->assertCount(1, $assignuser['warnings']);
        $this->assertEquals("User not found",
            reset($assignuser['warnings'])['message']);

        // Now add some of the allowed capabilities to the user and try again.
        assign_capability('tool/tenant:manage', CAP_ALLOW, $roleid, $context->id);
        $assignuser = assign_managers::execute([['id' => $employee3->id]], [['id' => $employee2->id]]);
        $this->assertEmpty($assignuser['warnings']);
    }
}
