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
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->libdir . '/tablelib.php');

/**
 * External testcase.
 *
 * @package    tool_reportbuilder
 * @group      tool_reportbuilder
 * @category   test
 * @covers     \tool_reportbuilder\external
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_external_testcase extends externallib_advanced_testcase {

    /** @var int $reportid */
    protected $reportid;
    /** @var int $tableuniqid */
    protected $tableuniqid;
    /** @var tool_reportbuilder\report_table */
    protected $table;

    /**
     * Setup function- create a dummy report in with some columns.
     */
    public function setUp() {
        $this->setAdminUser();
        $record = new stdClass();
        $record->name = 'Test report';
        $record->source = \tool_reportbuilder\test\mock_report::class;
        $report = $this->get_generator()->create_report($record);

        $this->reportid = $report->get_id();
        $this->tableuniqid = $report->get_report_uniqid();
        $table = new \tool_reportbuilder\report_table($this->tableuniqid);
        $table->setup = true;
        $this->table = $table;
    }

    /**
     * Test toggle the sort column
     */
    public function test_toggle_report_sorting_column() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Enable sorting for first column.
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);
        tool_reportbuilder\external::toggle_report_sorting_column($this->reportid, $columns['columnsinuse'][0]->id);
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);
        $this->assertTrue((boolean)$columns['columnsinuse'][0]->sortenabled);
        $this->assertTrue((boolean)$columns['columnsinuse'][0]->enabledsorting);

        $this->define_sort($columns);
        $this->assertEquals('firstname ASC', $this->table->get_sql_sort());

        // Disable one column.
        $this->table = new \tool_reportbuilder\report_table($this->tableuniqid);
        $this->table->setup = true;
        tool_reportbuilder\external::toggle_report_sorting_column($this->reportid, $columns['columnsinuse'][0]->id);
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);
        $this->assertFalse((boolean)$columns['columnsinuse'][0]->sortenabled);
        $this->assertFalse((boolean)$columns['columnsinuse'][0]->enabledsorting);

        $this->define_sort($columns);
        $this->assertEquals('', $this->table->get_sql_sort());

        // Enable both columns.
        $this->table = new \tool_reportbuilder\report_table($this->tableuniqid);
        $this->table->setup = true;
        tool_reportbuilder\external::toggle_report_sorting_column($this->reportid, $columns['columnsinuse'][0]->id);
        tool_reportbuilder\external::toggle_report_sorting_column($this->reportid, $columns['columnsinuse'][1]->id);
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);

        $this->define_sort($columns);
        $this->assertEquals("firstname ASC,\n\tidnumber ASC", $this->table->get_sql_sort());

    }

    /**
     * Test the toggle column sorting direction service.
     */
    public function test_toggle_column_sorting_direction() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Enable sorting on the column. It should be sorted ascending.
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);
        tool_reportbuilder\external::toggle_report_sorting_column($this->reportid, $columns['columnsinuse'][0]->id);
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);
        $this->assertEquals(SORT_ASC, $columns['columnsinuse'][0]->sortdirection);
        $this->assertEquals($columns['columnsinuse'][0]->sorticon, 'sort_asc');

        // Toggle the direction for one column.
        tool_reportbuilder\external::toggle_column_sorting_direction($this->reportid, $columns['columnsinuse'][0]->id);
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);
        $this->assertEquals(SORT_DESC, $columns['columnsinuse'][0]->sortdirection);
        $this->assertEquals($columns['columnsinuse'][0]->sorticon, 'sort_desc');

        $this->define_sort($columns);
        $this->assertEquals('firstname DESC', $this->table->get_sql_sort());

        // Toggle again.
        $this->table = new \tool_reportbuilder\report_table($this->tableuniqid);
        $this->table->setup = true;
        tool_reportbuilder\external::toggle_column_sorting_direction($this->reportid, $columns['columnsinuse'][0]->id);
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);
        $this->assertEquals(SORT_ASC, $columns['columnsinuse'][0]->sortdirection);
        $this->assertEquals($columns['columnsinuse'][0]->sorticon, 'sort_asc');

        $this->define_sort($columns);
        $this->assertEquals('firstname ASC', $this->table->get_sql_sort());
    }

    /**
     * Test the toggle column sorting direction service.
     */
    public function test_reorder_sortable_column() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);

        tool_reportbuilder\external::reorder_sortable_column(
            $this->reportid,
            json_encode([
                $columns['columnsinuse'][1]->id,
                $columns['columnsinuse'][0]->id
            ])
        );

        $columnsold = $columns['columnsinuse'];
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);
        $this->assertEquals($columnsold[1]->id, $columns['columnsinuse'][0]->id);
        $this->assertEquals($columnsold[0]->id, $columns['columnsinuse'][1]->id);
        $this->define_sort($columns);
        $this->assertEquals('', $this->table->get_sql_sort());

        $this->table = new \tool_reportbuilder\report_table($this->tableuniqid);
        $this->table->setup = true;
        tool_reportbuilder\external::toggle_report_sorting_column($this->reportid, $columns['columnsinuse'][0]->id);
        tool_reportbuilder\external::toggle_report_sorting_column($this->reportid, $columns['columnsinuse'][1]->id);
        $columns = tool_reportbuilder\external::get_report_sortable_columns($this->reportid);
        $this->define_sort($columns);
        $this->assertEquals("idnumber ASC,\n\tfirstname ASC", $this->table->get_sql_sort());
    }

    /**
     * Define the sort.
     *
     * @param array $columns
     */
    protected function define_sort($columns) {
        // TODO does not test anything. We create SQL query in unittest and test it in the same unittest. There is no point in it.
        foreach ($columns['columnsinuse'] as $column) {
            if ($column->sortenabled) {
                $this->table->add_sort_to_sql($column->sortorder, $column->name . ' ' .
                    (($column->sortdirection == SORT_DESC) ? 'DESC' : 'ASC'));
            }
        }

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
}