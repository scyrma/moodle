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
 * File contains the unit tests for tool_dynamicrule external API.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->libdir . '/externallib.php');

/**
 * Tests for tool_dynamicrule external API.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\external
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_external_testcase extends externallib_advanced_testcase {

    /**
     * Set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Test delete condition
     */
    public function test_delete_condition() {
        $rule0 = $this->get_generator()->create_rule();

        // There are no conditions of any type.
        $this->assertEmpty(\tool_dynamicrule\api::get_rule_conditions($rule0->id));

        $condition0 = $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_conditions($rule0->id));
        $conditionid = $condition0->get_id();

        // Delete condition0.
        $this->setAdminUser();
        \tool_dynamicrule\external::delete_condition($conditionid);

        // Validate.
        $this->assertCount(0, \tool_dynamicrule\api::get_rule_conditions($rule0->id));

        // Try deleting it again (not existing condition).
        $this->expectException(\moodle_exception::class);
        \tool_dynamicrule\external::delete_condition($conditionid);
    }

    /**
     * Test delete dummy condition
     */
    public function test_delete_condition_dummy() {
        $rule0 = $this->get_generator()->create_rule();

        // There are no conditions of any type.
        $this->assertEmpty(\tool_dynamicrule\api::get_rule_conditions($rule0->id));

        $condition0 = $this->get_generator()->create_condition_alwaystrue($rule0->id);

        // Validate.
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_conditions($rule0->id));

        // Make condition0 record referring to non-existing class.
        $persistent = new \tool_dynamicrule\condition($condition0->get_id());
        $persistent->set('classname', $persistent->get('classname') . 'missing');
        $persistent->save();

        // Expect dummy condition.
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_conditions($rule0->id));
        $condition0 = \tool_dynamicrule\api::get_rule_conditions($rule0->id)[0];
        $this->assertTrue($condition0->is_dummy());

        // Delete condition0.
        $this->setAdminUser();
        \tool_dynamicrule\external::delete_condition($condition0->get_id());

        // Validate.
        $this->assertCount(0, \tool_dynamicrule\api::get_rule_conditions($rule0->id));
    }

    /**
     * Test delete outcome
     */
    public function test_delete_outcome() {
        $rule0 = $this->get_generator()->create_rule();

        // There are no outcomes of any type.
        $this->assertEmpty(\tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        $outcome0 = $this->get_generator()->create_outcome_donothing($rule0->id);
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));
        $outcomeid = $outcome0->get_id();

        // Delete outcome0.
        $this->setAdminUser();
        \tool_dynamicrule\external::delete_outcome($outcomeid);

        // Validate.
        $this->assertCount(0, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        // Try deleting it again (not existing outcome).
        $this->expectException(\moodle_exception::class);
        \tool_dynamicrule\external::delete_outcome($outcomeid);
    }

    /**
     * Test delete dummy outcome
     */
    public function test_delete_outcome_dummy() {
        $rule0 = $this->get_generator()->create_rule();

        // There are no outcomes of any type.
        $this->assertEmpty(\tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        $outcome0 = $this->get_generator()->create_outcome_donothing($rule0->id);

        // Validate.
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        // Make outcome0 record referring to non-existing class.
        $persistent = new \tool_dynamicrule\outcome($outcome0->get_id());
        $persistent->set('classname', $persistent->get('classname') . 'missing');
        $persistent->save();

        // Expect dummy outcome.
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));
        $outcome0 = \tool_dynamicrule\api::get_rule_outcomes($rule0->id)[0];
        $this->assertTrue($outcome0->is_dummy());

        // Delete outcome0.
        $this->setAdminUser();
        \tool_dynamicrule\external::delete_outcome($outcome0->get_id());

        // Validate.
        $this->assertCount(0, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));
    }
}
