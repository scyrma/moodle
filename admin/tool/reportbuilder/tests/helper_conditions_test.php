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
 * File containing tests for conditions helper class.
 *
 * @package   tool_reportbuilder
 * @category test
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_reportbuilder_helper_conditions_testcase
 *
 * @package   tool_reportbuilder
 * @covers    \tool_reportbuilder\local\helpers\conditions
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_helper_conditions_testcase extends advanced_testcase {

    /**
     * Test reset all method.
     *
     * @covers \tool_reportbuilder\local\helpers\conditions::reset_all
     */
    public function test_reset_all() {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->get_generator();

        $report = $generator->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $record = new stdClass();
        $record->id = $report->get_id();
        $record->conditions = 'dummycontent';

        $DB->update_record('tool_reportbuilder', $record);

        $conditionshelper = new \tool_reportbuilder\local\helpers\conditions($report);
        $conditionshelper->reset_all();

        $conditions = $DB->get_field('tool_reportbuilder', 'conditions', ['id' => $report->get_id()]);

        $this->assertEquals(null, $conditions);
    }

    /**
     * Test reset condition method.
     *
     * @covers \tool_reportbuilder\local\helpers\conditions::reset
     */
    public function test_reset() {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->get_generator();

        $report = $generator->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $record = new stdClass();
        $record->id = $report->get_id();
        $record->conditions = 'dummycontent';

        $DB->update_record('tool_reportbuilder', $record);

        $conditionshelper = new \tool_reportbuilder\local\helpers\conditions($report);
        $conditionshelper->reset_all();

        $conditions = $DB->get_field('tool_reportbuilder', 'conditions', ['id' => $report->get_id()]);

        $this->assertEquals(null, $conditions);

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