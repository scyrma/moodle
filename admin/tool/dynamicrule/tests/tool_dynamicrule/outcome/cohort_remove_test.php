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

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use tool_dynamicrule\outcome;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_dynamicrule\outcome\cohort_remove as cohort_outcome;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;
use tool_dynamicrule_generator;
use tool_wp_generator;

/**
 * Unit tests for outcome\cohort_remove class.
 *
 * @package    tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\outcome\cohort_remove
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cohort_remove_test extends \advanced_testcase {

    /** @var tool_dynamicrule_generator */
    protected $generator;

    /** @var tool_wp_generator */
    protected $wpgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $outcome = cohort_outcome::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $outcome->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $outcome = cohort_outcome::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $outcome = cohort_outcome::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $outcome->get_category());
    }

    /**
     * Test apply_to_user and setup_for_applying
     */
    public function test_apply_to_user(): void {
        global $DB;

        // Create two users in default tenant.
        $userids = [
            $this->getDataGenerator()->create_user()->id,
            $this->getDataGenerator()->create_user()->id,
        ];

        // Create cohort, add users.
        $cohort1 = self::getDataGenerator()->create_cohort();
        cohort_add_member($cohort1->id, $userids[0]);
        cohort_add_member($cohort1->id, $userids[1]);

        // Create rule.
        $rule0 = $this->generator->create_rule(['enabled' => 1]);
        $this->generator->create_condition_alwaystrue($rule0->id);
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort_outcome::create($rule0->id, $configdata);

        // There are now two members in the cohort.
        $this->assertEqualsCanonicalizing($userids, array_column($DB->get_records('cohort_members'), 'userid'));

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Expect all users have been removed from the cohort.
        $this->assertEmpty(array_column($DB->get_records('cohort_members'), 'userid'));
    }

    /**
     * Test apply_to_user and setup_for_applying using non-current tenant
     */
    public function test_apply_to_user_in_other_tenant(): void {
        global $DB;

        $tenant = $this->get_tenant_generator()->create_tenant();

        // Create two users in tenant.
        $userids = [
            $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id])->id,
            $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id])->id,
        ];
        // And one user in default tenant.
        $user0 = $this->getDataGenerator()->create_user();

        // Create rule in this tenant with the outcome.
        $rule0 = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $this->generator->create_condition_alwaystrue($rule0->id);
        $cohort1 = self::getDataGenerator()->create_cohort();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort_outcome::create($rule0->id, $configdata);

        // Add users to cohort.
        cohort_add_member($cohort1->id, $userids[0]);
        cohort_add_member($cohort1->id, $userids[1]);
        cohort_add_member($cohort1->id, $user0->id);

        // Process rule manually as if we enabled it using our tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Only user from default tenant remains in the cohort.
        $this->assertEquals([$user0->id], array_column($DB->get_records('cohort_members'), 'userid'));
    }

    /**
     * Test apply_to_user and setup_for_applying using shared tenant
     */
    public function test_apply_to_user_in_shared_tenant(): void {
        global $DB;

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();

        $cohort1 = self::getDataGenerator()->create_cohort();
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user1, $user2, $user3]] = $tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $tenantgenerator->create_tenant_and_users(3);
        foreach ([$user1->id, $user2->id, $user21->id, $user22->id] as $userid) {
            cohort_add_member($cohort1->id, $userid);
        }

        // Create rule.
        $rule0 = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $sharedspaceid]);
        $this->generator->create_condition_alwaystrue($rule0->id);
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort_outcome::create($rule0->id, $configdata);

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Expect all tenant users to be affected.
        $this->assertEmpty(array_column($DB->get_records('cohort_members'), 'userid'));
    }

    /**
     * Test get_description.
     */
    public function test_get_description(): void {
        $name = 'Test Cohort 1';
        $cohort1 = self::getDataGenerator()->create_cohort(['name' => $name]);

        $rule0 = $this->generator->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort_outcome::create($rule0->id, $configdata);

        $str = get_string('outcomecohortremovedescription', 'tool_dynamicrule', $name);
        $this->assertEquals($str, $outcome->get_description());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $context = \context_system::instance();

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(cohort_outcome::instance()->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(cohort_outcome::instance()->user_can_add());

        // Grant assign priveleges to user (moodle/cohort:assign).
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertTrue(cohort_outcome::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $cohort1 = self::getDataGenerator()->create_cohort();
        $configform = ['cohortid' => $cohort1->id];
        $context = \context::instance_by_id($cohort1->contextid);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(cohort_outcome::instance()->user_can_edit($configform));

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(cohort_outcome::instance()->user_can_edit($configform));

        // Grant priveleges to user (moodle/cohort:assign).
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertTrue(cohort_outcome::instance()->user_can_edit($configform));
    }

    /**
     * Test get_broken_description
     */
    public function test_get_broken_description(): void {
        $cohort1 = self::getDataGenerator()->create_cohort(['name' => 'Test Cohort 1']);
        $rule0 = $this->generator->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort_outcome::create($rule0->id, $configdata);

        $this->assertNotEmpty($outcome->get_broken_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        global $DB;
        $cohort1 = self::getDataGenerator()->create_cohort(['name' => 'Test Cohort 1']);
        $rule0 = $this->generator->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort_outcome::create($rule0->id, $configdata);

        self::setAdminUser();
        $this->assertTrue($outcome->is_configuration_valid());

        // Delete cohort.
        $DB->delete_records('cohort', ['id' => $cohort1->id]);
        $this->assertFalse($outcome->is_configuration_valid());
    }

    /**
     * Test can_edit_cohort_outcome by tenant.
     */
    public function test_can_edit_cohort_outcome_tenant(): void {
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
        $this->assertTrue(cohort_outcome::instance()->user_can_edit(['cohortid' => $syscohort->id]));
        $this->assertTrue(cohort_outcome::instance()->user_can_edit(['cohortid' => $catcohort->id]));
        $this->assertTrue(cohort_outcome::instance()->user_can_edit(['cohortid' => $subcatcohort->id]));

        // Tenant admin can't edit cohort in category.
        self::setUser($tenantadmin);
        $this->assertFalse(cohort_outcome::instance()->user_can_edit(['cohortid' => $syscohort->id]));
        $this->assertFalse(cohort_outcome::instance()->user_can_edit(['cohortid' => $catcohort->id]));
        $this->assertFalse(cohort_outcome::instance()->user_can_edit(['cohortid' => $subcatcohort->id]));

        // Allocate category to tenant.
        $manager = new \tool_tenant\manager();
        $manager->update_tenant($tenant->id, (object)['categoryid' => $category->id]);
        // We need to re-assign admin role, so tenant manager is applied to new category.
        $manager->assign_tenant_admin_role($tenant->id, [$tenantadmin->id]);

        // Test again.
        $this->assertFalse(cohort_outcome::instance()->user_can_edit(['cohortid' => $syscohort->id]));
        $this->assertTrue(cohort_outcome::instance()->user_can_edit(['cohortid' => $catcohort->id]));
        $this->assertTrue(cohort_outcome::instance()->user_can_edit(['cohortid' => $subcatcohort->id]));
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
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

        // Site admin in default tenant should see tenant cohorts.
        self::setAdminUser();
        $rule = $this->generator->create_rule(['tenantid' => \tool_tenant\tenancy::get_tenant_id()]);
        $outcome = $this->generator->create_outcome(cohort_outcome::class, $rule->id, ['cohortid' => $syscohort->id]);
        $this->assertArrayNotHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $syscohort->id]));
        $this->assertArrayNotHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $catcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $subcatcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $tenant0catcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $tenant0subcatcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $tenant1catcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $tenant1subcatcohort->id]));

        // Shared space should not see tenant cohorts.
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        \tool_tenant\tenancy::set_switched_tenant_id($sharedspaceid);
        $rule = $this->generator->create_rule(['tenantid' => $sharedspaceid]);
        $outcome = $this->generator->create_outcome(cohort_outcome::class, $rule->id, ['cohortid' => $syscohort->id]);
        $this->assertArrayNotHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $syscohort->id]));
        $this->assertArrayNotHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $catcohort->id]));
        $this->assertArrayNotHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $subcatcohort->id]));
        $this->assertArrayHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $tenant0catcohort->id]));
        $this->assertArrayHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $tenant0subcatcohort->id]));
        $this->assertArrayHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $tenant1catcohort->id]));
        $this->assertArrayHasKey('cohortid', $outcome->validate_config_form(['cohortid' => $tenant1subcatcohort->id]));
    }

    /**
     * Test is_available.
     */
    public function test_is_available(): void {
        // Sanity check.
        self::setAdminUser();
        $this->assertFalse(cohort_outcome::is_available());

        // Add system cohort.
        $this->getDataGenerator()->create_cohort(['contextid' => SYSCONTEXTID]);
        $this->assertTrue(cohort_outcome::is_available());

        $category = $this->getDataGenerator()->create_category();
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant = $tenantgenerator->create_tenant(['categoryid' => $category->id]);
        $tenantadmin = $tenantgenerator->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);

        // Login as tenant admin.
        self::setUser($tenantadmin);
        $this->assertFalse(cohort_outcome::is_available());

        // Add some category cohorts.
        $this->getDataGenerator()->create_cohort(['contextid' => $category->get_context()->id]);
        $this->getDataGenerator()->create_cohort(['contextid' => $subcategory->get_context()->id]);
        $this->assertTrue(cohort_outcome::is_available());
    }

    /**
     * Test get_get_not_available_label
     */
    public function test_get_not_available_label(): void {
        $outcome = cohort_outcome::instance();
        $this->assertNotEmpty($outcome->get_not_available_label());
    }

    /**
     * Test field mapping during export/import
     */
    public function test_field_mapping(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'My cohort']);

        // Create rule containing outcome, pointing to the cohort we just created.
        $rule = $this->generator->create_rule();
        $this->generator->create_outcome(cohort_outcome::class, $rule->id, ['cohortid' => $cohort->id]);

        // Export our rule.
        $exportid = $this->wpgenerator->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original cohort, and create a new one with the same name.
        $originalid = $cohort->id;
        $originalname = $cohort->name;
        cohort_delete_cohort($cohort);

        $newcohort = $this->getDataGenerator()->create_cohort(['name' => $originalname]);

        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the cohort mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('cohort', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported outcome field should be mapped to the new cohort.
        $rules = rule::get_records([], 'id');

        $outcome = cohort_outcome::instance(0, outcome::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($newcohort->id, $outcome->get_configdata()['cohortid']);
        $this->assertTrue($outcome->is_configuration_valid());
    }

    /**
     * Return tenant generator
     *
     * @return \tool_tenant_generator
     */
    private function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}
