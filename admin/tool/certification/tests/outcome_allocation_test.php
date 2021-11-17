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
 * File contains the unit tests for outcome allocation class.
 *
 * @package    tool_certification
 * @category   test
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\tool_dynamicrule\outcome\allocation;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for outcome\allocation class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_outcome_allocation_testcase extends advanced_testcase {

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
        global $CFG;
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
        }
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
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
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $outcome = allocation::instance();
        $configform = ['certificationid' => 123];
        $this->assertArrayHasKey('certificationid', $outcome->validate_config_form($configform));

        $defaulttenantid = tenancy::get_default_tenant_id();
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

        $tenant = $this->tenantgenerator->create_tenant();

        $certification1 = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['certificationid' => $certification1->get('id'), 'startdatetype' => 0, 'startdateabsolute' => 0];
        $outcome = allocation::create($rule0->id, $configdata);

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'userid'));

        // We try to allocate same users again.
        \tool_dynamicrule\api::process_rule($ruleinstance);
        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'userid'));
        $this->assertCount(2, $users);
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $certification1 = $this->generator->generate_certification();

        $rule1 = $this->drgenerator->create_rule();
        $configdata = [
            'certificationid' => $certification1->get('id'),
            'startdatetype' => \tool_certification\constants::DATE_NONE
        ];
        $outcome1 = allocation::create($rule1->id, $configdata);

        $this->assertEquals(get_string('outcomeallocationdescription', 'tool_certification',
            $certification1->get('fullname')), $outcome1->get_description());

        $now = time();
        $rule2 = $this->drgenerator->create_rule();
        $configdata = [
            'certificationid' => $certification1->get('id'),
            'startdatetype' => \tool_certification\constants::DATE_ABSOLUTE,
            'startdateabsolute' => $now
        ];
        $outcome1 = allocation::create($rule2->id, $configdata);
        $startdate = userdate($now, get_string('strftimedatetimeshort'));
        $strparams = ['certificationname' => $certification1->get('fullname'), 'startdate' => $startdate];
        $expected = get_string('outcomeallocationdescriptionwithdate', 'tool_certification', $strparams);
        $this->assertEquals($expected, $outcome1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $outcome = allocation::create($rule1->id, []);
        $this->assertFalse($outcome->is_configuration_valid());

        // Users in certification1.
        $configdata = ['certificationid' => $certification->get('id')];
        $outcome = allocation::create($rule1->id, $configdata);

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
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        allocation::create($rule1->id, $configdata);

        $this->assertFalse(allocation::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertTrue(allocation::instance()->user_can_add());
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
        allocation::create($rule1->id, $configdata);
        $this->assertFalse(allocation::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertFalse(allocation::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(allocation::instance()->user_can_edit($configdata));
    }
}
