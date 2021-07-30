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
 * External conditions tests.
 *
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\external\conditions;
use tool_reportbuilder\test\mock_report;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\local\models\reportbuilder_conditions;

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Class tool_reportbuilder_external_conditions_testcase
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @category  test
 * @covers    \tool_reportbuilder\external\conditions
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_external_conditions_testcase extends externallib_advanced_testcase {

    /** @var \tool_reportbuilder\report_base $report */
    protected $report;

    /**
     * Test setup
     */
    public function setUp(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->report = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);

        // Set some default report conditions.
        $conditions = [
            'condition1' => 'conditionvalue',
            'condition1_op' => 'conditionop',
            'user:firstname' => 'conditionvalue',
            'user:firstname_op' => 'conditionop',
        ];
        $DB->set_field('tool_reportbuilder', 'conditions', json_encode($conditions), ['id' => $this->report->get_id()]);
    }

    /**
     * Test add_report_condition
     *
     * @return void
     */
    public function test_add_report_condition() {
        $conditions = conditions_helper::get_active_conditions($this->report->get_id());
        $this->assertEmpty($conditions);

        conditions::add_report_condition($this->report->get_id(), 'user:lastname');

        $conditions = conditions_helper::get_active_conditions($this->report->get_id());
        $this->assertCount(1, $conditions);

        $this->assertEquals($this->report->get_id(), $conditions[0]->get('reportid'));
        $this->assertEquals('user', $conditions[0]->get('entity'));
        $this->assertEquals('lastname', $conditions[0]->get('name'));
    }

    /**
     * Test reset all method.
     *
     * @covers \tool_reportbuilder\external\conditions::reset_all
     * @uses \tool_reportbuilder\external\conditions::reset_all_parameters()
     */
    public function test_reset_all() {
        global $DB;

        $reportid = $this->report->get_id();

        $this->assertNotNull($DB->get_field('tool_reportbuilder', 'conditions', ['id' => $reportid]));

        // Resetting the report conditions should set 'conditions' to null.
        conditions::reset_all($reportid);
        $this->assertNull($DB->get_field('tool_reportbuilder', 'conditions', ['id' => $reportid]));
    }

    /**
     * Test reset condition method.
     *
     * @covers \tool_reportbuilder\external\conditions::reset_condition
     * @uses \tool_reportbuilder\external\conditions::reset_condition_parameters
     */
    public function test_reset_condition() {
        global $DB;

        $condition = $this->add_condition('user:firstname');
        conditions::reset_condition(0, $condition->get('id'));

        // Resetting a report condition should remove the values from the condition.
        $expected = json_encode([
            'condition1' => 'conditionvalue',
            'condition1_op' => 'conditionop',
        ]);
        $this->assertEquals($expected, $DB->get_field('tool_reportbuilder', 'conditions', ['id' => $this->report->get_id()]));
    }

    /**
     * Test deletion of a condition
     *
     * @return void
     */
    public function test_delete_condition() {
        global $DB;

        $condition = $this->add_condition('user:firstname');
        conditions::delete_condition($condition->get('id'));

        // Deleting a report condition should remove the values from the condition.
        $expected = json_encode([
            'condition1' => 'conditionvalue',
            'condition1_op' => 'conditionop',
        ]);
        $this->assertEquals($expected, $DB->get_field('tool_reportbuilder', 'conditions', ['id' => $this->report->get_id()]));
    }

    /**
     * Add a condition to the test report
     *
     * @param string $conditionkey
     * @return reportbuilder_conditions
     */
    protected function add_condition(string $conditionkey) : reportbuilder_conditions {
        return $this->get_plugin_generator()->add_condition($this->report, $conditionkey);
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_plugin_generator() : tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }
}
