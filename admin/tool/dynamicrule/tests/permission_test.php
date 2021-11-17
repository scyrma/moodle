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
 * File containing tests for permission class
 *
 * @package     tool_dynamicrule
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\api;
use tool_dynamicrule\permission;

/**
 * Test class
 *
 * @package     tool_dynamicrule
 * @group       tool_dynamicrule
 * @category    test
 * @covers      \tool_dynamicrule\permission
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_permission_testcase extends advanced_testcase {

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void {
        $this->get_plugin_generator()->reset_condition_alwaystrue();
        $this->get_plugin_generator()->reset_outcome_donothing();
        $this->resetAfterTest();
    }

    /**
     * Test can_create_rule method for user with capability to do so
     *
     * @return void
     */
    public function test_can_create_rule() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        role_change_permission($roleid, context_system::instance(), 'tool/dynamicrule:manage', CAP_ALLOW);

        $this->assertTrue(permission::can_create_rule());
    }

    /**
     * Test can_create_rule method for user without capability to do so
     *
     * @return void
     */
    public function test_can_create_rule_no_capability() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse(permission::can_create_rule());
    }

    /**
     * Test can_create_rule method while observing site/tenant limits
     *
     * @return void
     */
    public function test_can_create_observe_limits() {
        global $DB, $CFG;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        role_change_permission($roleid, context_system::instance(), 'tool/dynamicrule:manage', CAP_ALLOW);

        $anothertenant = $this->get_tenant_generator()->create_tenant();

        // Test with site limit set to 0 and limits disabled.
        $CFG->tool_dynamicrule_limitsenabled = false;
        $CFG->tool_dynamicrule_sitelimit = 0;
        $this->assertTrue(permission::can_create_rule());

        // Enable limits.
        $CFG->tool_dynamicrule_limitsenabled = true;
        $this->assertFalse(permission::can_create_rule());

        // Test with limits ignored.
        $this->assertTrue(permission::can_create_rule(true));

        // Set site limit to two.
        $CFG->tool_dynamicrule_sitelimit = 2;
        $this->assertTrue(permission::can_create_rule());

        // Create a rule.
        $this->get_plugin_generator()->create_rule();
        $this->assertTrue(permission::can_create_rule());

        // Create a rule in another tenant.
        $this->get_plugin_generator()->create_rule(['tenantid' => $anothertenant->id]);
        $this->assertFalse(permission::can_create_rule());

        // Test with limits ignore.
        $this->assertTrue(permission::can_create_rule(true));

        // Test with tenant limit set to 0 and limits disabled.
        $CFG->tool_dynamicrule_limitsenabled = false;
        unset($CFG->tool_dynamicrule_sitelimit);
        $CFG->tool_dynamicrule_tenantlimit = 0;
        $this->assertTrue(permission::can_create_rule());

        // Enable limits.
        $CFG->tool_dynamicrule_limitsenabled = true;
        $this->assertFalse(permission::can_create_rule());

        // Test with limits ignored.
        $this->assertTrue(permission::can_create_rule(true));

        // Set tenant limit to 2.
        $CFG->tool_dynamicrule_tenantlimit = 2;

        // Current tenant only has one rule, so user should be able to create another.
        $this->assertTrue(permission::can_create_rule());

        // Create second rule and test.
        $this->get_plugin_generator()->create_rule();
        $this->assertFalse(permission::can_create_rule());

        // Test with limits ignore.
        $this->assertTrue(permission::can_create_rule(true));
    }

    /**
     * Get plugin test generator
     *
     * @return tool_dynamicrule_generator
     */
    private function get_plugin_generator() : tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Get tenant test generator
     *
     * @return tool_tenant_generator
     */
    private function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test require_can_use_edit_rule_interface method
     *
     * @return void
     */
    public function test_require_can_use_edit_rule_interface(): void {
        $tenantid = \tool_tenant\tenancy::get_tenant_id();
        $ruleid = api::create_rule_for_component('tool_program', 'program', 0, $tenantid, 'test');
        $rule = api::get_rule($ruleid);

        $this->expectException(\moodle_exception::class);
        permission::require_can_use_edit_rule_interface($rule);
    }

    /**
     * Test can_edit_condition.
     */
    public function test_can_edit_condition() {
        $rule = $this->get_plugin_generator()->create_rule();
        $condition = $this->get_plugin_generator()->create_condition_alwaystrue($rule->id);

        // Sanity check.
        $this->assertTrue(permission::can_edit_condition($condition));

        // Editing is not possible.
        $condition::set_user_can_edit(false);
        $this->assertFalse(permission::can_edit_condition($condition));

        // Editing is not possible, but condition is broken and instance adding is permitted.
        $condition->mark_as_broken();
        $this->assertTrue(permission::can_edit_condition($condition));

        // Editing is not possible, but condition is broken and instance adding is not permitted.
        $condition::set_user_can_add(false);
        $this->assertFalse(permission::can_edit_condition($condition));
    }

    /**
     * Test can_edit_condition.
     */
    public function test_can_delete_condition() {
        $rule = $this->get_plugin_generator()->create_rule();
        $condition = $this->get_plugin_generator()->create_condition_alwaystrue($rule->id);

        // Sanity check.
        $this->assertTrue(permission::can_delete_condition($condition));

        // Editing is not possible.
        $condition::set_user_can_edit(false);
        $this->assertFalse(permission::can_delete_condition($condition));

        // Editing is not possible, but condition is broken and instance adding is permitted.
        $condition->mark_as_broken();
        $this->assertTrue(permission::can_delete_condition($condition));

        // Editing is not possible, but condition is broken and instance adding is not permitted.
        $condition::set_user_can_add(false);
        $this->assertFalse(permission::can_delete_condition($condition));

        // Condition class is missing.
        $persistent = new \tool_dynamicrule\condition($condition->get_id());
        $persistent->set('classname', $persistent->get('classname') . 'missing');
        $persistent->save();

        // Expect dummy condition.
        $condition = \tool_dynamicrule\api::get_rule_conditions($rule->id)[0];
        $this->assertTrue($condition->is_dummy());
        $this->assertTrue(permission::can_delete_condition($condition));
    }

    /**
     * Test can_edit_outcome.
     */
    public function test_can_edit_outcome() {
        $rule = $this->get_plugin_generator()->create_rule();
        $outcome = $this->get_plugin_generator()->create_outcome_donothing($rule->id);

        // Sanity check.
        $this->assertTrue(permission::can_edit_outcome($outcome));

        // Editing is not possible.
        $outcome::set_user_can_edit(false);
        $this->assertFalse(permission::can_edit_outcome($outcome));

        // Editing is not possible, but outcome is broken and instance adding is permitted.
        $outcome->mark_as_broken();
        $this->assertTrue(permission::can_edit_outcome($outcome));

        // Editing is not possible, but outcome is broken and instance adding is not permitted.
        $outcome::set_user_can_add(false);
        $this->assertFalse(permission::can_edit_outcome($outcome));
    }

    /**
     * Test can_delete_outcome.
     */
    public function test_can_delete_outcome() {
        $rule = $this->get_plugin_generator()->create_rule();
        $outcome = $this->get_plugin_generator()->create_outcome_donothing($rule->id);

        // Sanity check.
        $this->assertTrue(permission::can_delete_outcome($outcome));

        // Editing is not possible.
        $outcome::set_user_can_edit(false);
        $this->assertFalse(permission::can_delete_outcome($outcome));

        // Editing is not possible, but outcome is broken and instance adding is permitted.
        $outcome->mark_as_broken();
        $this->assertTrue(permission::can_delete_outcome($outcome));

        // Editing is not possible, but outcome is broken and instance adding is not permitted.
        $outcome::set_user_can_add(false);
        $this->assertFalse(permission::can_delete_outcome($outcome));

        // Outcome class is missing.
        $persistent = new \tool_dynamicrule\outcome($outcome->get_id());
        $persistent->set('classname', $persistent->get('classname') . 'missing');
        $persistent->save();

        // Expect dummy outcome.
        $outcome = \tool_dynamicrule\api::get_rule_outcomes($rule->id)[0];
        $this->assertTrue($outcome->is_dummy());
        $this->assertTrue(permission::can_delete_outcome($outcome));

    }

    /**
     * Test can_edit_all_rule_conditions
     *
     * @return void
     */
    public function test_can_edit_all_rule_conditions(): void {
        $course1 = self::getDataGenerator()->create_course();
        $user1 = self::getDataGenerator()->create_user();
        self::setUser($user1);

        $rule = $this->get_plugin_generator()->create_rule();

        $configdata = ['courseid' => $course1->id];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\course_completed::create($rule->id, $configdata);

        $this->assertFalse(permission::can_edit_all_rule_conditions(api::get_rule($rule->id)));

        // We assign capability to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = context_course::instance($course1->id);
        assign_capability('moodle/course:update', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user1->id, $context->id);

        $this->assertTrue(permission::can_edit_all_rule_conditions(api::get_rule($rule->id)));
    }

    /**
     * Test can_edit_all_rule_outcomes
     *
     * @return void
     */
    public function test_can_edit_all_rule_outcomes(): void {
        $user1 = $this->getDataGenerator()->create_user();
        self::setUser($user1);

        $rule = $this->get_plugin_generator()->create_rule();

        $generator = self::getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency(array('competencyframeworkid' => $framework->get('id')));

        $configdata = ['competency' => $competency->get('id')];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\competency::create($rule->id, $configdata);

        $this->assertFalse(permission::can_edit_all_rule_outcomes(api::get_rule($rule->id)));

        // We assign capability to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = context_system::instance();
        role_assign($roleid, $user1->id, $context->id);
        assign_capability('moodle/competency:competencygrade', CAP_ALLOW, $roleid, $context->id);

        $this->assertTrue(permission::can_edit_all_rule_outcomes(api::get_rule($rule->id)));
    }
}
