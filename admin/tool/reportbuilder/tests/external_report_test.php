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
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\event\report_deleted;
use tool_reportbuilder\event\report_viewed;
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
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_external_report_testcase extends externallib_advanced_testcase {

    /** @var mock_report $report */
    protected $report;

    /**
     * Test setup
     */
    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);
    }

    /**
     * Test get_reportbuilder method for preview
     *
     * @return void
     */
    public function test_get_reportbuilder_preview() : void {
        $reportid = $this->report->get_id();
        $persistent = $this->report->get_persistent();

        // Catch the events.
        $sink = $this->redirectEvents();

        $result = external::get_reportbuilder($reportid, false);
        $result = external::clean_returnvalue(external::get_reportbuilder_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $this->assertInstanceOf(report_viewed::class, $events[0]);
        $this->assertEquals($reportid, $events[0]->objectid);
        $this->assertEquals(reportbuilder::TABLE, $events[0]->objecttable);
        $this->assertEquals('preview', $events[0]->other['source']);

        $sink->close();

        $this->assertEquals($result['reportid'], $reportid);
        $this->assertEquals($result['name'], $this->report->get_reportname());
        $this->assertEquals($result['idnumber'], $persistent->get('idnumber'));
        $this->assertEquals($result['shortname'], $persistent->get('shortname'));
        $this->assertEquals($result['source'], $persistent->get('source'));
        $this->assertEquals($result['ispreview'], true);

        $this->assertEmpty($result['availablecolumns']);
        $this->assertEmpty($result['availableconditions']);
        $this->assertEmpty($result['availablefilters']);
    }

    /**
     * Test get_reportbuilder method for edit
     *
     * @return void
     */
    public function test_get_reportbuilder_edit() : void {
        $reportid = $this->report->get_id();
        $persistent = $this->report->get_persistent();

        $result = external::get_reportbuilder($reportid, true);
        $result = external::clean_returnvalue(external::get_reportbuilder_returns(), $result);

        $this->assertEquals($result['reportid'], $reportid);
        $this->assertEquals($result['name'], $this->report->get_reportname());
        $this->assertEquals($result['idnumber'], $persistent->get('idnumber'));
        $this->assertEquals($result['shortname'], $persistent->get('shortname'));
        $this->assertEquals($result['source'], $persistent->get('source'));
        $this->assertEquals($result['ispreview'], false);
        $columnsinuse = array_column($result['columnsinuse'], 'key');
        $this->assertEquals($columnsinuse, ['user:firstname', 'user:idnumber']);

        $sortablecolumns = array_column($result['sortablecolumns'], 'key');
        $this->assertEquals($sortablecolumns, ['user:firstname', 'user:idnumber']);

        $availablecolumns = array_column($result['availablecolumns'][0]['optiongroup']['values'], 'value');
        $this->assertEquals($availablecolumns, ['user:firstname', 'user:idnumber', 'user:lastname', 'user:phone1']);

        $availableconditions = array_column($result['availableconditions'][0]['optiongroup']['values'], 'value');
        $this->assertEquals($availableconditions, ['user:firstname', 'user:idnumber', 'user:lastname', 'user:phone1']);

        $availablefilters = array_column($result['availablefilters'][0]['optiongroup']['values'], 'value');
        $this->assertEquals($availablefilters, ['user:firstname', 'user:idnumber', 'user:lastname', 'user:phone1']);

        $this->assertArrayHasKey('table', $result);
        $this->assertArrayHasKey('filtersform', $result);
        $this->assertArrayHasKey('conditionshelp', $result);
        $this->assertArrayHasKey('filtershelp', $result);
        $this->assertArrayHasKey('sortingshelp', $result);
    }

    /**
     * Test report_delete method
     *
     * @return void
     */
    public function test_report_delete() : void {
        global $DB;

        $reportid = $this->report->get_id();
        $persistent = $this->report->get_persistent();

        // Catch the events.
        $sink = $this->redirectEvents();

        $result = external::delete_report($reportid);
        $result = external::clean_returnvalue(external::delete_report_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $this->assertInstanceOf(report_deleted::class, $events[0]);
        $this->assertEquals($reportid, $events[0]->objectid);
        $this->assertEquals(reportbuilder::TABLE, $events[0]->objecttable);
        $this->assertEquals($persistent->get('name'), $events[0]->other['name']);
        $this->assertEquals($persistent->get('source'), $events[0]->other['source']);

        $sink->close();

        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);

        // Make sure report is actually deleted.
        $this->assertFalse($DB->record_exists('tool_reportbuilder', ['id' => $reportid]));
    }

    /**
     * Test report_delete method with an invalid source
     *
     * @return void
     */
    public function test_report_delete_invalid_source() : void {
        global $DB;

        $reportid = $this->report->get_id();

        // Fudge the report source field.
        $DB->set_field('tool_reportbuilder', 'source', '\not\real', ['id' => $reportid]);

        $result = external::delete_report($reportid);
        $result = external::clean_returnvalue(external::delete_report_returns(), $result);

        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);

        // Make sure report is actually deleted.
        $this->assertFalse($DB->record_exists('tool_reportbuilder', ['id' => $reportid]));
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