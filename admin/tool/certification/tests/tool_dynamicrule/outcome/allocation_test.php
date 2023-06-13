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
use tool_certification\constants;
use tool_certification_generator;
use tool_dynamicrule_generator;
use tool_tenant_generator;
use tool_dynamicrule\rule;
use tool_tenant\tenancy;

/**
 * Unit tests for outcome\allocation class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @covers     \tool_certification\tool_dynamicrule\outcome\allocation
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class allocation_test extends advanced_testcase {

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

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        $certification1 = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['certificationid' => $certification1->get('id'), 'startdatetype' => 0, 'startdateabsolute' => 0];
        $outcome = allocation::create($rule0->id, $configdata);

        // Process rule manually as if we enabled it.
        $ruleinstance = new rule(0, $rule0);
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
     * Test aply_to_user for action DONT_MODIFY_ALLOCATION
     */
    public function test_apply_to_user_actions_dont_modify(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        // By default start date is set to 2022-03-03.
        $startdate = strtotime('2022-03-03');

        $certification = $this->generator->generate_certification([
            'tenantid' => $tenant->id,
            'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => $startdate,
        ]);

        $params = [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
        ];
        api::allocate_user($certification, (object) $params);
        $params['userid'] = $user2->id;
        $params['allocationtype'] = constants::ALLOCATION_MANUAL;
        api::allocate_user($certification, (object) $params);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['certificationid' => $certification->get('id'), 'startdatetype' => constants::DATE_NONE,
            'startdateabsolute' => 0, 'action' => outcome_base::DONT_MODIFY_ALLOCATION];
        allocation::create($rule0->id, $configdata);

        $this->assertEquals(2, $DB->count_records('tool_certification_users'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $sql = "
            SELECT cu.userid, cu.status, pu.status as programstatus, pu.startdate
            FROM {tool_certification_users} cu
            JOIN {tool_program_users} pu
            ON cu.userid = pu.userid AND pu.certificationid = cu.certificationid
        ";
        $allocations = $DB->get_records_sql($sql);
        $this->assertCount(2, $allocations);

        // The action didn't change status to active and didn't modify start date because we are
        // using DONT_MODIFY_ALLOCATION.
        $cu1 = array_filter($allocations, static function($allocation) use ($user1) {
            return $allocation->userid == $user1->id;
        });
        $expected = [
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'programstatus' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdate' => $startdate,
        ];
        $this->assertEquals($expected, (array) reset($cu1));

        // The action didn't affect this allocation because is a MANUAL allocation.
        $cu2 = array_filter($allocations, static function($allocation) use ($user2) {
            return $allocation->userid == $user2->id;
        });
        $expected['userid'] = $user2->id;
        $this->assertEquals($expected, (array) reset($cu2));
    }

    /**
     * Test aply_to_user for action UNSUSPEND_AND_KEEP_DATES
     */
    public function test_apply_to_user_actions_unsuspend_keep_dates(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        // By default start date is set to 2022-03-03.
        $startdate = strtotime('2022-03-03');

        $certification = $this->generator->generate_certification([
            'tenantid' => $tenant->id,
            'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => $startdate,
        ]);
        $params = [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdate' => $startdate,
            'startdatelocked' => 1,
        ];
        api::allocate_user($certification, (object)$params);
        $params['userid'] = $user2->id;
        $params['allocationtype'] = constants::ALLOCATION_MANUAL;
        api::allocate_user($certification, (object)$params);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['certificationid' => $certification->get('id'), 'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => strtotime('2022-01-01'), 'action' => outcome_base::UNSUSPEND_AND_KEEP_DATES];
        allocation::create($rule0->id, $configdata);

        $this->assertEquals(2, $DB->count_records('tool_certification_users'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $sql = "
            SELECT cu.userid, cu.status, pu.status as programstatus, pu.startdate
            FROM {tool_certification_users} cu
            JOIN {tool_program_users} pu
            ON cu.userid = pu.userid AND pu.certificationid = cu.certificationid
        ";
        $allocations = $DB->get_records_sql($sql);
        $this->assertCount(2, $allocations);

        // The action changed status to active and didn't modify start date because we are
        // using UNSUSPEND_AND_KEEP_DATES.
        $cu1 = array_filter($allocations, static function($allocation) use ($user1) {
            return $allocation->userid == $user1->id;
        });
        $expected = [
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'programstatus' => constants::STATUS_OVERRIDE_DEFAULT,
            'startdate' => $startdate,
        ];
        $this->assertEquals($expected, (array) reset($cu1));

        // The action didn't affect this allocation because is a MANUAL allocation.
        $cu2 = array_filter($allocations, static function($allocation) use ($user2) {
            return $allocation->userid == $user2->id;
        });
        $expected = [
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'programstatus' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdate' => $startdate,
        ];
        $this->assertEquals($expected, (array) reset($cu2));
    }

    /**
     * Test aply_to_user for action UNSUSPEND_AND_CHANGE_DATES
     */
    public function test_apply_to_user_actions_unsuspend_change_dates(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        // By default start date is set to 2022-03-03.
        $startdate = strtotime('2022-03-03');

        $certification = $this->generator->generate_certification([
            'tenantid' => $tenant->id,
            'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => $startdate,
        ]);
        $params = [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdate' => $startdate,
            'startdatelocked' => 1,
        ];
        api::allocate_user($certification, (object)$params);
        $params['userid'] = $user2->id;
        $params['allocationtype'] = constants::ALLOCATION_MANUAL;
        api::allocate_user($certification, (object)$params);

        $rule0 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $newstartdate = strtotime('2022-01-01');
        $configdata = ['certificationid' => $certification->get('id'), 'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => $newstartdate, 'action' => outcome_base::UNSUSPEND_AND_CHANGE_DATES];
        allocation::create($rule0->id, $configdata);

        $this->assertEquals(2, $DB->count_records('tool_certification_users'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $sql = "
            SELECT cu.userid, cu.status, pu.status as programstatus, pu.startdate
            FROM {tool_certification_users} cu
            JOIN {tool_program_users} pu
            ON cu.userid = pu.userid AND pu.certificationid = cu.certificationid
        ";
        $allocations = $DB->get_records_sql($sql);
        $this->assertCount(2, $allocations);

        // Action changed status to active and modified start date.
        $cu1 = array_filter($allocations, static function($allocation) use ($user1) {
            return $allocation->userid == $user1->id;
        });
        $expected = [
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'programstatus' => constants::STATUS_OVERRIDE_DEFAULT,
            'startdate' => $newstartdate,
        ];
        $this->assertEquals($expected, (array) reset($cu1));

        // The action didn't affect this allocation because is a MANUAL allocation.
        $cu2 = array_filter($allocations, static function($allocation) use ($user2) {
            return $allocation->userid == $user2->id;
        });
        $expected = [
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'programstatus' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdate' => $startdate,
        ];
        $this->assertEquals($expected, (array) reset($cu2));
    }

    /**
     * Test apply_to_user using shared tenant.
     */
    public function test_apply_to_user_in_shared_tenant(): void {
        global $DB;
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12]] = $this->tenantgenerator->create_tenant_and_users(2);
        [$tenant2, [$user21, $user22]] = $this->tenantgenerator->create_tenant_and_users(2);

        $certification1 = $this->generator->generate_certification(['tenantid' => $sharedspaceid]);
        $rule0 = $this->drgenerator->create_rule(['tenantid' => $sharedspaceid, 'enabled' => 1]);
        $this->drgenerator->create_condition_alwaystrue($rule0->id);

        $configdata = ['certificationid' => $certification1->get('id'), 'startdatetype' => 0, 'startdateabsolute' => 0];
        $outcome = allocation::create($rule0->id, $configdata);

        $this->assertEquals(0, $DB->count_records('tool_certification_users'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user11->id, $user12->id, $user21->id, $user22->id, get_admin()->id],
            array_column($users, 'userid'));

        // We try to allocate same users again.
        \tool_dynamicrule\api::process_rule($ruleinstance);
        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEqualsCanonicalizing([$user11->id, $user12->id, $user21->id, $user22->id, get_admin()->id],
            array_column($users, 'userid'));
    }

    /**
     * Data provider for test_get_description
     *
     * @return array
     */
    public function tool_certification_description_provider(): array {
        return [
            [
                'startdatetype' => constants::DATE_NONE,
                'action' => outcome_base::DONT_MODIFY_ALLOCATION,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_certification', [
                    'certificationname' => 'Certification1',
                    'startdatestr' => get_string('outcomeallocationdesckeepstartdate', 'tool_certification'),
                    'suspendedusers' => get_string('outcomeallocationdontmodify', 'tool_certification'),
                ])
            ],
            [
                'startdatetype' => constants::DATE_NONE,
                'action' => outcome_base::UNSUSPEND_AND_CHANGE_DATES,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_certification', [
                    'certificationname' => 'Certification1',
                    'startdatestr' => get_string('outcomeallocationdesckeepstartdate', 'tool_certification'),
                    'suspendedusers' => get_string('outcomeallocationdescsuspendchangedate', 'tool_certification'),
                ])
            ],
            [
                'startdatetype' => constants::DATE_NONE,
                'action' => outcome_base::UNSUSPEND_AND_KEEP_DATES,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_certification', [
                    'certificationname' => 'Certification1',
                    'startdatestr' => get_string('outcomeallocationdesckeepstartdate', 'tool_certification'),
                    'suspendedusers' => get_string('outcomeallocationdesckeepdate', 'tool_certification'),
                ])
            ],
            [
                'startdatetype' => constants::DATE_ABSOLUTE,
                'action' => outcome_base::DONT_MODIFY_ALLOCATION,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_certification', [
                    'certificationname' => 'Certification1',
                    'startdatestr' => get_string('outcomeallocationdescstartdate', 'tool_certification',
                        ['startdate' => userdate(strtotime('2022-03-03'), get_string('strftimedatetimeshort'))]),
                    'suspendedusers' => get_string('outcomeallocationdontmodify', 'tool_certification'),
                ])
            ],
            [
                'startdatetype' => constants::DATE_ABSOLUTE,
                'action' => outcome_base::UNSUSPEND_AND_CHANGE_DATES,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_certification', [
                    'certificationname' => 'Certification1',
                    'startdatestr' => get_string('outcomeallocationdescstartdate', 'tool_certification',
                        ['startdate' => userdate(strtotime('2022-03-03'), get_string('strftimedatetimeshort'))]),
                    'suspendedusers' => get_string('outcomeallocationdescsuspendchangedate', 'tool_certification'),
                ])
            ],
            [
                'startdatetype' => constants::DATE_ABSOLUTE,
                'action' => outcome_base::UNSUSPEND_AND_KEEP_DATES,
                'expectedstr' => get_string('outcomeallocationdescdate', 'tool_certification', [
                    'certificationname' => 'Certification1',
                    'startdatestr' => get_string('outcomeallocationdescstartdate', 'tool_certification',
                        ['startdate' => userdate(strtotime('2022-03-03'), get_string('strftimedatetimeshort'))]),
                    'suspendedusers' => get_string('outcomeallocationdesckeepdate', 'tool_certification'),
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
     * @dataProvider tool_certification_description_provider
     */
    public function test_get_description(int $startdatetype, int $action, string $expectedstr): void {
        $certification1 = $this->generator->generate_certification(['fullname' => 'Certification1']);

        $rule = $this->drgenerator->create_rule();
        $configdata = [
            'certificationid' => $certification1->get('id'),
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
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
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
