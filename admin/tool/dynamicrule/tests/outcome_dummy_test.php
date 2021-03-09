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
 * File contains the unit tests for outcome dummy class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for outcome dummy class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\outcome\dummy
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_outcome_dummy_testcase extends advanced_testcase {

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->missing_outcome_setup();
    }

    /**
     * Set up rule with missing outcome
     */
    public function missing_outcome_setup() {
        $rule0 = $this->get_generator()->create_rule();
        $outcome = $this->get_generator()->create_outcome_donothing($rule0->id);

        // Make outcome record referring to non-existing class.
        $persistent = new \tool_dynamicrule\outcome($outcome->get_id());
        $persistent->set('classname', $persistent->get('classname') . 'missing');
        $persistent->save();

        $this->outcome = \tool_dynamicrule\api::get_rule_outcomes($rule0->id)[0];
    }

    /**
     * Test is_dummy
     */
    public function test_is_dummy() {
        // This expected to be dummy outcome.
        $this->assertTrue($this->outcome->is_dummy());
    }

    /**
     * Test get_broken_description
     */
    public function test_get_broken_description() {
        $this->assertNotEmpty($this->outcome->get_broken_description());
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $this->assertNotEmpty($this->outcome->get_title());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        $this->assertFalse($this->outcome->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        $this->assertTrue($this->outcome->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $this->assertFalse($this->outcome->user_can_edit($this->outcome->get_configdata()));
    }
}
