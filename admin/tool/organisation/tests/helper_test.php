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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_organisation;

use tool_organisation_generator;
use tool_tenant_generator;
use tool_organisation\local\helpers\user_manager;

/**
 * Tests for Organisation structure helper class
 *
 * @covers     \tool_organisation\helper
 * @package    tool_organisation
 * @category   test
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class helper_test extends \advanced_testcase {

    /**
     * Organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_generator() : tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Data provider for get_managed_users_and_direct_managers
     *
     * @return array [$users, $expectedsubordinates]
     */
    public static function direct_job_managers_provider(): array {
        $datepast1 = \tool_organisation\helper::round_time(time() - 10 * DAYSECS);
        $datepast2 = \tool_organisation\helper::round_time(time() - 5 * DAYSECS);
        $datefuture1 = \tool_organisation\helper::round_time(time() + 5 * DAYSECS);
        $datefuture2 = \tool_organisation\helper::round_time(time() + 10 * DAYSECS);
        [$inactivejob1, $inactivejob2, $inactivejob3] = [
            ['startdate' => $datefuture1],
            ['startdate' => $datepast1, 'enddate' => $datepast2],
            ['startdate' => $datefuture1, 'enddate' => $datefuture2],
        ];
        [$activejob1, $activejob2] = [
            ['startdate' => $datepast2],
            ['startdate' => $datepast2, 'enddate' => $datefuture1],
        ];
        return [
            'alice is a direct department lead of bob' => [
                [
                    ['user' => 'alice', 'position' => 'depmgr', 'department' => 'd2', 'other' => $activejob1],
                    ['user' => 'bob', 'position' => 'other', 'department' => 'd2', 'other' => $activejob2],
                ],
                ['dptlead' => true, 'manager' => false],
            ],
            'alice and bob are both department managers and neither is a manager of another one' => [
                [
                    ['user' => 'alice', 'position' => 'depmgr', 'department' => 'd2', 'other' => $activejob2],
                    ['user' => 'bob', 'position' => 'depmgr', 'department' => 'd2', 'other' => $activejob1],
                ],
                ['dptlead' => false, 'manager' => false],
            ],
            'alice is a direct manager of bob' => [
                [
                    ['user' => 'alice', 'position' => 'posmgr', 'department' => 'd2', 'other' => $activejob1],
                    ['user' => 'bob', 'position' => 'emp', 'department' => 'd1', 'other' => $activejob1],
                ],
                ['dptlead' => false, 'manager' => true],
            ],
            'alice is a both a direct dept lead and manager of bob' => [
                [
                    ['user' => 'alice', 'position' => 'depmgr', 'department' => 'd2', 'other' => $activejob2],
                    ['user' => 'alice', 'position' => 'posmgr', 'department' => 'd1', 'other' => $activejob2],
                    ['user' => 'bob', 'position' => 'emp', 'department' => 'd2', 'other' => $activejob1],
                ],
                ['dptlead' => true, 'manager' => true],
            ],
            'alice is not a direct department lead of bob because alices job is not active' => [
                [
                    ['user' => 'alice', 'position' => 'depmgr', 'department' => 'd2', 'other' => $inactivejob1],
                    ['user' => 'bob', 'position' => 'other', 'department' => 'd2', 'other' => $activejob1],
                ],
                ['dptlead' => false, 'manager' => false],
            ],
            'alice is not a direct department lead of bob because bobs job is not active' => [
                [
                    ['user' => 'alice', 'position' => 'depmgr', 'department' => 'd2', 'other' => $activejob1],
                    ['user' => 'bob', 'position' => 'other', 'department' => 'd2', 'other' => $inactivejob2],
                ],
                ['dptlead' => false, 'manager' => false],
            ],
            'alice is not a direct manager of bob because alices job is not active' => [
                [
                    ['user' => 'alice', 'position' => 'posmgr', 'department' => 'd2', 'other' => $inactivejob3],
                    ['user' => 'bob', 'position' => 'emp', 'department' => 'd1', 'other' => $activejob2],
                ],
                ['dptlead' => false, 'manager' => false],
            ],
            'alice is not a direct manager of bob because bobs job is not active' => [
                [
                    ['user' => 'alice', 'position' => 'posmgr', 'department' => 'd2', 'other' => $activejob2],
                    ['user' => 'bob', 'position' => 'emp', 'department' => 'd1', 'other' => $inactivejob1],
                ],
                ['dptlead' => false, 'manager' => false],
            ],
            'alice is a dept lead of bob because bobs deptlead job is not active' => [
                [
                    ['user' => 'alice', 'position' => 'depmgr', 'department' => 'd2', 'other' => $activejob1],
                    ['user' => 'bob', 'position' => 'emp', 'department' => 'd2', 'other' => $activejob2],
                    ['user' => 'bob', 'position' => 'depmgr', 'department' => 'd2', 'other' => $inactivejob2],
                ],
                ['dptlead' => true, 'manager' => false],
            ],
            'no jobs no reporting' => [
                [],
                ['dptlead' => false, 'manager' => false],
            ],
        ];
    }

    /**
     * Retrieves db recordset as array
     *
     * When we can not use $DB->get_records because of a non-unique first column
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    protected function get_recordset_as_array(string $sql, array $params = []): array {
        global $DB;
        $result = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $result[] = $row;
        }
        $rs->close();
        return $result;
    }

    /**
     * Testing queries for direct managers and subordinates based on users jobs
     *
     * @covers \tool_organisation\helper::get_all_direct_managed_users_sql
     * @covers \tool_organisation\helper::get_user_direct_dptleads_sql
     * @covers \tool_organisation\helper::get_user_direct_managers_sql
     * @covers \tool_organisation\helper::get_user_all_direct_managers_sql
     *
     * @param array $jobs
     * @param array $expected
     * @return void
     *
     * @dataProvider direct_job_managers_provider
     */
    public function test_direct_job_managers(array $jobs, array $expected): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant = $this->get_tenant_generator()->create_tenant();
        \tool_tenant\tenancy::set_switched_tenant_id($tenant->id);

        $generator = $this->get_generator();
        $posfrm = $generator->create_position(['tenantid' => $tenant->id, 'name' => 'Framework']);
        $pos1 = $generator->create_position(['globalmanager' => 1, 'parentid' => $posfrm->id]);
        $positions = [
            'depmgr' => $generator->create_position(['departmentmanager' => 1, 'parentid' => $posfrm->id]),
            'posmgr' => $pos1,
            'emp' => $generator->create_position(['parentid' => $pos1->id]),
            'other' => $generator->create_position(['parentid' => $posfrm->id]),
        ];
        $depfrm = $generator->create_department(['tenantid' => $tenant->id]);
        $dep1 = $generator->create_department(['parentid' => $depfrm->id]);
        $departments = [
            'd1' => $dep1,
            'd11' => $generator->create_department(['parentid' => $dep1->id]),
            'd2' => $generator->create_department(['parentid' => $depfrm->id]),
        ];
        $users = [
            'alice' => $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id]),
            'bob' => $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id]),
        ];

        // Create jobs as per provider data.
        foreach ($jobs as $job) {
            $generator->assign_job((object)([
                'userid' => $users[$job['user']]->id,
                'positionid' => $positions[$job['position']]->id,
                'departmentid' => $departments[$job['department']]->id,
                ] + $job['other']), false);
        }

        // For user 'bob' call get_user_direct_dptleads_sql() and check if 'alice' is returned as his direct dept lead.
        $sql = helper::get_user_direct_dptleads_sql('' . $users['bob']->id);
        $result = array_column($DB->get_records_sql($sql), 'userid');
        $this->assertEquals($expected['dptlead'] ? [$users['alice']->id] : [], $result);

        // For user 'bob' call get_user_direct_managers_sql() and check if 'alice' is returned as his direct manager.
        $sql = helper::get_user_direct_managers_sql('' . $users['bob']->id);
        $result = array_column($DB->get_records_sql($sql), 'userid');
        $this->assertEquals($expected['manager'] ? [$users['alice']->id] : [], $result);

        // For user 'bob' call get_user_all_direct_managers_sql() and check if 'alice' is a correct manager type.
        $sql = helper::get_user_all_direct_managers_sql('' . $users['bob']->id, 'type');
        $result = $this->get_recordset_as_array($sql);
        \core_collator::asort_objects_by_property($result, 'type');
        $expectedresult = array_merge($expected['dptlead'] ?
            [(object)['userid' => $users['alice']->id, 'type' => helper::DEPARTMENT_MANAGER]] : [],
            $expected['manager'] ? [(object)['userid' => $users['alice']->id, 'type' => helper::GLOBAL_MANAGER]] : []);
        $this->assertEqualsCanonicalizing($expectedresult, $result);

        // For user 'alice' call get_all_direct_managed_users_sql() and check if 'bob' is returned as her direct subordinate.
        $managerjobs = organisation::get_user_with_jobs((int)$users['alice']->id);
        [$allmanagedsql, $managedparams] = helper::get_all_direct_managed_users_sql($managerjobs);
        $result = $this->get_recordset_as_array($allmanagedsql, $managedparams);
        foreach ($result as $row) {
            unset($row->jobid);
        }

        $expectedresult = array_merge($expected['dptlead'] ?
            [(object)['userid' => $users['bob']->id, 'manager' => helper::DEPARTMENT_MANAGER]] : [],
            $expected['manager'] ? [(object)['userid' => $users['bob']->id, 'manager' => helper::GLOBAL_MANAGER]] : []);
        $this->assertEqualsCanonicalizing($expectedresult, $result);
    }

    /**
     * Test method returning direct manually assigned managers
     *
     * @covers \tool_organisation\helper::get_user_manually_assigned_managers_sql
     * @covers \tool_organisation\helper::get_user_all_direct_managers_sql
     * @covers \tool_organisation\helper::get_all_direct_managed_users_sql
     * @return void
     */
    public function test_direct_mam_managers(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant = $this->get_tenant_generator()->create_tenant();
        \tool_tenant\tenancy::set_switched_tenant_id($tenant->id);

        $users = [
            'alice' => $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id]),
            'bob' => $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id]),
        ];

        user_manager::add_assigned_manager((int)$users['bob']->id, $users['alice']);

        // For user 'bob' call get_user_manually_assigned_managers_sql() and check if 'alice' is returned as his MAM.
        $sql = helper::get_user_manually_assigned_managers_sql('' . $users['bob']->id);
        $result = array_column($DB->get_records_sql($sql), 'userid');
        $this->assertEquals([$users['alice']->id], $result);

        $sql = helper::get_user_all_direct_managers_sql('' . $users['bob']->id);
        $result = array_column($DB->get_records_sql($sql), 'userid');
        $this->assertEquals([$users['alice']->id], $result);

        // For user 'alice' call get_all_direct_managed_users_sql() and check if 'bob' is returned as her direct subordinate.
        $managerjobs = organisation::get_user_with_jobs((int)$users['alice']->id);
        [$allmanagedsql, $managedparams] = helper::get_all_direct_managed_users_sql($managerjobs);
        $result = array_values($DB->get_records_sql($allmanagedsql, $managedparams));
        foreach ($result as $row) {
            unset($row->jobid);
        }
        $expected = [(object)['userid' => $users['bob']->id, 'manager' => helper::MANUALLY_ASSIGNED_MANAGER]];
        $this->assertEqualsCanonicalizing($expected, $result);
    }
}
