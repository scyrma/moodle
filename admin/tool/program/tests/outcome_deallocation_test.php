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
 * File contains the unit tests for outcome deallocation class.
 *
 * @package     tool_program
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\api;
use tool_program\constants;
use tool_program\tool_dynamicrule\outcome\deallocation;
use tool_dynamicrule\rule;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for outcome deallocation class.
 *
 * @covers      \tool_program\tool_dynamicrule\outcome\deallocation
 * @package     tool_program
 * @group       tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_outcome_deallocation_testcase extends advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_dynamicrule_generator */
    protected $drgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        global $CFG;
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
        }
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
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
        $this->assertEquals(get_string('pluginname', 'tool_program'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $outcome = deallocation::instance();
        $configform = ['programid' => 10];
        $this->assertArrayHasKey('programid', $outcome->validate_config_form($configform));

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $program1 = $this->generator->generate_program((object) ['tenantid' => $defaulttenantid]);

        $configform = ['programid' => $program1->get('id')];
        $this->setAdminUser();
        $this->assertArrayNotHasKey('programid', $outcome->validate_config_form($configform));
    }

    /**
     * Test apply_to_user using non-current tenant.
     */
    public function test_apply_to_user_in_other_tenant(): void {
        global $DB;

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $tenant = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user3->id, $tenant->id);

        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $params = ['userid' => $user1->id, 'programid' => $program1->get('id'), 'certificationid' => 0,
            'allocationtype' => constants::ALLOCATION_DYNAMIC];
        api::allocate_user($program1, (object) $params);

        $params['userid'] = $user2->id;
        $params['allocationtype'] = constants::ALLOCATION_CERTIFICATION;
        api::allocate_user($program1, (object) $params);

        $params['userid'] = $user3->id;
        $params['allocationtype'] = constants::ALLOCATION_MANUAL;
        api::allocate_user($program1, (object) $params);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['programid' => $program1->get('id')];
        $outcome = deallocation::create($rule0->id, $configdata);

        $users = $DB->get_records('tool_program_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id], array_column($users, 'userid'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $users = $DB->get_records('tool_program_users', [], '', 'userid');
        // Deallocation should have only acted upon the user(s) with a dynamic allocation type.
        $this->assertEqualsCanonicalizing([$user2->id, $user3->id], array_column($users, 'userid'));
    }

    /**
     * Test apply_to_user using shared tenant.
     */
    public function test_apply_to_user_in_shared_tenant(): void {
        global $DB;
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program1 = $this->generator->generate_program_with_course((object)['tenantid' => $sharedspaceid]);
        $params = ['userid' => $user1->id, 'programid' => $program1->get('id'), 'certificationid' => 0,
            'allocationtype' => constants::ALLOCATION_DYNAMIC];
        api::allocate_user($program1, (object) $params);
        $params = ['userid' => $user21->id, 'programid' => $program1->get('id'), 'certificationid' => 0,
            'allocationtype' => constants::ALLOCATION_DYNAMIC];
        api::allocate_user($program1, (object) $params);
        $params = ['userid' => $user22->id, 'programid' => $program1->get('id'), 'certificationid' => 0,
            'allocationtype' => constants::ALLOCATION_MANUAL];
        api::allocate_user($program1, (object) $params);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $sharedspaceid, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['programid' => $program1->get('id')];
        $outcome = deallocation::create($rule0->id, $configdata);

        // Sanity check.
        $users = $DB->get_records('tool_program_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user21->id, $user22->id], array_column($users, 'userid'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $users = $DB->get_records('tool_program_users', [], '', 'userid');
        // Deallocation should have only acted upon the user(s) with a dynamic allocation type.
        $this->assertEqualsCanonicalizing([$user22->id], array_column($users, 'userid'));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $program1 = $this->generator->generate_program();

        $rule1 = $this->drgenerator->create_rule();
        $configdata = ['programid' => $program1->get('id')];
        /** @var deallocation $outcome1 */
        $outcome1 = deallocation::create($rule1->id, $configdata);

        $outcomestr = get_string('outcomedeallocationdescription', 'tool_program', $program1->get('fullname'));
        $this->assertEquals($outcomestr, $outcome1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $outcome = deallocation::create($rule1->id, []);
        $this->assertFalse($outcome->is_configuration_valid());

        // Users in program1.
        $configdata = ['programid' => $program1->get('id')];
        $outcome = deallocation::create($rule1->id, $configdata);

        $this->assertTrue($outcome->is_configuration_valid());

        // Test program is archived.
        \tool_program\api::archive_program($program1);
        $this->assertFalse($outcome->is_configuration_valid());

        // Restore program.
        \tool_program\api::restore_program($program1);
        $this->assertTrue($outcome->is_configuration_valid());

        // Delete program.
        \tool_program\api::archive_program($program1);
        \tool_program\api::delete_program($program1);
        $this->assertFalse($outcome->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        self::setUser($user);

        // Users in program1.
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        deallocation::create($rule1->id, $configdata);
        $this->assertFalse(deallocation::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertTrue(deallocation::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        deallocation::create($rule1->id, $configdata);
        $this->assertFalse(deallocation::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertFalse(deallocation::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(deallocation::instance()->user_can_edit($configdata));
    }
}
