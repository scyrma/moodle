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

namespace tool_organisation\local\helpers;

use advanced_testcase;
use moodle_exception;
use tool_organisation\local\persistent\user_manager as user_manager_model;
use tool_organisation\organisation;
use tool_organisation_generator;
use tool_tenant_generator;

/**
 * The user_manager test class.
 *
 * @package    tool_organisation
 * @covers     \tool_organisation\local\helpers\user_manager
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_manager_test extends advanced_testcase {

    /**
     * Tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Test for \tool_organisation\local\helpers\user_manager::add_assigned_manager
     * When a manager is added to employees.
     */
    public function test_add_assigned_manager_to_employee(): void {
        global $DB;
        $this->resetAfterTest();

        // Create some users in same tenant (employees and manager).
        [$tenant, [$manager, $employee1, $employee2, $employee3]] = $this->get_tenant_generator()->create_tenant_and_users(4);

        // Add manager to employee1.
        user_manager::add_assigned_manager((int)$employee1->id, $manager);
        $managed1 = user_manager_model::get_record(['userid' => (int)$employee1->id]);
        $this->assertEquals((int)$manager->id, $managed1->get('managerid'));
        $this->assertEquals(0, $managed1->get('permissions'));

        // Execute adhoc build reporting after update assignment.
        $this->get_generator()->execute_adhoc_build_reporting();

        // Let's retrieve the current reporting line records for the employee1 user.
        $employee1repline = $DB->get_records('tool_organisation_reporting', ['userid' => (int)$employee1->id, 'isdirect' => 1]);
        $this->assertCount(1, $employee1repline);
        $this->assertEquals((int)$manager->id, reset($employee1repline)->managerid);

        // Add employee1 as manager of employee2.
        $employee1->permissions = organisation::PERM_VIEW_REPORTS + organisation::PERM_ALLOCATE_PROGRAMS;
        user_manager::add_assigned_manager((int)$employee2->id, $employee1);
        $managed2 = user_manager_model::get_record(['userid' => (int)$employee2->id]);
        $this->assertEquals((int)$employee1->id, $managed2->get('managerid'));
        $this->assertEquals(3, $managed2->get('permissions'));

        // Execute adhoc build reporting.
        $this->get_generator()->execute_adhoc_build_reporting();

        // Let's retrieve the current reporting line records for the employee2 user.
        $employee2repline = $DB->get_records('tool_organisation_reporting', ['userid' => (int)$employee2->id]);
        $this->assertCount(2, $employee2repline);
        $direct = array_filter($employee2repline, fn($rline) => $rline->isdirect === '1');
        $nodirect = array_filter($employee2repline, fn($rline) => $rline->isdirect === '0');
        $this->assertEquals((int)$employee1->id, reset($direct)->managerid);
        $this->assertEquals((int)$manager->id, reset($nodirect)->managerid);

        // Add employee2 as manager of employee3.
        $employee2->permissions = organisation::PERM_RECEIVE_NOTIFICATIONS;
        user_manager::add_assigned_manager((int)$employee3->id, $employee2);
        $managed3 = user_manager_model::get_record(['userid' => (int)$employee3->id]);
        $this->assertEquals((int)$employee2->id, $managed3->get('managerid'));
        $this->assertEquals(4, $managed3->get('permissions'));

        // Execute adhoc build reporting.
        $this->get_generator()->execute_adhoc_build_reporting();

        // Let's retrieve the current reporting line records for the employee3 user.
        $employee3repline = $DB->get_records('tool_organisation_reporting', ['userid' => (int)$employee3->id]);
        $this->assertCount(3, $employee3repline);
        $direct = array_filter($employee3repline, fn($rline) => $rline->isdirect === '1');
        $nodirect = array_filter($employee3repline, fn($rline) => $rline->isdirect === '0');

        $this->assertEquals([$employee2->id], array_column($direct, 'managerid'));
        $this->assertEqualsCanonicalizing([$manager->id, $employee1->id], array_column($nodirect, 'managerid'));

        // Add employee1 as manager of manager, should show an exception since this is not allowed relation.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('usermanagednotallowed', 'tool_organisation'));
        user_manager::add_assigned_manager((int)$manager->id, $employee1);
    }

    /**
     * Test for \tool_organisation\local\helpers\user_manager::add_assigned_manager
     * When some reporters are added to current user (manager/employee).
     */
    public function test_add_assigned_employees_to_manager(): void {
        global $DB;
        $this->resetAfterTest();

        // Create some users in same tenant (employees and manager).
        [$tenant, [$manager, $employee1, $employee2, $employee3]] = $this->get_tenant_generator()->create_tenant_and_users(4);

        // Add employee1 as reporter to manager.
        user_manager::add_assigned_manager((int)$employee1->id, $manager);
        $managed1 = user_manager_model::get_record(['userid' => (int)$employee1->id]);
        $this->assertEquals((int)$manager->id, $managed1->get('managerid'));
        $this->assertEquals(0, $managed1->get('permissions'));

        // Execute adhoc build reporting after update assignment.
        $this->get_generator()->execute_adhoc_build_reporting();

        // Let's retrieve the current reporting line records for the employee1 user.
        $employee1repline = $DB->get_records('tool_organisation_reporting', ['userid' => (int)$employee1->id, 'isdirect' => 1]);
        $this->assertCount(1, $employee1repline);
        $this->assertEquals((int)$manager->id, reset($employee1repline)->managerid);

        // Add employee2 as reporter to manager.
        $manager->permissions = organisation::PERM_VIEW_REPORTS + organisation::PERM_ALLOCATE_PROGRAMS;
        user_manager::add_assigned_manager((int)$employee2->id, $manager);
        $managed2 = user_manager_model::get_record(['userid' => (int)$employee2->id]);
        $this->assertEquals((int)$manager->id, $managed2->get('managerid'));
        $this->assertEquals(3, $managed2->get('permissions'));

        // Execute adhoc build reporting.
        $this->get_generator()->execute_adhoc_build_reporting();

        // Let's retrieve the current reporting line records for the employee2 user.
        $employee2repline = $DB->get_records('tool_organisation_reporting', ['userid' => (int)$employee2->id, 'isdirect' => 1]);
        $this->assertCount(1, $employee2repline);
        $this->assertEquals((int)$manager->id, reset($employee2repline)->managerid);

        // Add employee3 as reporter to manager.
        $manager->permissions = organisation::PERM_RECEIVE_NOTIFICATIONS;
        user_manager::add_assigned_manager((int)$employee3->id, $manager);
        $managed3 = user_manager_model::get_record(['userid' => (int)$employee3->id]);
        $this->assertEquals((int)$manager->id, $managed3->get('managerid'));
        $this->assertEquals(4, $managed3->get('permissions'));

        // Execute adhoc build reporting.
        $this->get_generator()->execute_adhoc_build_reporting();

        // Let's retrieve the current reporting line records for the employee3 user.
        $employee3repline = $DB->get_records('tool_organisation_reporting', ['userid' => (int)$employee3->id, 'isdirect' => 1]);
        $this->assertCount(1, $employee3repline);
        $this->assertEquals((int)$manager->id, reset($employee3repline)->managerid);
    }

    /**
     * Test for \tool_organisation\local\helpers\user_manager::delete_assigned_manager
     */
    public function test_delete_assigned_manager(): void {
        global $DB;
        $this->resetAfterTest();

        // Create some users in same tenant (employees and manager).
        [$tenant, $users] = $this->get_tenant_generator()->create_tenant_and_users(4);

        array_walk($users, static function(object $user) use ($users) {
            if ($user->username !== $users[0]->username) {
                user_manager::add_assigned_manager((int)$user->id, $users[0]);
            }
        });

        // Execute adhoc build reporting.
        $this->get_generator()->execute_adhoc_build_reporting();

        // Let's retrieve the current reporting line records for the given user.
        $employee = $DB->get_records('tool_organisation_reporting', ['userid' => (int)$users[1]->id, 'isdirect' => 1]);
        $this->assertCount(1, $employee);
        $this->assertEquals((int)$users[0]->id, reset($employee)->managerid);

        // After delete manager from user1 the record will be deleted.
        user_manager::delete_assigned_manager((int)$users[1]->id, (int)$users[0]->id);
        $managed1 = user_manager_model::get_record(['userid' => (int)$users[1]->id]);
        $this->assertFalse($managed1);

        // Execute adhoc build reporting after update assignment.
        $this->get_generator()->execute_adhoc_build_reporting();

        // Let's retrieve the current reporting line records for the given user.
        $employee = $DB->get_records('tool_organisation_reporting', ['userid' => (int)$users[1]->id, 'isdirect' => 1]);
        $this->assertCount(0, $employee);
    }

    /**
     * Test for \tool_organisation\local\helpers\user_manager::update_assigned_manager
     */
    public function test_update_assigned_manager(): void {
        global $DB;
        $this->resetAfterTest();

        // Create some users in same tenant (employees and manager).
        [$tenant, $users] = $this->get_tenant_generator()->create_tenant_and_users(4);
        array_walk($users, static function(object $user) use ($users) {
            if ($user->username !== $users[0]->username) {
                user_manager::add_assigned_manager((int)$user->id, $users[0]);
            }
        });

        // Execute adhoc build reporting.
        $this->get_generator()->execute_adhoc_build_reporting();

        // Assert current record after add user manager.
        $managed1 = user_manager_model::get_record(['userid' => (int)$users[1]->id]);
        $this->assertEquals((int)$users[0]->id, $managed1->get('managerid'));

        // Let's retrieve the current reporting line records for the given user.
        $employee = $DB->get_records('tool_organisation_reporting', ['userid' => (int)$users[1]->id, 'isdirect' => 1]);
        $this->assertCount(1, $employee);
        $this->assertEquals((int)$users[0]->id, reset($employee)->managerid);

        // After update manager to user2 the record should now change the managerid.
        $users[2]->permissions = organisation::PERM_VIEW_REPORTS + organisation::PERM_ALLOCATE_PROGRAMS +
            organisation::PERM_RECEIVE_NOTIFICATIONS;
        user_manager::update_assigned_manager((int)$users[1]->id, $users[2], (int)$users[0]->id);
        $managed1 = user_manager_model::get_record(['userid' => (int)$users[1]->id]);
        $this->assertEquals((int)$users[2]->id, $managed1->get('managerid'));

        // Execute adhoc build reporting after update assignment.
        $this->get_generator()->execute_adhoc_build_reporting();

        $employee = $DB->get_records('tool_organisation_reporting', ['userid' => (int)$users[1]->id, 'isdirect' => 1]);
        $this->assertCount(1, $employee);
        $this->assertEquals((int)$users[2]->id, reset($employee)->managerid);
    }

    /**
     * Test for \tool_organisation\event\user_manager_created event
     * Check that event is triggered when manually assigned manager is added.
     */
    public function test_user_manager_created_event(): void {
        $this->resetAfterTest();

        // Create some users in same tenant (employees and manager).
        [$tenant, [$manager, $employee1, $employee2]] = $this->get_tenant_generator()->create_tenant_and_users(3);

        // Add manager as manager to employee1.
        $sink = $this->redirectEvents();
        user_manager::add_assigned_manager((int)$employee1->id, $manager);
        $events = $sink->get_events();
        $sink->close();

        // Check that the event data is valid.
        $event = reset($events);
        $this->assertInstanceOf('\tool_organisation\event\user_manager_created', $event);
        $expecteddescription = "The user ".$manager->id." was assigned as a manager over user ".$employee1->id;
        $this->assertEquals($expecteddescription, $event->get_description());
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test for \tool_organisation\event\user_manager_updated event
     * Check that event is triggered when manually assigned manager is updated.
     */
    public function test_user_manager_updated_event(): void {
        $this->resetAfterTest();

        // Create some users in same tenant (employees and manager).
        [$tenant, [$manager, $employee1, $employee2]] = $this->get_tenant_generator()->create_tenant_and_users(3);

        // Add manager as manager to employee1.
        user_manager::add_assigned_manager((int)$employee1->id, $manager);

        $sink = $this->redirectEvents();
        // Update employee2 as a manager over employee1.
        user_manager::update_assigned_manager((int)$employee1->id, $employee2, (int)$manager->id);
        $events = $sink->get_events();
        $sink->close();

        // Check that the event data is valid.
        $event = reset($events);
        $this->assertInstanceOf('\tool_organisation\event\user_manager_updated', $event);
        $expecteddescription = "The manually assigned manager was changed from ".(int)$manager->id." to ".$employee2->id."
            over user ".$employee1->id;
        $this->assertEquals($expecteddescription, $event->get_description());
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test for \tool_organisation\event\user_manager_deleted event
     * Check that event is triggered when manually assigned manager is deleted.
     */
    public function test_user_manager_deleted_event(): void {
        $this->resetAfterTest();

        // Create some users in same tenant (employees and manager).
        [$tenant, [$manager, $employee1]] = $this->get_tenant_generator()->create_tenant_and_users(2);

        // Delete manager from employee1.
        user_manager::add_assigned_manager((int)$employee1->id, $manager);
        $sink = $this->redirectEvents();
        user_manager::delete_assigned_manager((int)$employee1->id, (int)$manager->id);
        $events = $sink->get_events();
        $sink->close();

        // Check that the event data is valid.
        $event = reset($events);
        $this->assertInstanceOf('\tool_organisation\event\user_manager_deleted', $event);
        $expecteddescription = "The manually assigned manager was deleted from user with id ". $employee1->id;
        $this->assertEquals($expecteddescription, $event->get_description());
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test for \tool_organisation\organisation::get_user_assigned_managers
     */
    public function test_get_user_assigned_managers(): void {
        $this->resetAfterTest();

        $employeeindefaulttenant = $this->get_tenant_generator()->create_user();
        $managerindefaulttenant = $this->get_tenant_generator()->create_user();
        user_manager::add_assigned_manager((int)$employeeindefaulttenant->id, $managerindefaulttenant);

        // Create some users in same tenant (employees and manager).
        [$tenant, $users] = $this->get_tenant_generator()->create_tenant_and_users(4);
        array_walk($users, static function(object $user) use ($users) {
            if ($user->username !== $users[0]->username) {
                user_manager::add_assigned_manager((int)$user->id, $users[0]);
            }
        });

        // Assert manager over employee in defaulttenant.
        $usermanagers = organisation::get_user_assigned_managers((int)$employeeindefaulttenant->id);
        $this->assertCount(1, $usermanagers);
        $this->assertEquals($managerindefaulttenant->username, reset($usermanagers)->username);

        // Assert manager over employee1.
        $usermanagers = organisation::get_user_assigned_managers((int)$users[1]->id);
        $this->assertEquals($users[0]->username, reset($usermanagers)->username);

        // Assert manager over employee2.
        $usermanagers = organisation::get_user_assigned_managers((int)$users[2]->id);
        $this->assertEquals($users[0]->username, reset($usermanagers)->username);

        // Assert manager over employee3.
        $usermanagers = organisation::get_user_assigned_managers((int)$users[3]->id);
        $this->assertEquals($users[0]->username, reset($usermanagers)->username);
    }
}
