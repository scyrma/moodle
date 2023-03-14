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

namespace block_myteams;

use advanced_testcase;
use context_system;
use tool_tenant\tenancy;

/**
 * Unit tests for the permission class
 *
 * @package    block_myteams
 * @covers     \block_myteams\permission
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission_test extends advanced_testcase {

    /**
     * Test whether user can view course progress report
     */
    public function test_can_view_course_progress(): void {
        $this->resetAfterTest();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $this->assertFalse(permission::can_view_course_progress((int)$user2->id));

        // Create position with global manager permission.
        $params = ['name' => 'Team managers', 'shared' => 0, 'tenantid' => tenancy::get_default_tenant_id()];
        $departmentframework1 = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $params2 = ['name' => 'Manager', 'shared' => 0, 'globalmanager' => 1, 'tenantid' => tenancy::get_default_tenant_id()];
        $positionframework1 = (new \tool_organisation\position_manager())->create_position((object)$params2, false);
        self::getDataGenerator()->get_plugin_generator('tool_organisation')->assign_job([
            'userid' => $user1->id,
            'departmentid' => $departmentframework1->get('id'),
            'positionid' => $positionframework1->get('id'),
        ]);
        role_assign(1, $user1->id, context_system::instance()->id);

        $this->setUser($user1);
        $this->assertTrue(permission::can_view_course_progress((int)$user2->id));
    }

    /**
     * Test that exception is thrown when a user cannot view course progress report
     */
    public function test_require_can_view_course_progress(): void {
        $this->resetAfterTest();

        $user1 = self::getDataGenerator()->create_user();

        $this->expectExceptionMessage(get_string('errornopermissionviewreports', 'block_myteams'));
        permission::require_can_view_course_progress((int)$user1->id);
    }
}
