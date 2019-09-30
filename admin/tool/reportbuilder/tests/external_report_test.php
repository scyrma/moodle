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
 * File containing tests for external report class
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\event\report_deleted;
use tool_reportbuilder\external\report as external;
use tool_reportbuilder\test\mock_report;

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\event\report_deleted
 * @covers      \tool_reportbuilder\external\report
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_external_report_testcase extends externallib_advanced_testcase {

    /**
     * Test setup
     */
    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test report_delete method
     *
     * @return void
     */
    public function test_report_delete() : void {
        global $DB;

        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);

        $persistent = $report->get_persistent();

        // Catch the events.
        $sink = $this->redirectEvents();

        $result = external::delete_report($report->get_id());
        $result = external::clean_returnvalue(external::delete_report_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $this->assertInstanceOf(report_deleted::class, $events[0]);
        $this->assertEquals($report->get_id(), $events[0]->objectid);
        $this->assertEquals($persistent::TABLE, $events[0]->objecttable);
        $this->assertEquals($persistent->get('name'), $events[0]->other['name']);
        $this->assertEquals($persistent->get('source'), $events[0]->other['source']);

        $sink->close();

        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);

        // Make sure report is actually deleted.
        $this->assertFalse($DB->record_exists('tool_reportbuilder', ['id' => $report->get_id()]));
    }

    /**
     * Test report_delete method with an invalid source
     *
     * @return void
     */
    public function test_report_delete_invalid_source() : void {
        global $DB;

        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);

        // Fudge the report source field.
        $DB->set_field('tool_reportbuilder', 'source', '\not\real', ['id' => $report->get_id()]);

        $result = external::delete_report($report->get_id());
        $result = external::clean_returnvalue(external::delete_report_returns(), $result);

        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);

        // Make sure report is actually deleted.
        $this->assertFalse($DB->record_exists('tool_reportbuilder', ['id' => $report->get_id()]));
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_generator() : tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }
}