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
 * File containing tests for report access list class
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\report_base;
use tool_reportbuilder\local\helpers\columns as columns_helper;
use tool_reportbuilder\test\mock_report;

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\local\helpers\columns
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_helper_columns_testcase extends advanced_testcase {

    /** @var report_base $report */
    protected $report;

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void {
        $this->resetAfterTest();

        $this->report = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);
    }

    /**
     * Test adding column from key
     *
     * @return void
     */
    public function test_add_column_from_key() {
        // The mock report has two columns by default.
        $columns = $this->report->get_active_columns();
        $this->assertCount(2, $columns);

        $column = columns_helper::add_column_from_key($this->report, 'user:lastname');
        $this->assertEquals($this->report->get_id(), $column->get('reportid'));
        $this->assertEquals('user', $column->get('entity'));
        $this->assertEquals('lastname', $column->get('name'));
        $this->assertEquals(2, $column->get('columnorder'));

        // Get active columns and assert the last one is the column we just added.
        $columns = $this->report->get_active_columns();
        $this->assertCount(3, $columns);
        $this->assertEquals($column->get('id'), end($columns)->get('id'));
    }

    /**
     * Test adding column from invalid key
     *
     * @return void
     */
    public function test_add_column_from_key_invalid() {
        try {
            columns_helper::add_column_from_key($this->report, 'invalid:key');
            $this->fail('Exception expected');
        } catch (moodle_exception $ex) {
            $this->assertEquals('invalidcolumn', $ex->errorcode);
            $this->assertEquals('invalid:key', $ex->debuginfo);
        }
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_plugin_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }
}
