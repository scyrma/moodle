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
 * @category  test
 * @copyright  2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Class tool_reportbuilder_external_conditions_testcase
 *
 * @package   tool_reportbuilder
 * @covers    \tool_reportbuilder\external\conditions
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_external_conditions_testcase extends externallib_advanced_testcase {

    /**
     * Test reset all method.
     *
     * @covers \tool_reportbuilder\external\conditions::reset_all
     * @uses \tool_reportbuilder\external\conditions::reset_all_parameters()
     */
    public function test_reset_all() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->get_generator();

        $report = $generator->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $record = new stdClass();
        $record->id = $report->get_id();
        $record->conditions = 'dummycontent';

        $DB->update_record('tool_reportbuilder', $record);
        \tool_reportbuilder\external\conditions::reset_all($report->get_id());

        $conditions = $DB->get_field('tool_reportbuilder', 'conditions', ['id' => $report->get_id()]);

        $this->assertEquals(null, $conditions);
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
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->get_generator();

        $report = $generator->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $conditionsdata = [];
        $conditionsdata['condition1'] = 'conditionvalue';
        $conditionsdata['user:firstname'] = 'conditionvalue';
        $conditionsdata['user:firstname_op'] = 'conditionop';
        $conditionsdata['condition1_op'] = 'conditionop';

        $conditionid = $generator->add_condition($report->get_id(), 'user:firstname');
        $record = new stdClass();
        $record->id = $report->get_id();
        $record->conditions = json_encode($conditionsdata);
        $DB->update_record('tool_reportbuilder', $record);

        \tool_reportbuilder\external\conditions::reset_condition($report->get_id(), $conditionid);

        $conditions = $DB->get_field('tool_reportbuilder', 'conditions', ['id' => $report->get_id()]);
        $this->assertEquals('{"condition1":"conditionvalue","condition1_op":"conditionop"}', $conditions);
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_generator(): tool_reportbuilder_generator {
        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
        return $generator;
    }
}