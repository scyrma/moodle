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
 * File contains the unit tests for outcome course_enrol class.
 *
 * @package    enrol_dynamicrule
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol;
use tool_dynamicrule\rule;

/**
 * Unit tests for outcome\course_enrol  class.
 *
 * @package    enrol_dynamicrule
 * @group      enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class enrol_dynamicrule_outcome_course_enrol_testcase extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        global $CFG;
        $this->resetAfterTest();
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
        }
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $outcome = course_enrol::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $outcome->supports_rule_types());
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
        $outcome = course_enrol::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = course_enrol::instance();
        $this->assertEquals(get_string('courses'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        global $DB;

        $outcome = course_enrol::instance();
        $configform = ['coursetoenrol' => 10];
        $this->assertArrayHasKey('coursetoenrol', $outcome->validate_config_form($configform));
        $this->assertArrayHasKey('role', $outcome->validate_config_form($configform));

        $course1 = $this->getDataGenerator()->create_course();

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configform = ['coursetoenrol' => $course1->id, 'role' => $roleid];
        $this->assertArrayNotHasKey('coursetoenrol', $outcome->validate_config_form($configform));
        $this->assertArrayNotHasKey('role', $outcome->validate_config_form($configform));
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form_shared_tenant() {
        global $DB;

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantmanager = new \tool_tenant\manager();

        $tenantcat = $this->getDataGenerator()->create_category();
        [$tenant, [$user1]] = $tenantgenerator->create_tenant_and_users(1);
        $tenantmanager->update_tenant($tenant->id, (object)['categoryid' => $tenantcat->id]);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $rule = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid, 'enabled' => 1]);

        $coursetenant = $this->getDataGenerator()->create_course(['category' => $tenantcat->id]);
        $courseshared = $this->getDataGenerator()->create_course();

        $configformfail = ['coursetoenrol' => $coursetenant->id, 'role' => $roleid];
        $outcomefail = course_enrol::create($rule->id, $configformfail);
        $this->assertArrayHasKey('coursetoenrol', $outcomefail->validate_config_form($configformfail));

        $configformpass = ['coursetoenrol' => $courseshared->id, 'role' => $roleid];
        $outcomepass = course_enrol::create($rule->id, $configformpass);
        $this->assertArrayNotHasKey('coursetoenrol', $outcomepass->validate_config_form($configformpass));
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        // Anyone can add this outcome.
        $outcome = course_enrol::instance();
        $this->assertTrue($outcome->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $course0 = $this->getDataGenerator()->create_course();
        $configform = ['coursetoenrol' => $course0->id];
        $outcome = course_enrol::instance();

        // Admin user.
        $this->setAdminUser();
        $this->assertTrue($outcome->user_can_edit($configform));

        // Non-priveleged user.
        $user = $this->getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($outcome->user_can_edit($configform));

        // Grant priveleges to user.
        $this->getDataGenerator()->enrol_user($user->id, $course0->id, 'manager');
        $this->assertTrue($outcome->user_can_edit($configform));
    }

    /**
     * Test apply_to_users
     */
    public function test_apply_to_user() {
        global $DB, $CFG;

        // Users go first (so we don't trigger rule on create user event).
        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();

        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $course1 = $this->getDataGenerator()->create_course();
        $enddate = time() + WEEKSECS;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course1->id, 'role' => $roleid, 'enddate' => $enddate];
        $outcome = course_enrol::create($rule0->id, $configdata);

        // Make plugin disabled by default.
        $CFG->enrol_plugins_enabled = '';
        $this->assertFalse(enrol_is_enabled('dynamicrule'));

        $this->assertEquals(0, $DB->count_records('enrol', ['enrol' => 'dynamicrule']));
        $this->assertEquals(0, $DB->count_records('user_enrolments'));

        $this->assertEquals(0, $DB->count_records('groups'));
        $this->assertEquals(0, $DB->count_records('groups_members'));

        // Now apply on valid users (2 created + 1 admin user).
        $ruleinstance = new rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Check plugin has been enabled (we force-enable in on enrol request).
        $this->assertTrue(enrol_is_enabled('dynamicrule'));

        $params = ['courseid' => $course1->id, 'enrol' => 'dynamicrule', 'customint1' => $rule0->id];
        $this->assertEquals(1, $DB->count_records('enrol', $params));
        $this->assertEquals(3, $DB->count_records('user_enrolments'));

        $this->assertEquals(0, $DB->count_records('groups'));
        $this->assertEquals(0, $DB->count_records('groups_members'));

        // Check end date has been applied.
        $record = $DB->get_record('user_enrolments', ['userid' => $user0->id]);
        $this->assertEquals($enddate, $record->timeend);

        // Create another user (this will trigger same rule on user create event).
        $this->getDataGenerator()->create_user();

        $params = ['courseid' => $course1->id, 'enrol' => 'dynamicrule', 'customint1' => $rule0->id];
        $this->assertEquals(1, $DB->count_records('enrol', $params));
        $this->assertEquals(4, $DB->count_records('user_enrolments'));

        $this->assertEquals(0, $DB->count_records('groups'));
        $this->assertEquals(0, $DB->count_records('groups_members'));
    }

    /**
     * Test apply_to_user with group adding.
     */
    public function test_apply_to_user_with_group_adding() {
        global $DB;

        // Users (plus admin user already exists, i.e. 4 in total).
        $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user();

        $rule1 = $this->get_generator()->create_rule(['enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule1->id);

        $course1 = $this->getDataGenerator()->create_course();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course1->id, 'role' => $roleid, 'group' => 'Dynamic group'];
        $outcome = course_enrol::create($rule1->id, $configdata);

        // Now apply on valid users.
        $ruleinstance = new rule(0, $rule1);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $params = ['courseid' => $course1->id, 'enrol' => 'dynamicrule', 'customint1' => $rule1->id];
        $this->assertEquals(1, $DB->count_records('enrol', $params));
        $this->assertEquals(4, $DB->count_records('user_enrolments'));

        $this->assertEquals(1, $DB->count_records('groups'));
        $this->assertEquals(4, $DB->count_records('groups_members'));

        // Another user (this will trigger same rule on user create event).
        $this->getDataGenerator()->create_user();

        $params = ['courseid' => $course1->id, 'enrol' => 'dynamicrule', 'customint1' => $rule1->id];
        $this->assertEquals(1, $DB->count_records('enrol', $params));
        $this->assertEquals(5, $DB->count_records('user_enrolments'));

        $this->assertEquals(1, $DB->count_records('groups'));
        $this->assertEquals(5, $DB->count_records('groups_members'));
    }

    /**
     * Test enrol user with shared dynamic rules.
     */
    public function test_enrol_user_with_shared_dynamic_rules() {
        global $DB;
        $admin = get_admin();
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $courseshared = self::getDataGenerator()->create_course();
        $tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantmanager = new \tool_tenant\manager();

        $tenantcat = $this->getDataGenerator()->create_category();
        [$tenant, [$user1, $user2, $user3]] = $tenantgenerator->create_tenant_and_users(3);
        $tenantmanager->update_tenant($tenant->id, (object)['categoryid' => $tenantcat->id]);

        $coursetenant = $this->getDataGenerator()->create_course(['category' => $tenantcat->id]);

        $this->assertTrue(\tool_tenant\tenancy::is_shared_course($courseshared));
        $this->assertFalse(\tool_tenant\tenancy::is_shared_course($coursetenant, $tenant->id));

        $rule1 = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid, 'enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule1->id);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $courseshared->id, 'role' => $roleid, 'group' => 'Dynamic group'];
        course_enrol::create($rule1->id, $configdata);

        // Now apply on valid users.
        $ruleinstance = new rule(0, $rule1);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $params = ['courseid' => $courseshared->id, 'enrol' => 'dynamicrule', 'customint1' => $rule1->id];
        $enrols = $DB->get_records('enrol', $params);
        $enrolments = $DB->get_records('user_enrolments', ['enrolid' => reset($enrols)->id]);
        $this->assertEqualsCanonicalizing([$admin->id, $user1->id, $user2->id, $user3->id], array_column($enrolments, 'userid'));

        $params['courseid'] = $coursetenant->id;
        $this->assertEquals(0, $DB->count_records('enrol', $params));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        global $DB;
        $course1 = $this->getDataGenerator()->create_course();
        $role = $DB->get_record('role', ['shortname' => 'teacher']);

        $rule0 = $this->get_generator()->create_rule();
        $configdata = ['coursetoenrol' => $course1->id, 'role' => $role->id];
        $outcome = course_enrol::create($rule0->id, $configdata);

        $strparams = ['coursename' => $course1->fullname, 'role' => 'Non-editing teacher', 'groupname' => 'None'];
        $expected = get_string('outcomecourseenroldescription', 'enrol_dynamicrule', $strparams);
        $this->assertEquals($expected, $outcome->get_description());

        $now = time() + 3600;
        $rule1 = $this->get_generator()->create_rule();
        $configdata = [
            'coursetoenrol' => $course1->id,
            'role' => $role->id,
            'enddate' => $now
        ];
        $outcome = course_enrol::create($rule1->id, $configdata);

        $enddate = userdate($now, get_string('strftimedatefullshort'));
        $strparams = [
            'coursename' => $course1->fullname,
            'role' => 'Non-editing teacher',
            'groupname' => 'None',
            'enddate' => $enddate
        ];
        $expected = get_string('outcomecourseenroldescriptionwithenddate', 'enrol_dynamicrule', $strparams);
        $this->assertEquals($expected, $outcome->get_description());

        $rule2 = $this->get_generator()->create_rule();
        $configdata = [
            'coursetoenrol' => $course1->id,
            'role' => $role->id,
            'duration' => DAYSECS * 3,
            'group' => 'Group1'
        ];
        $outcome = course_enrol::create($rule2->id, $configdata);

        $strparams = [
            'coursename' => $course1->fullname,
            'role' => 'Non-editing teacher',
            'groupname' => 'Group1',
            'duration' => '3',
            'durationtype' => 'days'
        ];
        $expected = get_string('outcomecourseenroldescriptionwithduration', 'enrol_dynamicrule', $strparams);

        $this->assertEquals($expected, $outcome->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        // Valid course.
        $course1 = $this->getDataGenerator()->create_course();
        $roleid = $this->getDataGenerator()->create_role();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['coursetoenrol' => $course1->id, 'role' => $roleid];
        $outcome1 = course_enrol::create($rule1->id, $configdata);
        $this->assertTrue($outcome1->is_configuration_valid());

        // Delete course.
        $DB->delete_records('course', ['id' => $course1->id]);
        $this->assertFalse($outcome1->is_configuration_valid());

        // Valid course.
        $course2 = $this->getDataGenerator()->create_course();
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['coursetoenrol' => $course2->id, 'role' => $roleid];
        $outcome1 = course_enrol::create($rule2->id, $configdata);
        $this->assertTrue($outcome1->is_configuration_valid());

        // Valid course, missing role.
        $DB->delete_records('role', ['id' => $roleid]);
        $this->assertFalse($outcome1->is_configuration_valid());
    }

    /**
     * Test get_broken_description
     */
    public function test_get_broken_description() {
        $course1 = $this->getDataGenerator()->create_course();
        $roleid = $this->getDataGenerator()->create_role();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['coursetoenrol' => $course1->id, 'role' => $roleid];
        $outcome1 = course_enrol::create($rule1->id, $configdata);

        $this->assertNotEmpty($outcome1->get_broken_description());
    }

    /**
     * Test is_available
     */
    public function test_is_available() {
        $course0 = $this->getDataGenerator()->create_course();
        $outcome = course_enrol::instance();

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
        $roleid = $this->getDataGenerator()->create_role();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['coursetoenrol' => $course1->id, 'role' => $roleid];
        $outcome1 = course_enrol::create($rule1->id, $configdata);

        $this->assertNotEmpty($outcome1->get_not_available_label());
    }
}
