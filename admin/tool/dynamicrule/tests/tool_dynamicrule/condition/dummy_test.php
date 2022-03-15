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
 * File contains the unit tests for condition dummy class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule_generator;

/**
 * Unit tests for condition dummy class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\dummy
 * @covers     \tool_dynamicrule\condition_base
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class dummy_test extends \advanced_testcase {

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
        $this->missing_condition_setup();
    }

    /**
     * Set up rule with missing condition
     */
    public function missing_condition_setup() {
        $rule0 = $this->get_generator()->create_rule();
        $condition = $this->get_generator()->create_condition_alwaystrue($rule0->id);

        // Make condition record referring to non-existing class.
        $persistent = new \tool_dynamicrule\condition($condition->get_id());
        $persistent->set('classname', $persistent->get('classname') . 'missing');
        $persistent->save();

        $this->condition = \tool_dynamicrule\api::get_rule_conditions($rule0->id)[0];
    }

    /**
     * Test is_dummy
     */
    public function test_is_dummy() {
        // This expected to be dummy condition.
        $this->assertTrue($this->condition->is_dummy());
    }

    /**
     * Test get_broken_description
     */
    public function test_get_broken_description() {
        $this->assertNotEmpty($this->condition->get_broken_description());
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $this->assertNotEmpty($this->condition->get_title());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        $this->assertFalse($this->condition->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        $this->assertTrue($this->condition->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $this->assertFalse($this->condition->user_can_edit($this->condition->get_configdata()));
    }
}
