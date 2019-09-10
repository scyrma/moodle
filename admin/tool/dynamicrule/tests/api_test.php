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
 * File contains the unit tests for api class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

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
 * @copyright  2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dynamicrule_api_testcase extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp() {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['lastaccess' => strtotime('today')]);
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
     * Test get_all_instances_of_type with invalid type
     */
    public function test_get_all_instances_of_type_invalid_type() {

        $reflector = new ReflectionClass('\tool_dynamicrule\api');
        $method = $reflector->getMethod('get_all_instances_of_type');
        $method->setAccessible(true);
        $this->expectException(\coding_exception::class);
        $instances = $method->invokeArgs(null, ['invalidtype']);
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

        // Testing rule with different tenant.
        $othertenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();
        $rule1 = $this->get_generator()->create_rule(['tenantid' => $othertenant]);

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
        foreach ($conditions as $condition) {
            $this->assertEquals('testable_condition_alwaystrue', get_class($condition));
        }
    }

    /**
     * Test get_rule_outcomes
     */
    public function test_get_rule_outcomes() {
        $rule0 = $this->get_generator()->create_rule();

        // There are no outcomes on a recently created rule.
        $this->assertEmpty(\tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        $outcomeclass = 'tool_dynamicrule\tool_dynamicrule\outcome\notification';
        $this->get_generator()->create_outcome($outcomeclass, $rule0->id);

        $this->assertCount(1, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        $this->get_generator()->create_outcome($outcomeclass, $rule0->id);

        $this->assertCount(2, \tool_dynamicrule\api::get_rule_outcomes($rule0->id));

        $outcomes = \tool_dynamicrule\api::get_rule_outcomes($rule0->id);
        foreach ($outcomes as $outcome) {
            $this->assertEquals($outcomeclass, get_class($outcome));
        }
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
        $conditionclass = 'testable_condition_alwaystrue';
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
        $outcome1 = \tool_dynamicrule\api::create_rule_outcome($rule0->id, $outcomeclass);
        $configform = ['subject' => 'The notification subject', 'body' => 'Here comes the message.'];
        $outcome2 = \tool_dynamicrule\api::create_rule_outcome($rule0->id, $outcomeclass, $configform);
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_outcome'));

        // Test outcomes.
        $this->assertEquals($outcomeclass, get_class($outcome1));
        $this->assertEquals($rule0->id, $outcome1->get_ruleid());
        $this->assertEmpty($outcome1->get_configdata());

        $this->assertEquals($rule0->id, $outcome2->get_ruleid());
        $this->assertArrayHasKey('subject', $outcome2->get_configdata());
        $this->assertArrayHasKey('body', $outcome2->get_configdata());
    }

    /**
     * Test get_matching_users
     * A rule without conditions should match no users.
     */
    public function test_get_matching_users_new_empty_rule() {
        $rule0 = $this->get_generator()->create_rule();

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule0->id));
        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($rule0->id));
    }

    /**
     * Test get_matching_users
     * A rule with one static condition not matching should match no users.
     */
    public function test_get_matching_users_static_rule_no_matches() {
        $rule = $this->get_generator()->create_rule();

        $this->get_generator()->create_condition_alwaysfalse($rule->id);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule->id));
    }

    /**
     * Test get_matching_users
     * A rule with one matching user should return that user only.
     */
    public function test_get_matching_users_sql_rule_one_match() {
        $rule2 = $this->get_generator()->create_rule();

        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_INLAST,
                       'lastloginrelative' => '1 day'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule2->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule2->id));
        foreach (\tool_dynamicrule\api::get_matching_users($rule2->id) as $user) {
            $this->assertObjectHasAttribute('id', $user);
        }
    }

    /**
     * Test get_matching_users
     * A rule with two matching conditions should return the intersection between resultsets.
     */
    public function test_get_matching_users_two_conditions() {
        $rule = $this->get_generator()->create_rule();

        $this->get_generator()->create_condition_alwaystrue($rule->id);

        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_INLAST,
                       'lastloginrelative' => '1 day'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));
        foreach (\tool_dynamicrule\api::get_matching_users($rule->id) as $user) {
            $this->assertObjectHasAttribute('id', $user);
        }
    }

    /**
     * Test get_matching_users
     * A rule should match a user everytime by default.
     */
    public function test_get_matching_users_default_matchlimit() {
        $rule = $this->get_generator()->create_rule();

        $this->get_generator()->create_condition_alwaystrue($rule->id);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        foreach (\tool_dynamicrule\api::get_matching_users($rule->id) as $user) {
            $this->assertObjectHasAttribute('id', $user);
        }

        $conditionfalse = $this->get_generator()->create_condition_alwaysfalse($rule->id);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule->id));

        $conditionfalse->delete();

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule->id));
    }

    /**
     * Test get_matching_users
     * A rule should match a user the number of times specified by matchlimit property.
     */
    public function test_get_matching_users_other_matchlimit() {
        $rule = $this->get_generator()->create_rule(['matchlimit' => 2]);

        $this->get_generator()->create_condition_alwaystrue($rule->id);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        foreach (\tool_dynamicrule\api::get_matching_users($rule->id) as $user) {
            $this->assertObjectHasAttribute('id', $user);
        }

        $conditionfalse = $this->get_generator()->create_condition_alwaysfalse($rule->id);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule->id));

        $conditionfalse->delete();

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        foreach (\tool_dynamicrule\api::get_matching_users($rule->id) as $user) {
            $this->assertObjectHasAttribute('id', $user);
        }

        $conditionfalse = $this->get_generator()->create_condition_alwaysfalse($rule->id);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule->id));

        $conditionfalse->delete();

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule->id));
    }

    /**
     * Test get_matching_users
     * A rule should match a user the number of times specified by matchlimit property in the last matchinterval seconds.
     */
    public function test_get_matching_users_matchinterval() {

        $rule = $this->get_generator()->create_rule(['matchlimit' => 2, 'matchinterval' => 10]);

        $condition = $this->get_generator()->create_condition_alwaystrue($rule->id);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        foreach (\tool_dynamicrule\api::get_matching_users($rule->id) as $user) {
            $this->assertObjectHasAttribute('id', $user);
        }

        $conditionfalse = $this->get_generator()->create_condition_alwaysfalse($rule->id);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule->id));

        $conditionfalse->delete();

        // User is matched third time because it is not in the matchinterval.
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule->id, null, time() + 100));
    }

    /**
     * Setup for the next tests.
     * create two tenants.
     * First tenant has 5 users (user11, user12, user13, user14, user15), user11 & user12 have city "Barcelona"
     * Second tenant has 3 users (user21, user22, user23), user23 has city "Barcelona"
     * Create two rules with condition "user city = Barcelona", one for each tenant, the first with matchlimit = 2 to pass test 8.
     */
    private function get_matching_users_setup() {
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();

        $this->user11 = $this->getDataGenerator()->create_user(['city' => 'Barcelona']);
        $this->user12 = $this->getDataGenerator()->create_user(['city' => 'Barcelona']);
        $this->user13 = $this->getDataGenerator()->create_user();
        $this->user14 = $this->getDataGenerator()->create_user();
        $this->user15 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($this->user11->id, $defaulttenantid);
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($this->user12->id, $defaulttenantid);
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($this->user13->id, $defaulttenantid);
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($this->user14->id, $defaulttenantid);
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($this->user15->id, $defaulttenantid);

        $this->user21 = $this->getDataGenerator()->create_user();
        $this->user22 = $this->getDataGenerator()->create_user();
        $this->user23 = $this->getDataGenerator()->create_user(['city' => 'Barcelona']);

        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($this->user21->id, $othertenant->id);
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($this->user22->id, $othertenant->id);
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($this->user23->id, $othertenant->id);

        $this->ruledefaulttenant = $this->get_generator()->create_rule(['tenantid' => $defaulttenantid]);
        $configdata = ['userprofilefield' => 'city', 'userprofilefieldvalue' => 'Barcelona'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($this->ruledefaulttenant->id, $configdata);

        $this->ruleothertenant = $this->get_generator()->create_rule(['tenantid' => $othertenant->id]);
        $configdata = ['userprofilefield' => 'city', 'userprofilefieldvalue' => 'Barcelona'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($this->ruleothertenant->id, $configdata);
    }

    /**
     * Test 1.
     * Dry-count of users should return 2 for the first rule and 1 for the second rule
     * Dry-run number of affected users - should show user11&user12 for the first rule and user23 for the second rule
     */
    public function test_get_matching_users_one() {
        $this->get_matching_users_setup();
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($this->ruledefaulttenant->id));
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($this->ruleothertenant->id));

        $users = \tool_dynamicrule\api::get_matching_users_dry_run($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user12->id, $this->user11->id], array_column($users, 'id'), '', 0, 10, true);

        $users = \tool_dynamicrule\api::get_matching_users_dry_run($this->ruleothertenant->id);
        $this->assertEquals([$this->user23->id], array_column($users, 'id'));
    }

    /**
     * Test 2.
     * Fullrun should return user11&user12 for the first rule and user23 for the second rule
     */
    public function test_get_matching_users_two() {
        $this->get_matching_users_setup();

        $users = \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user11->id, $this->user12->id], array_column($users, 'id'), '', 0, 10, true);

        $users = \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);
        $this->assertEquals([$this->user23->id], array_column($users, 'id'));
    }

    /**
     * Test 3.
     * Fullrun (no assertion)
     * Dry-count should return 0 for both rule
     * Dry-run should return empty array for both rules
     */
    public function test_get_matching_users_three() {
        $this->get_matching_users_setup();

        \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($this->ruledefaulttenant->id));
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($this->ruleothertenant->id));

        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users_dry_run($this->ruledefaulttenant->id));
        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users_dry_run($this->ruleothertenant->id));
    }

    /**
     * Test 4.
     * Fullrun (no assertion)
     * set city Barcelona for user14
     * Dry-count of users should return 1 for the first rule and 0 for the second rule
     * Dry-run number of affected users - should show user14 for the first rule and empty array for the second rule
     */
    public function test_get_matching_users_four() {
        global $DB;

        $this->get_matching_users_setup();

        \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);

        $this->user14->city = 'Barcelona';
        $DB->update_record('user', $this->user14);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($this->ruledefaulttenant->id));
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($this->ruleothertenant->id));

        $users = \tool_dynamicrule\api::get_matching_users_dry_run($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user14->id], array_column($users, 'id'));

        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users_dry_run($this->ruleothertenant->id));
    }

    /**
     * Test 5.
     * Fullrun (no assertion)
     * set city Barcelona for user14
     * Fullrun should return user14 for the first rule and empty array for the second rule
     */
    public function test_get_matching_users_five() {
        global $DB;

        $this->get_matching_users_setup();

        \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);

        $this->user14->city = 'Barcelona';
        $DB->update_record('user', $this->user14);

        $users = \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user14->id], array_column($users, 'id'));

        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id));
    }

    /**
     * Test 6.
     * Fullrun (no assertion)
     * set city Perth for user12
     * Dry-count of users should return 0 for the first rule and 0 for the second rule
     * Dry-run number of affected users - should show empty array for the first rule and empty array for the second rule
     */
    public function test_get_matching_users_six() {
        global $DB;

        $this->get_matching_users_setup();

        \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);

        $this->user12->city = 'Perth';
        $DB->update_record('user', $this->user12);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($this->ruledefaulttenant->id));
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($this->ruleothertenant->id));

        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users_dry_run($this->ruledefaulttenant->id));
        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users_dry_run($this->ruleothertenant->id));
    }

    /**
     * Test 7.
     * Fullrun (no assertion)
     * set city Perth for user12
     * Fullrun should return empty array for both rules
     */
    public function test_get_matching_users_seven() {
        global $DB;

        $this->get_matching_users_setup();

        \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);

        $this->user12->city = 'Perth';
        $DB->update_record('user', $this->user12);

        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id));
        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id));
    }

    /**
     * Test 8.
     * Fullrun (no assertion)
     * set city Perth for user12
     * Fullrun (no assertion)
     * set city Barcelona for user12
     * Dry-count of users should return 1 for the first rule and 0 for the second rule
     * Dry-run number of affected users - should show user12 for the first rule and empty array for the second rule
     */
    public function test_get_matching_users_eight() {
        global $DB;

        $this->get_matching_users_setup();

        \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);

        $this->user12->city = 'Perth';
        $DB->update_record('user', $this->user12);

        \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);

        $this->user12->city = 'Barcelona';
        $DB->update_record('user', $this->user12);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($this->ruledefaulttenant->id));
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($this->ruleothertenant->id));

        $users = \tool_dynamicrule\api::get_matching_users_dry_run($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));

        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users_dry_run($this->ruleothertenant->id));
    }

    /**
     * Test 9.
     * Fullrun (no assertion)
     * set city Perth for user12
     * Fullrun (no assertion)
     * set city Barcelona for user12
     * Fullrun should return user12 for the first rule and empty array for the second rule
     */
    public function test_get_matching_users_nine() {
        global $DB;

        $this->get_matching_users_setup();

        \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);

        $this->user12->city = 'Perth';
        $DB->update_record('user', $this->user12);

        \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);

        $this->user12->city = 'Barcelona';
        $DB->update_record('user', $this->user12);

        $users = \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));

        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users_dry_run($this->ruleothertenant->id));
    }

    /**
     * Test 10 (only test rule1).
     * Update rule1, set matchlimit = 2, matchinterval = 1 hour
     * Execute changing countries test (a, b)
     * c)
     * fast-forward one hour and one minute
     * Dry-count should return 1
     * Dry-run should return user12
     * Fullrun should return user12 - add a comment that match happened
     *   even though we did not trigger condition just because the time changed
     */
    public function test_get_matching_users_ten() {

        $this->get_matching_users_setup();

        $this->ruledefaulttenant->matchinterval = 3600;
        $this->ruledefaulttenant->matchlimit = 2;
        \tool_dynamicrule\api::update_rule($this->ruledefaulttenant->id, $this->ruledefaulttenant);

        $this->changing_countries_test();

        // C - Testing with time in future to match again.
        $currenttime = time() + 3660;
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($this->ruledefaulttenant->id, $currenttime));
        $users = \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        $this->assertEquals([], array_column($users, 'id'));

        $users = \tool_dynamicrule\api::get_matching_users_dry_run($this->ruledefaulttenant->id, $currenttime);
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));

        $users = \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id, null, $currenttime);
        // Match happened even though we did not trigger condition just because the time changed.
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));
    }

    /**
     * Test 11 (only test rule1).
     * Update rule, set matchlimit = 2, matchinterval - ever
     * - same test as 10 except it does not need part c) at all
     */
    public function test_get_matching_users_eleven() {

        $this->get_matching_users_setup();

        $this->ruledefaulttenant->matchinterval = 0;
        $this->ruledefaulttenant->matchlimit = 2;
        \tool_dynamicrule\api::update_rule($this->ruledefaulttenant->id, $this->ruledefaulttenant);

        $this->changing_countries_test();
    }

    /**
     * This function is used by tests 10 and 11
     *
     * Fullrun should return user11&user12 - add a comment that this is the first match for the user12
     * a)
     * set city Perth for user12
     * Fullrun should return empty arrray
     * set city Barcelona for user12
     * Dry-count should return 1
     * Dry-run should return user12
     * Fullrun should return user12 - add a comment that this is the second match for the user12
     * b)
     * set city Perth for user12
     * Fullrun should return empty arrray
     * set city Barcelona for user12
     * Dry-count should return 0 - add a comment that we hit the match limit
     * Dry-run should return empty array
     * Fullrun should return empty array
     */
    private function changing_countries_test() {
        global $DB;

        $users = \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        // First match for the user12.
        $this->assertEquals([$this->user11->id, $this->user12->id], array_column($users, 'id'), '', 0, 10, true);

        // A - change city to unmatch and change back to match again.
        $this->user12->city = 'Perth';
        $DB->update_record('user', $this->user12);

        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id));

        $this->user12->city = 'Barcelona';
        $DB->update_record('user', $this->user12);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($this->ruledefaulttenant->id));
        $users = \tool_dynamicrule\api::get_matching_users_dry_run($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));

        $users = \tool_dynamicrule\api::get_matching_users_dry_run($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));

        $users = \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        // Second match for user12.
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));

        // B - Change city to unmatch an change back to npt match again because of matchlimit.
        $this->user12->city = 'Perth';
        $DB->update_record('user', $this->user12);

        $users = \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        $this->assertEquals([], array_column($users, 'id'));

        $this->user12->city = 'Barcelona';
        $DB->update_record('user', $this->user12);

        // The matchlimit was already hit by user12 at second match.
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($this->ruledefaulttenant->id));

        $users = \tool_dynamicrule\api::get_matching_users_dry_run($this->ruledefaulttenant->id);
        $this->assertEquals([], array_column($users, 'id'));
    }

    /**
     * Test get_matching_users using two conditions instances of same condition class for same rule.
     */
    public function test_get_matching_users_two_conditions_same_type_same_rule() {

        $user1 = $this->getDataGenerator()->create_user(['city' => 'Perth']);
        $user2 = $this->getDataGenerator()->create_user(['city' => 'Barcelona']);

        $rule = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => 'city', 'userprofilefieldvalue' => 'Perth'];
        $condcity = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));

        $users = \tool_dynamicrule\api::get_matching_users_dry_run($rule->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'));

        $users = \tool_dynamicrule\api::get_matching_users($rule->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'));

        $condcity->update_configdata(['userprofilefield' => 'city', 'userprofilefieldvalue' => 'Florianópolis']);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));

        $users = \tool_dynamicrule\api::get_matching_users_dry_run($rule->id);
        $this->assertEquals([], array_column($users, 'id'));

        $users = \tool_dynamicrule\api::get_matching_users($rule->id);
        $this->assertEquals([], array_column($users, 'id'));
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
        $outcomeclass = 'tool_dynamicrule\tool_dynamicrule\outcome\notification';
        $this->get_generator()->create_outcome($outcomeclass, $rule0->id);
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

        // Test broken rule with condition and outcome.
        \tool_dynamicrule\api::mark_rule_as_broken($rule0->id);
        $this->assertFalse(\tool_dynamicrule\api::enable_rule($rule0->id));
        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertTrue($rule->is_broken());
        $this->assertFalse($rule->is_enabled());
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
        $this->expectException(\moodle_exception::class);
        \tool_dynamicrule\api::get_rule($rule0->id);
        foreach ($rule0conditions as $condition) {
            $this->assertFalse(\tool_dynamicrule\condition::record_exists($condition->get_id()));
        }
        foreach ($rule0outcomes as $outcome) {
            $this->assertFalse(\tool_dynamicrule\outcome::record_exists($outcome->get_id()));
        }

        $this->assertFalse($DB->record_exists('tool_dynamicrule_match', ['ruleid' => $rule0->id]));

        // Check that rule1 records are not affected.
        $rule1 = \tool_dynamicrule\api::get_rule($rule1->id);
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_conditions($rule1->id));
        $this->assertCount(1, \tool_dynamicrule\api::get_rule_outcomes($rule1->id));

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
     * Test \tool_dynamicrule\matching_users_report
     *
     * @covers \tool_dynamicrule\matching_users_report
     */
    public function test_matching_users_report() {
        global $DB, $PAGE;

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
     * Test mark_rule_rule_as_broken
     */
    public function test_mark_rule_as_broken() {
        $othertenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();

        $rule0 = $this->get_generator()->create_rule();
        $rule1 = $this->get_generator()->create_rule(['tenantid' => $othertenant->id]);

        // Mark rule0 as broken.
        \tool_dynamicrule\api::mark_rule_as_broken($rule0->id);

        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertTrue($rule->is_broken());
        $this->assertFalse($rule->is_archived());
        $this->assertFalse($rule->is_enabled());

        // Try to mark rule1 as broken.
        $this->expectException(\moodle_exception::class);
        \tool_dynamicrule\api::mark_rule_as_broken($rule1->id);
    }

    /**
     * Test mark_rule_rule_as_not_broken
     */
    public function test_mark_rule_as_not_broken() {
        $othertenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();

        $rule0 = $this->get_generator()->create_rule(['broken' => 1]);
        $rule1 = $this->get_generator()->create_rule(['broken' => 1, 'tenantid' => $othertenant->id]);

        // Mark rule0 as broken.
        \tool_dynamicrule\api::mark_rule_as_not_broken($rule0->id);

        $rule = \tool_dynamicrule\api::get_rule($rule0->id);
        $this->assertFalse($rule->is_broken());
        $this->assertFalse($rule->is_archived());
        $this->assertFalse($rule->is_enabled());

        // Try to mark rule1 as broken.
        $this->expectException(\moodle_exception::class);
        \tool_dynamicrule\api::mark_rule_as_not_broken($rule1->id);
    }

    /**
     * Test get_conditions.
     */
    public function test_get_conditions() {
        $conditions = \tool_dynamicrule\api::get_conditions();
        $this->assertNotEmpty($conditions);
        foreach ($conditions as $condition) {
            $this->assertTrue(is_a($condition, '\tool_dynamicrule\condition_base'));
            $this->assertTrue($condition->is_available());
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
            $this->assertTrue($outcome->is_available());
        }
    }

    /**
     * Test process_rule.
     */
    public function test_process_rule() {
        global $DB;

        // A rule with valid condition and outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $subject = 'Test subject 1';
        $configdata = ['subject' => $subject, 'body' => 'Test body'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $this->assertCount(2, $DB->get_records('notifications'));

        // A rule with a broken condition.
        $rule1 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['userprofilefield' => 9999, 'userprofilefieldvalue' => 'Example value'];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule1->id, $configdata);

        $subject = 'Test subject 2';
        $configdata = ['subject' => $subject, 'body' => 'Test body'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        $ruleinstance = new \tool_dynamicrule\rule(0, $rule1);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Need to reload condition.
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field($condition->get_id());

        $this->assertTrue(\tool_dynamicrule\api::get_rule($rule1->id)->is_broken());
        $this->assertTrue($condition->is_broken());
        $this->assertEquals(2, $DB->count_records('notifications'));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));

        // A rule with a broken outcome.
        $rule2 = $this->get_generator()->create_rule(['enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule2->id);

        $subject = '';
        $configdata = ['subject' => $subject, 'body' => 'Test body'];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule2->id, $configdata);

        $ruleinstance = new \tool_dynamicrule\rule(0, $rule2);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Need to reload outcome.
        $outcome = new \tool_dynamicrule\tool_dynamicrule\outcome\notification($outcome->get_id());

        $this->assertTrue(\tool_dynamicrule\api::get_rule($rule2->id)->is_broken());
        $this->assertTrue($outcome->is_broken());
        $this->assertEquals(2, $DB->count_records('notifications'));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
    }

    /**
     * Test process_rule for user.
     */
    public function test_process_rule_for_user() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();

        // A rule with valid condition and outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);
        $configdata = ['subject' => 'Test subject 1', 'body' => 'Test body'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Process rule.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance, $user0->id);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'Test subject 1');
        $this->assertEquals($messages[0]->useridto, $user0->id);
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }

    /**
     * Test get_matching_users_dry_run.
     */
    public function test_get_matching_users_dry_run() {
        $rule0 = $this->get_generator()->create_rule();
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $subject = 'Test subject 1';
        $configdata = ['subject' => $subject, 'body' => 'Test body'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users_dry_run($rule0->id));

        $rule1 = $this->get_generator()->create_rule();
        $this->get_generator()->create_condition_alwaysfalse($rule1->id);

        $subject = 'Test subject 2';
        $configdata = ['subject' => $subject, 'body' => 'Test body'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users_dry_run($rule1->id));
    }

    /**
     * Test duplicate_rule.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \testable_condition_alwaystrue
     */
    public function test_duplicate_rule() {
        global $DB;

        $rule0 = $this->get_generator()->create_rule();
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $subject = 'Test subject 1';
        $configdata = ['subject' => $subject, 'body' => 'Test body'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

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
        $context = context_system::instance();
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
}
