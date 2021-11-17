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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * External tests.
 *
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\external;
use tool_reportbuilder\test\mock_report;

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External testcase.
 *
 * @package    tool_reportbuilder
 * @group      tool_reportbuilder
 * @category   test
 * @covers     \tool_reportbuilder\external
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_external_column_testcase extends externallib_advanced_testcase {

    /** @var mock_report $report */
    protected $report;

    /**
     * Setup function- create a dummy report in with some columns.
     *
     * @return void
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);
    }

    /**
     * Test external get_report_sortable_columns method
     *
     * @return void
     */
    public function test_get_report_sortable_columns() {
        $columns = $this->get_external_report_sortable_columns();

        $this->assertTrue($columns['hassortablecolumns']);
        $this->assertCount(2, $columns['columnsinuse']);

        list($column1, $column2) = $columns['columnsinuse'];
        $this->assertEquals('user:firstname', $column1['key']);
        $this->assertEquals(0, $column1['sortorder']);
        $this->assertFalse($column1['sortenabled']);
        $this->assertEquals('user:idnumber', $column2['key']);
        $this->assertEquals(1, $column2['sortorder']);
        $this->assertFalse($column2['sortenabled']);
    }

    /**
     * Test toggle the sort column
     *
     * @return void
     */
    public function test_toggle_report_sorting_column() {
        $column = $this->get_external_report_sortable_columns()['columnsinuse'][0];

        // Enable sorting on the column.
        $result = external::toggle_report_sorting_column($this->report->get_id(), $column['id']);
        $result = external::clean_returnvalue(external::toggle_report_sorting_column_returns(), $result);
        $this->assertEquals(1, $result['newcolumnstate']);

        $column = $this->get_external_report_sortable_columns()['columnsinuse'][0];
        $this->assertTrue($column['sortenabled']);

        // Disable sorting on the column.
        $result = external::toggle_report_sorting_column($this->report->get_id(), $column['id']);
        $result = external::clean_returnvalue(external::toggle_report_sorting_column_returns(), $result);
        $this->assertEquals(0, $result['newcolumnstate']);

        $column = $this->get_external_report_sortable_columns()['columnsinuse'][0];
        $this->assertFalse($column['sortenabled']);
    }

    /**
     * Test the toggle column sorting direction service.
     *
     * @return void
     */
    public function test_toggle_column_sorting_direction() {
        $column = $this->get_external_report_sortable_columns()['columnsinuse'][0];

        // Enable sorting on the column. It should be sorted ascending.
        external::toggle_report_sorting_column($this->report->get_id(), $column['id']);

        $column = $this->get_external_report_sortable_columns()['columnsinuse'][0];
        $this->assertEquals(SORT_ASC, $column['sortdirection']);
        $this->assertEquals('sort_asc', $column['sorticon']);

        // Toggle the direction for one column.
        $result = external::toggle_column_sorting_direction($this->report->get_id(), $column['id']);
        $result = external::clean_returnvalue(external::toggle_column_sorting_direction_returns(), $result);
        $this->assertEquals(SORT_DESC, $result['newcolumnstate']);

        $column = $this->get_external_report_sortable_columns()['columnsinuse'][0];
        $this->assertEquals(SORT_DESC, $column['sortdirection']);
        $this->assertEquals('sort_desc', $column['sorticon']);

        // Toggle again.
        $result = external::toggle_column_sorting_direction($this->report->get_id(), $column['id']);
        $result = external::clean_returnvalue(external::toggle_column_sorting_direction_returns(), $result);
        $this->assertEquals(SORT_ASC, $result['newcolumnstate']);

        $column = $this->get_external_report_sortable_columns()['columnsinuse'][0];
        $this->assertEquals(SORT_ASC, $column['sortdirection']);
        $this->assertEquals('sort_asc', $column['sorticon']);
    }

    /**
     * Test the toggle column sorting direction service.
     *
     * @return void
     */
    public function test_reorder_sortable_column() {
        list($column1, $column2) = $this->get_external_report_sortable_columns()['columnsinuse'];

        // Re-order column sorting, so the second column moves to the beginning.
        $result = external::reorder_sortable_column($this->report->get_id(), json_encode([$column2['id'], $column1['id']]));
        $this->assertNull($result);

        list($column1, $column2) = $this->get_external_report_sortable_columns()['columnsinuse'];
        $this->assertEquals('user:idnumber', $column1['key']);
        $this->assertEquals(0, $column1['sortorder']);
        $this->assertEquals('user:firstname', $column2['key']);
        $this->assertEquals(1, $column2['sortorder']);
    }

    /**
     * Test the column sorting by table heading.
     *
     * @return void
     */
    public function test_sort_table_by_heading() {
        // Sort flextable by 'c0_firstname' table heading DESC.
        external\columns::sort_table_by_heading($this->report->get_id(), 'c0_firstname', SORT_DESC);

        $sortpreferences = $this->report->get_sort_preferences();
        $this->assertEquals(['c0_firstname' => SORT_DESC], $sortpreferences);

        // Sort flextable by 'c1_lastname' table heading ASC.
        external\columns::sort_table_by_heading($this->report->get_id(), 'c1_lastname', SORT_ASC);

        $sortpreferences = $this->report->get_sort_preferences();
        $this->assertEquals([
            'c1_lastname' => SORT_ASC,
            'c0_firstname' => SORT_DESC,
        ], $sortpreferences);

        // Sort flextable by 'c3_username' table heading ASC.
        external\columns::sort_table_by_heading($this->report->get_id(), 'c3_username', SORT_ASC);

        $sortpreferences = $this->report->get_sort_preferences();
        // Only 2 sorting preferences are saved. Oldest is removed.
        $this->assertEquals([
            'c3_username' => SORT_ASC,
            'c1_lastname' => SORT_ASC,
        ], $sortpreferences);
    }

    /**
     * Helper method to call external method for getting sortable columns for a report
     *
     * @return array
     */
    protected function get_external_report_sortable_columns(): array {
        $columns = external::get_report_sortable_columns($this->report->get_id());

        return external::clean_returnvalue(external::get_report_sortable_columns_returns(), $columns);
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }
}
