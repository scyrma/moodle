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
 * File containing tests for helper filter class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\report_base;
use tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_reportbuilder\test\mock_report;

/**
 * Class tool_reportbuilder_helper_filters_testcase
 *
 * @package   tool_reportbuilder
 * @covers    \tool_reportbuilder\local\helpers\filters
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_helper_filters_testcase extends advanced_testcase {

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
     * Test adding filter from key
     *
     * @return void
     */
    public function test_add_filter_from_key() {
        $filter = filters_helper::add_filter_from_key($this->report, 'user:firstname');
        $this->assertEquals($this->report->get_id(), $filter->get('reportid'));
        $this->assertEquals('user', $filter->get('entity'));
        $this->assertEquals('firstname', $filter->get('name'));

        // Get active filters and assert it's the filter we just added.
        $filters = filters_helper::get_active_filters($this->report->get_id());
        $this->assertCount(1, $filters);
        $this->assertEquals($filter->get('id'), $filters[0]->get('id'));

        // Try to add the same filter again, make sure we get the original back.
        $duplicate = filters_helper::add_filter_from_key($this->report, 'user:firstname');
        $this->assertEquals($filter->get('id'), $duplicate->get('id'));
    }

    /**
     * Test adding filter from invalid key
     *
     * @return void
     */
    public function test_add_filter_from_key_invalid() {
        try {
            filters_helper::add_filter_from_key($this->report, 'invalid:key');
            $this->fail('Exception expected');
        } catch (moodle_exception $ex) {
            $this->assertEquals('invalidfilter', $ex->errorcode);
            $this->assertEquals('invalid:key', $ex->debuginfo);
        }
    }

    /**
     * Test get_filters method.
     *
     * @return void
     */
    public function test_get_filters() {
        $value = ['key' => 'value'];
        filters_helper::set_filter($this->report->get_id(), (object) $value);

        $result = (new filters_helper($this->report->get_id()))->get_report_filters();
        $this->assertEquals($value, $result);
    }

    /**
     * Test get_filers method without value.
     *
     * @return void
     */
    public function test_get_filters_without_value() {
        filters_helper::set_filter($this->report->get_id(), null);

        $result = (new filters_helper($this->report->get_id()))->get_report_filters();
        $this->assertEquals([], $result);
    }

    /**
     * Test getting filter values greater than 1333 characters (user preference limit)
     */
    public function test_get_filters_large_value(): void {
        $value = ['longvalue' => str_repeat('A', 2000)];
        filters_helper::set_filter($this->report->get_id(), (object) $value);

        $result = (new filters_helper($this->report->get_id()))->get_report_filters();
        $this->assertEquals($value, $result);
    }

    /**
     * Test getting filter values that have been corrupted (invalid JSON)
     */
    public function test_get_filters_corrupted_value(): void {
        $invalidjson = '{"user:fullname_op":"0","user:fullname":"J';
        set_user_preference('filters_report_' . $this->report->get_id(), $invalidjson);

        $result = (new filters_helper($this->report->get_id()))->get_report_filters();
        $this->assertEquals([], $result);
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
