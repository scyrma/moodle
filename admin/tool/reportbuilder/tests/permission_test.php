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
 * Tests for the tool_reportbuilder permission class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Permission tests.
 *
 * @package   tool_reportbuilder
 * @covers    \tool_reportbuilder\permission
 * @copyright 2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_permission_testcase extends advanced_testcase{

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
    public function setUp() {
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
     * @throws coding_exception
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Get reportbuilder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_report_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Creates tenant and assigns user.
     * @return object
     * @throws coding_exception
     */
    protected function create_tenant_and_user() {
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
    public function test_can_view_any() {
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
     * Test can_view_as_manager method.
     *
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function test_can_view_as_manager() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // User without manager position.
        $canview = \tool_reportbuilder\permission::can_view_some_as_a_manager();
        $this->assertFalse($canview);

        $this->assign_job_with_report_permissions($user->id);
        // User without manager position.
        $canview = \tool_reportbuilder\permission::can_view_some_as_a_manager();
        $this->assertTrue($canview);
    }

    /**
     * Test can_view_report_as_a_manager method.
     *
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function test_can_view_report_as_a_manager() {
        $user = $this->getDataGenerator()->create_user();
        $reportgenerator = $this->get_report_generator();
        $this->setUser($user);

        // Report without organization filter.
        $report1 = $reportgenerator->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
           ]);
        $canview = \tool_reportbuilder\permission::can_view_report_as_a_manager($report1);
        $this->assertFalse($canview);

        // Report with organization filter but the user can not view as a manager.
        $report1 = $reportgenerator->create_report([
            'source' => \tool_reportbuilder\test\mock_report2::class
        ]);
        $canview = \tool_reportbuilder\permission::can_view_report_as_a_manager($report1);
        $this->assertFalse($canview);

        // Report with organization filter and the user can view as a manager.
        $this->assign_job_with_report_permissions($user->id);
        $report1 = $reportgenerator->create_report([
            'source' => \tool_reportbuilder\test\mock_report2::class
        ]);
        $canview = \tool_reportbuilder\permission::can_view_report_as_a_manager($report1);
        $this->assertTrue($canview);
    }

    /**
     * Test can_manage_reports method.
     */
    public function test_can_manage_reports() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // User without any permissions.
        $result = \tool_reportbuilder\permission::can_view_reports_list();
        $this->assertFalse($result);

        // User has the capability 'tool/reportbuilder:read'.
        $this->assign_read_capability($user->id);
        $result = \tool_reportbuilder\permission::can_view_reports_list();
        $this->assertTrue($result);

        // User has not the capability but has a job with permissions.
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user2);
        $this->assign_job_with_report_permissions($user2->id);
        $result = \tool_reportbuilder\permission::can_view_reports_list();
        $this->assertTrue($result);
    }

    /**
     * Test can_edit method.
     */
    public function test_can_edit() {
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
    public function test_delete_edit() {
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
    public function test_duplicate_edit() {
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
    public function test_schedule_edit() {
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
    public function test_is_system_report() {
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
    public function test_check_belongs_same_tenant() {
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
    public function test_require_can_delete() {
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
    public function test_require_can_edit() {
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
     * Test require_can_create method.
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_require_can_create() {
        $user1 = $this->getDataGenerator()->create_user()->id;

        $this->setUser($user1);

        $this->expectExceptionMessage('Sorry, but you do not currently have permissions to do that ' .
            '(Edit a report configuration)');
        \tool_reportbuilder\permission::require_can_create();

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
     * @throws coding_exception
     * @throws dml_exception
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
    protected function assign_job_with_report_permissions(int $userid) : void {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($userid, $tenant->id);
        $this->get_report_generator()->assign_job_with_report_permissions($userid, []);
    }

    /**
     * Tests that various "require" methods return descriptive error message
     */
    public function test_require() {
        $report = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);
        $schedule = $this->get_report_generator()->create_schedule(['reportid' => $report->get_id()]);
        $schedule = new \tool_reportbuilder\local\models\schedules($schedule->id);

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
            \tool_reportbuilder\permission::require_can_manage_schedules();
            $this->fail('Exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('You don\'t have permission to manage schedules', $exception->getMessage());
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
}