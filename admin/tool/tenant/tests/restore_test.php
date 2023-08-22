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

namespace tool_tenant;

use advanced_testcase;
use backup;
use context_coursecat;
use tool_tenant_generator;

/**
 * Unit tests of tenancy related to course restore
 *
 * @package     tool_tenant
 * @covers      \tool_tenant\manager::allocate_user_on_creation()
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Roberto Bravo <roberto.bravo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class restore_test extends advanced_testcase {
    /** @var tool_tenant_generator */
    protected $generator;

    protected function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot.'/backup/util/includes/backup_includes.php');
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test that a course restoration allocates new users in the correct tenant.
     */
    public function test_restore_course_with_new_users(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Create tenant and admin tenant user.
        $category = $this->getDataGenerator()->create_category();
        $tenant = $this->generator->create_tenant(['categoryid' => $category->id]);
        $tenantadmin = $this->generator->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);

        self::setUser($tenantadmin->id);

        // Create a tenant user.
        $user = $this->generator->create_user(['firstname' => 'Ziven', 'lastname' => 'Takach', 'tenantid' => $tenant->id]);

        // Create a course and enrol user.
        $course = $generator->create_course();
        $generator->enrol_user($user->id, $course->id, 'student');

        // Backup course.
        $backupid = $this->backup_course((int)$course->id);

        // Delete the course and the user completely.
        delete_course($course, false);
        delete_user($user);
        $DB->delete_records('user', ['id' => $user->id]);

        // Restore backup course in a new course.
        $restoredcourse = $this->restore_course($backupid, (int)$category->id, (int)$tenantadmin->id);
        $this->assertEquals($category->id, $restoredcourse->category);

        // Get the restored user.
        $restoreduser = $DB->get_record('user', ['firstname' => 'Ziven', 'lastname' => 'Takach'], '*', MUST_EXIST);

        // Assert that the user is in the correct tenant (same as admin tenant).
        $this->assertEquals($tenant->id, tenancy::get_tenant_id((int)$restoreduser->id));

        // Assert that the user has "tenant user" role in the tenant category.
        $categorycontext = context_coursecat::instance($category->id);
        $restoreduserroles = get_user_roles($categorycontext, $restoreduser->id);
        $this->assertEquals('tool_tenant_user', reset($restoreduserroles)->shortname);
    }

    /**
     * Test that a course restoration doesn't allocate/enrol users whose are in another tenant.
     */
    public function test_restore_course_with_existing_users_in_another_tenant(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Create two tenants.
        $category1 = $this->getDataGenerator()->create_category();
        $tenant1 = $this->generator->create_tenant(['categoryid' => $category1->id]);
        $category2 = $this->getDataGenerator()->create_category();
        $tenant2 = $this->generator->create_tenant(['categoryid' => $category2->id]);

        // Create an admin tenant user for first tenant.
        $tenantadmin = $this->generator->create_user(['tenantid' => $tenant1->id, 'tenantadmin' => true]);

        self::setUser($tenantadmin->id);

        // Create two user in the same tenant.
        $user1 = $this->generator->create_user(['firstname' => 'Ziven', 'lastname' => 'Takach', 'tenantid' => $tenant1->id]);
        $user2 = $this->generator->create_user(['tenantid' => $tenant1->id]);

        // Create a course and enrol users in the same course.
        $course = $generator->create_course();
        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');

        // Backup course.
        $backupid = $this->backup_course((int)$course->id);

        // Delete the course.
        delete_course($course, false);

        // Delete the first user completely.
        delete_user($user1);
        $DB->delete_records('user', ['id' => $user1->id]);

        // Change the second user to another tenant.
        $this->generator->allocate_user((int)$user2->id, $tenant2->id);

        // Restore backup course in a new course.
        $restoredcourse = $this->restore_course($backupid, (int)$category1->id, (int)$tenantadmin->id);
        $restoredcoursecontext = \context_course::instance($restoredcourse->id);
        $this->assertEquals($category1->id, $restoredcourse->category);

        // Get the restored user.
        $restoreduser = $DB->get_record('user', ['firstname' => 'Ziven', 'lastname' => 'Takach'], '*', MUST_EXIST);

        // Assert that the restored user (previously deleted) belongs to the first tenant (same as admin tenant).
        $this->assertEquals($tenant1->id, tenancy::get_tenant_id((int)$restoreduser->id));

        // Assert that the restored user is enrolled in the restored course.
        $this->assertTrue(is_enrolled($restoredcoursecontext, $restoreduser->id));

        // Assert that the existing (not deleted) user is not enrolled in the restored course.
        $this->assertFalse(is_enrolled($restoredcoursecontext, $user2->id));
    }

    /**
     * Test that a course restoration allocates existing users with the correct roles.
     */
    public function test_restore_course_with_existing_users(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Create tenant and admin tenant user.
        $category = $this->getDataGenerator()->create_category();
        $tenant = $this->generator->create_tenant(['categoryid' => $category->id]);
        $tenantadmin = $this->generator->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);

        self::setUser($tenantadmin->id);

        // Create a tenant user.
        $user = $this->generator->create_user(['tenantid' => $tenant->id]);

        // Create a course and enrol user.
        $course = $generator->create_course();
        $generator->enrol_user($user->id, $course->id, 'student');

        // Backup course.
        $backupid = $this->backup_course((int)$course->id);

        // Delete the course.
        delete_course($course, false);

        // Restore backup course in a new course.
        $restoredcourse = $this->restore_course($backupid, (int)$category->id, (int)$tenantadmin->id);
        $restoredcoursecontext = \context_course::instance($restoredcourse->id);
        $this->assertEquals($category->id, $restoredcourse->category);

        // Assert that the existing user is enrolled in the restored course.
        $this->assertTrue(is_enrolled($restoredcoursecontext, $user->id));

        // Assert that the user has "tenant user" role in the tenant category.
        $categorycontext = context_coursecat::instance($category->id);
        $restoreduserroles = get_user_roles($categorycontext, $user->id);
        $this->assertEquals('tool_tenant_user', reset($restoreduserroles)->shortname);
    }

    /**
     * Backup a course and return its backup ID.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user doing the backup.
     * @return string The backup ID.
     */
    protected function backup_course(int $courseid, int $userid = 2): string {
        $backuptempdir = make_backup_temp_directory('');
        $packer = get_file_packer('application/vnd.moodle.backup');

        $bc = new \backup_controller(backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO,
            backup::MODE_GENERAL, $userid);
        $bc->execute_plan();

        $results = $bc->get_results();
        $results['backup_destination']->extract_to_pathname($packer, "$backuptempdir/core_course_testcase");

        $bc->destroy();
        return 'core_course_testcase';
    }

    /**
     * Restore a course.
     *
     * @param string $backupid The backup ID.
     * @param int $categoryid The category ID to restore in.
     * @param int $userid The ID of the user performing the restore.
     * @return \stdClass The updated course object.
     */
    protected function restore_course(string $backupid, int $categoryid, int $userid): \stdClass {
        global $DB;

        $newcourseid = \restore_dbops::create_new_course('Tmp', 'tmp', $categoryid);
        $rc = new \restore_controller($backupid, $newcourseid, backup::INTERACTIVE_NO,
            backup::MODE_GENERAL, $userid, backup::TARGET_NEW_COURSE);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();

        $course = $DB->get_record('course', array('id' => $rc->get_courseid()));

        $rc->destroy();
        return $course;
    }
}
