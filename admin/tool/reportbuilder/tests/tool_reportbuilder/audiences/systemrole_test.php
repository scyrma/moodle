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
 * File containing tests for system role audience type
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\audiences;

use advanced_testcase;
use coding_exception;
use context_system;
use tool_reportbuilder\permission;
use tool_reportbuilder\test\mock_report;
use tool_reportbuilder_generator;

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\local\helpers\audience
 * @covers      \tool_reportbuilder\tool_reportbuilder\audiences\systemrole
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class systemrole_test extends advanced_testcase {
    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Test get_title()
     */
    public function test_get_title(): void {
        self::setAdminUser();
        $roles = get_assignable_roles(context_system::instance(), ROLENAME_ALIAS);
        $this->assertNotEmpty($roles);
        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);
        $audience = systemrole::create($report->get_id(), ['roles' => array_keys($roles)]);
        $this->assertNotEmpty($audience->get_title());
    }

    /**
     * Test get_description()
     */
    public function test_get_description(): void {
        global $DB;

        self::setAdminUser();

        $role = $DB->get_record('role', ['shortname' => 'manager']);
        $rolename = role_get_name($role, null, ROLENAME_ALIAS);

        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);

        $audience = systemrole::create($report->get_id(), ['roles' => [$role->id]]);
        $this->assertEquals($audience->get_title() . ' ' . $rolename, $audience->get_description());
    }

    /**
     * Test user_can_add()
     */
    public function test_user_can_add(): void {
        self::setAdminUser();
        $roles = get_assignable_roles(context_system::instance(), ROLENAME_ALIAS);
        $this->assertNotEmpty($roles);
        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);
        $audience = systemrole::create($report->get_id(), ['roles' => array_keys($roles)]);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue($audience->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($audience->user_can_add());
    }

    /**
     * Test user_can_edit()
     */
    public function test_user_can_edit(): void {
        self::setAdminUser();
        $roles = get_assignable_roles(context_system::instance(), ROLENAME_ALIAS);
        $this->assertNotEmpty($roles);
        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);
        $audience = systemrole::create($report->get_id(), ['roles' => array_keys($roles)]);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue($audience->user_can_edit());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($audience->user_can_edit());
    }

    /**
     * Test get_sql()
     */
    public function test_get_sql(): void {
        global $DB;
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        self::setAdminUser();
        $roles = get_assignable_roles(context_system::instance(), ROLENAME_ALIAS);
        $this->assertNotEmpty($roles);
        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);

        // Assign new role to user1 and user3.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        role_assign($roleid, $user1->id, context_system::instance()->id);
        role_assign($roleid, $user3->id, context_system::instance()->id);

        $audience = systemrole::create($report->get_id(), ['roles' => [$roleid]]);

        [$join, $where, $params] = $audience->get_sql('u');
        $query = 'SELECT u.* FROM {user} u ' . $join . ' WHERE ' . $where;
        $records = $DB->get_records_sql($query, $params);

        $this->assertEqualsCanonicalizing([$user1->id, $user3->id], array_column($records, 'id'));
    }

    /**
     * Check that audience can be converted to the core reportbuilder
     */
    public function test_convert_to_core_reportbuilder(): void {
        $this->setAdminUser();
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantgenerator->create_tenant(); // Make site multi-tenant.
        $this->get_generator()->audience_test_convert_to_core_reportbuilder(systemrole::class, $this);
    }

    /**
     * Check that audience can be converted to the core reportbuilder and creates the correct configdata
     */
    public function test_convert_to_core_reportbuilder_config(): void {
        $this->setAdminUser();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $roles = get_assignable_roles(context_system::instance(), ROLENAME_ALIAS);
        $this->assertNotEmpty($roles);
        $report = $this->get_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion::class,
        ]);
        // Assign new role to user1 and user2.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        role_assign($roleid, $user1->id, context_system::instance()->id);
        role_assign($roleid, $user2->id, context_system::instance()->id);
        $audience = systemrole::create($report->get_id(), ['roles' => [$roleid]]);

        $newid = $report->convert(false);
        $newaudience = \core_reportbuilder\local\models\audience::get_record(['reportid' => $newid]);

        $this->assertEquals(json_encode(['roles' => [$roleid]]), $newaudience->get('configdata'));
        $this->assertEquals($newaudience->get('configdata'), $audience->get_persistent()->get('configdata'));

        // Make sure user1 and user2 can view both original and converted report, and user3 can not.
        $origreport = \tool_reportbuilder\manager::get_report($report->get_id());
        $newreport = \core_reportbuilder\manager::get_report_from_id($newid)->get_report_persistent();
        $this->setUser($user1);
        $this->assertTrue(permission::can_view($origreport));
        $this->assertTrue(\core_reportbuilder\permission::can_view_report($newreport));
        $this->setUser($user2);
        $this->assertTrue(permission::can_view($origreport));
        $this->assertTrue(\core_reportbuilder\permission::can_view_report($newreport));
        $this->setUser($user3);
        $this->assertFalse(permission::can_view($origreport));
        $this->assertFalse(\core_reportbuilder\permission::can_view_report($newreport));
    }
}
