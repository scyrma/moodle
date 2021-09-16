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
 * File containing tests for conditions helper class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\report_base;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\test\mock_report;

/**
 * Class tool_reportbuilder_helper_conditions_testcase
 *
 * @package   tool_reportbuilder
 * @covers    \tool_reportbuilder\local\helpers\conditions
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_helper_conditions_testcase extends advanced_testcase {

    /** @var report_base $report */
    protected $report;

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void {
        global $DB;

        $this->resetAfterTest();

        $this->report = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);

        // Set some default report condition values.
        $conditions = [
            'user:firstname' => 'conditionvalue',
            'user:firstname_op' => 'conditionop',
        ];
        $DB->set_field(reportbuilder::TABLE, 'conditions', json_encode($conditions), ['id' => $this->report->get_id()]);
    }

    /**
     * Test adding condition from key
     *
     * @return void
     */
    public function test_add_condition_from_key() {
        $condition = conditions_helper::add_condition_from_key($this->report, 'user:firstname');
        $this->assertEquals($this->report->get_id(), $condition->get('reportid'));
        $this->assertEquals('user', $condition->get('entity'));
        $this->assertEquals('firstname', $condition->get('name'));

        // Get active conditions and assert it's the condition we just added.
        $conditions = conditions_helper::get_active_conditions($this->report->get_id());
        $this->assertCount(1, $conditions);
        $this->assertEquals($condition->get('id'), $conditions[0]->get('id'));

        // Try to add the same condition again, make sure we get the original back.
        $duplicate = conditions_helper::add_condition_from_key($this->report, 'user:firstname');
        $this->assertEquals($condition->get('id'), $duplicate->get('id'));
    }

    /**
     * Test adding condition from invalid key
     *
     * @return void
     */
    public function test_add_condition_from_key_invalid() {
        try {
            conditions_helper::add_condition_from_key($this->report, 'invalid:key');
            $this->fail('Exception expected');
        } catch (moodle_exception $ex) {
            $this->assertEquals('invalidcondition', $ex->errorcode);
            $this->assertEquals('invalid:key', $ex->debuginfo);
        }
    }

    /**
     * Test reset all method.
     *
     * @return void
     */
    public function test_reset_all() {
        global $DB;

        (new conditions_helper($this->report))->reset_all();

        $conditions = $DB->get_field(reportbuilder::TABLE, 'conditions', ['id' => $this->report->get_id()]);
        $this->assertNull($conditions);
    }

    /**
     * Test reset condition method.
     *
     * @return void
     */
    public function test_reset() {
        global $DB;

        $condition = $this->get_plugin_generator()->add_condition($this->report, 'user:firstname');
        (new conditions_helper($this->report))->reset($condition->get('id'));

        $conditions = $DB->get_field(reportbuilder::TABLE, 'conditions', ['id' => $this->report->get_id()]);
        $this->assertEquals([], json_decode($conditions));
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
