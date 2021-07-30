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
 * Class export_import_test
 *
 * @package   tool_dynamicrule
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\tool_wp\exporter\rules as rules_exporter;
use tool_dynamicrule\tool_wp\importer\rules as rules_importer;
use tool_dynamicrule\condition;
use tool_dynamicrule\rule;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;

/**
 * Class export_import_test
 *
 * @covers     \tool_dynamicrule\tool_wp\exporter\rules
 * @covers     \tool_dynamicrule\tool_wp\importer\rules
 * @package    tool_dynamicrule
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_export_import_testcase extends advanced_testcase {

    /** @var string $importfixture */
    protected $importfixture = __DIR__ . '/fixtures/dynamic-rules-export.zip';

    /** @var string $importfixtureinvalid Test fixture containing invalid rule condition/outcome. */
    protected $importfixtureinvalid = __DIR__ . '/fixtures/dynamic-rules-export-invalid.zip';

    /** @var tool_dynamicrule_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_wp_generator */
    protected $wpgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->resetAfterTest();
    }

    /**
     * Test exporting all active rules
     */
    public function test_export_all_active_rules(): void {
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $params = (object)['enabled' => 1, 'tenantid' => $defaulttenantid];
        $rule1 = $this->generator->create_rule($params);
        $params->archived = 1;
        $rule2 = $this->generator->create_rule($params);

        // Create a new export.
        $exportid = $this->wpgenerator->perform_export(rules_exporter::class, [
            rules_exporter::EXPORT_CONTENT => 1,
            rules_exporter::EXPORT_INSTANCES => rules_exporter::EXPORT_INSTANCES_ACTIVE
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importer = $importmanager->get_importer();
        $this->assertInstanceOf(\tool_dynamicrule\tool_wp\importer\rules::class, $importer);

        // We should have the first rule in the import.
        $rules = $importer->get_entities_in_workplace_export_file(\tool_dynamicrule\rule::TABLE);
        $this->assertCount(1, $rules);

        $this->assertEquals([[
            'entityname' => 'tool_dynamicrule',
            'instancename' => $rule1->name,
            'id' => -1,
        ]], $importmanager->get_instances());

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($rules, false)[0];
        $this->assertEquals($rule1->name, $entity->get_raw_field('name'));
    }

    /**
     * Test exporting all enabled rules
     */
    public function test_export_all_enabled_rules(): void {
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $params = (object)['enabled' => 1, 'tenantid' => $defaulttenantid, 'archived' => 0];
        $rule1 = $this->generator->create_rule($params);
        $params->enabled = 0;
        $rule2 = $this->generator->create_rule($params);

        // Create a new export.
        $exportid = $this->wpgenerator->perform_export(rules_exporter::class, [
            rules_exporter::EXPORT_CONTENT => 1,
            rules_exporter::EXPORT_INSTANCES => rules_exporter::EXPORT_INSTANCES_ENABLED
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(\tool_dynamicrule\tool_wp\importer\rules::class, $importer);

        // We should have the first rule in the import.
        $rules = $importer->get_entities_in_workplace_export_file(\tool_dynamicrule\rule::TABLE);
        $this->assertCount(1, $rules);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($rules, false)[0];
        $this->assertEquals($rule1->name, $entity->get_raw_field('name'));
    }

    /**
     * Test exporting all active and archived rules
     */
    public function test_export_all_rules(): void {
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $params = (object)['enabled' => 1, 'tenantid' => $defaulttenantid, 'archived' => 0];
        $rule1 = $this->generator->create_rule($params);
        $params->archived = 1;
        $rule2 = $this->generator->create_rule($params);

        // Create a new export.
        $exportid = $this->wpgenerator->perform_export(rules_exporter::class, [
            rules_exporter::EXPORT_CONTENT => 1,
            rules_exporter::EXPORT_INSTANCES => rules_exporter::EXPORT_INSTANCES_ALL
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(\tool_dynamicrule\tool_wp\importer\rules::class, $importer);

        $rules = $importer->get_entities_in_workplace_export_file(\tool_dynamicrule\rule::TABLE);
        $this->assertCount(2, $rules);

        $rulenames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($rules, false));
        $expected = [$rule1->name, $rule2->name];
        $this->assertEqualsCanonicalizing($expected, $rulenames);
    }

    /**
     * Test exporting a rule as a user who doesn't have permission to edit one of it's conditions
     */
    public function test_export_rule_can_not_edit_condition(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Allow user to manage rules, but NOT cohorts.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = context_system::instance();
        assign_capability('tool/dynamicrule:manage', CAP_ALLOW, $roleid, $context->id);
        assign_capability('moodle/cohort:manage', CAP_PROHIBIT, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);

        $rule = $this->generator->create_rule([
            'enabled' => 1,
        ]);

        // Simple rule with single condition and outcome.
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->generator->create_condition(\tool_dynamicrule\tool_dynamicrule\condition\cohort_member::class, $rule->id,
            ['cohortid' => $cohort->id]);
        $this->generator->create_outcome(\tool_dynamicrule\tool_dynamicrule\outcome\notification::class, $rule->id);

        // Export the rule.
        $exportid = $this->wpgenerator->perform_export(rules_exporter::class, [
            rules_exporter::EXPORT_CONTENT => 1,
            rules_exporter::EXPORT_INSTANCES => rules_exporter::EXPORT_INSTANCES_ENABLED,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(\tool_dynamicrule\tool_wp\importer\rules::class, $importer);

        $rules = $importer->get_entities_in_workplace_export_file(\tool_dynamicrule\rule::TABLE);
        $this->assertEmpty($rules);
    }

    /**
     * Test export and import of a rule containing a condition and outcome
     */
    public function test_export_and_import_complete_rule(): void {
        $this->setAdminUser();

        $rule = $this->generator->create_rule([
            'enabled' => 1,
        ]);

        // Simple rule with single condition and outcome.
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->generator->create_condition(\tool_dynamicrule\tool_dynamicrule\condition\cohort_member::class, $rule->id,
            ['cohortid' => $cohort->id]);
        $this->generator->create_outcome(\tool_dynamicrule\tool_dynamicrule\outcome\notification::class, $rule->id);

        // Export the rule.
        $exportid = $this->wpgenerator->perform_export(rules_exporter::class, [
            rules_exporter::EXPORT_CONTENT => 1,
            rules_exporter::EXPORT_INSTANCES => rules_exporter::EXPORT_INSTANCES_ENABLED,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import it.
        $this->wpgenerator->perform_import_from_export_id($exportid, [
            rules_importer::IMPORT_CONTENT => 1,
            rules_importer::IMPORT_INSTANCES => rules_importer::IMPORT_INSTANCES_SELECTED,
            rules_importer::IMPORT_SELECT_RULES => [$rule->id],
        ]);

        // There should now be two rules (the original one, plus the one we imported).
        $rules = rule::get_records([], 'id');
        $this->assertCount(2, $rules);

        // Assert the imported rule is not enabled, and has a condition and outcome.
        $rule = end($rules);
        $this->assertFalse($rule->is_enabled());
        $this->assertTrue($rule->has_conditions());
        $this->assertTrue($rule->has_outcomes());
    }

    /**
     * Data provider for testing the import of rules into given tenant
     *
     * @see test_import_all_rules_to_tenant
     *
     * @return array
     */
    public function import_all_rules_to_tenant_provider(): array {
        return [
            'Default tenant' => [true],
            'Other tenant' => [false],
        ];
    }

    /**
     * Test importing all rules from an export file into given tenant
     *
     * @param bool $defaulttenant
     *
     * @dataProvider import_all_rules_to_tenant_provider
     */
    public function test_import_all_rules_to_tenant(bool $defaulttenant): void {
        $this->setAdminUser();

        // If we are testing the default tenant, use that, otherwise create a new tenant.
        $tenantid = $defaulttenant ?
            tenancy::get_default_tenant_id() :
            $this->tenantgenerator->create_tenant()->id;

        // Sanity test.
        $this->assertEquals(0, rule::count_records(['tenantid' => $tenantid]));

        $importid = $this->wpgenerator->perform_import_from_file($this->importfixture, [
            'tenantid' => $tenantid,
            rules_importer::IMPORT_CONTENT => 1,
            rules_importer::IMPORT_INSTANCES => rules_importer::IMPORT_INSTANCES_ALL,
        ]);

        $rules = rule::get_records(['tenantid' => $tenantid], 'name');
        $this->assertCount(2, $rules);

        list($rule1, $rule2) = $rules;

        $this->assertEquals('Rule 1', $rule1->get('name'));
        $this->assertFalse($rule1->is_enabled());
        $this->assertFalse($rule1->is_archived());
        $this->assertTrue($rule1->has_conditions());
        $this->assertTrue($rule1->has_outcomes());

        $this->assertEquals('Rule 2', $rule2->get('name'));
        $this->assertFalse($rule2->is_enabled());
        $this->assertTrue($rule2->is_archived());
        $this->assertTrue($rule2->has_conditions());
        $this->assertTrue($rule2->has_outcomes());

        $this->assertEmpty($this->wpgenerator->get_import_conflict_review($importid));

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(2, $logs);

        list($log1, $log2) = $logs;

        $rule1url = (new moodle_url('/admin/tool/dynamicrule/rule.php', ['id' => $rule1->get('id')]))->out();
        $this->assertEquals("Created new rule '<a href=\"{$rule1url}\">Rule 1</a>' with 1 conditions and 1 actions",
            $log1['detail']);
        $this->assertCount(0, $log1['errors']);
        $this->assertCount(0, $log1['notices']);

        $rule2url = (new moodle_url('/admin/tool/dynamicrule/index.php', [], 'archivedrules'))->out();
        $this->assertEquals("Created new rule '<a href=\"{$rule2url}\">Rule 2</a>' with 1 conditions and 1 actions",
            $log2['detail']);
        $this->assertCount(0, $log2['errors']);
        $this->assertCount(0, $log2['notices']);
    }

    /**
     * Test importing a rule with invalid outcome/condition generates appropriate conflicts and errors
     */
    public function test_import_invalid_rule(): void {
        $this->setAdminUser();

        $importid = $this->wpgenerator->perform_import_from_file($this->importfixtureinvalid, [
            rules_importer::IMPORT_CONTENT => 1,
            rules_importer::IMPORT_INSTANCES => rules_importer::IMPORT_INSTANCES_ALL,
            helper::get_importer_setting_name_for_conflict_form(rule::TABLE, 'invalidcondition', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form(rule::TABLE, 'invalidoutcome', 'action') => 'skip',
        ]);

        // Validate import conflict review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(2, $conflicts);

        $expectedconflictmessages = ['Missing or invalid rule condition', 'Missing or invalid rule outcome'];
        $this->assertEquals($expectedconflictmessages, array_column($conflicts, 0));

        // Validate import logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);

        $log = reset($logs);
        $this->assertEquals('Couldn\'t import rule \'Invalid rule\'', $log['detail']);
        $this->assertEquals($expectedconflictmessages, $log['errors']);
        $this->assertCount(0, $log['notices']);
    }

    /**
     * Test importing a rule as a user who doesn't have permission to add one of it's conditions
     */
    public function test_import_rule_can_not_add_condition(): void {
        $this->setAdminUser();

        $rule = $this->generator->create_rule([
            'enabled' => 1,
        ]);

        // Simple rule with single condition and outcome.
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->generator->create_condition(\tool_dynamicrule\tool_dynamicrule\condition\cohort_member::class, $rule->id,
            ['cohortid' => $cohort->id]);
        $this->generator->create_outcome(\tool_dynamicrule\tool_dynamicrule\outcome\notification::class, $rule->id,
            ['subject' => 'You matched!', 'body' => ['text' => "{{userfullname}} matched!", 'format' => FORMAT_HTML]]);

        // Export the rule.
        $exportid = $this->wpgenerator->perform_export(rules_exporter::class, [
            rules_exporter::EXPORT_CONTENT => 1,
            rules_exporter::EXPORT_INSTANCES => rules_exporter::EXPORT_INSTANCES_ENABLED,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Switch to a user who can't add the rule condition.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Allow user to manage rules, but NOT cohorts.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = context_system::instance();
        assign_capability('tool/dynamicrule:manage', CAP_ALLOW, $roleid, $context->id);
        assign_capability('moodle/cohort:manage', CAP_PROHIBIT, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);

        // Import the export we previously created.
        $importid = $this->wpgenerator->perform_import_from_export_id($export->get('id'), [
            rules_importer::IMPORT_CONTENT => 1,
            rules_importer::IMPORT_INSTANCES => rules_importer::IMPORT_INSTANCES_ALL,
            helper::get_importer_setting_name_for_conflict_form(rule::TABLE, 'invalidcondition', 'action') => 'skip',
        ]);

        // Validate import conflict review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);

        $expectedconflictmessage = 'Missing or invalid rule condition';
        $this->assertEquals($expectedconflictmessage, $conflicts[0][0]);

        // Validate import logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);

        $log = reset($logs);
        $this->assertEquals("Couldn't import rule '{$rule->name}'", $log['detail']);
        $this->assertEquals($expectedconflictmessage, $log['errors'][0]);
        $this->assertCount(0, $log['notices']);
    }

    /**
     * Test importing a rule that became broken during import because the entity referred to by the condition import
     * mapping doesn't exist
     */
    public function test_import_rule_missing_condition_mapping(): void {
        $this->setAdminUser();

        $rule = $this->generator->create_rule([
            'enabled' => 1,
        ]);

        // Simple rule with single condition and outcome.
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->generator->create_condition(\tool_dynamicrule\tool_dynamicrule\condition\cohort_member::class, $rule->id,
            ['cohortid' => $cohort->id]);
        $this->generator->create_outcome(\tool_dynamicrule\tool_dynamicrule\outcome\notification::class, $rule->id);

        // Export the rule.
        $exportid = $this->wpgenerator->perform_export(rules_exporter::class, [
            rules_exporter::EXPORT_CONTENT => 1,
            rules_exporter::EXPORT_INSTANCES => rules_exporter::EXPORT_INSTANCES_ENABLED,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Delete the cohort.
        cohort_delete_cohort($cohort);

        // Import the export we previously created.
        $importid = $this->wpgenerator->perform_import_from_export_id($export->get('id'), [
            rules_importer::IMPORT_CONTENT => 1,
            rules_importer::IMPORT_INSTANCES => rules_importer::IMPORT_INSTANCES_ALL,
        ]);

        // Validate import logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);

        $log = reset($logs);

        $rules = rule::get_records([], 'id');
        $newrule = end($rules);

        $ruleurl = (new moodle_url('/admin/tool/dynamicrule/rule.php', ['id' => $newrule->get('id')]))->out();
        $this->assertEquals("Created new rule '<a href=\"{$ruleurl}\">{$newrule->get('name')}</a>' with 1 conditions and 1 actions",
            $log['detail']);
        $this->assertCount(0, $log['errors']);
        $this->assertCount(0, $log['notices']);

        // The configuration of the new rule condition should have been reset/emptied.
        $condition = condition::get_record(['ruleid' => $newrule->get('id')]);
        $this->assertEquals([], json_decode($condition->get('configdata'), true));
    }

    /**
     * Test importing a rule as a user who doesn't have permission to edit one of it's conditions after the entity referred to
     * by the condition import mapping cannot be accessed by the user
     */
    public function test_import_rule_can_not_edit_condition(): void {
        $this->setAdminUser();

        $rule = $this->generator->create_rule([
            'enabled' => 1,
        ]);

        // Simple rule with single condition and outcome.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->generator->create_condition(\tool_dynamicrule\tool_dynamicrule\condition\course_completed::class, $rule->id,
            ['courseid' => $course->id]);
        $this->generator->create_outcome(\tool_dynamicrule\tool_dynamicrule\outcome\notification::class, $rule->id,
            ['subject' => 'You matched!', 'body' => ['text' => "{{userfullname}} matched!", 'format' => FORMAT_HTML]]);

        // Export the rule.
        $exportid = $this->wpgenerator->perform_export(rules_exporter::class, [
            rules_exporter::EXPORT_CONTENT => 1,
            rules_exporter::EXPORT_INSTANCES => rules_exporter::EXPORT_INSTANCES_ENABLED,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Switch to a user who will be able to add the condition, but not edit it.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Allow user to manage rules, but NOT courses.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = context_system::instance();
        assign_capability('tool/dynamicrule:manage', CAP_ALLOW, $roleid, $context->id);
        assign_capability('moodle/course:update', CAP_PROHIBIT, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);

        // Import the export we previously created.
        $importid = $this->wpgenerator->perform_import_from_export_id($export->get('id'), [
            rules_importer::IMPORT_CONTENT => 1,
            rules_importer::IMPORT_INSTANCES => rules_importer::IMPORT_INSTANCES_ALL,
        ]);

        // Validate import logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);

        $log = reset($logs);

        $rules = rule::get_records([], 'id');
        $newrule = end($rules);

        $ruleurl = (new moodle_url('/admin/tool/dynamicrule/rule.php', ['id' => $newrule->get('id')]))->out();
        $this->assertEquals("Created new rule '<a href=\"{$ruleurl}\">{$newrule->get('name')}</a>' with 1 conditions and 1 actions",
            $log['detail']);
        $this->assertCount(0, $log['errors']);
        $this->assertCount(0, $log['notices']);

        // The configuration of the new rule condition should have been reset/emptied.
        $condition = condition::get_record(['ruleid' => $newrule->get('id')]);
        $this->assertEquals([], json_decode($condition->get('configdata'), true));
    }
}
