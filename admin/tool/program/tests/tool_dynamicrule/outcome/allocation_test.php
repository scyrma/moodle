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

namespace tool_program\tool_dynamicrule\outcome;

use advanced_testcase;
use tool_dynamicrule_generator;
use tool_program\constants;
use tool_program_generator;
use tool_dynamicrule\rule;
use tool_tenant_generator;

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
class allocation_test extends advanced_testcase {

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
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->resetAfterTest();
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $outcome = allocation::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $outcome->supports_rule_types());
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

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
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
     * Test aply_to_user for action DONT_MODIFY_ALLOCATION
     */
    public function test_apply_to_user_actions_dont_modify(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        // By default start date is set to 2022-03-03.
        $startdate = strtotime('2022-03-03');
        // By default end date is set to 7 days after 2022-03-03.
        $enddate = strtotime('+7 day', $startdate);

        $program1 = $this->generator->generate_program((object)[
            'tenantid' => $tenant->id,
            'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => $startdate,
            'enddatetype' => constants::DATE_AFTER_START,
            'enddaterelative' => '7 day',
        ]);
        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id, 0,
            [
                'allocationtype' => constants::ALLOCATION_DYNAMIC,
                'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            ]);
        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id, 0,
            [
                'allocationtype' => constants::ALLOCATION_MANUAL,
                'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            ]);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['programid' => $program1->get('id'), 'startdatetype' => constants::DATE_NONE,
            'startdateabsolute' => 0, 'action' => outcome_base::DONT_MODIFY_ALLOCATION];
        allocation::create($rule0->id, $configdata);

        $this->assertEquals(2, $DB->count_records('tool_program_users'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $programusers = $DB->get_records('tool_program_users', [], 'id', 'userid, status, startdate, enddate');

        // Action didn't change status to active and didn't modify dates.
        $pu1 = array_filter($programusers, static function($programuser) use ($user1) {
            return $programuser->userid == $user1->id;
        });
        $expected = [
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdate' => $startdate,
            'enddate' => $enddate,
        ];
        $this->assertEquals($expected, (array) reset($pu1));

        // Action didn't change status to active and didn't modify dates.
        $pu2 = array_filter($programusers, static function($programuser) use ($user2) {
            return $programuser->userid == $user2->id;
        });
        $expected = [
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdate' => $startdate,
            'enddate' => $enddate,
        ];
        $this->assertEquals($expected, (array) reset($pu2));
    }

    /**
     * Test aply_to_user for action UNSUSPEND_AND_KEEP_DATES
     */
    public function test_apply_to_user_actions_unsuspend_keep_dates(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        // By default start date is set to 2022-03-03.
        $startdate = strtotime('2022-03-03');
        // By default end date is set to 7 days after 2022-03-03.
        $enddate = strtotime('+7 day', $startdate);

        $program1 = $this->generator->generate_program((object)[
            'tenantid' => $tenant->id,
            'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => $startdate,
            'enddatetype' => constants::DATE_AFTER_START,
            'enddaterelative' => '7 day',
        ]);
        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id, 0,
            [
                'allocationtype' => constants::ALLOCATION_DYNAMIC,
                'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            ]);
        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id, 0,
            [
                'allocationtype' => constants::ALLOCATION_MANUAL,
                'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            ]);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['programid' => $program1->get('id'), 'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => strtotime('2022-01-01'), 'action' => outcome_base::UNSUSPEND_AND_KEEP_DATES];
        allocation::create($rule0->id, $configdata);

        $this->assertEquals(2, $DB->count_records('tool_program_users'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $programusers = $DB->get_records('tool_program_users', [], 'id', 'userid, status, startdate, enddate, allocationtype');

        // Action changed status to active and didn't modify dates because allocation type is dinamic.
        $pu1 = array_filter($programusers, static function($programuser) use ($user1) {
            return $programuser->userid == $user1->id;
        });
        $expected = [
            'userid' => $user1->id,
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'startdate' => $startdate,
            'enddate' => $enddate,
        ];
        $this->assertEquals($expected, (array) reset($pu1));

        // Action didn't change status to active and didn't modify dates because allocation type is manual.
        $pu2 = array_filter($programusers, static function($programuser) use ($user2) {
            return $programuser->userid == $user2->id;
        });
        $expected = [
            'userid' => $user2->id,
            'allocationtype' => constants::ALLOCATION_MANUAL,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdate' => $startdate,
            'enddate' => $enddate,
        ];
        $this->assertEquals($expected, (array) reset($pu2));
    }

    /**
     * Test aply_to_user for action UNSUSPEND_AND_CHANGE_DATES
     */
    public function test_apply_to_user_actions_unsuspend_change_dates(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        // By default start date is set to 2022-03-03.
        $startdate = strtotime('2022-03-03');
        // By default end date is set to 7 days after 2022-03-03.
        $enddate = strtotime('+7 day', $startdate);

        $program1 = $this->generator->generate_program((object)[
            'tenantid' => $tenant->id,
            'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => $startdate,
            'enddatetype' => constants::DATE_AFTER_START,
            'enddaterelative' => '7 day',
        ]);
        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id, 0,
            [
                'allocationtype' => constants::ALLOCATION_DYNAMIC,
                'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            ]);
        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id, 0,
            [
                'allocationtype' => constants::ALLOCATION_MANUAL,
                'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            ]);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $newstartdate = strtotime('2022-01-01');
        $newenddate = strtotime('+7 day', $newstartdate);
        $configdata = ['programid' => $program1->get('id'), 'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => $newstartdate, 'action' => outcome_base::UNSUSPEND_AND_CHANGE_DATES];
        allocation::create($rule0->id, $configdata);

        $this->assertEquals(2, $DB->count_records('tool_program_users'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $programusers = $DB->get_records('tool_program_users', [], 'id', 'userid, status, startdate, enddate, allocationtype');

        // Action changed status to active and modified dates because allocation type is dinamic.
        $pu1 = array_filter($programusers, static function($programuser) use ($user1) {
            return $programuser->userid == $user1->id;
        });
        $expected = [
            'userid' => $user1->id,
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'startdate' => $newstartdate,
            'enddate' => $newenddate,
        ];
        $this->assertEquals($expected, (array) reset($pu1));

        // Action didn't change status to active and didn't modify dates because allocation type is manual.
        $pu2 = array_filter($programusers, static function($programuser) use ($user2) {
            return $programuser->userid == $user2->id;
        });
        $expected = [
            'userid' => $user2->id,
            'allocationtype' => constants::ALLOCATION_MANUAL,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdate' => $startdate,
            'enddate' => $enddate,
        ];
        $this->assertEquals($expected, (array) reset($pu2));
    }

    /**
     * Test apply_to_user using shared tenant.
     */
    public function test_apply_to_user_in_shared_tenant(): void {
        global $DB;
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        [$tenant2, [$user21, $user22]] = $this->tenantgenerator->create_tenant_and_users(2);

        $program1 = $this->generator->generate_program((object)['tenantid' => $sharedspaceid]);
        $rule0 = $this->drgenerator->create_rule(['tenantid' => $sharedspaceid, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['programid' => $program1->get('id'), 'startdatetype' => 0, 'startdateabsolute' => 0];
        allocation::create($rule0->id, $configdata);

        $this->assertEquals(0, $DB->count_records('tool_program_users'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $users = $DB->get_records('tool_program_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user21->id, $user22->id, get_admin()->id],
            array_column($users, 'userid'));

        // We try to allocate same users again.
        \tool_dynamicrule\api::process_rule($ruleinstance);
        $users = $DB->get_records('tool_program_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user21->id, $user22->id, get_admin()->id],
            array_column($users, 'userid'));
    }

    /**
     * Data provider for test_get_description
     *
     * @return array
     */
    public function tool_program_description_provider(): array {
        return [
            [
                'startdatetype' => constants::DATE_NONE,
                'action' => outcome_base::DONT_MODIFY_ALLOCATION,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_program', [
                    'programname' => 'Program1',
                    'startdatestr' => get_string('outcomeallocationdesckeepstartdate', 'tool_program'),
                    'suspendedusers' => get_string('outcomeallocationdontmodify', 'tool_program'),
                ])
            ],
            [
                'startdatetype' => constants::DATE_NONE,
                'action' => outcome_base::UNSUSPEND_AND_CHANGE_DATES,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_program', [
                    'programname' => 'Program1',
                    'startdatestr' => get_string('outcomeallocationdesckeepstartdate', 'tool_program'),
                    'suspendedusers' => get_string('outcomeallocationdescsuspendchangedate', 'tool_program'),
                ])
            ],
            [
                'startdatetype' => constants::DATE_NONE,
                'action' => outcome_base::UNSUSPEND_AND_KEEP_DATES,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_program', [
                    'programname' => 'Program1',
                    'startdatestr' => get_string('outcomeallocationdesckeepstartdate', 'tool_program'),
                    'suspendedusers' => get_string('outcomeallocationdesckeepdate', 'tool_program'),
                ])
            ],
            [
                'startdatetype' => constants::DATE_ABSOLUTE,
                'action' => outcome_base::DONT_MODIFY_ALLOCATION,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_program', [
                    'programname' => 'Program1',
                    'startdatestr' => get_string('outcomeallocationdescstartdate', 'tool_program',
                        ['startdate' => userdate(strtotime('2022-03-03'), get_string('strftimedatetimeshort'))]),
                    'suspendedusers' => get_string('outcomeallocationdontmodify', 'tool_program'),
                ])
            ],
            [
                'startdatetype' => constants::DATE_ABSOLUTE,
                'action' => outcome_base::UNSUSPEND_AND_CHANGE_DATES,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_program', [
                    'programname' => 'Program1',
                    'startdatestr' => get_string('outcomeallocationdescstartdate', 'tool_program',
                        ['startdate' => userdate(strtotime('2022-03-03'), get_string('strftimedatetimeshort'))]),
                    'suspendedusers' => get_string('outcomeallocationdescsuspendchangedate', 'tool_program'),
                ])
            ],
            [
                'startdatetype' => constants::DATE_ABSOLUTE,
                'action' => outcome_base::UNSUSPEND_AND_KEEP_DATES,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_program', [
                    'programname' => 'Program1',
                    'startdatestr' => get_string('outcomeallocationdescstartdate', 'tool_program',
                        ['startdate' => userdate(strtotime('2022-03-03'), get_string('strftimedatetimeshort'))]),
                    'suspendedusers' => get_string('outcomeallocationdesckeepdate', 'tool_program'),
                ])
            ],
        ];
    }

    /**
     * Test get_description
     *
     * @param int $startdatetype
     * @param int $action
     * @param string $expectedstr
     *
     * @dataProvider tool_program_description_provider
     */
    public function test_get_description(int $startdatetype, int $action, string $expectedstr): void {
        $program = $this->generator->generate_program((object) ['fullname' => 'Program1']);

        $rule = $this->drgenerator->create_rule();
        $configdata = [
            'programid' => $program->get('id'),
            'startdatetype' => $startdatetype,
            'action' => $action,
            'startdateabsolute' => strtotime('2022-03-03'),
        ];
        $outcome = allocation::create($rule->id, $configdata);

        $this->assertEquals($expectedstr, $outcome->get_description());
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
