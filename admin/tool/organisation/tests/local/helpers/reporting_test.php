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

namespace tool_organisation\local\helpers;

use advanced_testcase;
use core\task\manager;
use core_reportbuilder\local\helpers\database;
use stdClass;
use tool_organisation\department_manager;
use tool_organisation\job_manager;
use tool_organisation\position_manager;
use tool_organisation_generator;
use tool_tenant\tenancy;
use tool_organisation\organisation;

/**
 * The reporting test class.
 *
 * @package    tool_organisation
 * @covers     \tool_organisation\local\helpers\reporting
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reporting_test extends advanced_testcase {

    /**
     * Generator
     *
     * @return tool_organisation_generator
     */
    protected function get_generator(): tool_organisation_generator {
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        return $generator;
    }

    /**
     * Generates the org structure:
     *
     * $pos1mgr is a globalmanager with permissions (allocate, view reports) position
     * $subpos1mgr is a globalmanager with permissions (view reports) position that is child of $pos1mgr
     * $subpos11 is a position that is a child of $subpos1mgr
     * $pos1deplead is a departmentmanager position that is child of $pos1mgr (for testing mixed reporting)
     * $subpos12 is a position that is a child of $pos1mgr (for testing when position has children but is not a manager)
     * $subpos121 is a position that is a child of $subpos12
     *
     * @return array
     */
    protected function generate_structure(): array {
        $generator = $this->get_generator();
        $deparmentfrm = $generator->create_department();
        $dep1 = $generator->create_department(['parentid' => $deparmentfrm->id]);
        $dep2 = $generator->create_department(['parentid' => $deparmentfrm->id]);
        $positionfrm = $generator->create_position();
        $pos1mgr = $generator->create_position(['parentid' => $positionfrm->id, 'globalmanager' => 1,
            'globalpermissions' => organisation::PERM_ALLOCATE_PROGRAMS | organisation::PERM_VIEW_REPORTS, ]);
        $subpos1mgr = $generator->create_position(['parentid' => $pos1mgr->id, 'globalmanager' => 1,
            'globalpermissions' => organisation::PERM_VIEW_REPORTS, ]);
        $subpos11 = $generator->create_position(['parentid' => $subpos1mgr->id]);
        $pos1deplead = $generator->create_position(['parentid' => $pos1mgr->id, 'departmentmanager' => 1,
            'departmentpermissions' => organisation::PERM_RECEIVE_NOTIFICATIONS, ]);
        $subpos12 = $generator->create_position(['parentid' => $pos1mgr->id]);
        $subpos121 = $generator->create_position(['parentid' => $subpos12->id]);

        return [
            $dep1,
            $dep2,
            $pos1mgr,
            $subpos1mgr,
            $subpos11,
            $pos1deplead,
            $subpos12,
            $subpos121,
        ];
    }

    /**
     * Generates a user (if does not exist yet) and assign them a job.
     *
     * @param string $username
     * @param int $positionid
     * @param int $departmentid
     * @param bool $rebuildtree immediately rebuild reporting tree
     * @return stdClass
     */
    protected function generate_user_with_job(string $username, int $positionid, int $departmentid,
            bool $rebuildtree = true): \stdClass {
        global $DB;
        $user = $DB->get_record('user', ['username' => $username], '*', IGNORE_MISSING);
        if (!$user) {
            $user = self::getDataGenerator()->create_user(['username' => $username]);
        }
        $user->jobdata = $this->get_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $positionid,
            'departmentid' => $departmentid,
        ], $rebuildtree);

        return $user;
    }

    /**
     * Helper method to assert the list of managed users.
     *
     * @param string $username
     * @param int $withpermission
     * @param array $expecteddirect
     * @param array $expectedindirect
     */
    protected function assert_managed_users(string $username, int $withpermission,
            array $expecteddirect, array $expectedindirect): void {
        global $DB;
        $managerid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $alias = database::generate_alias();
        [$where, $params] = reporting::get_managed_users_select($managerid, $alias, $withpermission, true);
        $direct = $DB->get_records_sql("SELECT username FROM {user} $alias WHERE $where ORDER BY username", $params);
        [$where, $params] = reporting::get_managed_users_select($managerid, $alias, $withpermission);
        $all = $DB->get_records_sql("SELECT username FROM {user} $alias WHERE $where ORDER BY username", $params);
        $this->assertEquals($expecteddirect, array_keys($direct),
            "Failed asserting direct managed users of '$username' with permissions $withpermission");
        $this->assertEquals($expectedindirect, array_keys($all),
            "Failed asserting all managed users of '$username' with permissions $withpermission". json_encode($all));
    }

    /**
     * Reporting tree test: simple position hierarchy
     */
    public function test_simple_positions(): void {
        $this->resetAfterTest();
        [$dep1, $dep2, $pos1mgr, $subpos1mgr, $subpos11, $pos1deplead, $subpos12, $subpos121] = $this->generate_structure();

        // Simple position-based hierarchy, u1 is manager over u2, u2 is manager over u3 and u4.
        $this->generate_user_with_job('u1', $pos1mgr->id, $dep1->id, false);
        $this->generate_user_with_job('u2', $subpos1mgr->id, $dep1->id, false);
        $this->generate_user_with_job('u3', $subpos11->id, $dep1->id, false);
        $this->generate_user_with_job('u4', $subpos11->id, $dep1->id, false);

        // Rebuild reporting table.
        $this->get_generator()->execute_adhoc_build_reporting();

        // User u1 is a direct manager over u2; u1 is manager over u2, u3 and u4.
        $this->assert_managed_users('u1', 0, ['u2'], ['u2', 'u3', 'u4']);

        // User u2 is a direct manager and overall manager over u3 and u4.
        $this->assert_managed_users('u2', 0, ['u3', 'u4'], ['u3', 'u4']);

        // Users u3 and u4 are not managers.
        $this->assert_managed_users('u3', 0, [], []);
        $this->assert_managed_users('u4', 0, [], []);

        // Let's add a new position for u4 who is managed by users (u1,u2),
        // so we can check that circular dependency is prevented.
        $this->generate_user_with_job('u4', $pos1mgr->id, $dep1->id);

        // User u4 is a direct manager over u2; u4 is manager over u2 and u3.
        $this->assert_managed_users('u4', 0, ['u2'], ['u2', 'u3']);

        // User u2 is a direct manager and overall manager over u3 and u4.
        $this->assert_managed_users('u2', 0, ['u3', 'u4'], ['u3', 'u4']);

        // Users u3 and u4 are not managers.
        $this->assert_managed_users('u3', 0, [], []);
    }

    /**
     * Reporting tree test: simple position hierarchy
     */
    public function test_simple_departments(): void {
        $this->resetAfterTest();
        [$dep1, $dep2, $pos1mgr, $subpos1mgr, $subpos11, $pos1deplead, $subpos12, $subpos121] = $this->generate_structure();

        // Users u1 and u2 are department managers, u1, u2, u3 and u4 are all in the same department.
        $this->generate_user_with_job('u1', $pos1deplead->id, $dep1->id, false);
        $this->generate_user_with_job('u2', $pos1deplead->id, $dep1->id, false);
        $this->generate_user_with_job('u3', $subpos12->id, $dep1->id, false);
        $this->generate_user_with_job('u4', $subpos12->id, $dep1->id, false);

        // Rebuild reporting table.
        $this->get_generator()->execute_adhoc_build_reporting();

        // User u1 is a manager over u3 and u4.
        $this->assert_managed_users('u1', 0, ['u3', 'u4'], ['u3', 'u4']);

        // User u2 is a manager over u3 and u4.
        $this->assert_managed_users('u2', 0, ['u3', 'u4'], ['u3', 'u4']);

        // Users u3 and u4 are not managers.
        $this->assert_managed_users('u3', 0, [], []);
        $this->assert_managed_users('u4', 0, [], []);

        // Let's add a new department for u4 who is managed by users (u1,u2),
        // so we can check that circular dependency is prevented.
        $this->generate_user_with_job('u4', $pos1deplead->id, $dep1->id);

        // User u1, u2 & u4 are now a direct/no-direct manager over u3.
        $this->assert_managed_users('u1', 0, ['u3'], ['u3']);
        $this->assert_managed_users('u2', 0, ['u3'], ['u3']);
        $this->assert_managed_users('u4', 0, ['u3'], ['u3']);

        // Users u3 is not a manager.
        $this->assert_managed_users('u3', 0, [], []);
    }

    /**
     * Test mixed reporting tree: position hierarchy and department hierarchy
     */
    public function test_mixed_reporting(): void {
        $this->resetAfterTest();
        [$dep1, $dep2, $pos1mgr, $subpos1mgr, $subpos11, $pos1deplead, $subpos12, $subpos121] = $this->generate_structure();

        // User u1 is a pos manager over u2, u2 is a dep manager over u3 and u4.
        $this->generate_user_with_job('u1', $subpos1mgr->id, $dep1->id, false);
        $this->generate_user_with_job('u2', $subpos11->id, $dep1->id, false);

        $this->generate_user_with_job('u2', $pos1deplead->id, $dep2->id, false);
        $this->generate_user_with_job('u3', $subpos12->id, $dep2->id, false);
        $this->generate_user_with_job('u4', $subpos12->id, $dep2->id, false);

        // Rebuild reporting table.
        $this->get_generator()->execute_adhoc_build_reporting();

        // User u1 is a manager over u3 and u4.
        $this->assert_managed_users('u1', 0, ['u2'], ['u2', 'u3', 'u4']);

        // User u2 is a manager over u3 and u4.
        $this->assert_managed_users('u2', 0, ['u3', 'u4'], ['u3', 'u4']);

        // Users u3 and u4 are not managers.
        $this->assert_managed_users('u3', 0, [], []);
        $this->assert_managed_users('u4', 0, [], []);

        // Let's add a new position/department for u4 who is managed by users (u1,u2),
        // so we can check that circular dependency is prevented.
        $this->generate_user_with_job('u4', $subpos1mgr->id, $dep1->id);
        $this->generate_user_with_job('u4', $pos1deplead->id, $dep2->id);

        // User u1 is now a direct manager over u2 and indirect over u3.
        $this->assert_managed_users('u1', 0, ['u2'], ['u2', 'u3']);

        // User u2 is a direct and indirect manager over u3.
        $this->assert_managed_users('u2', 0, ['u3'], ['u3']);

        // User u4 is a direct manager and overall manager over u2 and u3.
        $this->assert_managed_users('u4', 0, ['u2', 'u3'], ['u2', 'u3']);

        // Users u3 is not a manager.
        $this->assert_managed_users('u3', 0, [], []);
    }

    /**
     * Testing retrieving list of managed users with permissions
     */
    public function test_with_permissions(): void {
        $this->resetAfterTest();
        [$dep1, $dep2, $pos1mgr, $subpos1mgr, $subpos11, $pos1deplead, $subpos12, $subpos121] = $this->generate_structure();

        // Simple position-based hierarchy, u1 is manager over u2, u2 is manager over u3 and u4.
        $this->generate_user_with_job('u1', $pos1mgr->id, $dep1->id, false); // Allocate, reports.
        $this->generate_user_with_job('u2', $subpos1mgr->id, $dep1->id, false); // Reports.
        $this->generate_user_with_job('u3', $subpos11->id, $dep2->id, false);
        $this->generate_user_with_job('u4', $subpos11->id, $dep1->id, false);

        // User 2 is also a department lead over u3.
        $this->generate_user_with_job('u2', $pos1deplead->id, $dep2->id, false); // Notifications.

        // Rebuild reporting table.
        $this->get_generator()->execute_adhoc_build_reporting();

        // User u1 has permissions to allocate programs and view reports over all their subordinates.
        $this->assert_managed_users('u1', organisation::PERM_ALLOCATE_PROGRAMS, ['u2'], ['u2', 'u3', 'u4']);
        $this->assert_managed_users('u1', organisation::PERM_VIEW_REPORTS, ['u2'], ['u2', 'u3', 'u4']);
        $this->assert_managed_users('u1',
            organisation::PERM_ALLOCATE_PROGRAMS | organisation::PERM_VIEW_REPORTS | organisation::PERM_RECEIVE_NOTIFICATIONS,
            ['u2'], ['u2', 'u3', 'u4']);
        $this->assert_managed_users('u3', organisation::PERM_RECEIVE_NOTIFICATIONS, [], []);

        // User u2 has permissions to view reports over u3 and u4, and to receive notifications over u3.
        $this->assert_managed_users('u2', organisation::PERM_VIEW_REPORTS, ['u3', 'u4'], ['u3', 'u4']);
        $this->assert_managed_users('u2', organisation::PERM_RECEIVE_NOTIFICATIONS, ['u3'], ['u3']);
        $this->assert_managed_users('u2', organisation::PERM_VIEW_REPORTS | organisation::PERM_RECEIVE_NOTIFICATIONS,
            ['u3', 'u4'], ['u3', 'u4']);
        $this->assert_managed_users('u2', organisation::PERM_ALLOCATE_PROGRAMS, [], []);
    }

    /**
     * Testing reporting line ad-hoc task is trigger during job operations.
     */
    public function test_adhoc_job_operations(): void {
        $this->resetAfterTest();
        [$dep1, $dep2, $pos1mgr, $subpos1mgr, $subpos11, $pos1deplead, $subpos12, $subpos121] = $this->generate_structure();
        $jobmanager = new job_manager();

        // Simple position-based hierarchy, u1 is manager over u2, u2 is manager over u3 and u4.
        $job1 = $this->generate_user_with_job('u1', $pos1mgr->id, $dep1->id, false);
        $job2 = $this->generate_user_with_job('u2', $subpos1mgr->id, $dep1->id, false);

        // Rebuild reporting table.
        $this->get_generator()->execute_adhoc_build_reporting();

        // User u1 is a direct manager over u2.
        $this->assert_managed_users('u1', 0, ['u2'], ['u2']);

        // Delete job from user u4.
        $jobmanager->delete_job($job2->jobdata->id);

        // Check that ad-hoc tasks is created when job is deleted.
        $now = time();
        $task = manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\tool_organisation\\task\\build_reporting', $task);
        $task->execute();
        manager::adhoc_task_complete($task);

        // Users u1 is not a manager.
        $this->assert_managed_users('u1', 0, [], []);

        // Create again the job for u2.
        $this->generate_user_with_job('u2', $subpos1mgr->id, $dep1->id, false);

        // Check that ad-hoc tasks is created when job is created.
        $now = time();
        $task = manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\tool_organisation\\task\\build_reporting', $task);
        $task->execute();
        manager::adhoc_task_complete($task);

        // User u1 is a direct manager over u2.
        $this->assert_managed_users('u1', 0, ['u2'], ['u2']);

        // Update startdate job for user u1.
        $jobmanager->update_job($job1->jobdata->id, (object)['startdate' => 2539440651]);

        // Check that ad-hoc tasks is created when job is updated.
        $now = time();
        $task = manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\tool_organisation\\task\\build_reporting', $task);
        $task->execute();
        manager::adhoc_task_complete($task);

        // User u1 is not a manager since the new startdate of job is in future.
        $this->assert_managed_users('u1', 0, [], []);
    }

    /**
     * Testing reporting line ad-hoc task is trigger during position operations.
     */
    public function test_adhoc_position_operations(): void {
        $this->resetAfterTest();
        $posmanager = new position_manager();

        // Create a simple position framework and the position itself.
        $posf = $this->get_generator()->create_position();
        $pos1 = $this->get_generator()->create_position(['parentid' => $posf->id]);

        // Check that ad-hoc tasks is created when position is created.
        $now = time();
        $task = manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\tool_organisation\\task\\build_reporting', $task);
        $task->execute();
        manager::adhoc_task_complete($task);

        // Edit position name.
        $posmanager->update_position($posf->id, (object)['name' => 'New position name']);

        // Check that ad-hoc tasks is created when position is updated.
        $now = time();
        $task = manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\tool_organisation\\task\\build_reporting', $task);
        $task->execute();
        manager::adhoc_task_complete($task);

        // Delete position.
        $posmanager->delete_position($pos1->id);

        // Check that ad-hoc tasks is created when position is deleted.
        $now = time();
        $task = manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\tool_organisation\\task\\build_reporting', $task);
        $task->execute();
        manager::adhoc_task_complete($task);
    }

    /**
     * Testing reporting line ad-hoc task is trigger during department operations.
     */
    public function test_adhoc_department_operations(): void {
        $this->resetAfterTest();
        $depmanager = new department_manager();

        // Create a simple department framework and the department itself.
        $depf = $this->get_generator()->create_department();
        $dep1 = $this->get_generator()->create_department(['parentid' => $depf->id]);

        // Check that ad-hoc tasks is created when department is created.
        $now = time();
        $task = manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\tool_organisation\\task\\build_reporting', $task);
        $task->execute();
        manager::adhoc_task_complete($task);

        // Edit department name.
        $depmanager->update_department($depf->id, (object)['name' => 'New department name']);

        // Check that ad-hoc tasks is created when department is updated.
        $now = time();
        $task = manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\tool_organisation\\task\\build_reporting', $task);
        $task->execute();
        manager::adhoc_task_complete($task);

        // Delete department.
        $depmanager->delete_department($dep1->id);

        // Check that ad-hoc tasks is created when department is deleted.
        $now = time();
        $task = manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\tool_organisation\\task\\build_reporting', $task);
        $task->execute();
        manager::adhoc_task_complete($task);
    }

    /**
     * Testing building reporting tree when org structure has circular reporting lines.
     */
    public function test_reporting_line_edge_cases(): void {
        global $DB;
        $this->resetAfterTest();
        [$dep1, $dep2, $pos1mgr, $subpos1mgr, $subpos11, $pos1deplead, $subpos12, $subpos121] = $this->generate_structure();

        // Given the following org structure with some edge cases, we need to make sure that all the direct/no-direct
        // managers returned are the expected.
        // User u1 is a pos manager over u2.
        // User u2 is a sub-pos manager over u3 and u4.
        $user1 = $this->generate_user_with_job('u1', $pos1mgr->id, $dep1->id, false);
        $user2 = $this->generate_user_with_job('u2', $subpos1mgr->id, $dep1->id, false);
        $user3 = $this->generate_user_with_job('u3', $subpos11->id, $dep1->id, false);
        $user4 = $this->generate_user_with_job('u4', $subpos11->id, $dep1->id, false);

        // User u4 is a pos department lead over u2 and u3.
        $this->generate_user_with_job('u4', $pos1deplead->id, $dep2->id, false);
        $this->generate_user_with_job('u2', $subpos12->id, $dep2->id, false);
        $this->generate_user_with_job('u3', $subpos121->id, $dep2->id, false);

        // Rebuild reporting table.
        $this->get_generator()->execute_adhoc_build_reporting();

        // User u1 does not have any record(as a subordinate) in reporting table.
        $u1rl = $DB->get_records('tool_organisation_reporting', ['userid' => $user1->id]);
        $this->assertEmpty($u1rl);

        // User u2 have 3 records in reporting table.
        $u2rl = $DB->get_records('tool_organisation_reporting', ['userid' => $user2->id]);
        $this->assertCount(3, $u2rl);

        // Let's see how u2 is managed by u1
        // 1. $pos1mgr(u1)->$subpos1mgr(u2).
        // 2. $pos1mgr(u1)->$subpos11(u4)->$pos1deplead(u4)->$subpos12(u2).
        $u2replineu1 = array_filter($u2rl, function($rl) use ($user1){
            return $rl->managerid === $user1->id;
        });
        $this->assertCount(2, $u2replineu1);

        // User u3 have 9 records in reporting table.
        $u3rl = $DB->get_records('tool_organisation_reporting', ['userid' => $user3->id]);
        $this->assertCount(9, $u3rl);

        // Let's see how u3 is managed by u1
        // 1. $pos1mgr(u1)->$subpos11(u3).
        // 2. $pos1mgr(u1)->$subpos1mgr(u2)->$subpos11(u3).
        // 3. $pos1mgr(u1)->$subpos11/$pos1deplead(u4)->$subpos121(u3).
        // 4. $pos1mgr(u1)->$subpos1mgr(u2)->$subpos11/$pos1deplead(u4)->$subpos121(u3).
        // 5. $pos1mgr(u1)->$subpos11/$pos1deplead(u4)->$subpos1mgr/$subpos1mgr(u2)->$subpos11(u2).
        $u3replineu1 = array_filter($u3rl, function($rl) use ($user1) {
            return $rl->managerid === $user1->id;
        });
        $this->assertCount(5, $u3replineu1);

        // Let's see how u3 is managed by u2
        // 1. $subpos1mgr(u2)->$subpos11(u3).
        // 2. $subpos1mgr(u2)->$subpos11(u4)/$pos1deplead->$subpos121(u3).
        $u3replineu2 = array_filter($u3rl, function($rl) use ($user2) {
            return $rl->managerid === $user2->id;
        });
        $this->assertCount(2, $u3replineu2);

        // Let's see how u3 is managed by u4
        // 1. $pos1deplead(u4)->$subpos121(u3).
        // 2. $pos1deplead(u4)->$subpos12/$subpos1mgr(u2)/$pos1deplead->$subpos11(u3).
        $u3replineu4 = array_filter($u3rl, function($rl) use ($user4) {
            return $rl->managerid === $user4->id;
        });
        $this->assertCount(2, $u3replineu4);

        // User u4 have 4 records in reporting table.
        $u4rl = $DB->get_records('tool_organisation_reporting', ['userid' => $user4->id]);
        $this->assertCount(4, $u4rl);

        // Let's see how u4 is managed by u1
        // 1. $pos1mgr(u1)->$subpos11(u4).
        // 2. $pos1mgr(u1)->$pos1deplead(u4).
        // 2. $pos1mgr(u1)->$subpos1mgr(u2)->$subpos11(u4).
        $u4replineu1 = array_filter($u4rl, function($rl) use ($user1) {
            return $rl->managerid === $user1->id;
        });
        $this->assertCount(3, $u4replineu1);

        // Let's see how u4 is managed by u2
        // 1. $subpos1mgr(u2)->$subpos11(u4).
        $u4replineu2 = array_filter($u4rl, function($rl) use ($user2) {
            return $rl->managerid === $user2->id;
        });
        $this->assertCount(1, $u4replineu2);

        // Retrieve all direct and no-direct users managed by each one in this test.
        $this->assert_managed_users('u1', 0, ['u2', 'u4'], ['u2', 'u3', 'u4']);
        $this->assert_managed_users('u2', 0, ['u3', 'u4'], ['u3', 'u4']);
        $this->assert_managed_users('u3', 0, [], []);
        $this->assert_managed_users('u4', 0, ['u2', 'u3'], ['u2', 'u3']);
    }
}
