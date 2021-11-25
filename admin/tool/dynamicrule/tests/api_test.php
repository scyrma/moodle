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

namespace tool_dynamicrule;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;

/**
 * Unit tests for api class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\api
 * @covers     \tool_dynamicrule\rule
 * @covers     \tool_dynamicrule\condition
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\outcome
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_api_testcase extends \advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): \tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Test get_all_instances_of_type with invalid type
     */
    public function test_get_all_instances_of_type_invalid_type() {

        $reflector = new \ReflectionClass('\tool_dynamicrule\api');
        $method = $reflector->getMethod('get_all_instances_of_type');
        $method->setAccessible(true);
        $this->expectException(\coding_exception::class);
        $instances = $method->invokeArgs(null, ['invalidtype']);
    }

    /**
     * Test create rule
     */
    public function test_create_rule(): void {
        global $DB;
        $data = (object) [
            'name' => 'Rule',
        ];
        $rule = api::create_rule($data);

        // Test rule has been created.
        $this->assertNotEmpty($rule->get('id'));

        // Test program has been created.
        $record = $DB->get_record(rule::TABLE, ['id' => $rule->get('id')]);
        $this->assertNotFalse($record);
        $this->assertEquals(tenancy::get_tenant_id(), $record->tenantid);
        $this->assertEquals($data->name, $record->name);
        $this->assertEquals(0, $record->shared);
        $this->assertEquals(0, $record->archived);
        $this->assertEquals(0, $record->enabled);
    }

    /**
     * Rule created in the tenant is always not shared, in the shared space is always shared
     */
    public function test_create_rule_shared(): void {
        $tenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();

        // Rule created in a tenant is always not shared.
        $data = ['tenantid' => $tenant->id, 'shared' => 1, 'name' => 'Rule 1'];
        $rule1 = api::create_rule((object)$data);
        $this->assertEquals(0, $rule1->get('shared'));

        // Rule created in a shared space is always shared.
        $data = ['tenantid' => $sharedspaceid, 'shared' => 0, 'name' => 'Rule 2'];
        $rule2 = api::create_rule((object)$data);
        $this->assertEquals(1, $rule2->get('shared'));

        // Shared rule duplicated into a tenant is not shared.
        self::setAdminUser();
        tenancy::set_switched_tenant_id($tenant->id);
        $newruleid = api::duplicate_rule($rule2->get('id'));
        $newrule = api::get_rule($newruleid);
        $this->assertEquals((string)$tenant->id, $newrule->get('tenantid'));
        $this->assertEquals(0, $newrule->get('shared'));
    }

    /**
     * Test get_rule
     */
    public function test_get_rule() {
        // Generate a rule.
        $rule0 = $this->get_generator()->create_rule();
        $rule = \tool_dynamicrule\api::get_rule($rule0->id);

        // Testing existing rule.
        $this->assertInstanceOf('\tool_dynamicrule\rule', $rule);
        $this->assertEquals($rule0->id, $rule->get('id'));

        // Testing non-existing rule.
        $this->expectException(\moodle_exception::class);
        $rule = \tool_dynamicrule\api::get_rule(1024);
    }

    /**
     * Test get_rule with different tenant
     */
    public function test_get_rule_wrong_tenant() {
        // Testing rule with different tenant.
        $othertenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();
        $rule1 = $this->get_generator()->create_rule(['tenantid' => $othertenant->id]);

        // Testing rule getter using different tenant.
        $this->expectException(\moodle_exception::class);
        $rule = \tool_dynamicrule\api::get_rule($rule1->id);
    }

    /**
     * Test get_rule_conditions
     */
    public function test_get_rule_conditions() {
        $rule0 = $this->get_generator()->create_rule();

        // There are no conditions of any type on a recently created rule.
        $this->assertEmpty(\tool_dynamicrule\api::get_rule_conditions($rule0->id));

        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_conditions($rule0->id));

        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->assertCount(2, \tool_dynamicrule\api::get_rule_conditions($rule0->id));

        $conditions = \tool_dynamicrule\api::get_rule_conditions($rule0->id);
        $class = 'tool_dynamicrule\tool_dynamicrule\condition\testable_condition_alwaystrue';
        foreach ($conditions as $condition) {
            $this->assertInstanceOf($class, $condition);
        }
    }

    /**
     * Test get_rule_conditions_dummy
     */
    public function test_get_rule_conditions_dummy() {
        $rule0 = $this->get_generator()->create_rule();

        // There are no conditions of any type on a recently created rule.
        $this->assertEmpty(\tool_dynamicrule\api::get_rule_conditions($rule0->id));

        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_conditions($rule0->id));

        $condition = \tool_dynamicrule\api::get_rule_conditions($rule0->id)[0];

        // Sanity check.
        $this->assertFalse($condition->is_dummy());

        // Make condition record referring to non-existing class.
        $persistent = new \tool_dynamicrule\condition($condition->get_id());
        $persistent->set('classname', $persistent->get('classname') . 'missing');
        $persistent->save();

        // Expect dummy condition.
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_conditions($rule0->id));
        $condition = \tool_dynamicrule\api::get_rule_conditions($rule0->id)[0];
        $this->assertTrue($condition->is_dummy());
    }

    /**
     * Test get_rule_outcomes
     */
    public function test_get_rule_outcomes() {
        $rule0 = $this->get_generator()->create_rule();

        // There are no outcomes on a recently created rule.
        $this->assertEmpty(\tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        $this->get_generator()->create_outcome_donothing($rule0->id);

        $this->assertCount(1, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        $this->get_generator()->create_outcome_donothing($rule0->id);

        $this->assertCount(2, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        $outcomes = \tool_dynamicrule\api::get_rule_outcomes($rule0->id);
        $class = 'tool_dynamicrule\tool_dynamicrule\outcome\testable_outcome_donothing';
        foreach ($outcomes as $outcome) {
            $this->assertInstanceOf($class, $outcome);
        }
    }

    /**
     * Test get_rule_outcomes_dummy
     */
    public function test_get_rule_outcomes_dummy() {
        $rule0 = $this->get_generator()->create_rule();

        // There are no outcomes of any type on a recently created rule.
        $this->assertEmpty(\tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        $this->get_generator()->create_outcome_donothing($rule0->id);
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        $outcome = \tool_dynamicrule\api::get_rule_outcomes($rule0->id)[0];

        // Sanity check.
        $this->assertFalse($outcome->is_dummy());

        // Make outcome record referring to non-existing class.
        $persistent = new \tool_dynamicrule\outcome($outcome->get_id());
        $persistent->set('classname', $persistent->get('classname') . 'missing');
        $persistent->save();

        // Expect dummy outcome.
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));
        $outcome = \tool_dynamicrule\api::get_rule_outcomes($rule0->id)[0];
        $this->assertTrue($outcome->is_dummy());
    }

    /**
     * Create rule for component.
     */
    public function test_create_rule_for_component() {
        global $DB;
        $this->resetAfterTest();
        // There are no rules in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule'));

        // Generate a rule for tool_program.
        $othertenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();
        $ruleid0 = \tool_dynamicrule\api::create_rule_for_component('tool_program', 'program', 0, $othertenant->id);

        // Check created rule.
        $rule = \tool_dynamicrule\api::get_rule($ruleid0, true);
        $record = $rule->to_record();
        $this->assertEquals($othertenant->id, $record->tenantid);
        $this->assertSame('tool_program', $record->component);
        $this->assertSame('program', $record->componentarea);
        $this->assertSame('0', $record->itemid);

        $this->assertFalse($rule->is_enabled());
        $this->assertFalse($rule->is_archived());
        $this->assertSame('tool_program_program_0', $rule->get_formatted_name());

        // Generate a rule for tool_program with optional params specified.
        $ruleid1 = \tool_dynamicrule\api::create_rule_for_component('tool_program',
            'program', 1, $othertenant->id, 'testname', true);
        $rule = \tool_dynamicrule\api::get_rule($ruleid1, true);
        $record = $rule->to_record();
        $this->assertEquals($othertenant->id, $record->tenantid);
        $this->assertSame('tool_program', $record->component);
        $this->assertSame('program', $record->componentarea);
        $this->assertSame('1', $record->itemid);

        $this->assertTrue($rule->is_enabled());
        $this->assertFalse($rule->is_archived());
        $this->assertSame('testname', $rule->get_formatted_name());
    }

    /**
     * Create condition for rule.
     */
    public function test_create_rule_condition() {
        global $DB;
        $this->resetAfterTest();
        // There are no conditions in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_condition'));

        // Generate a rule for a default tenant.
        $rule0 = $this->get_generator()->create_rule();

        // Create conditions.
        require_once(__DIR__.'/fixtures/testable_condition_alwaystrue.php');
        $conditionclass = 'tool_dynamicrule\tool_dynamicrule\condition\testable_condition_alwaystrue';
        $condition1 = \tool_dynamicrule\api::create_rule_condition($rule0->id, $conditionclass);
        $condition2 = \tool_dynamicrule\api::create_rule_condition($rule0->id, $conditionclass, []);
        $condition3 = \tool_dynamicrule\api::create_rule_condition($rule0->id, $conditionclass, [], true);
        $this->assertEquals(3, $DB->count_records('tool_dynamicrule_condition'));

        // Test conditions.
        $this->assertEquals($conditionclass, get_class($condition1));
        $this->assertEquals($rule0->id, $condition1->get_ruleid());
        $this->assertEmpty($condition1->get_configdata());

        $this->assertEquals($rule0->id, $condition2->get_ruleid());

        $this->assertEquals($conditionclass, get_class($condition3));
        $this->assertEquals($rule0->id, $condition3->get_ruleid());
        $this->assertEmpty($condition3->get_configdata());

        // Testing non-existing condition class.
        $this->expectException(\invalid_parameter_exception::class);
        $conditionclass = 'tool_dynamicrule\tool_dynamicrule\condition\fake_condition';
        $condition4 = \tool_dynamicrule\api::create_rule_condition($rule0->id, $conditionclass);
    }

    /**
     * Create outcome for the rule.
     */
    public function test_create_rule_outcome() {
        global $DB;
        $this->resetAfterTest();
        // There are no rules in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_outcome'));

        // Generate a rule for a default tenant.
        $rule0 = $this->get_generator()->create_rule();

        // Create outcomes.
        $outcomeclass = 'tool_dynamicrule\tool_dynamicrule\outcome\notification';
        $configform = ['subject' => 'The notification subject', 'body' => 'Here comes the message.'];
        $outcome1 = \tool_dynamicrule\api::create_rule_outcome($rule0->id, $outcomeclass, $configform);
        $outcome2 = \tool_dynamicrule\api::create_rule_outcome($rule0->id, $outcomeclass, $configform);
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_outcome'));

        // Test outcomes.
        $this->assertEquals($outcomeclass, get_class($outcome1));
        $this->assertEquals($rule0->id, $outcome1->get_ruleid());
        $this->assertArrayHasKey('subject', $outcome1->get_configdata());
        $this->assertArrayHasKey('body', $outcome1->get_configdata());

        $this->assertEquals($rule0->id, $outcome2->get_ruleid());
        $this->assertArrayHasKey('subject', $outcome2->get_configdata());
        $this->assertArrayHasKey('body', $outcome2->get_configdata());

        // Testing non-existing outcome class.
        $this->expectException(\invalid_parameter_exception::class);
        $outcomeclass = 'tool_dynamicrule\tool_dynamicrule\outcome\fake_outcome';
        $outcome3 = \tool_dynamicrule\api::create_rule_outcome($rule0->id, $outcomeclass);
    }

    /**
     * Test enable_rule.
     */
    public function test_enable_rule() {
        $rule0 = $this->get_generator()->create_rule();

        // Test new rule.
        $this->assertFalse(\tool_dynamicrule\api::enable_rule($rule0->id));
        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertFalse($rule->is_enabled());

        // Test rule with condition.
        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->assertFalse(\tool_dynamicrule\api::enable_rule($rule0->id));
        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertTrue($rule->has_conditions());
        $this->assertFalse($rule->is_enabled());

        // Test rule with condition and outcome (should be possible to enable).
        $subject = 'You matched!';
        $configdata = ['subject' => $subject,
            'body' => ['text' => 'Congratulations, you matched the condition', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);
        $this->assertTrue(\tool_dynamicrule\api::enable_rule($rule0->id));
        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertTrue($rule->has_outcomes());
        $this->assertTrue($rule->is_enabled());

        // Disable rule which we just enabled.
        \tool_dynamicrule\api::disable_rule($rule0->id);
        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertFalse($rule->is_enabled());

        // Test archived rule with condition and outcome.
        \tool_dynamicrule\api::archive_rule($rule0->id);
        $this->assertFalse(\tool_dynamicrule\api::enable_rule($rule0->id));
        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertTrue($rule->is_archived());
        $this->assertFalse($rule->is_enabled());

        // Restore it.
        \tool_dynamicrule\api::unarchive_rule($rule0->id);
        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertFalse($rule->is_archived());
    }

    /**
     * Test disable_rule.
     */
    public function test_disable_rule() {
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertTrue($rule->is_enabled());

        // Disable rule.
        \tool_dynamicrule\api::disable_rule($rule0->id);

        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertFalse($rule->is_enabled());
    }

    /**
     * Test delete_rule
     */
    public function test_delete_rule() {
        global $DB;

        $rule0 = $this->get_generator()->create_rule(['archived' => 1]);
        $rule1 = $this->get_generator()->create_rule(['archived' => 1]);

        // Add 2 conditions to rule0 and 1 condition to rule1.
        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->get_generator()->create_condition_alwaystrue($rule1->id);
        $rule0conditions = \tool_dynamicrule\api::get_rule_conditions($rule0->id);
        $this->assertCount(2, $rule0conditions);
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_conditions($rule1->id));

        // Add 2 outcomes to rule0 and 1 outcome to rule1.
        $outcomeclass = 'tool_dynamicrule\tool_dynamicrule\outcome\notification';
        $this->get_generator()->create_outcome($outcomeclass, $rule0->id);
        $this->get_generator()->create_outcome($outcomeclass, $rule0->id);
        $this->get_generator()->create_outcome($outcomeclass, $rule1->id);
        $rule0outcomes = \tool_dynamicrule\api::get_rule_outcomes($rule0->id);
        $this->assertCount(2, $rule0outcomes);
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_outcomes($rule1->id));

        // Delete rule0.
        \tool_dynamicrule\api::delete_rule($rule0->id);

        // Check records no longer there.
        foreach ($rule0conditions as $condition) {
            $this->assertFalse(\tool_dynamicrule\condition::record_exists($condition->get_id()));
        }
        foreach ($rule0outcomes as $outcome) {
            $this->assertFalse(\tool_dynamicrule\outcome::record_exists($outcome->get_id()));
        }

        $this->assertFalse($DB->record_exists('tool_dynamicrule_match', ['ruleid' => $rule0->id]));

        // Check that rule1 records are not affected.
        \tool_dynamicrule\api::get_rule($rule1->id);
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_conditions($rule1->id));
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_outcomes($rule1->id));

        // Finally, try to get deleted rule.
        $this->expectException(\moodle_exception::class);
        \tool_dynamicrule\api::get_rule($rule0->id);
    }

    /**
     * Test rule_delete for active rule
     */
    public function test_delete_rule_active() {
        $rule0 = $this->get_generator()->create_rule(['archived' => 0]);

        $this->expectException(\moodle_exception::class);
        \tool_dynamicrule\api::delete_rule($rule0->id);
    }

    /**
     * Test rule_delete for other tenant
     */
    public function test_delete_rule_other_tenant() {
        \tool_tenant\tenancy::get_default_tenant_id();
        $othertenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();

        $rule0 = $this->get_generator()->create_rule(['archived' => 1, 'tenantid' => $othertenant->id]);
        $this->expectException(\moodle_exception::class);
        \tool_dynamicrule\api::delete_rule($rule0->id);
    }

    /**
     * Test rule_archive
     */
    public function test_archive_rule() {
        global $DB;
        \tool_tenant\tenancy::get_default_tenant_id();
        $othertenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();

        $rule0 = $this->get_generator()->create_rule();
        $rule1 = $this->get_generator()->create_rule();

        $this->assertCount(2, $DB->get_records('tool_dynamicrule', ['archived' => 0]));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule', ['archived' => 1]));

        // Archive rule0.
        $this->assertTrue(\tool_dynamicrule\api::archive_rule($rule0->id));
        $this->assertCount(1, $DB->get_records('tool_dynamicrule', ['archived' => 0]));
        $this->assertCount(1, $DB->get_records('tool_dynamicrule', ['archived' => 1]));

        // Archive rule1.
        $this->assertTrue(\tool_dynamicrule\api::archive_rule($rule1->id));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule', ['archived' => 0]));
        $this->assertCount(2, $DB->get_records('tool_dynamicrule', ['archived' => 1]));

        // Try to archive rule2.
        $rule2 = $this->get_generator()->create_rule(['tenantid' => $othertenant->id]);
        $this->expectException(\moodle_exception::class);
        \tool_dynamicrule\api::archive_rule($rule2->id);
    }

    /**
     * Test unarchive_rule
     */
    public function test_unarchive_rule() {
        global $DB;
        \tool_tenant\tenancy::get_default_tenant_id();
        $othertenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();

        $rule0 = $this->get_generator()->create_rule(['archived' => 1]);
        $rule1 = $this->get_generator()->create_rule(['archived' => 1]);

        $this->assertCount(2, $DB->get_records('tool_dynamicrule', ['archived' => 1]));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule', ['archived' => 0]));

        // Unarchive rule0.
        $this->assertTrue(\tool_dynamicrule\api::unarchive_rule($rule0->id));
        $this->assertCount(1, $DB->get_records('tool_dynamicrule', ['archived' => 0]));
        $this->assertCount(1, $DB->get_records('tool_dynamicrule', ['archived' => 1]));

        // Unarchive rule1.
        $this->assertTrue(\tool_dynamicrule\api::unarchive_rule($rule1->id));
        $this->assertCount(2, $DB->get_records('tool_dynamicrule', ['archived' => 0]));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule', ['archived' => 1]));

        // Try to unarchive rule2.
        $rule2 = $this->get_generator()->create_rule(['tenantid' => $othertenant->id]);
        $this->expectException(\moodle_exception::class);
        \tool_dynamicrule\api::unarchive_rule($rule2->id);
    }

    /**
     * Test is_rule_configuration_valid
     *
     * @uses \tool_tenant\manager::archive_tenant
     * @uses \tool_tenant\manager::restore_tenant
     */
    public function test_is_rule_configuration_valid() {
        global $DB;

        $course0 = $this->getDataGenerator()->create_course();
        $tenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();
        $tenantuser = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($tenantuser->id, $tenant->id);

        // Create default tenant rule with Course0 not enrolled conditon and Course0 enrol outcome.
        $rulerecord = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['courseid' => $course0->id, 'enrol' => 'manual'];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled::create($rulerecord->id, $configdata);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course0->id, 'role' => $roleid];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rulerecord->id, $configdata);
        $rule = new \tool_dynamicrule\rule($rulerecord->id);

        // Use tenant user (required for update_configdata callback function checks.
        self::setUser($tenantuser->id);

        // Sanity check.
        $this->assertTrue(\tool_dynamicrule\api::is_rule_configuration_valid($rule));

        // Break condition.
        $condition->update_configdata(['courseid' => 1000, 'enrol' => 'manual'], true);
        $this->assertFalse(\tool_dynamicrule\api::is_rule_configuration_valid($rule));

        // Fix condition.
        $condition->update_configdata(['courseid' => $course0->id, 'enrol' => 'manual'], true);
        $this->assertTrue(\tool_dynamicrule\api::is_rule_configuration_valid($rule));

        // Empty condition configuration.
        $condition->update_configdata([], true);
        $this->assertFalse(\tool_dynamicrule\api::is_rule_configuration_valid($rule));

        // Fix condition.
        $condition->update_configdata(['courseid' => $course0->id, 'enrol' => 'manual'], true);
        $this->assertTrue(\tool_dynamicrule\api::is_rule_configuration_valid($rule));

        // Break outcome.
        $outcome->update_configdata(['coursetoenrol' => 1000, 'role' => $roleid], true);
        $this->assertFalse(\tool_dynamicrule\api::is_rule_configuration_valid($rule));

        // Fix outcome.
        $outcome->update_configdata(['coursetoenrol' => $course0->id, 'role' => $roleid], true);
        $this->assertTrue(\tool_dynamicrule\api::is_rule_configuration_valid($rule));

        // Empty outcome configuration.
        $outcome->update_configdata([], true);
        $this->assertFalse(\tool_dynamicrule\api::is_rule_configuration_valid($rule));

        // Fix outcome.
        $outcome->update_configdata(['coursetoenrol' => $course0->id, 'role' => $roleid], true);
        $this->assertTrue(\tool_dynamicrule\api::is_rule_configuration_valid($rule));

        // Archive tenant.
        self::setAdminUser();
        $manager = new \tool_tenant\manager();
        $manager->archive_tenant($tenant->id);
        $this->assertFalse(\tool_dynamicrule\api::is_rule_configuration_valid($rule));

        // Restore tenant.
        $manager->restore_tenant($tenant->id);
        $this->assertTrue(\tool_dynamicrule\api::is_rule_configuration_valid($rule));
    }

    /**
     * Test \tool_dynamicrule\matching_users_report
     *
     * @covers \tool_dynamicrule\matching_users_report
     */
    public function test_matching_users_report() {
        global $DB, $PAGE, $CFG;

        $CFG->showuseridentity = 'username,email';
        $rule0 = $this->get_generator()->create_rule();

        $reportclass = \tool_dynamicrule\matching_users_report::class;
        $params = ['ruleid' => $rule0->id];
        $report = \tool_reportbuilder\system_report_factory::create($reportclass, $params);
        $this->assertEquals($reportclass, get_class($report));

        $reportid = $report->get_id();
        $dbcolumns = $DB->get_records('tool_reportbuilder_column', ['reportid' => $reportid]);
        $this->assertEquals(3, count($dbcolumns));

        // If we initiate the same report again the id will be the same.
        $report2 = \tool_reportbuilder\system_report_factory::create($reportclass, $params);
        $this->assertEquals($report->get_id(), $report2->get_id());

        // Try to render the report only to make sure that there are no debugging and other errors.
        // The contents of the report is checked in behat test.
        $PAGE->set_url('/');
        (new \tool_reportbuilder\output\system_report($report))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Test is_matching_users_count_needed
     */
    public function test_is_matching_users_count_needed() {
        $rule0 = $this->get_generator()->create_rule();

        // Empty rule.
        $this->assertFalse(\tool_dynamicrule\api::is_matching_users_count_needed($rule0->id));

        // Add valid condition.
        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->assertTrue(\tool_dynamicrule\api::is_matching_users_count_needed($rule0->id));

        // Add broken condition.
        $cond1 = $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $cond1->mark_as_broken();

        $this->assertFalse(\tool_dynamicrule\api::is_matching_users_count_needed($rule0->id));
    }

    /**
     * Test count_matching_users
     */
    public function test_count_matching_users() {
        $rule0 = $this->get_generator()->create_rule();
        // Two users.
        self::getDataGenerator()->create_user();
        self::getDataGenerator()->create_user();

        // Empty rule.
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule0->id));

        // Add valid condition.
        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        // Two users and site admin.
        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule0->id));

        // Add broken condition.
        $configdata = ['courseid' => 1000, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule0->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule0->id));
    }

    /**
     * Test get_conditions.
     */
    public function test_get_conditions() {
        $conditions = \tool_dynamicrule\api::get_conditions();
        $this->assertNotEmpty($conditions);
        foreach ($conditions as $condition) {
            $this->assertTrue(is_a($condition, '\tool_dynamicrule\condition_base'));
            // Dummy condition is not expected here.
            $this->assertFalse($condition->is_dummy());
        }
    }

    /**
     * Test get_outcomes.
     */
    public function test_get_outcomes() {
        $outcomes = \tool_dynamicrule\api::get_outcomes();
        $this->assertNotEmpty($outcomes);
        foreach ($outcomes as $outcome) {
            $this->assertTrue(is_a($outcome, '\tool_dynamicrule\outcome_base'));
            // Dummy outcome is not expected here.
            $this->assertFalse($outcome->is_dummy());
        }
    }

    /**
     * Test is_in_progress_for_user.
     */
    public function test_is_in_progress_for_user() {
        global $DB;

        $course1 = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student', 'manual');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, 'student', 'manual');

        // A rule with valid condition and donothing outcome with error.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course1->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule0->id, $configdata);
        $this->get_generator()->create_outcome_donothing($rule0->id, true);

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Check matches record presence.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user1->id]);
        $matchuser2 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user2->id]);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertNull($matchuser2->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_ERROR, $matchuser1->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_ERROR, $matchuser2->status);

        // Use reflection class.
        $reflector = new \ReflectionClass('\tool_dynamicrule\api');
        $method = $reflector->getMethod('is_in_progress_for_user');
        $method->setAccessible(true);

        // Check in progress.
        $this->assertFalse($method->invokeArgs(null, [$rule0->id, $user1->id]));
        $this->assertFalse($method->invokeArgs(null, [$rule0->id, $user2->id]));

        // Change statuses in DB.
        $matchuser1->status = \tool_dynamicrule\api::STATUS_IN_PROGRESS;
        $matchuser2->status = \tool_dynamicrule\api::STATUS_IN_PROGRESS;
        $DB->update_record('tool_dynamicrule_match', $matchuser1);
        $DB->update_record('tool_dynamicrule_match', $matchuser2);

        // Check in progress.
        $this->assertTrue($method->invokeArgs(null, [$rule0->id, $user1->id]));
        $this->assertTrue($method->invokeArgs(null, [$rule0->id, $user2->id]));
    }

    /**
     * Test process_rule in general.
     */
    public function test_process_rule() {
        global $DB;

        $course1 = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student', 'manual');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, 'student', 'manual');

        // A rule with valid condition and outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course1->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Test subject 1', 'body' => ['text' => 'Test body', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Check outcomes, we should have the same message subject sent to each of our test users.
        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
        $this->assertEquals(['Test subject 1', 'Test subject 1'], array_column($messages, 'subject'));
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($messages, 'useridto'));
        $sink->close();

        // Check matches record presence.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user1->id]);
        $matchuser2 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user2->id]);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertNull($matchuser2->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser1->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser2->status);

        // Process rule manually as if we enabled it.
        $sink = $this->redirectMessages();
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Check outcomes, no messages now.
        $messages = $sink->get_messages();
        $this->assertCount(0, $messages);
        $sink->close();

        // No difference in matches records.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user1->id]);
        $matchuser2 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user2->id]);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertNull($matchuser2->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser1->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser2->status);
    }

    /**
     * Test process_rule with broken conditions and outcomes.
     */
    public function test_process_rule_with_broken_instances() {
        global $DB;

        // A rule with valid condition and outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $subject = 'Test subject 1';
        $configdata = ['subject' => $subject, 'body' => ['text' => 'Test body', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $this->assertCount(1, $DB->get_records('notifications'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));

        // A rule with a broken condition.
        $rule1 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['userprofilefield' => 9999, 'userprofilefieldvalue' => 'Example value'];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule1->id, $configdata);

        $subject = 'Test subject 2';
        $configdata = ['subject' => $subject, 'body' => ['text' => 'Test body', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        $ruleinstance = new \tool_dynamicrule\rule(0, $rule1);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Reload condition following rule processing.
        $condition = \tool_dynamicrule\condition_base::instance($condition->get_id());

        $this->assertTrue($condition->is_broken());
        // We still have 2 records from the first valid case.
        $this->assertEquals(1, $DB->count_records('notifications'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));

        // A rule with a broken outcome.
        $rule2 = $this->get_generator()->create_rule(['enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule2->id);

        // We use a non existing badge id.
        $configdata = ['badge' => '99'];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\badge::create($rule2->id, $configdata);

        $ruleinstance = new \tool_dynamicrule\rule(0, $rule2);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Reload outcome following rule processing.
        $outcome = \tool_dynamicrule\outcome_base::instance($outcome->get_id());

        $this->assertTrue($outcome->is_broken());
        $this->assertEquals(1, $DB->count_records('notifications'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
    }

    /**
     * Test process_rule outcome error.
     */
    public function test_process_rule_outcome_error() {
        global $DB;

        $course1 = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student', 'manual');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, 'student', 'manual');

        // A rule with valid condition and donothing outcome with error.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course1->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule0->id, $configdata);
        $this->get_generator()->create_outcome_donothing($rule0->id, true);

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Check matches record presence.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user1->id]);
        $matchuser2 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user2->id]);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertNull($matchuser2->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_ERROR, $matchuser1->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_ERROR, $matchuser2->status);

        // Process rule manually as if we enabled it.
        $sink = $this->redirectMessages();
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // No difference in matches records.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user1->id]);
        $matchuser2 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user2->id]);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertNull($matchuser2->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_ERROR, $matchuser1->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_ERROR, $matchuser2->status);
    }

    /**
     * Test process_rule when number of users that requires processing in chunks.
     */
    public function test_process_rule_chunks() {
        global $DB;
        require_once(__DIR__.'/fixtures/mock_api.php');
        $rule = $this->get_generator()->create_rule(['enabled' => 1]);

        // Create number of users above Postgres default limit of IN statement.
        $aboveinlimit = (\tool_dynamicrule\test\mock_api::FILTER_USERS_CHUNK_SIZE * 2) + 5;
        for ($i = 0; $i < $aboveinlimit; $i++) {
            $this->getDataGenerator()->create_user();
        }

        $this->get_generator()->create_condition_alwaystrue($rule->id);
        $this->get_generator()->create_outcome_donothing($rule->id);

        // Expect 1 more user (admin).
        $this->assertEquals($aboveinlimit + 1, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount($aboveinlimit + 1, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount($aboveinlimit + 1, $this->get_generator()->get_matching_users_for_outcomes($rule->id));

        // Process rule first time (matched).
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule);
        \tool_dynamicrule\test\mock_api::process_rule($ruleinstance);

        $this->assertEquals($aboveinlimit + 1, $DB->count_records('tool_dynamicrule_match'));
    }

    /**
     * Test process_rule and validate unmatching.
     */
    public function test_process_rule_unmatched() {
        global $DB;

        $course1 = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student', 'manual');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, 'student', 'manual');

        // A rule with valid condition and outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course1->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Test subject 1', 'body' => ['text' => 'Test body', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Check matches record presence.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user1->id]);
        $matchuser2 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user2->id]);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertNull($matchuser2->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser1->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser2->status);

        // Unenroll user1.
        $instance = $DB->get_record('enrol', ['courseid' => $course1->id, 'enrol' => 'manual']);
        $plugin = enrol_get_plugin('manual');
        $plugin->unenrol_user($instance, $user1->id);

        // Process rule manually as if we enabled it.
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user1->id]);
        $matchuser2 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user2->id]);
        $this->assertNotNull($matchuser1->unmatchedtime);
        $this->assertNull($matchuser2->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser1->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser2->status);

        // Process rule manually as if we enabled it.
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // No difference expected.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user1->id]);
        $matchuser2 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule0->id, 'userid' => $user2->id]);
        $this->assertNotNull($matchuser1->unmatchedtime);
        $this->assertNull($matchuser2->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser1->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser2->status);
    }

    /**
     * Test process_rule triggered by event.
     */
    public function test_process_rule_by_event() {
        global $DB;
        $course0 = $this->getDataGenerator()->create_course();
        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Pre-enrol user0.
        $instance = $DB->get_record('enrol', ['courseid' => $course0->id, 'enrol' => 'manual']);
        $plugin = enrol_get_plugin('manual');
        $plugin->enrol_user($instance, $user0->id);

        $rule1 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course0->id, 'enrol' => ''];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule1->id, $configdata);
        $configdata = ['subject' => 'Test subject 2', 'body' => ['text' => 'Test body 2', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        $ruleinstance = new \tool_dynamicrule\rule(0, $rule1);
        // Process rule manually as if we enabled it.
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $matchuser0 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user0->id]);

        $this->assertNull($matchuser0->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser0->status);

        // Enrol users, this should trigger rule processing on background.
        $plugin->enrol_user($instance, $user1->id);
        $plugin->enrol_user($instance, $user2->id);

        $this->assertEquals(3, $DB->count_records('tool_dynamicrule_match'));
        $matchuser0 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user0->id]);
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user1->id]);
        $matchuser2 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user2->id]);

        $this->assertNull($matchuser2->unmatchedtime);
        $this->assertNull($matchuser0->unmatchedtime);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser0->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser1->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser2->status);

        // Process rule manually as if we enabled it.
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // No difference expected.
        $this->assertEquals(3, $DB->count_records('tool_dynamicrule_match'));
        $matchuser0 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user0->id]);
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user1->id]);
        $matchuser2 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user2->id]);
        $this->assertNull($matchuser2->unmatchedtime);
        $this->assertNull($matchuser0->unmatchedtime);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser0->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser1->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser2->status);
    }

    /**
     * Test process_rule triggered by event with re-matching.
     */
    public function test_process_rule_by_event_rematched() {
        global $DB;
        $course0 = $this->getDataGenerator()->create_course();
        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();

        // Pre-enrol user0 amnd user1.
        $instance = $DB->get_record('enrol', ['courseid' => $course0->id, 'enrol' => 'manual']);
        $plugin = enrol_get_plugin('manual');
        $plugin->enrol_user($instance, $user0->id);
        $plugin->enrol_user($instance, $user1->id);

        $rule1 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course0->id, 'enrol' => ''];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule1->id, $configdata);
        $configdata = ['subject' => 'Test subject 2', 'body' => ['text' => 'Test body 2', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        $ruleinstance = new \tool_dynamicrule\rule(0, $rule1);
        // Process rule manually as if we enabled it.
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $matchuser0 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user0->id]);
        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user1->id]);
        $this->assertNull($matchuser0->unmatchedtime);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser0->status);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser1->status);

        // Unenrol user0 and enrol it again.
        // TODO: Remove rule processing call in the middle of unenrol/enrol when WP-2253 is addressed.
        $plugin->unenrol_user($instance, $user0->id);
        \tool_dynamicrule\api::process_rule($ruleinstance);
        $plugin->enrol_user($instance, $user0->id);

        // We have new matching record for user0.
        $this->assertEquals(3, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user0->id]));

        $matchuser0recs = $DB->get_records('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user0->id]);
        // Previous record for user0 got unmatched time added.
        $this->assertNotNull($matchuser0recs[$matchuser0->id]->unmatchedtime);
        // Other record for user0 does not have unmatched time.
        $matchuser0rec = array_diff_key($matchuser0recs, [$matchuser0->id => '']);
        $this->assertNull(array_values($matchuser0rec)[0]->unmatchedtime);

        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user1->id]);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser1->status);

        // Process rule manually as if we enabled it.
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // No difference expected.
        $this->assertEquals(3, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user0->id]));

        $matchuser0recs = $DB->get_records('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user0->id]);
        // Previous record for user0 got unmatched time.
        $this->assertNotNull($matchuser0recs[$matchuser0->id]->unmatchedtime);
        // Other record for user0 does not have unmatched time.
        $matchuser0rec = array_diff_key($matchuser0recs, [$matchuser0->id => '']);
        $this->assertNull(array_values($matchuser0rec)[0]->unmatchedtime);

        $matchuser1 = $DB->get_record('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user1->id]);
        $this->assertNull($matchuser1->unmatchedtime);
        $this->assertEquals(\tool_dynamicrule\api::STATUS_DONE, $matchuser1->status);
    }

    /**
     * Test duplicate_rule.
     */
    public function test_duplicate_rule() {
        global $DB;

        $rule0 = $this->get_generator()->create_rule();
        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $this->get_generator()->create_outcome_donothing($rule0->id);

        $newruleid1 = \tool_dynamicrule\api::duplicate_rule($rule0->id);
        $newruleid2 = \tool_dynamicrule\api::duplicate_rule($rule0->id);

        $newrule1 = \tool_dynamicrule\api::get_rule($newruleid1);
        $newrule2 = \tool_dynamicrule\api::get_rule($newruleid2);

        $this->assertEquals($rule0->name . ' Copy 1', $newrule1->get('name'));
        $this->assertEquals($rule0->name . ' Copy 2', $newrule2->get('name'));

        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_condition', ['ruleid' => $newrule1->get('id')]));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_condition', ['ruleid' => $newrule2->get('id')]));

        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_outcome', ['ruleid' => $newrule1->get('id')]));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_outcome', ['ruleid' => $newrule2->get('id')]));

        unset($rule0->id);
        unset($rule0->name);
        unset($rule0->timecreated);
        unset($rule0->timemodified);

        $newrule1record = $newrule1->to_record();
        unset($newrule1record->id);
        unset($newrule1record->name);
        unset($newrule1record->timecreated);
        unset($newrule1record->timemodified);

        $newrule2record = $newrule2->to_record();
        unset($newrule2record->id);
        unset($newrule2record->name);
        unset($newrule2record->timecreated);
        unset($newrule2record->timemodified);

        $this->assertEquals($rule0, $newrule1record);
        $this->assertEquals($newrule1record, $newrule2record);
    }

    /**
     * Test that manager role is created on installation
     */
    public function test_roles() {
        $allroles = get_all_roles();
        $roles = array_combine(array_keys($allroles), array_column($allroles, 'shortname'));
        $this->assertContains('tool_dynamicrule_manager', $roles);
        $fliproles = array_flip($roles);

        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        role_assign($fliproles['tool_tenant_admin'], $user->id, $context->id);
        $this->setUser($user);

        // Tenant admin can assign tool_dynamicrule_manager in the system context.
        $assignableroles = get_assignable_roles($context);
        $this->assertTrue(array_key_exists($fliproles['tool_dynamicrule_manager'], $assignableroles));

        // The manager role has two capabilities (tool/dynamicrule:manage and moodle/site:doclinks),
        // it is valid and belong to this project.
        $caps = get_capabilities_from_role_on_context((object)['id' => $fliproles['tool_dynamicrule_manager']], $context);
        $this->assertEquals(2, count($caps));
        foreach ($caps as $cap) {
            if (!preg_match('|^tool/dynamicrule:|', $cap->capability) &&
                    $cap->capability != 'moodle/site:doclinks') {
                $this->fail('Capability ' . $cap->capability . ' does not belong to this plugin');
            }
            get_capability_info($cap->capability);
        }
    }

    /**
     * Data provider for {{@see test_is_course_category_allowed_in_rule}}
     *
     * @return array
     */
    public function provider_is_course_category_allowed_in_rule(): array {
        return [
            'shared0' => ['sharedrule', 'catshared1', true],
            'shared1' => ['sharedrule', 'catshared2', true],
            'shared3' => ['sharedrule', 'cat1', false],
            'shared4' => ['sharedrule', 'cat11', false],
            'shared5' => ['sharedrule', 'cat2', false],
            'tenant10' => ['tenant1rule', 'catshared1', true],
            'tenant11' => ['tenant1rule', 'catshared2', true],
            'tenant12' => ['tenant1rule', 'cat1', true],
            'tenant13' => ['tenant1rule', 'cat11', true],
            'tenant14' => ['tenant1rule', 'cat2', true],
            'tenant20' => ['tenant2rule', 'catshared1', true],
            'tenant21' => ['tenant2rule', 'catshared2', true],
            'tenant22' => ['tenant2rule', 'cat1', true],
            'tenant23' => ['tenant2rule', 'cat11', true],
            'tenant24' => ['tenant2rule', 'cat2', true],
        ];
    }

    /**
     * Test function api::test_is_course_category_allowed_in_rule()
     *
     * @param string $rulename
     * @param string $catname
     * @param bool $result
     *
     * @dataProvider provider_is_course_category_allowed_in_rule
     */
    public function test_is_course_category_allowed_in_rule(string $rulename, string $catname, bool $result): void {
        $cat1 = $this->getDataGenerator()->create_category([]);
        $cat11 = $this->getDataGenerator()->create_category(['parent' => $cat1->id]);
        $cat2 = $this->getDataGenerator()->create_category([]);
        $catshared1 = $this->getDataGenerator()->create_category([]);
        $catshared2 = $this->getDataGenerator()->create_category(['parent' => $catshared1->id]);
        $tenant1 = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant(['categoryid' => $cat1->id]);
        $tenant2 = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant(['categoryid' => $cat2->id]);
        $tenant3 = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant(['categoryid' => 0]);
        $sharedspaceid = sharedspace::enable_shared_space();

        // Create rules in each tenant.
        $rules = [
            'sharedrule' => new rule(0, $this->get_generator()->create_rule(['tenantid' => $sharedspaceid])),
            'tenant1rule' => new rule(0, $this->get_generator()->create_rule(['tenantid' => $tenant1->id])),
            'tenant2rule' => new rule(0, $this->get_generator()->create_rule(['tenantid' => $tenant2->id])),
            'tenant3rule' => new rule(0, $this->get_generator()->create_rule(['tenantid' => $tenant3->id])),
        ];

        // Categories.
        $categories = [
            'cat1' => $cat1->id,
            'cat11' => $cat11->id,
            'cat2' => $cat2->id,
            'catshared1' => $catshared1->id,
            'catshared2' => $catshared2->id,
        ];

        $this->assertEquals($result, api::is_course_category_allowed_in_rule($rules[$rulename], $categories[$catname]));
    }
}
