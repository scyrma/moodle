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
 * File contains the unit tests for condition cohort_member class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule\condition;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;

use tool_dynamicrule\tool_dynamicrule\condition\cohort_member;
use tool_dynamicrule\tool_dynamicrule\outcome\notification;

/**
 * Unit tests for condition cohort_member  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\cohort_member
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\condition_sql
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cohort_member_test extends \advanced_testcase {

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
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $condition = cohort_member::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $condition->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $condition = cohort_member::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = cohort_member::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users() {

        $cohort1 = $this->getDataGenerator()->create_cohort();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user2->id);

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        cohort_member::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'id'));

        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['cohortid' => $cohort1->id, 'timeadded' => strtotime('+1 day')];
        cohort_member::create($rule2->id, $configdata);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEmpty(array_column($users, 'id'));

        $rule3 = $this->get_generator()->create_rule();
        $configdata = ['cohortid' => $cohort1->id, 'timeadded' => strtotime('-1 day')];
        cohort_member::create($rule3->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule3->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'id'));
    }

    /**
     * Test condition matching in tenant
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_tenant() {
        $cohort1 = $this->getDataGenerator()->create_cohort();

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user1, $user2, $user3]] = $tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $tenantgenerator->create_tenant_and_users(3);

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user21->id);

        $rule1 = $this->get_generator()->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['cohortid' => $cohort1->id];
        cohort_member::create($rule1->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));
    }

    /**
     * Test condition matching in shared rule
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_shared_rule() {
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $cohort1 = $this->getDataGenerator()->create_cohort();

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user1, $user2, $user3]] = $tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $tenantgenerator->create_tenant_and_users(3);

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user21->id);

        $rule1 = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid]);
        $configdata = ['cohortid' => $cohort1->id];
        cohort_member::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user21->id], array_column($users, 'id'));
    }

    /**
     * Test cohort_member_added event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing() {
        global $DB;

        $cohort0 = $this->getDataGenerator()->create_cohort();
        $user0 = $this->getDataGenerator()->create_user();

        // Create rule0 with cohort0 membership conditon and notification outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['cohortid' => $cohort0->id];
        cohort_member::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Added to cohort0',
            'body' => ['text' => 'Congratulations, you have been added to cohort0.', 'format' => FORMAT_MOODLE]];
        notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Add user0 into cohort0, this supposed to trigger rule0.
        cohort_add_member($cohort0->id, $user0->id);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'Added to cohort0');
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        $cohort1 = $this->getDataGenerator()->create_cohort();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $condition1 = cohort_member::create($rule1->id, $configdata);

        $this->assertEquals(get_string('conditioncohortmemberdescription', 'tool_dynamicrule', $cohort1->name),
            $condition1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;
        $cohort1 = $this->getDataGenerator()->create_cohort();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $condition1 = cohort_member::create($rule1->id, $configdata);

        // Test valid configuration.
        $this->assertTrue($condition1->is_configuration_valid());

        // Change cohort context to category.
        $coursecategory = $this->getDataGenerator()->create_category();
        $coursecategoryctx = \context_coursecat::instance($coursecategory->id);
        $cohort1->contextid = $coursecategoryctx->id;
        cohort_update_cohort($cohort1);
        $this->assertTrue($condition1->is_configuration_valid());

        // Delete cohort.
        $DB->delete_records('cohort', ['id' => $cohort1->id]);
        $this->assertFalse($condition1->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        $context = \context_system::instance();

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(cohort_member::instance()->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(cohort_member::instance()->user_can_add());

        // Grant priveleges to user (moodle/cohort:view).
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertTrue(cohort_member::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $cohort1 = self::getDataGenerator()->create_cohort();
        $configform = ['cohortid' => $cohort1->id];
        $context = \context::instance_by_id($cohort1->contextid);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(cohort_member::instance()->user_can_edit($configform));

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(cohort_member::instance()->user_can_edit($configform));

        // Grant priveleges to user (moodle/cohort:view).
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertTrue(cohort_member::instance()->user_can_edit($configform));
    }

    /**
     * Test can_edit_cohort_condition by tenant.
     */
    public function test_can_edit_cohort_condition_tenant() {
        $category = $this->getDataGenerator()->create_category();
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $catcohort = $this->getDataGenerator()->create_cohort(['contextid' => $category->get_context()->id]);
        $subcatcohort = $this->getDataGenerator()->create_cohort(['contextid' => $subcategory->get_context()->id]);
        $syscohort = $this->getDataGenerator()->create_cohort(['contextid' => SYSCONTEXTID]);

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant = $tenantgenerator->create_tenant();
        $tenantadmin = $tenantgenerator->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);

        // Sanity check.
        self::setAdminUser();
        $this->assertTrue(cohort_member::instance()->user_can_edit(['cohortid' => $syscohort->id]));
        $this->assertTrue(cohort_member::instance()->user_can_edit(['cohortid' => $catcohort->id]));
        $this->assertTrue(cohort_member::instance()->user_can_edit(['cohortid' => $subcatcohort->id]));

        // Tenant admin can't edit cohort in category.
        self::setUser($tenantadmin);
        $this->assertFalse(cohort_member::instance()->user_can_edit(['cohortid' => $syscohort->id]));
        $this->assertFalse(cohort_member::instance()->user_can_edit(['cohortid' => $catcohort->id]));
        $this->assertFalse(cohort_member::instance()->user_can_edit(['cohortid' => $subcatcohort->id]));

        // Allocate category to tenant.
        $manager = new \tool_tenant\manager();
        $manager->update_tenant($tenant->id, (object)['categoryid' => $category->id]);
        // We need to re-assign admin role, so tenant manager is applied to new category.
        $manager->assign_tenant_admin_role($tenant->id, [$tenantadmin->id]);

        // Test again.
        $this->assertFalse(cohort_member::instance()->user_can_edit(['cohortid' => $syscohort->id]));
        $this->assertTrue(cohort_member::instance()->user_can_edit(['cohortid' => $catcohort->id]));
        $this->assertTrue(cohort_member::instance()->user_can_edit(['cohortid' => $subcatcohort->id]));
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $category = $this->getDataGenerator()->create_category();
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $catcohort = $this->getDataGenerator()->create_cohort(['contextid' => $category->get_context()->id]);
        $subcatcohort = $this->getDataGenerator()->create_cohort(['contextid' => $subcategory->get_context()->id]);

        $syscohort = $this->getDataGenerator()->create_cohort(['contextid' => SYSCONTEXTID]);

        $tenant0category = $this->getDataGenerator()->create_category();
        $tenant0subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant0category->id]);
        $tenant0catcohort = $this->getDataGenerator()->create_cohort(['contextid' => $tenant0category->get_context()->id]);
        $tenant0subcatcohort = $this->getDataGenerator()->create_cohort(['contextid' => $tenant0subcategory->get_context()->id]);

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant0 = $tenantgenerator->create_tenant(['categoryid' => $tenant0category->id]);

        $tenant1category = $this->getDataGenerator()->create_category();
        $tenant1subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant1category->id]);
        $tenant1catcohort = $this->getDataGenerator()->create_cohort(['contextid' => $tenant1category->get_context()->id]);
        $tenant1subcatcohort = $this->getDataGenerator()->create_cohort(['contextid' => $tenant1subcategory->get_context()->id]);
        $tenant1 = $tenantgenerator->create_tenant(['categoryid' => $tenant1category->id]);

        // Site admin in default tenant should see all cohorts.
        self::setAdminUser();
        $rule = $this->get_generator()->create_rule(['tenantid' => \tool_tenant\tenancy::get_tenant_id()]);
        $condition = $this->get_generator()->create_condition(cohort_member::class, $rule->id, ['cohortid' => $syscohort->id]);
        $this->assertArrayNotHasKey('cohortid', $condition->validate_config_form(['cohortid' => $syscohort->id]));
        $this->assertArrayNotHasKey('cohortid', $condition->validate_config_form(['cohortid' => $catcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $condition->validate_config_form(['cohortid' => $subcatcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $condition->validate_config_form(['cohortid' => $tenant0catcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $condition->validate_config_form(['cohortid' => $tenant0subcatcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $condition->validate_config_form(['cohortid' => $tenant1catcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $condition->validate_config_form(['cohortid' => $tenant1subcatcohort->id]));

        // Shared space should not see tenant cohorts.
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        \tool_tenant\tenancy::set_switched_tenant_id($sharedspaceid);
        $rule = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid]);
        $condition = $this->get_generator()->create_condition(cohort_member::class, $rule->id, ['cohortid' => $syscohort->id]);
        $this->assertArrayNotHasKey('cohortid', $condition->validate_config_form(['cohortid' => $syscohort->id]));
        $this->assertArrayNotHasKey('cohortid', $condition->validate_config_form(['cohortid' => $catcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $condition->validate_config_form(['cohortid' => $subcatcohort->id]));
        $this->assertArrayHasKey('cohortid', $condition->validate_config_form(['cohortid' => $tenant0catcohort->id]));
        $this->assertArrayHasKey('cohortid', $condition->validate_config_form(['cohortid' => $tenant0subcatcohort->id]));
        $this->assertArrayHasKey('cohortid', $condition->validate_config_form(['cohortid' => $tenant1catcohort->id]));
        $this->assertArrayHasKey('cohortid', $condition->validate_config_form(['cohortid' => $tenant1subcatcohort->id]));
    }

    /**
     * Test is_available.
     */
    public function test_is_available() {
        // Sanity check.
        self::setAdminUser();
        $this->assertFalse(cohort_member::is_available());

        // Add system cohort.
        $this->getDataGenerator()->create_cohort(['contextid' => SYSCONTEXTID]);
        $this->assertTrue(cohort_member::is_available());

        $category = $this->getDataGenerator()->create_category();
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant = $tenantgenerator->create_tenant(['categoryid' => $category->id]);
        $tenantadmin = $tenantgenerator->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);

        // Login as tenant admin.
        self::setUser($tenantadmin);
        $this->assertFalse(cohort_member::is_available());

        // Add some category cohorts.
        $this->getDataGenerator()->create_cohort(['contextid' => $category->get_context()->id]);
        $this->getDataGenerator()->create_cohort(['contextid' => $subcategory->get_context()->id]);
        $this->assertTrue(cohort_member::is_available());
    }

    /**
     * Test field mapping during export/import
     */
    public function test_field_mapping(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'My cohort']);

        // Create rule containing condition, pointing to the cohort we just created.
        $rule = $this->get_generator()->create_rule();
        $this->get_generator()->create_condition(cohort_member::class, $rule->id, ['cohortid' => $cohort->id]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original cohort, and create a new one with the same name.
        $originalid = $cohort->id;
        $originalname = $cohort->name;
        cohort_delete_cohort($cohort);

        $newcohort = $this->getDataGenerator()->create_cohort(['name' => $originalname]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the cohort mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('cohort', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported condition field should be mapped to the new cohort.
        $rules = rule::get_records([], 'id');

        $condition = cohort_member::instance(0, condition::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($newcohort->id, $condition->get_configdata()['cohortid']);
        $this->assertTrue($condition->is_configuration_valid());
    }
}
