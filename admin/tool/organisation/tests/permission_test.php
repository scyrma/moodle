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
 * File containing tests for permission class.
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The permission test class.
 *
 * @package    tool_organisation
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_permission_testcase extends advanced_testcase {

    /** @var tool_organisation_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /** setUp */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_organisation');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');

        $this->resetAfterTest();
    }

    /**
     * Test for can_access_entity
     */
    public function test_can_access_entity(): void {
        self::setAdminUser();
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant2, [$user2]] = $this->tenantgenerator->create_tenant_and_users(1);

        // Frameworks in same tenant should be visible.
        $positionframework1 = (new \tool_organisation\position_manager())->create_position((object)['name' => 'F1']);
        $this->assertTrue(\tool_organisation\permission::can_access_entity($positionframework1));
        $departmentframework1 = (new \tool_organisation\department_manager())->create_department((object)['name' => 'F1']);
        $this->assertTrue(\tool_organisation\permission::can_access_entity($departmentframework1));

        // Frameworks in shared space should be visible in default tenant.
        $params = ['name' => 'Frmk Shared Space', 'shared' => 1, 'tenantid' => $sharedtenantid];
        $positionframework2 = (new \tool_organisation\position_manager())->create_position((object)$params, false);
        $this->assertTrue(\tool_organisation\permission::can_access_entity($positionframework2));
        $departmentframework2 = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $this->assertTrue(\tool_organisation\permission::can_access_entity($departmentframework2));

        // Framework sin tenant2 should not be visible in default tenant.
        $params = ['name' => 'Frmk Tenant2', 'shared' => 1, 'tenantid' => $tenant2->id];
        $positionframework3 = (new \tool_organisation\position_manager())->create_position((object)$params, false);
        $this->assertFalse(\tool_organisation\permission::can_access_entity($positionframework3));
        $departmentframework3 = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $this->assertFalse(\tool_organisation\permission::can_access_entity($departmentframework3));

        // Execute for a user in tenant2. They should see shared frameworks and frameworks in tenant2.
        $this->assertFalse(\tool_organisation\permission::can_access_entity($positionframework1, $user2->id));
        $this->assertFalse(\tool_organisation\permission::can_access_entity($departmentframework1, $user2->id));
        $this->assertTrue(\tool_organisation\permission::can_access_entity($positionframework2, $user2->id));
        $this->assertTrue(\tool_organisation\permission::can_access_entity($departmentframework2, $user2->id));
        $this->assertTrue(\tool_organisation\permission::can_access_entity($positionframework3, $user2->id));
        $this->assertTrue(\tool_organisation\permission::can_access_entity($departmentframework3, $user2->id));
    }

    /**
     * Test for permission::can_edit_position() and permission::can_edit_department()
     */
    public function test_can_edit_with_shared(): void {
        self::setAdminUser();
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();

        // Frameworks on default tenant, seen from same tenant, should show actions.
        $params = ['name' => 'F1', 'tenantid' => $defaulttenantid];
        $positionframework1 = (new \tool_organisation\position_manager())->create_position((object)$params, false);
        $departmentframework1 = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $this->assertTrue(\tool_organisation\permission::can_edit_position($positionframework1));
        $this->assertTrue(\tool_organisation\permission::can_edit_position_in_its_tenant($positionframework1));
        $this->assertTrue(\tool_organisation\permission::can_edit_department($departmentframework1));
        $this->assertTrue(\tool_organisation\permission::can_edit_department_in_its_tenant($departmentframework1));

        // Frameworks from shared space, seen from default tenant, should not show actions.
        $params = ['name' => 'F1', 'tenantid' => $sharedtenantid, 'shared' => 1];
        $positionframework2 = (new \tool_organisation\position_manager())->create_position((object)$params, false);
        $departmentframework2 = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $this->assertFalse(\tool_organisation\permission::can_edit_position($positionframework2));
        $this->assertTrue(\tool_organisation\permission::can_edit_position_in_its_tenant($positionframework2));
        $this->assertFalse(\tool_organisation\permission::can_edit_department($departmentframework2));
        $this->assertTrue(\tool_organisation\permission::can_edit_department_in_its_tenant($departmentframework2));

        // Switch to shared space tenant.
        \tool_tenant\tenancy::set_switched_tenant_id($sharedtenantid);

        // Frameworks from shared space, seen from shared space, should show actions.
        $this->assertTrue(\tool_organisation\permission::can_edit_position($positionframework2));
        $this->assertTrue(\tool_organisation\permission::can_edit_position_in_its_tenant($positionframework2));
        $this->assertTrue(\tool_organisation\permission::can_edit_department($departmentframework2));
        $this->assertTrue(\tool_organisation\permission::can_edit_department_in_its_tenant($departmentframework2));

        // User in tenant2 can only edit departments and positions inside their tenants but not in other tenant or shared.
        [$tenant2, [$user2]] = $this->tenantgenerator->create_tenant_and_users(1);
        $params = ['name' => 'F3', 'tenantid' => $tenant2->id];
        $positionframework3 = (new \tool_organisation\position_manager())->create_position((object)$params, false);
        $departmentframework3 = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $this->generator->assign_capability('tool/organisation:managedepartments', $user2->id, \context_system::instance());
        $this->generator->assign_capability('tool/organisation:managepositions', $user2->id, \context_system::instance());
        $this->setUser($user2);
        $this->assertFalse(\tool_organisation\permission::can_edit_position($positionframework2));
        $this->assertFalse(\tool_organisation\permission::can_edit_position_in_its_tenant($positionframework2));
        $this->assertFalse(\tool_organisation\permission::can_edit_department($departmentframework2));
        $this->assertFalse(\tool_organisation\permission::can_edit_department_in_its_tenant($departmentframework2));
        $this->assertTrue(\tool_organisation\permission::can_edit_position($positionframework3));
        $this->assertTrue(\tool_organisation\permission::can_edit_position_in_its_tenant($positionframework3));
        $this->assertTrue(\tool_organisation\permission::can_edit_department($departmentframework3));
        $this->assertTrue(\tool_organisation\permission::can_edit_department_in_its_tenant($departmentframework3));
    }

    /**
     * Test for permission::can_edit_position() and permission::can_edit_department()
     */
    public function test_user_is_manager(): void {
        [$tenant1, $users] = $this->tenantgenerator->create_tenant_and_users(3);

        // Create framework and position without global/department manager.
        $params = ['name' => 'General Tenant1', 'shared' => 0, 'tenantid' => $tenant1->id];
        $departmentframework1 = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $positionframework1 = (new \tool_organisation\position_manager())->create_position((object)$params, false);

        // Create position with global manager permission.
        $params2 = ['name' => 'Manager Tenant1', 'shared' => 0, 'tenantid' => $tenant1->id, 'globalmanager' => 1];
        $positionframework2 = (new \tool_organisation\position_manager())->create_position((object)$params2, false);

        // Create position with department manager permission.
        $params3 = ['name' => 'Department lead Tenant1', 'shared' => 0, 'tenantid' => $tenant1->id, 'departmentmanager' => 1];
        $positionframework3 = (new \tool_organisation\position_manager())->create_position((object)$params3, false);

        // Set positions to each user created above.
        $this->get_generator()->assign_job([
            'userid' => $users[0]->id,
            'departmentid' => $departmentframework1->get('id'),
            'positionid' => $positionframework1->get('id'),
        ]);
        $this->get_generator()->assign_job([
            'userid' => $users[1]->id,
            'departmentid' => $departmentframework1->get('id'),
            'positionid' => $positionframework2->get('id'),
        ]);
        $this->get_generator()->assign_job([
            'userid' => $users[2]->id,
            'departmentid' => $departmentframework1->get('id'),
            'positionid' => $positionframework3->get('id'),
        ]);

        // Check if user has manager permission.
        $this->setUser($users[0]);
        $this->assertFalse(\tool_organisation\permission::user_is_manager());

        $this->setUser($users[1]);
        $this->assertTrue(\tool_organisation\permission::user_is_manager());

        $this->setUser($users[2]);
        $this->assertTrue(\tool_organisation\permission::user_is_manager());
    }

    /**
     * Get plugin generator
     *
     * @return tool_organisation_generator
     */
    protected function get_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }
}
