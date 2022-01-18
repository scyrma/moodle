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
 * File contains the unit tests for outcome course_unenrol class.
 *
 * @package    enrol_dynamicrule
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol;

/**
 * Unit tests for outcome\course_enrol  class.
 *
 * @package    enrol_dynamicrule
 * @group      enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class enrol_dynamicrule_outcome_course_unenrol_testcase extends advanced_testcase {

    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        global $CFG;

        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');

        $this->resetAfterTest();
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
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
     * Test get_title
     */
    public function test_get_title() {
        $outcome = course_unenrol::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = course_unenrol::instance();
        $this->assertEquals(get_string('courses'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $outcome = course_unenrol::instance();
        $configform = ['coursetounenrol' => 10];
        $this->assertArrayHasKey('coursetounenrol', $outcome->validate_config_form($configform));

        $course1 = $this->getDataGenerator()->create_course();

        $configform = ['coursetounenrol' => $course1->id];
        $this->assertArrayNotHasKey('coursetoenrol', $outcome->validate_config_form($configform));

        $tenant = $this->tenantgenerator->create_tenant();
        $tenantuser0 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($tenantuser0->id, $tenant->id);
        $defaultenrolmentmethod = course_unenrol::DEFAULT_ENROLMENT_METHOD;

        $this->setUser($tenantuser0);

        // Check user without unenrol capability in default method can't add current action.
        $configform = ['coursetounenrol' => $course1->id, 'enrolmentmethod' => $defaultenrolmentmethod];
        $this->assertArrayHasKey('enrolmentmethod', $outcome->validate_config_form($configform));

        // Add capability to unenrol default method.
        $roleunenrol = create_role('Unenrol role', 'unenrolrole', 'Rol to test unenrol');
        assign_capability("enrol/{$defaultenrolmentmethod}:unenrol", CAP_ALLOW, $roleunenrol,
            context_system::instance()->id, true);
        role_assign($roleunenrol, $tenantuser0->id, context_course::instance($course1->id)->id);

        // Check user with unenrol capability in default method can add current action.
        $configform = ['coursetounenrol' => $course1->id, 'enrolmentmethod' => $defaultenrolmentmethod];
        $this->assertArrayNotHasKey('enrolmentmethod', $outcome->validate_config_form($configform));
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        // Anyone can add this outcome.
        $outcome = course_unenrol::instance();
        $this->assertTrue($outcome->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $course0 = $this->getDataGenerator()->create_course();
        $configform = ['coursetounenrol' => $course0->id];
        $outcome = course_unenrol::instance();

        // Admin user.
        $this->setAdminUser();
        $this->assertTrue($outcome->user_can_edit($configform));

        // Non-priveleged user.
        $user = $this->getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($outcome->user_can_edit($configform));

        // User without unenrol capability in default method can't edit DR.
        $configformenrol = $configform + ['enrolmentmethod' => course_unenrol::DEFAULT_ENROLMENT_METHOD];
        $this->assertFalse($outcome->user_can_edit($configformenrol));

        // Grant priveleges to user.
        $this->getDataGenerator()->enrol_user($user->id, $course0->id, 'manager');
        $this->assertTrue($outcome->user_can_edit($configform));

        // User with unenrol capability in default method can edit DR.
        $this->assertTrue($outcome->user_can_edit($configformenrol));
    }

    /**
     * Test apply_to_users disable enrolment
     */
    public function test_apply_to_users_disable_enrolment() {
        global $DB;

        $this->apply_to_users_setup();

        $configdata = ['coursetounenrol' => $this->course1->id,
            'action' => course_unenrol::ACTION_DISABLE_ENROLMENT];
        $outcome = course_unenrol::create($this->rule0->id, $configdata);

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $this->rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $this->assertEquals(1, $DB->count_records('user_enrolments',
            ['userid' => $this->user1->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(1, $DB->count_records('role_assignments', ['userid' => $this->user1->id]));

        $this->assertEquals(0, $DB->count_records('user_enrolments',
            ['userid' => $this->user2->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(1, $DB->count_records('role_assignments', ['userid' => $this->user2->id]));
    }

    /**
     * Test apply_to_users disable any enrolment method
     */
    public function test_apply_to_users_disable_multiple_enrolment() {
        global $DB;

        $this->apply_to_users_setup();
        $this->setAdminUser();
        $configdatamultipleenrol = [
            'coursetounenrol' => $this->course1->id,
            'enrolmentmethod' => [course_unenrol::DEFAULT_ENROLMENT_METHOD, 'manual'],
            'action' => course_unenrol::ACTION_DISABLE_ENROLMENT
        ];

        course_unenrol::create($this->rule0->id, $configdatamultipleenrol);

        // Process rule manually as if we enabled it.
        $ruleinstancemultipleenrol = new \tool_dynamicrule\rule(0, $this->rule0);
        \tool_dynamicrule\api::process_rule($ruleinstancemultipleenrol);

        $this->assertEquals(1, $DB->count_records('user_enrolments',
            ['userid' => $this->user1->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(1, $DB->count_records('role_assignments', ['userid' => $this->user1->id]));

        $this->assertEquals(1, $DB->count_records('user_enrolments',
            ['userid' => $this->user2->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(1, $DB->count_records('role_assignments', ['userid' => $this->user2->id]));
    }

    /**
     * Test apply_to_users disable enrolment and remove roles
     */
    public function test_apply_to_users_disable_enrolment_remove_roles() {
        global $DB;

        $this->apply_to_users_setup();

        $configdata = ['coursetounenrol' => $this->course1->id,
            'action' => course_unenrol::ACTION_DISABLE_ENROLMENT_REMOVE_ROLES];
        $outcome = course_unenrol::create($this->rule0->id, $configdata);

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $this->rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $this->assertEquals(1, $DB->count_records('user_enrolments',
            ['userid' => $this->user1->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(0, $DB->count_records('role_assignments', ['userid' => $this->user1->id]));

        $this->assertEquals(0, $DB->count_records('user_enrolments',
            ['userid' => $this->user2->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(1, $DB->count_records('role_assignments', ['userid' => $this->user2->id]));
    }

    /**
     * Test apply_to_users disable enrolment and remove roles of any method
     */
    public function test_apply_to_users_disable_multiple_enrolment_remove_roles() {
        global $DB;

        $this->apply_to_users_setup();
        $this->setAdminUser();

        $configdatamultipleanyremove = [
            'coursetounenrol' => $this->course1->id,
            'enrolmentmethod' => [course_unenrol::DEFAULT_ENROLMENT_METHOD, 'manual'],
            'action' => course_unenrol::ACTION_DISABLE_ENROLMENT_REMOVE_ROLES
        ];

        course_unenrol::create($this->rule0->id, $configdatamultipleanyremove);

        // Process rule manually as if we enabled it.
        $ruleinstancemultipleremove = new \tool_dynamicrule\rule(0, $this->rule0);
        \tool_dynamicrule\api::process_rule($ruleinstancemultipleremove);

        $this->assertEquals(1, $DB->count_records('user_enrolments',
            ['userid' => $this->user1->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(0, $DB->count_records('role_assignments', ['userid' => $this->user1->id]));

        $this->assertEquals(1, $DB->count_records('user_enrolments',
            ['userid' => $this->user2->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(0, $DB->count_records('role_assignments', ['userid' => $this->user2->id]));
    }

    /**
     * Test apply_to_users unenrol
     */
    public function test_apply_to_users_unenrol() {
        global $DB;

        $this->apply_to_users_setup();

        $configdata = ['coursetounenrol' => $this->course1->id,
            'action' => course_unenrol::ACTION_UNENROL];
        $outcome = course_unenrol::create($this->rule0->id, $configdata);

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $this->rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $this->assertEquals(0, $DB->count_records('user_enrolments', ['userid' => $this->user1->id]));
        $this->assertEquals(0, $DB->count_records('role_assignments', ['userid' => $this->user1->id]));

        $this->assertEquals(0, $DB->count_records('user_enrolments',
            ['userid' => $this->user2->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(1, $DB->count_records('role_assignments', ['userid' => $this->user2->id]));
    }

    /**
     * Test apply_to_users unenrol any method
     */
    public function test_apply_to_users_multiple_unenrol() {
        global $DB;

        $this->apply_to_users_setup();
        $this->setAdminUser();

        $configdatamultiple = [
            'coursetounenrol' => $this->course1->id,
            'enrolmentmethod' => [course_unenrol::DEFAULT_ENROLMENT_METHOD, 'manual'],
            'action' => course_unenrol::ACTION_UNENROL
        ];
        course_unenrol::create($this->rule0->id, $configdatamultiple);

        // Process rule manually as if we enabled it.
        $ruleinstancemultiple = new \tool_dynamicrule\rule(0, $this->rule0);
        \tool_dynamicrule\api::process_rule($ruleinstancemultiple);

        $this->assertEquals(0, $DB->count_records('user_enrolments', ['userid' => $this->user1->id]));
        $this->assertEquals(0, $DB->count_records('role_assignments', ['userid' => $this->user1->id]));

        $this->assertEquals(0, $DB->count_records('user_enrolments', ['userid' => $this->user2->id]));
        $this->assertEquals(0, $DB->count_records('role_assignments', ['userid' => $this->user2->id]));
    }

    /**
     * This is used by functions that test apply_to_users
     */
    private function apply_to_users_setup() {
        global $CFG;

        // Create users.
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();

        // Create rule.
        $this->rule0 = $this->get_generator()->create_rule(['enabled' => 1]);

        // Create course and enrol users.
        $this->course1 = $this->getDataGenerator()->create_course();
        $CFG->enrol_plugins_enabled .= ',dynamicrule';
        $plugin = enrol_get_plugin('dynamicrule');
        $plugin->add_instance(get_course($this->course1->id), ['customint1' => $this->rule0->id]);

        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course1->id, 'student', 'dynamicrule');
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course1->id, 'student', 'manual');

        // Finally add condition to rule.
        $this->get_generator()->create_condition_alwaystrue($this->rule0->id);
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        // Set rules, courses and variables.
        $rule0 = $this->get_generator()->create_rule();
        $rule1 = $this->get_generator()->create_rule();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $straction = get_string('actiondisableenrolment', 'enrol_dynamicrule');

        // Create course unenrol action with default enrolment method.
        $configdataenrolmethod = ['coursetounenrol' => $course2->id, 'action' => 0];
        $outcomeenrolmethod = course_unenrol::create($rule1->id, $configdataenrolmethod);
        $strparamsenrolmethod = [
            'coursename' => $course2->fullname,
            'action' => $straction,
            'enrol' => get_string('pluginname', 'enrol_' . course_unenrol::DEFAULT_ENROLMENT_METHOD)
        ];
        $expectedenrolmethod = get_string('outcomecourseunenroldescriptionwithmethod', 'enrol_dynamicrule', $strparamsenrolmethod);

        $this->assertEquals($expectedenrolmethod, $outcomeenrolmethod->get_description());

        // Create course unenrol action with multiple enrolment methods.
        $methods = [course_unenrol::DEFAULT_ENROLMENT_METHOD, 'manual'];
        $enrolmentsmethods = implode(', ', array_map(function(string $enrol) {
            return get_string('pluginname', 'enrol_' . format_string($enrol, true, ['escape' => false]));
        }, $methods));
        $configdatamultiple = ['coursetounenrol' => $course1->id, 'action' => 0, 'enrolmentmethod' => $methods];
        $outcomemultiple = course_unenrol::create($rule0->id, $configdatamultiple);
        $strparamsmultiple = [
            'coursename' => $course1->fullname,
            'action' => $straction,
            'enrol' => $enrolmentsmethods
        ];
        $expectedmultiple = get_string('outcomecourseunenroldescriptionwithmethod', 'enrol_dynamicrule', $strparamsmultiple);

        $this->assertEquals($expectedmultiple, $outcomemultiple->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        // Valid course.
        $course1 = $this->getDataGenerator()->create_course();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['coursetounenrol' => $course1->id];
        $outcome1 = course_unenrol::create($rule1->id, $configdata);
        $this->assertTrue($outcome1->is_configuration_valid());

        // Delete course.
        $DB->delete_records('course', ['id' => $course1->id]);
        $this->assertFalse($outcome1->is_configuration_valid());
    }

    /**
     * Test get_broken_description
     */
    public function test_get_broken_description() {
        $course1 = $this->getDataGenerator()->create_course();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['coursetounenrol' => $course1->id];
        $outcome1 = course_unenrol::create($rule1->id, $configdata);

        $this->assertNotEmpty($outcome1->get_broken_description());
    }

    /**
     * Test is_available
     */
    public function test_is_available() {
        $course0 = $this->getDataGenerator()->create_course();
        $outcome = course_unenrol::instance();

        // Admin user.
        $this->setAdminUser();
        $this->assertTrue($outcome::is_available());

        // Non-priveleged user.
        $user = $this->getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($outcome::is_available());

        // Grant priveleges to user.
        $this->getDataGenerator()->enrol_user($user->id, $course0->id, 'manager');
        cache_helper::purge_by_event('changesincourse');

        $this->assertTrue($outcome::is_available());
    }

    /**
     * Test get_get_not_available_label
     */
    public function test_get_not_available_label() {
        $course1 = $this->getDataGenerator()->create_course();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['coursetoenrol' => $course1->id];
        $outcome1 = course_unenrol::create($rule1->id, $configdata);

        $this->assertNotEmpty($outcome1->get_not_available_label());
    }
}
