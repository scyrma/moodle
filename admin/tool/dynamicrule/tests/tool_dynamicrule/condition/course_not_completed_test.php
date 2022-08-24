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
 * File contains the unit tests for condition course_not_completed class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use cache_helper;
use completion_completion;
use completion_criteria;
use stdClass;
use tool_dynamicrule\condition;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;

/**
 * Unit tests for condition course_not_completed  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\course_not_completed
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_not_completed_test extends \advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        set_config('enablecompletion', COMPLETION_ENABLED);
        $this->resetAfterTest();
    }

    /**
     * Get dynamic rule generator
     *
     * @return \tool_dynamicrule_generator
     */
    protected function get_generator(): \tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Get workplace generator
     *
     * @return \tool_wp_generator
     */
    protected function get_workplace_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $condition = course_not_completed::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $condition->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $condition = course_not_completed::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = course_not_completed::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $category = $this->getDataGenerator()->create_category();
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED,
            'category' => $subcategory->id]);
        $tenant0category = $this->getDataGenerator()->create_category();
        $tenant0subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant0category->id]);
        $subtenant0catcourse = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED,
            'category' => $tenant0subcategory->id]);
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant0 = $tenantgenerator->create_tenant(['categoryid' => $tenant0category->id]);

        // Site admin in default tenant should see tenant courses.
        self::setAdminUser();
        $rule = $this->get_generator()->create_rule(['tenantid' => \tool_tenant\tenancy::get_tenant_id()]);
        $condition = $this->get_generator()->create_condition(course_not_completed::class, $rule->id, []);
        $this->assertArrayNotHasKey('courseid', $condition->validate_config_form(['courseid' => $subcatcourse->id]));
        $this->assertArrayNotHasKey('courseid', $condition->validate_config_form(['courseid' => $subtenant0catcourse->id]));

        // Shared space should not see tenant courses.
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        \tool_tenant\tenancy::set_switched_tenant_id($sharedspaceid);
        $rule = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid]);
        $condition = $this->get_generator()->create_condition(course_not_completed::class, $rule->id, []);
        $this->assertArrayNotHasKey('courseid', $condition->validate_config_form(['courseid' => $subcatcourse->id]));
        $this->assertArrayHasKey('courseid', $condition->validate_config_form(['courseid' => $subtenant0catcourse->id]));
    }


    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        // Anyone can add this condition.
        $condition = course_not_completed::instance();
        $this->assertTrue($condition->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $course0 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $configform = ['courseid' => $course0->id];
        $condition = course_not_completed::instance();

        // Admin user.
        $this->setAdminUser();
        $this->assertTrue($condition->user_can_edit($configform));

        // Non-priveleged user.
        $user = $this->getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($condition->user_can_edit($configform));

        // Grant priveleges to user.
        $this->getDataGenerator()->enrol_user($user->id, $course0->id, 'editingteacher');
        $this->assertTrue($condition->user_can_edit($configform));
    }

    /**
     * Test condition matching
     */
    public function test_get_matching_users() {
        global $DB;

        $course1 = $this->getDataGenerator()->create_course();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course1->id, 'student');

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        course_not_completed::create($rule1->id, $configdata);

        // User 1 and user 2 started the course, but not completed yet.
        $record1 = ['course' => $course1->id, 'userid' => $user1->id,
                   'timeenrolled' => time(), 'timestarted' => time(), 'timecompleted' => null, 'reaggregate' => 0];

        $record2 = ['course' => $course1->id, 'userid' => $user2->id,
                   'timeenrolled' => time(), 'timestarted' => time(), 'timecompleted' => null, 'reaggregate' => 0];

        $record1['id'] = $DB->insert_record('course_completions', (object)$record1);
        $record2['id'] = $DB->insert_record('course_completions', (object)$record2);

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        \tool_dynamicrule\tool_dynamicrule\condition\course_not_completed::create($rule1->id, $configdata);

        // Expect all users.
        $this->assertEquals(4, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, get_admin()->id, $user3->id], array_column($users, 'id'));

        // Complete course for user 1 and user 2.
        $record1['timecompleted'] = time();
        $DB->update_record('course_completions', $record1);
        $record2['timecompleted'] = time();
        $DB->update_record('course_completions', $record2);

        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        course_not_completed::create($rule2->id, $configdata);

        // Expect user 1 and admin.
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([get_admin()->id, $user3->id], array_column($users, 'id'));
    }

    /**
     * Test condition matching in shared rule
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_shared_rule() {
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        // Course with self-completion enabled.
        $completedcourse = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $criteriadata = new stdClass();
        $criteriadata->id = $completedcourse->id;
        $criteriadata->criteria_self = COMPLETION_CRITERIA_TYPE_SELF;

        $criterion = completion_criteria::factory(['criteriatype' => COMPLETION_CRITERIA_TYPE_SELF]);
        $criterion->update_config($criteriadata);

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user1, $user2, $user3]] = $tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $tenantgenerator->create_tenant_and_users(3);

        // Create rule and condition.
        $rule1 = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid]);
        $configdata = ['courseid' => $completedcourse->id];
        $condition = course_not_completed::create($rule1->id, $configdata);

        // Sanity check.
        $this->assertEquals(7, \tool_dynamicrule\api::count_matching_users($rule1->id));

        $timenow = time();

        // Complete course for users 1 and 2.
        $completion = new completion_completion(array('course' => $completedcourse->id, 'userid' => $user1->id));
        $completion->mark_complete($timenow - DAYSECS);
        $completion = new completion_completion(array('course' => $completedcourse->id, 'userid' => $user21->id));
        $completion->mark_complete($timenow + DAYSECS);

        // Validate matching.
        $this->assertEquals(5, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user2->id, $user3->id, $user22->id, $user23->id, get_admin()->id],
            array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = course_not_completed::create($rule1->id, $configdata);

        $this->assertNotEmpty($condition1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_DISABLED]);

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course->id];
        $condition = course_not_completed::create($rule1->id, $configdata);

        // Completion disabled.
        $this->assertFalse($condition->is_configuration_valid());

        // Enable completion in the course.
        $course->enablecompletion = COMPLETION_ENABLED;
        update_course($course);
        $this->assertTrue($condition->is_configuration_valid());

        // Delete course.
        $DB->delete_records('course', ['id' => $course->id]);
        $this->assertFalse($condition->is_configuration_valid());
    }

    /**
     * Test get_broken_description
     */
    public function test_get_broken_description() {
        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = course_not_completed::create($rule1->id, $configdata);

        $this->assertNotEmpty($condition1->get_broken_description());
    }

    /**
     * Test is_available
     */
    public function test_is_available() {
        $condition = course_not_completed::instance();
        $this->setAdminUser();
        $this->assertFalse($condition::is_available());

        // Add course with completion enabled.
        $course0 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $this->assertTrue($condition::is_available());

        // Non-priveleged user.
        $user = $this->getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($condition::is_available());

        // Grant priveleges to user.
        $this->getDataGenerator()->enrol_user($user->id, $course0->id, 'editingteacher');
        cache_helper::purge_by_event('changesincourse');

        $this->assertTrue($condition::is_available());

        // Disable completion.
        $course0->enablecompletion = COMPLETION_DISABLED;
        update_course($course0);
        $this->assertFalse($condition::is_available());
    }

    /**
     * Test get_get_not_available_label
     */
    public function test_get_not_available_label() {
        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = course_not_completed::create($rule1->id, $configdata);

        $this->assertNotEmpty($condition1->get_not_available_label());
    }

    /**
     * Test field mapping during export/import
     */
    public function test_field_mapping(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['shortname' => 'My course', 'enablecompletion' => true]);

        // Create rule containing condition, pointing to the course we just created.
        $rule = $this->get_generator()->create_rule();
        $this->get_generator()->create_condition(course_not_completed::class, $rule->id, ['courseid' => $course->id]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original course, and create a new one with the same name.
        $originalid = $course->id;
        $originalname = $course->shortname;
        delete_course($course, false);

        $newcourse = $this->getDataGenerator()->create_course(['shortname' => $originalname, 'enablecompletion' => true]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the course mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('course', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported condition field should be mapped to the new course.
        $rules = rule::get_records([], 'id');

        $condition = course_not_completed::instance(0, condition::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($newcourse->id, $condition->get_configdata()['courseid']);
        $this->assertTrue($condition->is_configuration_valid());
    }
}
