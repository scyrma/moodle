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
 * Schedules external services tests.
 *
 * @package   tool_reportbuilder
 * @category test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\external;

use coding_exception;
use external_api;
use externallib_advanced_testcase;
use moodle_exception;
use stdClass;
use tool_reportbuilder\external\schedule as external;
use tool_reportbuilder\local\models\schedule;
use tool_reportbuilder\test\mock_report;
use tool_reportbuilder_generator;
use tool_tenant_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Class tool_reportbuilder_external_schedule_testcase
 *
 * @package   tool_reportbuilder
 * @covers    \tool_reportbuilder\external\schedule
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class schedule_test extends externallib_advanced_testcase {

    /** @var stdClass $user */
    protected $user;

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void {
        $this->resetAfterTest(true);

        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);
    }

    /**
     * Test for external toggle_schedule method
     *
     * @return void
     */
    public function test_toggle_schedule() {
        global $DB;

        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'scheduled' => time() - MINSECS,
        ]);

        // Sanity test (should be enabled).
        $this->assertEquals(1, $schedule->get('enabled'));

        $this->get_generator()->assign_edit_capability($this->user->id);

        external::toggle_schedule($schedule->get('id'), false);
        $this->assertEquals(0, $DB->get_field($schedule::TABLE, 'enabled', ['id' => $schedule->get('id')]));

        // Re-enable.
        external::toggle_schedule($schedule->get('id'), true);
        $this->assertEquals(1, $DB->get_field($schedule::TABLE, 'enabled', ['id' => $schedule->get('id')]));
    }

    /**
     * Test for external delete_schedule method
     *
     * @return void
     */
    public function test_delete_schedule() {
        global $DB;

        // Create report + schedule in separate tenants.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $report1 = $this->get_generator()->create_report(['source' => mock_report::class, 'tenantid' => $tenant1->id]);
        $schedule1 = $this->get_generator()->create_schedule(['reportid' => $report1->get_id()]);

        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $report2 = $this->get_generator()->create_report(['source' => mock_report::class, 'tenantid' => $tenant2->id]);
        $schedule2 = $this->get_generator()->create_schedule(['reportid' => $report2->get_id()]);

        // Allocate our test user to the first tenant.
        $this->get_tenant_generator()->allocate_user($this->user->id, $tenant1->id);

        // Sanity check.
        $this->assertEquals(2, schedule::count_records());

        // Test with user without permissions.
        try {
            \tool_reportbuilder\external\schedule::delete_schedule($schedule1->get('id'));
            $this->fail('moodle_exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('You don\'t have permission to manage schedules', $exception->getMessage());
        }

        // Test with user with permissions.
        $this->get_generator()->assign_edit_capability($this->user->id);

        $result = \tool_reportbuilder\external\schedule::delete_schedule($schedule1->get('id'));
        $result = external_api::clean_returnvalue(\tool_reportbuilder\external\schedule::delete_schedule_returns(), $result);
        $this->assertTrue($result);

        $this->assertEquals(1, schedule::count_records());

        // Try delete a schedule of other tenant.
        try {
            \tool_reportbuilder\external\schedule::delete_schedule($schedule2->get('id'));
            $this->fail('moodle_exception expected');
        } catch (moodle_exception $exception) {
            $this->assertEquals('You don\'t have permission to manage schedules', $exception->getMessage());
        }
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_generator(): tool_reportbuilder_generator {
        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
        return $generator;
    }

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator
     * @throws coding_exception
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        return $generator;
    }
}
