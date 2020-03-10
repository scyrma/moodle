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

    /** @var int $reportid */
    protected $reportid;

    /**
     * Setup function- create a dummy report in with some columns.
     *
     * @return void
     */
    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->reportid = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ])->get_id();
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
        $result = external::toggle_report_sorting_column($this->reportid, $column['id']);
        $result = external::clean_returnvalue(external::toggle_report_sorting_column_returns(), $result);
        $this->assertEquals(1, $result['newcolumnstate']);

        $column = $this->get_external_report_sortable_columns()['columnsinuse'][0];
        $this->assertTrue($column['sortenabled']);

        // Disable sorting on the column.
        $result = external::toggle_report_sorting_column($this->reportid, $column['id']);
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
        external::toggle_report_sorting_column($this->reportid, $column['id']);

        $column = $this->get_external_report_sortable_columns()['columnsinuse'][0];
        $this->assertEquals(SORT_ASC, $column['sortdirection']);
        $this->assertEquals('sort_asc', $column['sorticon']);

        // Toggle the direction for one column.
        $result = external::toggle_column_sorting_direction($this->reportid, $column['id']);
        $result = external::clean_returnvalue(external::toggle_column_sorting_direction_returns(), $result);
        $this->assertEquals(SORT_DESC, $result['newcolumnstate']);

        $column = $this->get_external_report_sortable_columns()['columnsinuse'][0];
        $this->assertEquals(SORT_DESC, $column['sortdirection']);
        $this->assertEquals('sort_desc', $column['sorticon']);

        // Toggle again.
        $result = external::toggle_column_sorting_direction($this->reportid, $column['id']);
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
        $result = external::reorder_sortable_column($this->reportid, json_encode([$column2['id'], $column1['id']]));
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
        $tableid = uniqid();

        // Sort flextable by 'c0_firstname' table heading DESC.
        external\columns::sort_table_by_heading($tableid, 'c0_firstname', SORT_DESC);

        $prefs = json_decode(get_user_preferences('flextable_'.$tableid), true);
        $this->assertArrayHasKey('sortby', $prefs);
        $this->assertCount(1, $prefs['sortby']);
        $this->assertArrayHasKey('c0_firstname', $prefs['sortby']);
        $this->assertEquals(SORT_DESC, $prefs['sortby']['c0_firstname']);

        // Sort flextable by 'c1_lastname' table heading ASC.
        external\columns::sort_table_by_heading($tableid, 'c1_lastname', SORT_ASC);

        $prefs = json_decode(get_user_preferences('flextable_'.$tableid), true);
        $this->assertCount(2, $prefs['sortby']);
        $this->assertEquals(['c1_lastname', 'c0_firstname'], array_keys($prefs['sortby']));
        $this->assertEquals(SORT_ASC, $prefs['sortby']['c1_lastname']);

        // Sort flextable by 'c3_username' table heading ASC.
        external\columns::sort_table_by_heading($tableid, 'c3_username', SORT_ASC);

        $prefs = json_decode(get_user_preferences('flextable_'.$tableid), true);
        // Only 2 sorting preferences are saved. Oldest is removed.
        $this->assertCount(2, $prefs['sortby']);
        $this->assertEquals(['c3_username', 'c1_lastname'], array_keys($prefs['sortby']));
        $this->assertEquals(SORT_ASC, $prefs['sortby']['c3_username']);
    }

    /**
     * Test the table reset heading sort.
     *
     * @return void
     */
    public function test_reset_table_heading_sort() {
        $tableid = uniqid();
        // Sort flextable by 'c0_firstname' table heading DESC.
        external\columns::sort_table_by_heading($tableid, 'c0_firstname', SORT_DESC);
        // Sort flextable by 'c1_lastname' table heading ASC.
        external\columns::sort_table_by_heading($tableid, 'c1_lastname', SORT_ASC);
        $prefs = json_decode(get_user_preferences('flextable_'.$tableid), true);
        $this->assertArrayHasKey('sortby', $prefs);
        $this->assertCount(2, $prefs['sortby']);

        // Reset the table sorting by heading.
        external\columns::reset_table_heading_sort($tableid);
        $prefs = json_decode(get_user_preferences('flextable_'.$tableid), true);
        $this->assertEmpty($prefs);
    }

    /**
     * Helper method to call external method for getting sortable columns for a report
     *
     * @return array
     */
    protected function get_external_report_sortable_columns(): array {
        $columns = external::get_report_sortable_columns($this->reportid);

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