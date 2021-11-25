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
 * File contains the unit tests for api class that validates matching functionality.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Unit tests for api class that validate matching functionality.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\api
 * @covers     \tool_dynamicrule\rule
 * @covers     \tool_dynamicrule\condition
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\outcome
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_api_matching_testcase extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        // Stop clock during test execution if uopz extension is available.
        if (extension_loaded('uopz')) {
            // Use today midnight timestamp.
            $timestamp = strtotime('today');

            // Override time().
            uopz_set_return('time', $timestamp);

            // Override strtotime().
            $strtotime = function($t, $n = null){
                $n = $n ?? uopz_get_return('time');
                return strtotime($t, $n);
            };
            uopz_set_return('strtotime', $strtotime, true);
        }

        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['lastaccess' => strtotime('today')]);
    }

    /**
     * tearDown.
     */
    public function tearDown(): void {
        if (extension_loaded('uopz')) {
            // Revert function overrides.
            uopz_unset_return('time');
            uopz_unset_return('strtotime');
        }
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
     * Test get_matching_users
     * A rule without conditions should match no users.
     */
    public function test_get_matching_users_new_empty_rule() {
        $rule0 = $this->get_generator()->create_rule();

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule0->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule0->id));
        $this->assertCount(0, $this->get_generator()->get_matching_users_for_outcomes($rule0->id));
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
        $this->assertCount(0, $this->get_generator()->get_matching_users_for_outcomes($rule->id));
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
        $this->assertCount(1, \tool_dynamicrule\api::get_matching_users($rule2->id));
        $this->assertCount(1, $this->get_generator()->get_matching_users_for_outcomes($rule2->id));
    }

    /**
     * Test get_matching_users
     * A rule with two matching conditions should return the intersection between resultsets.
     */
    public function test_get_matching_users_for_outcomes_two_conditions() {
        $rule = $this->get_generator()->create_rule();

        $this->get_generator()->create_condition_alwaystrue($rule->id);

        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_INLAST,
                       'lastloginrelative' => '1 day'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(1, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(1, $this->get_generator()->get_matching_users_for_outcomes($rule->id));
    }

    /**
     * Test get_matching_users_for_outcomes with limits
     *
     * A rule should match a user everytime by default.
     *
     * @covers \tool_dynamicrule\api::filter_users_by_rule_restriction
     */
    public function test_get_matching_users_for_outcomes_default_matchlimit() {
        $rule = $this->get_generator()->create_rule(['enabled' => 1]);

        $this->get_generator()->create_condition_alwaystrue($rule->id);
        $this->get_generator()->create_outcome_donothing($rule->id);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(2, $this->get_generator()->get_matching_users_for_outcomes($rule->id));

        // Process rule first time (matched).
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Add condition to unmatch users.
        $conditionfalse = $this->get_generator()->create_condition_alwaysfalse($rule->id);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(0, $this->get_generator()->get_matching_users_for_outcomes($rule->id));

        // Process rule second time (unmatch).
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $conditionfalse->delete();
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(2, $this->get_generator()->get_matching_users_for_outcomes($rule->id));
    }

    /**
     * Test get_matching_users_for_outcomes with limits
     *
     * A rule should match a user the number of times specified by matchlimit property.
     *
     * @covers \tool_dynamicrule\api::filter_users_by_rule_restriction
     */
    public function test_get_matching_users_for_outcomes_matchlimit() {
        $rule = $this->get_generator()->create_rule(['matchlimit' => 2, 'enabled' => 1]);

        $this->get_generator()->create_condition_alwaystrue($rule->id);
        $this->get_generator()->create_outcome_donothing($rule->id);

        // Check counter before any user matched.
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(2, $this->get_generator()->get_matching_users_for_outcomes($rule->id));

        // Process rule first time (matched).
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Add condition to unmatch users.
        $conditionfalse = $this->get_generator()->create_condition_alwaysfalse($rule->id);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(0, $this->get_generator()->get_matching_users_for_outcomes($rule->id));

        // Process rule second time (unmatched).
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $conditionfalse->delete();
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(2, $this->get_generator()->get_matching_users_for_outcomes($rule->id));

        // Process rule the third time (matched).
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Add condition to unmatch users again.
        $conditionfalse = $this->get_generator()->create_condition_alwaysfalse($rule->id);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(0, $this->get_generator()->get_matching_users_for_outcomes($rule->id));

        // Process rule the third time (unmatched).
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Matching limit has been reached.
        $conditionfalse->delete();
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(0, $this->get_generator()->get_matching_users_for_outcomes($rule->id));
    }

    /**
     * Test get_matching_users_for_outcomes with limits
     *
     * A rule should match a user the number of times specified by matchlimit property in the last matchinterval seconds.
     *
     * @covers \tool_dynamicrule\api::filter_users_by_rule_restriction
     */
    public function test_get_matching_users_for_outcomes_matchinterval() {

        $rule = $this->get_generator()->create_rule(['matchlimit' => 1, 'matchinterval' => HOURSECS, 'enabled' => 1]);

        $this->get_generator()->create_condition_alwaystrue($rule->id);
        $this->get_generator()->create_outcome_donothing($rule->id);

        // Check counter before any user matched.
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(2, $this->get_generator()->get_matching_users_for_outcomes($rule->id));

        // Process rule first time (matched).
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Add condition to unmatch users.
        $conditionfalse = $this->get_generator()->create_condition_alwaysfalse($rule->id);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(0, $this->get_generator()->get_matching_users_for_outcomes($rule->id));

        // Process rule second time (unmatched).
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Matching limit has been reached.
        $conditionfalse->delete();
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(0, $this->get_generator()->get_matching_users_for_outcomes($rule->id));

        if (!extension_loaded('uopz')) {
            // Leave here if uopz is not loaded, as remaining test scenario depends on it.
            $this->markTestIncomplete('Substituting future match interval requires uopz php extension.');
        }

        // Set time to future to overcome matchinterval.
        uopz_set_return('time', time() + HOURSECS + MINSECS);
        // User is matched again.
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule->id));
        $this->assertCount(2, $this->get_generator()->get_matching_users_for_outcomes($rule->id));
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

        $this->ruledefaulttenant = $this->get_generator()->create_rule(['tenantid' => $defaulttenantid, 'enabled' => 1]);
        $this->ruledefaulttenantinstance = new \tool_dynamicrule\rule(0, $this->ruledefaulttenant);
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Barcelona', 'city_op' => 2];
        \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($this->ruledefaulttenant->id, $configdata);
        $this->get_generator()->create_outcome_donothing($this->ruledefaulttenant->id);

        $this->ruleothertenant = $this->get_generator()->create_rule(['tenantid' => $othertenant->id, 'enabled' => 1]);
        $this->ruleothertenantinstance = new \tool_dynamicrule\rule(0, $this->ruleothertenant);
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Barcelona', 'city_op' => 2];
        \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($this->ruleothertenant->id, $configdata);
        $this->get_generator()->create_outcome_donothing($this->ruleothertenant->id);

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $this->rulesharedtenant = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid, 'enabled' => 1]);
        $this->rulesharedtenantinstance = new \tool_dynamicrule\rule(0, $this->rulesharedtenant);
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Barcelona', 'city_op' => 2];
        \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($this->rulesharedtenant->id, $configdata);
        $this->get_generator()->create_outcome_donothing($this->rulesharedtenant->id);
    }

    /**
     * Test 1.
     * Count of users should return 2 for the first rule, 1 for the second rule and 3 for shared rule.
     * Number of matches users - should show user11&user12 for the first rule, user23 for the second rule
     * and all 3 for the shared rule.
     */
    public function test_get_matching_users_one() {
        $this->get_matching_users_setup();
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($this->ruledefaulttenant->id));
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($this->ruleothertenant->id));
        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($this->rulesharedtenant->id));

        $users = \tool_dynamicrule\api::get_matching_users($this->ruledefaulttenant->id);
        $this->assertEqualsCanonicalizing([$this->user11->id, $this->user12->id], array_column($users, 'id'));

        $users = \tool_dynamicrule\api::get_matching_users($this->ruleothertenant->id);
        $this->assertEqualsCanonicalizing([$this->user23->id], array_column($users, 'id'));

        $users = \tool_dynamicrule\api::get_matching_users($this->rulesharedtenant->id);
        $this->assertEqualsCanonicalizing([$this->user11->id, $this->user12->id, $this->user23->id], array_column($users, 'id'));
    }

    /**
     * Test 2.
     * Getting matches for outcome should return user11&user12 for the first rule,
     * user23 for the second rule, and all users for shared rule.
     */
    public function test_get_matching_users_two() {
        $this->get_matching_users_setup();

        $users = $this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id);
        $this->assertEqualsCanonicalizing([$this->user12->id, $this->user11->id], array_column($users, 'id'));

        $users = $this->get_generator()->get_matching_users_for_outcomes($this->ruleothertenant->id);
        $this->assertEqualsCanonicalizing([$this->user23->id], array_column($users, 'id'));

        $users = $this->get_generator()->get_matching_users_for_outcomes($this->rulesharedtenant->id);
        $this->assertEqualsCanonicalizing([$this->user12->id, $this->user11->id, $this->user23->id], array_column($users, 'id'));
    }

    /**
     * Test 3.
     * Process rules.
     * Getting matches for outcome should return empty array for all rules.
     */
    public function test_get_matching_users_three() {
        $this->get_matching_users_setup();

        // Process rules.
        \tool_dynamicrule\api::process_rule($this->ruledefaulttenantinstance);
        \tool_dynamicrule\api::process_rule($this->ruleothertenantinstance);
        \tool_dynamicrule\api::process_rule($this->rulesharedtenantinstance);

        // No longer matching.
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id));
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruleothertenant->id));
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->rulesharedtenant->id));
    }

    /**
     * Test 4 and 5
     * Process rules.
     * Set city Barcelona for user14
     * Getting matches for outcome should show user14 for the first rule, empty
     * array for the second rule, and user14 for shared rule.
     */
    public function test_get_matching_users_four_five() {
        global $DB;

        $this->get_matching_users_setup();

        // Process rules.
        \tool_dynamicrule\api::process_rule($this->ruledefaulttenantinstance);
        \tool_dynamicrule\api::process_rule($this->ruleothertenantinstance);
        \tool_dynamicrule\api::process_rule($this->rulesharedtenantinstance);

        $this->user14->city = 'Barcelona';
        $DB->update_record('user', $this->user14);

        $users = $this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user14->id], array_column($users, 'id'));

        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruleothertenant->id));

        $users = $this->get_generator()->get_matching_users_for_outcomes($this->rulesharedtenant->id);
        $this->assertEquals([$this->user14->id], array_column($users, 'id'));
    }

    /**
     * Test 6 and 7
     * Process rules.
     * Set city Perth for user12
     * Getting matches for outcome should show empty array for all rules.
     */
    public function test_get_matching_users_six_seven() {
        global $DB;

        $this->get_matching_users_setup();

        // Process rules.
        \tool_dynamicrule\api::process_rule($this->ruledefaulttenantinstance);
        \tool_dynamicrule\api::process_rule($this->ruleothertenantinstance);
        \tool_dynamicrule\api::process_rule($this->rulesharedtenantinstance);

        $this->user12->city = 'Perth';
        $DB->update_record('user', $this->user12);

        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id));
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruleothertenant->id));
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->rulesharedtenant->id));
    }

    /**
     * Test 8 and 9.
     * Process rules.
     * Set city Perth for user12
     * Process rules.
     * Set city Barcelona for user12
     * Getting matches for outcome should show user12 for the first rule, empty array for the second rule
     * and user12 for the shared rule.
     */
    public function test_get_matching_users_eight_nine() {
        global $DB;

        $this->get_matching_users_setup();

        // Process rules.
        \tool_dynamicrule\api::process_rule($this->ruledefaulttenantinstance);
        \tool_dynamicrule\api::process_rule($this->ruleothertenantinstance);
        \tool_dynamicrule\api::process_rule($this->rulesharedtenantinstance);

        $this->user12->city = 'Perth';
        $DB->update_record('user', $this->user12);

        // Process rules.
        \tool_dynamicrule\api::process_rule($this->ruledefaulttenantinstance);
        \tool_dynamicrule\api::process_rule($this->ruleothertenantinstance);
        \tool_dynamicrule\api::process_rule($this->rulesharedtenantinstance);

        $this->user12->city = 'Barcelona';
        $DB->update_record('user', $this->user12);

        $users = $this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));

        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruleothertenant->id));

        $users = $this->get_generator()->get_matching_users_for_outcomes($this->rulesharedtenant->id);
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));
    }

    /**
     * Test 10 (only test rule1).
     * Update rule1, set matchlimit = 2, matchinterval = 1 hour
     * Execute changing countries test (a, b)
     * c) fast-forward one hour and one minute
     * Getting matches for outcome should return user12.
     */
    public function test_get_matching_users_ten() {
        $this->get_matching_users_setup();

        $this->ruledefaulttenant->matchinterval = HOURSECS;
        $this->ruledefaulttenant->matchlimit = 2;
        \tool_dynamicrule\api::update_rule($this->ruledefaulttenant->id, $this->ruledefaulttenant);

        $this->changing_countries_test();

        if (!extension_loaded('uopz')) {
            // Leave here if uopz is not loaded, as remaining test scenario depends on it.
            $this->markTestIncomplete('Substituting future match interval requires uopz php extension.');
        }
        // C - Testing with time in future to match again.
        uopz_set_return('time', time() + HOURSECS + MINSECS);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($this->ruledefaulttenant->id));
        $users = $this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id);
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));
    }

    /**
     * Test 11 (only test rule1).
     * Update rule, set matchlimit = 2, matchinterval - ever
     * - same test as 10 except it does not need part c)
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
     * Getting matches for outcome should return user11&user12
     * Process rule to match.
     * a)
     * set city Perth for user12
     * Process rule to unmatch user12.
     * Getting matches for outcome should return empty array
     * set city Barcelona for user12
     * Getting matches for outcome should return user12
     * Process rule to match user12
     * b)
     * set city Perth for user12
     * Process rule to unmatch user12.
     * set city Barcelona for user12
     * Process rule to match user12.
     * Getting matches for outcome should return empty array
     */
    private function changing_countries_test() {
        global $DB;

        $users = $this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id);
        // First match for the user12.
        $this->assertEqualsCanonicalizing([$this->user11->id, $this->user12->id], array_column($users, 'id'));

        // Process rule first time (matched).
        \tool_dynamicrule\api::process_rule($this->ruledefaulttenantinstance);
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id));

        // A - change city to unmatch and change back to match again.
        $this->user12->city = 'Perth';
        $DB->update_record('user', $this->user12);

        // Process rule (unmatched).
        \tool_dynamicrule\api::process_rule($this->ruledefaulttenantinstance);
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id));

        $this->user12->city = 'Barcelona';
        $DB->update_record('user', $this->user12);

        $users = $this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id);
        // Second match for user12.
        $this->assertEquals([$this->user12->id], array_column($users, 'id'));

        // Process rule (matched).
        \tool_dynamicrule\api::process_rule($this->ruledefaulttenantinstance);
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id));

        // B - Change city to unmatch an change back to npt match again because of matchlimit.
        $this->user12->city = 'Perth';
        $DB->update_record('user', $this->user12);

        // Process rule (unmatched).
        \tool_dynamicrule\api::process_rule($this->ruledefaulttenantinstance);
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id));

        $this->user12->city = 'Barcelona';
        $DB->update_record('user', $this->user12);

        // The matchlimit was already hit by user12 at second match, so no matching.
        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($this->ruledefaulttenant->id));
    }

    /**
     * Test get_matching_users using two conditions instances of same condition class for same rule.
     *
     * @covers \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_for_outcomes_two_conditions_same_type_same_rule() {

        $user1 = $this->getDataGenerator()->create_user(['city' => 'Perth']);
        $user2 = $this->getDataGenerator()->create_user(['city' => 'Barcelona']);

        $rule = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Perth', 'city_op' => 2];
        $condcity = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));

        $users = $this->get_generator()->get_matching_users_for_outcomes($rule->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'));

        $condcity->update_configdata(['userprofilefield' => 'city', 'city_value' => 'Florianópolis', 'city_op' => 2], true);

        $this->assertEmpty($this->get_generator()->get_matching_users_for_outcomes($rule->id));
    }
}
