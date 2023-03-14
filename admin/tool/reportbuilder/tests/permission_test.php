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
 * Tests for the tool_reportbuilder permission class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use advanced_testcase;
use coding_exception;
use context_system;
use core_user\reportbuilder\datasource\users;
use core_reportbuilder_generator;
use dml_exception;
use moodle_exception;
use tool_reportbuilder_generator;
use tool_tenant_generator;
use tool_reportbuilder\test\mock_report;

/**
 * Permission tests.
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @category  test
 * @covers    \tool_reportbuilder\permission
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission_test extends advanced_testcase {

    /** @var int */
    protected $user1;
    /** @var int */
    protected $user2;
    /** @var int */
    protected $defaulttenantid;
    /** @var int */
    protected $othertenantid;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        // Create tenants.
        $this->defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $this->othertenantid = $this->get_tenant_generator()->create_tenant()->id;
        // Create users.
        $this->user1 = $this->getDataGenerator()->create_user()->id;
        $this->user2 = $this->getDataGenerator()->create_user()->id;
        // Allocate users in tenant.
        $this->get_tenant_generator()->allocate_user($this->user1, $this->defaulttenantid);
        $this->get_tenant_generator()->allocate_user($this->user2, $this->othertenantid);
    }

    /**
     * Returns the tenant generator
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Get reportbuilder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_report_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Creates tenant and assigns user.
     * @return object
     */
    protected function create_tenant_and_user(): object {
        // We create default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        // Create one user.
        $user = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user->id, $defaulttenantid);
        return (object)[
            'user' => $user,
            'defaulttenantid' => $defaulttenantid
        ];
    }

    /**
     * Test can_view_any method.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_can_view_any(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // User without 'tool/reportbuilder:read' capability.
        $canview = \tool_reportbuilder\permission::can_view_any();
        $this->assertFalse($canview);

        $this->assign_read_capability($user->id);

        // User with 'tool/reportbuilder:read' capability.
        $canview = \tool_reportbuilder\permission::can_view_any();
        $this->assertTrue($canview);
    }

    /**
     * Test can_view_reports_list method.
     */
    public function test_can_view_reports_list(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // User without any permissions.
        $result = \tool_reportbuilder\permission::can_view_reports_list();
        $this->assertFalse($result);

        // User has the capability 'tool/reportbuilder:read'.
        $this->assign_read_capability($user->id);
        $result = \tool_reportbuilder\permission::can_view_reports_list();
        $this->assertTrue($result);

        // User has not the capability but has permission thru an audience type. // TODO.
    }

    public function test_can_view_report(): void {
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();

        $report0 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
            'tenantid' => $sharedspaceid,
            'adddefault' => (int)true,
            'shared' => true,
        ]);

        $report1 = $this->get_report_generator()->create_report([
            'source' => mock_report::class,
            'tenantid' => $tenant1->id,
        ]);
        $report2 = $this->get_report_generator()->create_report([
            'source' => mock_report::class,
            'tenantid' => $tenant1->id,
        ]);

        // First user has ability to view all reports.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);

        $this->assign_read_capability($user1->id);
        $this->assertTrue(permission::can_view($report1));
        $this->assertTrue(permission::can_view($report2));
        $this->assertTrue(permission::can_view($report0));

        // Second user.
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user2);
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant1->id);

        $this->assertFalse(permission::can_view($report1));
        $this->assertFalse(permission::can_view($report2));
        $this->assertFalse(permission::can_view($report0));

        // Assign second user to an audience in the second report.
        \tool_reportbuilder\tool_reportbuilder\audiences\manual::create($report2->get_id(), ['users' => [$user2->id]]);

        $this->assertFalse(permission::can_view($report1));
        $this->assertTrue(permission::can_view($report2));
        $this->assertFalse(permission::can_view($report0));
    }

    /**
     * Test class can_manage_reports method
     */
    public function test_can_manage_reports(): void {
        // Assign edit capability to first test user, confirm they can manage reports in their own tenant.
        $this->setUser($this->user1);
        $this->assign_edit_capability($this->user1);

        $this->assertTrue(permission::can_manage_reports($this->defaulttenantid));
        $this->assertFalse(permission::can_manage_reports($this->othertenantid));

        // Assign edit capability to second test user, confirm they can manage reports in their own tenant.
        $this->setUser($this->user2);
        $this->assign_edit_capability($this->user2);

        $this->assertFalse(permission::can_manage_reports($this->defaulttenantid));
        $this->assertTrue(permission::can_manage_reports($this->othertenantid));
    }

    /**
     * Test can_edit method.
     */
    public function test_can_edit(): void {
        $this->setUser($this->user2);
        // Create report with user1.
        $report2 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $this->setUser($this->user1);

        // Create report with user1.
        $report1 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        // Try edit without capability.
        $canedit = \tool_reportbuilder\permission::can_edit($report1);
        $this->assertFalse($canedit);

        // Try edit with capability.
        $canedit = \tool_reportbuilder\permission::can_edit($report1);
        $this->assertFalse($canedit);

        $this->assign_edit_capability($this->user1);

        // Try edit.
        $canedit = \tool_reportbuilder\permission::can_edit($report1);
        $this->assertTrue($canedit);

        // Try edit report for other tenant.
        $canedit = \tool_reportbuilder\permission::can_edit($report2);
        $this->assertFalse($canedit);

        // Try edit a system report.
        $systemreport = \tool_reportbuilder\system_report_factory::create(
            \tool_reportbuilder\local\systemreports\reports_list::class
        );
        $canedit = \tool_reportbuilder\permission::can_edit($systemreport);
        $this->assertFalse($canedit);
    }

    /**
     * Test can_edit method.
     */
    public function test_delete_edit(): void {
        $this->setUser($this->user2);
        // Create report with user1.
        $report2 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $this->setUser($this->user1);

        // Create report with user1.
        $report1 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        // Try edit without capability.
        $canedit = \tool_reportbuilder\permission::can_delete($report1);
        $this->assertFalse($canedit);

        // Try edit with capability.
        $canedit = \tool_reportbuilder\permission::can_delete($report1);
        $this->assertFalse($canedit);

        $this->assign_edit_capability($this->user1);

        // Try edit.
        $canedit = \tool_reportbuilder\permission::can_delete($report1);
        $this->assertTrue($canedit);

        // Try edit report for other tenant.
        $canedit = \tool_reportbuilder\permission::can_delete($report2);
        $this->assertFalse($canedit);

        // Try edit a system report.
        $systemreport = \tool_reportbuilder\system_report_factory::create(
            \tool_reportbuilder\local\systemreports\reports_list::class
        );
        $canedit = \tool_reportbuilder\permission::can_delete($systemreport);
        $this->assertFalse($canedit);
    }

    /**
     * Test can_duplicate method.
     */
    public function test_duplicate_edit(): void {
        $this->setUser($this->user2);
        // Create report with user1.
        $report2 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $this->setUser($this->user1);

        // Create report with user1.
        $report1 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        // Try edit without capability.
        $canedit = \tool_reportbuilder\permission::can_duplicate($report1);
        $this->assertFalse($canedit);

        // Try edit with capability.
        $canedit = \tool_reportbuilder\permission::can_duplicate($report1);
        $this->assertFalse($canedit);

        $this->assign_edit_capability($this->user1);

        // Try edit.
        $canedit = \tool_reportbuilder\permission::can_duplicate($report1);
        $this->assertTrue($canedit);

        // Try edit report for other tenant.
        $canedit = \tool_reportbuilder\permission::can_duplicate($report2);
        $this->assertFalse($canedit);

        // Try edit a system report.
        $systemreport = \tool_reportbuilder\system_report_factory::create(
            \tool_reportbuilder\local\systemreports\reports_list::class
        );
        $canedit = \tool_reportbuilder\permission::can_duplicate($systemreport);
        $this->assertFalse($canedit);
    }

    /**
     * Test can_schedule method.
     */
    public function test_schedule_edit(): void {
        $this->setUser($this->user2);
        // Create report with user1.
        $report2 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $this->setUser($this->user1);

        // Create report with user1.
        $report1 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        // Try edit without capability.
        $canedit = \tool_reportbuilder\permission::can_schedule($report1);
        $this->assertFalse($canedit);

        // Try edit with capability.
        $canedit = \tool_reportbuilder\permission::can_schedule($report1);
        $this->assertFalse($canedit);

        $this->assign_edit_capability($this->user1);

        // Try edit.
        $canedit = \tool_reportbuilder\permission::can_schedule($report1);
        $this->assertTrue($canedit);

        // Try edit report for other tenant.
        $canedit = \tool_reportbuilder\permission::can_schedule($report2);
        $this->assertFalse($canedit);

        // Try edit a system report.
        $systemreport = \tool_reportbuilder\system_report_factory::create(
            \tool_reportbuilder\local\systemreports\reports_list::class
        );
        $canedit = \tool_reportbuilder\permission::can_schedule($systemreport);
        $this->assertFalse($canedit);
    }

    /**
     * Test is_system_report method.
     */
    public function test_is_system_report(): void {
        // Is a system report.
        $systemreport = \tool_reportbuilder\system_report_factory::create(
            \tool_reportbuilder\local\systemreports\reports_list::class
        );
        $issystemreport = \tool_reportbuilder\permission::is_system_report($systemreport);
        $this->assertTrue($issystemreport);

        // Not is a system report.
        $systemreport = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);
        $issystemreport = \tool_reportbuilder\permission::is_system_report($systemreport);
        $this->assertFalse($issystemreport);
    }

    /**
     * Test check_belongs_same_tenant method.
     */
    public function test_check_belongs_same_tenant(): void {
        // Create tenants.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenantid = $this->get_tenant_generator()->create_tenant()->id;
        // Create users.
        $user1 = $this->getDataGenerator()->create_user()->id;
        $user2 = $this->getDataGenerator()->create_user()->id;
        // Allocate users in tenant.
        $this->get_tenant_generator()->allocate_user($user1, $defaulttenantid);
        $this->get_tenant_generator()->allocate_user($user2, $othertenantid);

        $this->setUser($user2);
        // Create report with user1.
        $report2 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $this->setUser($user1);

        // Create report with user1.
        $report1 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $issametenant = \tool_reportbuilder\permission::check_belongs_same_tenant($report1);
        $this->assertTrue($issametenant);

        $issametenant = \tool_reportbuilder\permission::check_belongs_same_tenant($report2);
        $this->assertFalse($issametenant);
    }

    /**
     * Test require_can_delete method.
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_require_can_delete(): void {
        // Create tenants.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenantid = $this->get_tenant_generator()->create_tenant()->id;
        // Create users.
        $user1 = $this->getDataGenerator()->create_user()->id;
        $user2 = $this->getDataGenerator()->create_user()->id;
        // Allocate users in tenant.
        $this->get_tenant_generator()->allocate_user($user1, $defaulttenantid);
        $this->get_tenant_generator()->allocate_user($user2, $othertenantid);

        $this->setUser($user2);
        // Create report with user1.
        $report2 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $this->setUser($user1);

        $this->expectExceptionMessage(
            'Sorry, but you do not currently have permissions to do that (Edit a report configuration).');
        \tool_reportbuilder\permission::require_can_delete($report2);
    }

    /**
     * Test require_can_edit method.
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_require_can_edit(): void {
        // Create tenants.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenantid = $this->get_tenant_generator()->create_tenant()->id;
        // Create users.
        $user1 = $this->getDataGenerator()->create_user()->id;
        $user2 = $this->getDataGenerator()->create_user()->id;
        // Allocate users in tenant.
        $this->get_tenant_generator()->allocate_user($user1, $defaulttenantid);
        $this->get_tenant_generator()->allocate_user($user2, $othertenantid);

        $this->setUser($user2);
        // Create report with user1.
        $report2 = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $this->setUser($user1);

        $this->expectExceptionMessage(
            'Sorry, but you do not currently have permissions to do that (Edit a report configuration).');
        \tool_reportbuilder\permission::require_can_edit($report2);

    }

    /**
     * Test can_create method for user with capability to do so
     */
    public function test_can_create(): void {
        global $CFG;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assign_edit_capability($user->id);

        $this->assertTrue(permission::can_create());

        // If enablecustomreports is false, users cannot create reports.
        $CFG->enablecustomreports = 0;
        $this->assertFalse(permission::can_create());
    }

    /**
     * Test can_create method for user without capability to do so
     */
    public function test_can_create_no_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse(permission::can_create());
    }

    /**
     * Test can_create method while observing site/tenant limits
     */
    public function test_can_create_observe_limits(): void {
        global $CFG;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assign_edit_capability($user->id);

        $this->get_report_generator()->create_report(['source' => mock_report::class]);

        // No limits have been set.
        $this->assertTrue(permission::can_create());

        // Disable limits, limit should be ignored.
        $CFG->tool_reportbuilder_limitsenabled = false;
        $CFG->tool_reportbuilder_sitelimit = 1;
        $this->assertTrue(permission::can_create());

        // Enable limits, set limit to zero.
        $CFG->tool_reportbuilder_limitsenabled = true;
        $CFG->tool_reportbuilder_sitelimit = 0;
        $this->assertFalse(permission::can_create());

        // Set site limit to two.
        $CFG->tool_reportbuilder_sitelimit = 2;
        $this->assertTrue(permission::can_create());

        // Create a report in another tenant.
        $anothertenant = $this->get_tenant_generator()->create_tenant();
        $this->get_report_generator()->create_report(['source' => mock_report::class, 'tenantid' => $anothertenant->id]);
        $this->assertFalse(permission::can_create());

        // Now ignore the limit.
        $this->assertTrue(permission::can_create(true));

        // Reset site limit, set tenant limit to 2.
        unset($CFG->tool_reportbuilder_sitelimit);
        $CFG->tool_reportbuilder_tenantlimit = 2;

        // Current tenant only has one report, so user should be able to create another.
        $this->assertTrue(permission::can_create());

        $this->get_report_generator()->create_report(['source' => mock_report::class]);
        $this->assertFalse(permission::can_create());

        // Now ignore the limit.
        $this->assertTrue(permission::can_create(true));
    }

    /**
     * Data provider for {@see test_can_create_observe_site_core_limits}
     *
     * @return array
     */
    public function can_create_observe_site_core_limits(): array {
        return [
            [true, 1, 0, 0, 0, true],
            [true, 1, 0, 1, 0, false],
            [true, 2, 0, 1, 0, true],
            [true, 2, 0, 1, 1, false],
            [false, 0, 1, 0, 0, true],
            [false, 0, 1, 1, 0, false],
            [false, 0, 1, 0, 1, false],
            [true, 10, 8, 5, 3, true],
            [false, 10, 8, 5, 3, false],
        ];
    }

    /**
     * Test can_create method while observing site core/tool limits
     * @param bool $toolreportslimitenabled
     * @param int $toolreportslimit
     * @param int $customreportslimit
     * @param int $existingtoolreports
     * @param int $existingcorereports
     * @param bool $expected
     * @dataProvider can_create_observe_site_core_limits
     */
    public function test_can_create_observe_site_core_limits(bool $toolreportslimitenabled, int $toolreportslimit,
                         int $customreportslimit, int $existingtoolreports, int $existingcorereports, bool $expected): void {
        global $CFG;

        $this->setAdminUser();

        // Set possible config values.
        $CFG->tool_reportbuilder_limitsenabled = $toolreportslimitenabled;
        $CFG->tool_reportbuilder_sitelimit = $toolreportslimit;
        $CFG->customreportslimit = $customreportslimit;

        // Check if tool reports needs to be created.
        if ($existingtoolreports) {
            for ($i = 0; $i < $existingtoolreports; $i++) {
                $this->get_report_generator()->create_report(['name' => 'Tool report' . $i, 'source' => mock_report::class]);
            }
        }

        // Check if core reports needs to be created.
        if ($existingcorereports) {
            /** @var core_reportbuilder_generator $generator */
            $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
            for ($i = 0; $i < $existingcorereports; $i++) {
                $generator->create_report(['name' => 'Core report' . $i, 'source' => users::class]);
            }
        }

        // Check if user can create tool rb.
        if ($expected) {
            $this->assertTrue(permission::can_create());
        } else {
            $this->assertFalse(permission::can_create());
        }
    }

    /**
     * Assigns 'tool/reportbuilder:read' capability.
     *
     * Create a dummy role with the capability allowed.
     *
     * @param int $userid The ID of the user to assign the capability.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function assign_read_capability(int $userid): void {
        $this->get_report_generator()->assign_read_capability($userid);
    }

    /**
     * Assigns 'tool/reportbuilder:edit' capability.
     *
     * Create a dummy role with the capability allowed.
     *
     * @param int $userid The ID of the user to assign the capability.
     */
    protected function assign_edit_capability(int $userid): void {
        $this->get_report_generator()->assign_edit_capability($userid);
    }

    /**
     * Assign a job with view reports permissions.
     *
     * @param int $userid
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function assign_job_with_report_permissions(int $userid): void {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($userid, $tenant->id);
        $this->get_report_generator()->assign_job_with_report_permissions($userid, []);
    }

    /**
     * Tests that various "require" methods return descriptive error message
     */
    public function test_require(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $report = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);
        $schedule = $this->get_report_generator()->create_schedule(['reportid' => $report->get_id()]);

        try {
            \tool_reportbuilder\permission::require_can_create();
            $this->fail('Exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Edit a report configuration).', $exception->getMessage());
        }

        try {
            \tool_reportbuilder\permission::require_can_view($report);
            $this->fail('Exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that (View reports).',
                $exception->getMessage());
        }

        try {
            \tool_reportbuilder\permission::require_can_delete($report);
            $this->fail('Exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Edit a report configuration).', $exception->getMessage());
        }

        try {
            \tool_reportbuilder\permission::require_can_view_access_tab($report);
            $this->fail('Exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Edit a report configuration).', $exception->getMessage());
        }

        try {
            \tool_reportbuilder\permission::require_can_create_schedule();
            $this->fail('Exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('You don\'t have permission to manage schedules', $exception->getMessage());
        }

        try {
            \tool_reportbuilder\permission::require_can_send_schedule($schedule);
            $this->fail('Exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('You don\'t have permission to manage schedules', $exception->getMessage());
        }

        try {
            \tool_reportbuilder\permission::require_can_delete_schedule($schedule);
            $this->fail('Exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('You don\'t have permission to manage schedules', $exception->getMessage());
        }

        try {
            \tool_reportbuilder\permission::require_can_edit_schedule($schedule);
            $this->fail('Exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('You don\'t have permission to manage schedules', $exception->getMessage());
        }
    }

    /**
     * Tests that a user with edit capability and manage tenants can edit report in shared space.
     */
    public function test_can_edit_in_report_tenant(): void {
        self::setAdminUser();
        $tenant = $this->get_tenant_generator()->create_tenant();
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();

        $this->get_tenant_generator()->allocate_user(get_admin()->id, $tenant->id);

        $user1 = $this->getDataGenerator()->create_user()->id;
        $this->get_tenant_generator()->allocate_user($user1, $tenant->id);
        $this->setUser($user1);

        $report = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
            'tenantid' => $sharedspaceid,
            'adddefault' => (int)true,
            'shared' => true,
        ]);

        $this->assertFalse(permission::can_edit_in_report_tenant($report));

        $this->assign_edit_capability($user1);
        $this->assertFalse(permission::can_edit_in_report_tenant($report));

        $roleid = create_role('tool/tenant:manage', 'tool/tenant:manage', "");
        assign_capability('tool/tenant:manage', CAP_ALLOW, $roleid, context_system::instance()->id);
        role_assign($roleid, $user1, context_system::instance()->id);

        $this->assertTrue(permission::can_edit_in_report_tenant($report));
    }

    public function test_require_can_edit_in_report_tenant(): void {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();

        $user1 = $this->getDataGenerator()->create_user()->id;
        $this->get_tenant_generator()->allocate_user($user1, $tenant->id);
        $this->setUser($user1);

        $report = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
            'tenantid' => $sharedspaceid,
            'adddefault' => (int)true,
            'shared' => true,
        ]);

        $this->expectExceptionMessage('Sorry, but you do not currently have permissions to do that (Edit a report configuration).');
        permission::require_can_edit_in_report_tenant($report);
    }
}
