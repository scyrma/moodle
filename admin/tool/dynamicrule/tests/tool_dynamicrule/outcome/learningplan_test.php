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

declare(strict_types=1);

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use tool_dynamicrule\api;
use tool_dynamicrule\outcome;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;
use tool_tenant\sharedspace;
use tool_wp\local\exportimport\import_manager;

/**
 * Unit tests for outcome\learning_plan class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\outcome\learning_plan
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class learningplan_test extends \advanced_testcase {

    /** @var \core_competency_generator $lpg */
    protected $lpg;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
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
     * Return tenant generator
     *
     * @return \tool_tenant_generator
     */
    private function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $outcome = learning_plan::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $outcome->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $outcome = learning_plan::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $outcome = learning_plan::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $outcome->get_category());
    }

    /**
     * Test apply_to_user
     */
    public function test_apply_to_user(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);

        $lptemplate = $this->lpg->create_template();
        $configdata = ['learningplan' => $lptemplate->get('id')];
        learning_plan::create($rule0->id, $configdata);

        // Create two new users.
        $userids = [
            $this->getDataGenerator()->create_user()->id,
            $this->getDataGenerator()->create_user()->id,
        ];

        // Create third user, and add it to learning plan.
        $userwithlp = $this->getDataGenerator()->create_user()->id;
        $this->lpg->create_plan(array('userid' => $userwithlp, 'templateid' => $lptemplate->get('id')));

        // Only user with learning plan assigned is stored.
        $this->assertEquals([$userwithlp], $DB->get_fieldset_select('competency_plan', 'userid', ''));

        // Set a conditions always in true.
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        // Process rule manually.
        $ruleinstance = new rule(0, $rule0);
        api::process_rule($ruleinstance);

        // Expect four affected users (admin user and those three we created).
        $userids[] = get_admin()->id;
        $userids[] = $userwithlp;
        $this->assertEqualsCanonicalizing($userids, $DB->get_fieldset_select('competency_plan', 'userid', ''));
    }

    /**
     * Test apply_to_user using non-current tenant
     */
    public function test_apply_to_user_in_other_tenant(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $tenant = $this->get_tenant_generator()->create_tenant();
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $lptemplate = $this->lpg->create_template();
        $configdata = ['learningplan' => $lptemplate->get('id')];
        learning_plan::create($rule0->id, $configdata);

        // Create two users in tenant.
        $userids = [
            $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id])->id,
            $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id])->id,
        ];

        // Create third user in tenant1.
        $userintenant1 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant1->id])->id;

        // Process rule manually.
        $ruleinstance = new rule(0, $rule0);
        api::process_rule($ruleinstance);

        // Expect two tenant users to be affected since third user is belongs to other tenant.
        $this->assertEqualsCanonicalizing($userids, $DB->get_fieldset_select('competency_plan', 'userid', ''));

        // Now let's create a rule in tenant1.
        $rule1 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant1->id]);
        $this->get_generator()->create_condition_alwaystrue($rule1->id);

        $configdata1 = ['learningplan' => $lptemplate->get('id')];
        learning_plan::create($rule1->id, $configdata1);

        // Process rule manually.
        $ruleinstance1 = new rule(0, $rule1);
        api::process_rule($ruleinstance1);

        // Expect all users including the once allocated in tenant1.
        $userids[] = $userintenant1;
        $this->assertEqualsCanonicalizing($userids, $DB->get_fieldset_select('competency_plan', 'userid', ''));
    }

    /**
     * Test apply_to_user using shared tenant
     */
    public function test_apply_to_user_in_shared_tenant(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $sharedspaceid = sharedspace::enable_shared_space();
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $sharedspaceid]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $lptemplate = $this->lpg->create_template();
        $configdata = ['learningplan' => $lptemplate->get('id')];
        learning_plan::create($rule0->id, $configdata);

        [$tenant, [$user1, $user2]] = $this->get_tenant_generator()->create_tenant_and_users(2);
        [$tenant2, [$user21, $user22]] = $this->get_tenant_generator()->create_tenant_and_users(2);

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new rule(0, $rule0);
        api::process_rule($ruleinstance);

        // Expect all tenant users to be affected.
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user21->id, $user22->id, get_admin()->id],
            $DB->get_fieldset_select('competency_plan', 'userid', ''));
    }

    /**
     * Test get_displayed_description.
     *
     */
    public function test_get_displayed_description(): void {
        global $DB;
        $this->resetAfterTest();
        $shortname = 'Test learning plan 1';
        $lptemplate = $this->lpg->create_template(['shortname' => $shortname]);
        $rule0 = $this->get_generator()->create_rule();
        $configdata = ['learningplan' => $lptemplate->get('id')];
        $outcome = learning_plan::create($rule0->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $str = get_string('outcomelearningplandescription', 'tool_dynamicrule', $shortname);
        $this->assertEquals($str, $outcome->get_displayed_description());

        // User with user role has required permission.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user']);
        assign_capability('moodle/competency:planmanage', CAP_ALLOW, $roleid, \context_system::instance());
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertEquals($str, $outcome->get_displayed_description());

        // Revoke permission to view learning plan template.
        role_change_permission($roleid, \context_system::instance(), 'moodle/competency:planmanage', CAP_PROHIBIT);
        $strunedit = get_string('uneditabledescription', 'tool_dynamicrule', $outcome->get_title());
        $this->assertEquals($strunedit, $outcome->get_displayed_description());
    }

    /**
     * Test outcome field mapping during export/import
     */
    public function test_field_mapping(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $lpdata = ['shortname' => 'My learning plan template'];
        $lptemplate = $this->lpg->create_template($lpdata);

        // Create rule containing outcome, pointing to the learning plan template we just created.
        $rule = $this->get_generator()->create_rule();
        $this->get_generator()->create_outcome(learning_plan::class, $rule->id, ['learningplan' => $lptemplate->get('id')]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original learning plan, and create a new one with the same name.
        $originalid = $lptemplate->get('id');

        \core_competency\api::delete_template($originalid);

        $newlp = $this->lpg->create_template($lpdata)->to_record();

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the learning plan mapping data was added.
        $mappingdata = (new import_manager($importid))->get_raw_mapping_from_workplace_export_file('learningplan', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported outcome field should be mapped to the new learning plan.
        $rules = rule::get_records([], 'id');

        $outcome = learning_plan::instance(0, outcome::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($newlp->id, $outcome->get_configdata()['learningplan']);
        $this->assertTrue($outcome->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $this->resetAfterTest();
        $rule0 = $this->get_generator()->create_rule();

        $lpdata = ['shortname' => 'My learning plan template'];
        $lptemplate = $this->lpg->create_template($lpdata);
        $configdata = ['learningplan' => $lptemplate->get('id')];
        learning_plan::create($rule0->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(learning_plan::instance()->user_can_add());

        // Tenant admin cannot add this action.
        $tenantadmin = self::get_tenant_generator()->create_user(['tenantadmin' => true]);
        self::setUser($tenantadmin);
        $this->assertFalse(learning_plan::instance()->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(learning_plan::instance()->user_can_add());

        // Grant priveleges to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = \context_system::instance();
        assign_capability('moodle/competency:planmanage', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertTrue(learning_plan::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $this->resetAfterTest();
        $rule0 = $this->get_generator()->create_rule();
        $lpdata = ['shortname' => 'My learning plan template'];
        $lptemplate = $this->lpg->create_template($lpdata);
        $configdata = ['learningplan' => $lptemplate->get('id')];
        learning_plan::create($rule0->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(learning_plan::instance()->user_can_edit($configdata));

        // Tenant admin cannot edit this action.
        $tenantadmin = self::get_tenant_generator()->create_user(['tenantadmin' => true]);
        self::setUser($tenantadmin);
        $this->assertFalse(learning_plan::instance()->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(learning_plan::instance()->user_can_edit($configdata));

        // Grant priveleges to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = \context_system::instance();
        assign_capability('moodle/competency:planmanage', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertTrue(learning_plan::instance()->user_can_edit($configdata));
    }
}
