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

namespace tool_certification\tool_dynamicrule\outcome;

use advanced_testcase;
use tool_certification\api;
use tool_certification\certification_user;
use tool_certification_generator;
use tool_dynamicrule\tool_dynamicrule\condition\cohort_member;
use tool_dynamicrule\tool_dynamicrule\condition\cohort_not_member;
use tool_dynamicrule_generator;
use tool_tenant_generator;
use tool_certification\constants;
use tool_dynamicrule\rule;

/**
 * Unit tests for outcome deallocation class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @covers     \tool_certification\tool_dynamicrule\outcome\deallocation
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class deallocation_test extends advanced_testcase {

    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_dynamicrule_generator */
    protected $drgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $outcome = deallocation::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $outcome->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $outcome = deallocation::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $outcome = deallocation::instance();
        $this->assertNotEmpty(get_string('pluginname', 'tool_certification'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $outcome = deallocation::instance();
        $configform = ['certificationid' => 10];
        $this->assertArrayHasKey('certificationid', $outcome->validate_config_form($configform));

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $certification1 = $this->generator->generate_certification(['tenantid' => $defaulttenantid]);

        $this->setAdminUser();
        $configform = ['certificationid' => $certification1->get('id')];
        $this->assertArrayNotHasKey('certificationid', $outcome->validate_config_form($configform));
    }

    /**
     * Test apply_to_user using non-current tenant.
     */
    public function test_apply_to_user_in_other_tenant(): void {
        global $DB;

        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);
        $certification1 = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $params = ['userid' => $user1->id, 'certificationid' => $certification1->get('id'),
                   'allocationtype' => constants::ALLOCATION_DYNAMIC,
                   'status' => constants::STATUS_OVERRIDE_DEFAULT];
        \tool_certification\api::allocate_user($certification1, (object)$params);

        $params['userid'] = $user2->id;
        \tool_certification\api::allocate_user($certification1, (object)$params);

        $params['userid'] = $user3->id;
        $params['allocationtype'] = constants::ALLOCATION_MANUAL;
        \tool_certification\api::allocate_user($certification1, (object)$params);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['certificationid' => $certification1->get('id'), 'action' => outcome_base::DEALLOCATE_USER];
        $outcome = deallocation::create($rule0->id, $configdata);

        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id], array_column($users, 'userid'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // All users were de-allocated.
        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([], array_column($users, 'userid'));
    }

    /**
     * Test apply_to_user using non-current tenant to suspend users.
     */
    public function test_apply_to_user_in_other_tenant_suspend(): void {
        global $DB;

        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $params = ['userid' => $user1->id, 'certificationid' => $certification->get('id'),
            'allocationtype' => constants::ALLOCATION_DYNAMIC, 'status' => constants::STATUS_OVERRIDE_DEFAULT];
        api::allocate_user($certification, (object) $params);

        $params['userid'] = $user2->id;
        $params['allocationtype'] = constants::ALLOCATION_CERTIFICATION;
        api::allocate_user($certification, (object) $params);

        $params['userid'] = $user3->id;
        $params['allocationtype'] = constants::ALLOCATION_MANUAL;
        api::allocate_user($certification, (object) $params);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['certificationid' => $certification->get('id'), 'action' => outcome_base::SUSPEND_USER];
        deallocation::create($rule0->id, $configdata);

        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id], array_column($users, 'userid'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $users = $DB->get_records('tool_certification_users', [], 'id', 'userid, status, allocationtype');
        // Deallocation should have only acted upon the user(s) with a dynamic allocation type and suspend it.
        $cu1 = array_filter($users, static function($allocation) use ($user1) {
            return $allocation->userid == $user1->id;
        });
        $expected = [
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
        ];
        $this->assertEquals($expected, (array) reset($cu1));

        $cu2 = array_filter($users, static function($allocation) use ($user2) {
            return $allocation->userid == $user2->id;
        });
        $expected = [
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
        ];
        $this->assertEquals($expected, (array) reset($cu2));

        $cu3 = array_filter($users, static function($allocation) use ($user3) {
            return $allocation->userid == $user3->id;
        });
        $expected = [
            'userid' => $user3->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_MANUAL,
        ];
        $this->assertEquals($expected, (array) reset($cu3));
    }

    /**
     * Test apply_to_user using shared tenant.
     */
    public function test_apply_to_user_in_shared_tenant(): void {
        global $DB;
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12, $user13]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $certification1 = $this->generator->generate_certification(['tenantid' => $sharedspaceid]);
        $params = ['userid' => $user11->id, 'certificationid' => $certification1->get('id'),
            'allocationtype' => constants::ALLOCATION_DYNAMIC, 'status' => constants::STATUS_OVERRIDE_DEFAULT];
        \tool_certification\api::allocate_user($certification1, (object) $params);
        $params = ['userid' => $user21->id, 'certificationid' => $certification1->get('id'),
            'allocationtype' => constants::ALLOCATION_DYNAMIC, 'status' => constants::STATUS_OVERRIDE_DEFAULT];
        \tool_certification\api::allocate_user($certification1, (object) $params);
        $params = ['userid' => $user22->id, 'certificationid' => $certification1->get('id'),
            'allocationtype' => constants::ALLOCATION_MANUAL, 'status' => constants::STATUS_OVERRIDE_DEFAULT];
        \tool_certification\api::allocate_user($certification1, (object) $params);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $sharedspaceid, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['certificationid' => $certification1->get('id'), 'action' => outcome_base::DEALLOCATE_USER];
        $outcome = deallocation::create($rule0->id, $configdata);

        // Sanity check.
        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user11->id, $user21->id, $user22->id], array_column($users, 'userid'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        // All users were de-allocated.
        $this->assertEqualsCanonicalizing([], array_column($users, 'userid'));
    }

    /**
     * Test apply_to_user using shared tenant to suspend allocations.
     */
    public function test_apply_to_user_in_shared_tenant_suspend(): void {
        global $DB;
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $certification = $this->generator->generate_certification(['tenantid' => $sharedspaceid]);
        $params = ['userid' => $user1->id, '$certificationid' => $certification->get('id'),
            'allocationtype' => constants::ALLOCATION_DYNAMIC, 'status' => constants::STATUS_OVERRIDE_DEFAULT];
        api::allocate_user($certification, (object) $params);
        $params = ['userid' => $user22->id, 'certificationid' => $certification->get('id'),
            'allocationtype' => constants::ALLOCATION_MANUAL, 'status' => constants::STATUS_OVERRIDE_DEFAULT];
        api::allocate_user($certification, (object) $params);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $sharedspaceid, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['certificationid' => $certification->get('id'), 'action' => outcome_base::SUSPEND_USER];
        $outcome = deallocation::create($rule0->id, $configdata);

        // Sanity check.
        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user22->id], array_column($users, 'userid'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $users = $DB->get_records('tool_certification_users', [], 'id', 'userid, status, allocationtype');
        // Deallocation should have only acted upon the user(s) with a dynamic allocation type.
        $cu1 = array_filter($users, static function($allocation) use ($user1) {
            return $allocation->userid == $user1->id;
        });
        $expected = [
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
        ];
        $this->assertEquals($expected, (array) reset($cu1));

        $cu2 = array_filter($users, static function($allocation) use ($user22) {
            return $allocation->userid == $user22->id;
        });
        $expected = [
            'userid' => $user22->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_MANUAL,
        ];
        $this->assertEquals($expected, (array) reset($cu2));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $certification1 = $this->generator->generate_certification();

        $rule1 = $this->drgenerator->create_rule();
        $configdata = ['certificationid' => $certification1->get('id'), 'action' => outcome_base::DEALLOCATE_USER];
        $outcome1 = deallocation::create($rule1->id, $configdata);

        $this->assertEquals(get_string('outcomedeallocationdescription', 'tool_certification',
            $certification1->get('fullname')), $outcome1->get_description());

        $rule2 = $this->drgenerator->create_rule();
        $configdata = ['certificationid' => $certification1->get('id'), 'action' => outcome_base::SUSPEND_USER];
        /** @var deallocation $outcome2 */
        $outcome2 = deallocation::create($rule2->id, $configdata);

        $outcomestr = get_string('outcomedeallocationdescriptionsuspend', 'tool_certification', $certification1->get('fullname'));
        $this->assertEquals($outcomestr, $outcome2->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $outcome = deallocation::create($rule1->id, []);
        $this->assertFalse($outcome->is_configuration_valid());

        // Users in certification1.
        $configdata = ['certificationid' => $certification->get('id')];
        $outcome = deallocation::create($rule1->id, $configdata);

        $this->assertTrue($outcome->is_configuration_valid());

        // Test certification is archived.
        \tool_certification\api::archive_certification($certification->get('id'));
        $this->assertFalse($outcome->is_configuration_valid());

        // Test certification is restored.
        \tool_certification\api::restore_certification($certification->get('id'));
        $this->assertTrue($outcome->is_configuration_valid());

        // Archive the tenant and delete the tenant.
        $manager = new \tool_tenant\manager();
        $manager->archive_tenant($tenant->id);
        $manager->delete_tenant($tenant->id);
        $this->assertFalse($outcome->is_configuration_valid());

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        // Delete certification.
        \tool_certification\api::archive_certification($certification->get('id'));
        $certification = new \tool_certification\certification($certification->get('id'));
        \tool_certification\api::delete_certification($certification);
        $this->assertFalse($outcome->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        deallocation::create($rule1->id, $configdata);

        $this->assertFalse(deallocation::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertTrue(deallocation::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        deallocation::create($rule1->id, $configdata);
        $this->assertFalse(deallocation::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertFalse(deallocation::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(deallocation::instance()->user_can_edit($configdata));
    }

    /**
     * Test apply_to_user using deallocation action.
     *
     * Add user to cohort, remove, add again.
     */
    public function test_apply_to_user_deallocate_several_times(): void {
        [$tenant, [$user1]] = $this->tenantgenerator->create_tenant_and_users(1);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $cohort1 = $this->getDataGenerator()->create_cohort();

        // Dynamic rule 1: If user is in cohort1 -> allocate to program1.
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $configdata = ['cohortid' => $cohort1->id];
        cohort_member::create($rule1->id, $configdata);
        $configdata = ['certificationid' => $certification->get('id'), 'action' => outcome_base::DONT_MODIFY_ALLOCATION];
        allocation::create($rule1->id, $configdata);

        // Dynamic rule 2: If user is not in cohort1 -> deallocate from program1.
        $rule2 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $configdata = ['cohortid' => $cohort1->id];
        cohort_not_member::create($rule2->id, $configdata);
        $configdata = ['certificationid' => $certification->get('id'), 'action' => outcome_base::DEALLOCATE_USER];
        deallocation::create($rule2->id, $configdata);

        // Sanity check.
        $this->assertCount(0, certification_user::get_records());

        cohort_add_member($cohort1->id, $user1->id);

        $certificationusers = certification_user::get_records();
        $this->assertCount(1, $certificationusers);
        $certificationuser = reset($certificationusers);
        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $certificationuser->get('status'));

        cohort_remove_member($cohort1->id, $user1->id);

        $this->assertCount(0, certification_user::get_records());

        cohort_add_member($cohort1->id, $user1->id);

        $certificationusers = certification_user::get_records();
        $this->assertCount(1, $certificationusers);
        $certificationuser = reset($certificationusers);
        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $certificationuser->get('status'));
    }

    /**
     * Test apply_to_user using suspend action.
     *
     * Add user to cohort, remove, add again.
     */
    public function test_apply_to_user_suspend_several_times(): void {
        [$tenant, [$user1]] = $this->tenantgenerator->create_tenant_and_users(1);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $cohort1 = $this->getDataGenerator()->create_cohort();

        // Dynamic rule 1: If user is in cohort1 -> allocate/unsuspend to program1.
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $configdata = ['cohortid' => $cohort1->id];
        cohort_member::create($rule1->id, $configdata);
        $configdata = ['certificationid' => $certification->get('id'), 'action' => outcome_base::UNSUSPEND_AND_KEEP_DATES];
        allocation::create($rule1->id, $configdata);

        // Dynamic rule 2: If user is not in cohort1 -> suspend from program1.
        $rule2 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $configdata = ['cohortid' => $cohort1->id];
        cohort_not_member::create($rule2->id, $configdata);
        $configdata = ['certificationid' => $certification->get('id'), 'action' => outcome_base::SUSPEND_USER];
        deallocation::create($rule2->id, $configdata);

        // Sanity check.
        $this->assertCount(0, certification_user::get_records());

        cohort_add_member($cohort1->id, $user1->id);

        $certificationusers = certification_user::get_records();
        $this->assertCount(1, $certificationusers);
        $certificationuser = reset($certificationusers);
        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $certificationuser->get('status'));

        cohort_remove_member($cohort1->id, $user1->id);

        $certificationusers = certification_user::get_records();
        $this->assertCount(1, $certificationusers);
        $certificationuser = reset($certificationusers);
        $this->assertEquals(constants::STATUS_OVERRIDE_SUSPENDED, $certificationuser->get('status'));

        cohort_add_member($cohort1->id, $user1->id);

        $certificationusers = certification_user::get_records();
        $this->assertCount(1, $certificationusers);
        $certificationuser = reset($certificationusers);
        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $certificationuser->get('status'));
    }
}
