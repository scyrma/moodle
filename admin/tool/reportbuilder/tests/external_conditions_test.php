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
 * External conditions tests.
 *
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \tool_reportbuilder\external\conditions;
use \tool_reportbuilder\test\mock_report;

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
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_external_conditions_testcase extends externallib_advanced_testcase {

    /** @var \tool_reportbuilder\report_base $report */
    protected $report;

    /**
     * Test setup
     */
    public function setUp() {
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
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws restricted_context_exception
     */
    public function test_reset_condition() {
        global $DB;

        $conditionid = $this->add_condition('user:firstname');
        conditions::reset_condition(0, $conditionid);

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

        $conditionid = $this->add_condition('user:firstname');
        conditions::delete_condition($conditionid);

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
     * @return int
     */
    protected function add_condition(string $conditionkey) : int {
        return $this->get_plugin_generator()->add_condition($this->report->get_id(), $conditionkey);
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