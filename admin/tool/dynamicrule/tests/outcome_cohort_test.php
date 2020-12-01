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
 * File contains the unit tests for outcome\cohort class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $outcome->get_category());
    }

    /**
     * Test apply_to_users
     */
    public function test_apply_to_users() {
        global $DB;
        self::setAdminUser();

        $rule0 = $this->generator->create_rule(['enabled' => 1]);
        $this->generator->create_condition_alwaystrue($rule0->id);

        $cohort1 = self::getDataGenerator()->create_cohort();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\cohort::create($rule0->id, $configdata);

        $userids = [self::getDataGenerator()->create_user(), self::getDataGenerator()->create_user()];

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Three affected users (admin user plus those we created).
        $this->assertEquals(3, $DB->count_records('cohort_members', ['cohortid' => $cohort1->id]));
    }

    /**
     * Test get_description.
     */
    public function test_get_description() {
        $name = 'Test Cohort 1';
        $cohort1 = self::getDataGenerator()->create_cohort(['name' => $name]);

        $rule0 = $this->generator->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\cohort::create($rule0->id, $configdata);

        $str = get_string('outcomecohortdescription', 'tool_dynamicrule', $name);
        $this->assertEquals($str, $outcome->get_description());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        // Admin user.
        self::setAdminUser();
        $this->assertTrue(\tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance()->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(\tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance()->user_can_add());

        // Grant priveleges to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = \context_system::instance();
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertFalse(\tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance()->user_can_add());

        assign_capability('moodle/cohort:manage', CAP_ALLOW, $roleid, $context->id);
        $this->assertTrue(\tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $cohort1 = self::getDataGenerator()->create_cohort(['name' => 'Test Cohort 1']);
        $configdata = ['cohortid' => $cohort1->id];

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(\tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance()->user_can_edit($configdata));

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(\tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance()->user_can_edit($configdata));

        // Grant priveleges to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = \context::instance_by_id($cohort1->contextid, MUST_EXIST);
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertFalse(\tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance()->user_can_edit($configdata));

        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $context->id);
        $this->assertTrue(\tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance()->user_can_edit($configdata));
    }

    /**
     * Test get_broken_description
     */
    public function test_get_broken_description() {
        $cohort1 = self::getDataGenerator()->create_cohort(['name' => 'Test Cohort 1']);
        $rule0 = $this->generator->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\cohort::create($rule0->id, $configdata);

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
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\cohort::create($rule0->id, $configdata);

        self::setAdminUser();
        $this->assertTrue($outcome->is_configuration_valid());

        // Change cohort context.
        $coursecategory = self::getDataGenerator()->create_category();
        $coursecategoryctx = \context_coursecat::instance($coursecategory->id);
        $cohort1->contextid = $coursecategoryctx->id;
        cohort_update_cohort($cohort1);
        $this->assertFalse($outcome->is_configuration_valid());

        // Delete cohort.
        $DB->delete_records('cohort', ['id' => $cohort1->id]);
        $this->assertFalse($outcome->is_configuration_valid());
    }

    /**
     * Test is_available
     */
    public function test_is_available() {
        // No cohorts.
        $this->assertFalse(\tool_dynamicrule\tool_dynamicrule\outcome\cohort::is_available());

        // Create one cohort.
        $this->getDataGenerator()->create_cohort();
        $this->assertTrue(\tool_dynamicrule\tool_dynamicrule\outcome\cohort::is_available());
    }

    /**
     * Test get_get_not_available_label
     */
    public function test_get_not_available_label() {
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance();
        $this->assertNotEmpty($outcome->get_not_available_label());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\cohort::instance();
        $configform = ['cohortid' => 0];
        $this->assertArrayHasKey('cohortid', $outcome->validate_config_form($configform));
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
}