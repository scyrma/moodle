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
 * File contains the unit tests for outcome program allocation class.
 *
 * @package    tool_program
 * @category   test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\tool_dynamicrule\outcome\allocation;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for outcome program allocation class.
 *
 * @covers      \tool_program\tool_dynamicrule\outcome\allocation
 * @package     tool_program
 * @group       tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_outcome_allocation_testcase extends advanced_testcase {

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
     * Test get_title
     */
    public function test_get_title(): void {
        $outcome = allocation::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $outcome = allocation::instance();
        $this->assertEquals(get_string('pluginname', 'tool_program'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $outcome = allocation::instance();
        $configform = ['programid' => 123];
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
        $tenant = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);

        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['programid' => $program1->get('id'), 'startdatetype' => 0, 'startdateabsolute' => 0];
        allocation::create($rule0->id, $configdata);

        $this->assertEquals(0, $DB->count_records('tool_program_users'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $users = $DB->get_records('tool_program_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'userid'));

        // We try to allocate same users again.
        \tool_dynamicrule\api::process_rule($ruleinstance);
        $users = $DB->get_records('tool_program_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'userid'));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $program1 = $this->generator->generate_program();

        $rule1 = $this->drgenerator->create_rule();
        $configdata = ['programid' => $program1->get('id'), 'startdatetype' => \tool_program\constants::DATE_NONE];
        /** @var allocation $outcome1 */
        $outcome1 = allocation::create($rule1->id, $configdata);

        $expected = get_string('outcomeallocationdescription', 'tool_program', $program1->get('fullname'));
        $this->assertEquals($expected, $outcome1->get_description());

        $now = time();
        $rule2 = $this->drgenerator->create_rule();
        $configdata = [
            'programid' => $program1->get('id'),
            'startdatetype' => \tool_program\constants::DATE_ABSOLUTE,
            'startdateabsolute' => $now
        ];
        /** @var allocation $outcome1 */
        $outcome1 = allocation::create($rule2->id, $configdata);

        $options = ['programname' => $program1->get('fullname')];
        $options['startdate'] = userdate($now, get_string('strftimedatetimeshort'));
        $expected = get_string('outcomeallocationdescriptionwithdate', 'tool_program', $options);
        $this->assertEquals($expected, $outcome1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $outcome = allocation::create($rule1->id, []);
        $this->assertFalse($outcome->is_configuration_valid());

        // Users in program1.
        $configdata = ['programid' => $program1->get('id')];
        $outcome = allocation::create($rule1->id, $configdata);

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
        allocation::create($rule1->id, $configdata);
        $this->assertFalse(allocation::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertTrue(allocation::instance()->user_can_add());
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
        allocation::create($rule1->id, $configdata);
        $this->assertFalse(allocation::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertFalse(allocation::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(allocation::instance()->user_can_edit($configdata));
    }
}
