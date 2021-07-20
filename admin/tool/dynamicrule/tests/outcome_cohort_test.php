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
 * File contains the unit tests for outcome\cohort class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\outcome;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_dynamicrule\outcome\cohort as cohort_outcome;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;

/**
 * Unit tests for outcome\cohort class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     tool_dynamicrule\tool_dynamicrule\outcome\cohort
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_outcome_cohort_testcase extends advanced_testcase {

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
     * Test get_title
     */
    public function test_get_title() {
        $outcome = cohort_outcome::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = cohort_outcome::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $outcome->get_category());
    }

    /**
     * Test apply_to_user and setup_for_applying
     */
    public function test_apply_to_user() {
        global $DB;

        $rule0 = $this->generator->create_rule(['enabled' => 1]);
        $this->generator->create_condition_alwaystrue($rule0->id);

        $cohort1 = self::getDataGenerator()->create_cohort();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort_outcome::create($rule0->id, $configdata);

        // Create two users in default tenant.
        $userids = [
            $this->getDataGenerator()->create_user()->id,
            $this->getDataGenerator()->create_user()->id
        ];

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Expect three affected users (admin user and those two we created).
        $userids[] = get_admin()->id;
        $this->assertEqualsCanonicalizing($userids, array_column($DB->get_records('cohort_members'), 'userid'));
    }

    /**
     * Test apply_to_user and setup_for_applying using non-current tenant
     */
    public function test_apply_to_user_in_other_tenant() {
        global $DB;

        $tenant = $this->get_tenant_generator()->create_tenant();
        $rule0 = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $this->generator->create_condition_alwaystrue($rule0->id);

        $cohort1 = self::getDataGenerator()->create_cohort();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\cohort::create($rule0->id, $configdata);

        // Create two users in tenant.
        $userids = [
            $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id])->id,
            $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id])->id,
        ];

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Expect two tenant users to be affected.
        $this->assertEqualsCanonicalizing($userids, array_column($DB->get_records('cohort_members'), 'userid'));
    }

    /**
     * Test get_description.
     */
    public function test_get_description() {
        $name = 'Test Cohort 1';
        $cohort1 = self::getDataGenerator()->create_cohort(['name' => $name]);

        $rule0 = $this->generator->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort_outcome::create($rule0->id, $configdata);

        $str = get_string('outcomecohortdescription', 'tool_dynamicrule', $name);
        $this->assertEquals($str, $outcome->get_description());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        $context = context_system::instance();

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
    public function test_user_can_edit() {
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
    public function test_get_broken_description() {
        $cohort1 = self::getDataGenerator()->create_cohort(['name' => 'Test Cohort 1']);
        $rule0 = $this->generator->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort_outcome::create($rule0->id, $configdata);

        $this->assertNotEmpty($outcome->get_broken_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
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
    public function test_can_edit_cohort_outcome_tenant() {
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
     * Test is_available.
     */
    public function test_is_available() {
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
    public function test_get_not_available_label() {
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
