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
use tool_organisation\organisation;

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
        /** @var \tool_organisation_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('tool_organisation');

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $this->assertFalse(permission::can_view_course_progress((int)$user2->id));

        // Create position with global manager permission.
        $departmentframework1 = $generator->create_department();
        $d1 = $generator->create_department(['parentid' => $departmentframework1->id]);
        $positionframework1 = $generator->create_position();
        $p1 = $generator->create_position(['parentid' => $positionframework1->id,
            'name' => 'Manager', 'globalmanager' => 1, 'globalpermissions' => organisation::PERM_VIEW_REPORTS, ]);
        $p2 = $generator->create_position(['parentid' => $p1->id]);
        $generator->assign_job(['userid' => $user1->id, 'departmentid' => $d1->id, 'positionid' => $p1->id]);
        $generator->assign_job(['userid' => $user2->id, 'departmentid' => $d1->id, 'positionid' => $p2->id]);

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
