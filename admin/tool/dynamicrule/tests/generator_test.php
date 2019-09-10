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
 * Class generator_test
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class generator_test
 *
 * @package     tool_dynamicrule
 * @group       tool_dynamicrule
 * @covers      \tool_dynamicrule_generator
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $conditionclass = 'testable_condition_alwaystrue';
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

        $this->assertEquals('testable_condition_alwaystrue', get_class($condition1));
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

        $this->assertEquals('testable_condition_alwaysfalse', get_class($condition1));
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

        $outcomeclass = 'tool_dynamicrule\tool_dynamicrule\outcome\notification';
        $outcome1 = $this->get_generator()->create_outcome($outcomeclass, $rule0->id);
        $outcome2 = $this->get_generator()->create_outcome($outcomeclass, $rule0->id);
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_outcome'));

        $this->assertEquals($outcomeclass, get_class($outcome1));
        $this->assertEquals($rule0->id, $outcome1->get_ruleid());
        $this->assertEquals($rule0->id, $outcome2->get_ruleid());
    }
}
