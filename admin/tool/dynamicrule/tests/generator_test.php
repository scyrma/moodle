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
 * Class generator_test
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy <marina@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class generator_test
 *
 * @package     tool_dynamicrule
 * @group       tool_dynamicrule
 * @covers      \tool_dynamicrule_generator
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_generator_testcase extends advanced_testcase {

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Create rule
     *
     * @covers \tool_dynamicrule\api::create_rule
     */
    public function test_create_rule() {
        global $DB;
        $this->resetAfterTest();
        // There are no rules in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule'));

        // Generate a rule for a default tenant.
        $rule0 = $this->get_generator()->create_rule();
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule'));

        $this->assertEquals(\tool_tenant\tenancy::get_default_tenant_id(),
            $DB->get_field('tool_dynamicrule', 'tenantid', ['id' => $rule0->id]));

        // Now create a new tenant and generate a rule for this tenant.
        $tenant1 = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();
        $rule1 = $this->get_generator()->create_rule([
            'tenantid' => $tenant1->id
        ]);

        $this->assertEquals($tenant1->id,
            $DB->get_field('tool_dynamicrule', 'tenantid', ['id' => $rule1->id]));
    }

    /**
     * Create condition
     *
     * @covers \tool_dynamicrule\api::create_rule_condition
     */
    public function test_create_condition() {
        global $DB;
        $this->resetAfterTest();
        // There are no rules in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_condition'));

        // Generate a rule for a default tenant.
        $rule0 = $this->get_generator()->create_rule();

        require_once(__DIR__.'/fixtures/testable_condition_alwaystrue.php');
        $conditionclass = 'tool_dynamicrule\tool_dynamicrule\condition\testable_condition_alwaystrue';
        $condition1 = $this->get_generator()->create_condition($conditionclass, $rule0->id);
        $condition2 = $this->get_generator()->create_condition($conditionclass, $rule0->id);
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_condition'));

        $this->assertEquals($conditionclass, get_class($condition1));
        $this->assertEquals($rule0->id, $condition1->get_ruleid());
        $this->assertEquals($rule0->id, $condition2->get_ruleid());
    }

    /**
     * Create testable condition alwaystrue
     *
     * @covers \tool_dynamicrule\api::create_rule_condition
     */
    public function test_create_condition_alwaystrue() {
        global $DB;
        $this->resetAfterTest();
        // There are no rules in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_condition'));

        $rule0 = $this->get_generator()->create_rule();
        $condition1 = $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_condition'));

        $conditionclass = 'tool_dynamicrule\tool_dynamicrule\condition\testable_condition_alwaystrue';
        $this->assertEquals($conditionclass, get_class($condition1));
    }

    /**
     * Create testable condition alwaysfalse
     *
     * @covers \tool_dynamicrule\api::create_rule_condition
     */
    public function test_create_condition_alwaysfalse() {
        global $DB;
        $this->resetAfterTest();
        // There are no rules in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_condition'));

        $rule0 = $this->get_generator()->create_rule();
        $condition1 = $this->get_generator()->create_condition_alwaysfalse($rule0->id);
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_condition'));

        $conditionclass = 'tool_dynamicrule\tool_dynamicrule\condition\testable_condition_alwaysfalse';
        $this->assertEquals($conditionclass, get_class($condition1));
    }

    /**
     * Create outcome
     *
     * @covers \tool_dynamicrule\api::create_rule_outcome
     */
    public function test_create_outcome() {
        global $DB;
        $this->resetAfterTest();
        // There are no rules in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_outcome'));

        // Generate a rule for a default tenant.
        $rule0 = $this->get_generator()->create_rule();

        require_once(__DIR__.'/fixtures/testable_outcome_donothing.php');
        $outcomeclass = 'tool_dynamicrule\tool_dynamicrule\outcome\testable_outcome_donothing';
        $outcome1 = $this->get_generator()->create_outcome($outcomeclass, $rule0->id);
        $outcome2 = $this->get_generator()->create_outcome($outcomeclass, $rule0->id);
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_outcome'));

        $this->assertEquals($outcomeclass, get_class($outcome1));
        $this->assertEquals($rule0->id, $outcome1->get_ruleid());
        $this->assertEquals($rule0->id, $outcome2->get_ruleid());
    }

    /**
     * Create testable outcome donothing
     *
     * @covers \tool_dynamicrule\api::create_rule_outcome
     */
    public function test_create_outcome_donothing() {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        // There are no rules in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_outcome'));

        $rule0 = $this->get_generator()->create_rule();
        $outcome1 = $this->get_generator()->create_outcome_donothing($rule0->id);
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_outcome'));

        $outcomeclass = 'tool_dynamicrule\tool_dynamicrule\outcome\testable_outcome_donothing';
        $this->assertEquals($outcomeclass, get_class($outcome1));

        // Should apply with no errors.
        $outcome1->apply_to_user($user);
    }

    /**
     * Create testable outcome donothing with processing exception.
     *
     * @covers \tool_dynamicrule\api::create_rule_outcome
     */
    public function test_create_outcome_donothing_exception() {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $rule0 = $this->get_generator()->create_rule();
        // Configure outcome to produce an error.
        $outcome1 = $this->get_generator()->create_outcome_donothing($rule0->id, true);

        $this->expectExceptionMessage("Do nothing cant be applied to busy user id {$user->id}");
        $outcome1->apply_to_user($user);
    }

    /**
     * Test get_matching_users_for_outcomes.
     */
    public function test_get_matching_users_for_outcomes() {
        $this->resetAfterTest();
        $rule0 = $this->get_generator()->create_rule();

        // No conditions.
        $this->assertCount(0, $this->get_generator()->get_matching_users_for_outcomes($rule0->id));

        // Condition true.
        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->get_generator()->create_outcome_donothing($rule0->id);
        $this->assertCount(1, $this->get_generator()->get_matching_users_for_outcomes($rule0->id));

        // Condition false.
        $rule1 = $this->get_generator()->create_rule();
        $this->get_generator()->create_condition_alwaysfalse($rule1->id);
        $this->get_generator()->create_outcome_donothing($rule1->id);
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($rule1->id));
    }

}
