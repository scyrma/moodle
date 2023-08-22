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
 * File containing tests for external report class
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\external;

use externallib_advanced_testcase;
use tool_reportbuilder\event\report_deleted;
use tool_reportbuilder\event\report_viewed;
use tool_reportbuilder\external\columns as external_columns;
use tool_reportbuilder\external\report as external;
use tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_reportbuilder\local\models\reportbuilder_conditions as condition;
use tool_reportbuilder\local\report\reportbuilder_filter as filter;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\reportbuilder_column as column;
use tool_reportbuilder\test\mock_report;
use tool_reportbuilder_generator;

defined('MOODLE_INTERNAL') || die();

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
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_test extends externallib_advanced_testcase {

    /** @var mock_report $report */
    protected $report;

    /**
     * Test setup
     */
    public function setUp(): void {
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
        $reportid = $this->report->get_id();
        $persistent = $this->report->get_persistent();

        // Make sure we have some columns, so we can confirm they are deleted later.
        $this->assertGreaterThan(0, column::count_records(['reportid' => $reportid]));

        // Add some conditions & filters.
        $this->get_generator()->add_condition($this->report, 'user:firstname');
        $this->get_generator()->add_filter($this->report, 'user:lastname');

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

        // Make sure report data is actually deleted.
        $this->assertFalse(reportbuilder::record_exists($reportid));

        $params = ['reportid' => $reportid];
        $this->assertEquals(0, column::count_records($params));
        $this->assertEquals(0, condition::count_records($params));
        $this->assertEquals(0, filter::count_records($params));
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
     * Test report_delete method with an unavailable source
     *
     * @return void
     */
    public function test_report_delete_unavailable_source() : void {
        global $DB;

        $obj = \tool_reportbuilder\manager::save_report((object)[
            'name' => 'Mock Report',
            'type' => \tool_reportbuilder\constants::TYPE_DATASOURCE,
            'description' => '',
            'source' => \tool_reportbuilder\test\mock_report_unavailable::class,
        ], false);

        $report = new \tool_reportbuilder\test\mock_report_unavailable($obj);
        $reportid = $report->get_id();

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

    /**
     * Test table reset.
     *
     * @return void
     */
    public function test_reset_table() {
        $reportid = $this->report->get_id();

        // Sort flextable by 'c0_firstname' table heading DESC.
        external_columns::sort_table_by_heading($reportid, 'c0_firstname', SORT_DESC);
        // Sort flextable by 'c1_lastname' table heading ASC.
        external_columns::sort_table_by_heading($reportid, 'c1_lastname', SORT_ASC);

        $sortpreferences = $this->report->get_sort_preferences();
        $this->assertCount(2, $sortpreferences);

        // Filter report by user firstname and lastname.
        filters_helper::set_filter(
            $reportid,
            (object)['user:firstname' => 'Firstname', 'user:lastname' => 'Lastname']
        );
        $filters = json_decode(get_user_preferences('filters_report_' . $reportid));
        $this->assertCount(2, (array)$filters);

        // Reset the table.
        external::reset_table($reportid);

        $sortpreferences = $this->report->get_sort_preferences();
        $this->assertEmpty($sortpreferences);

        $filters = json_decode(get_user_preferences('filters_report_' . $reportid));
        $this->assertEmpty($filters);
    }
}
