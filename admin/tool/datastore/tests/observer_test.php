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
 * File containing tests for observer class
 *
 * @package     tool_datastore
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_datastore\task\migrate_course_completion;

/**
 * Test class
 *
 * @package     tool_datastore
 * @group       tool_datastore
 * @category    test
 * @covers      \tool_datastore\observer
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_datastore_observer_testcase extends advanced_testcase {

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test observer course_restored event
     *
     * @return void
     */
    public function test_course_restored() {
        global $CFG, $USER;

        require_once("{$CFG->dirroot}/backup/util/includes/backup_includes.php");
        require_once("{$CFG->dirroot}/backup/util/includes/restore_includes.php");

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $newcourse = $this->getDataGenerator()->create_course();

        // Sanity test.
        $tasks = \core\task\manager::get_adhoc_tasks(migrate_course_completion::class);
        $this->assertCount(0, $tasks);

        // Backup original course.
        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id);
        $bc->execute_plan();
        $bc->destroy();

        // Restore to new course.
        $rc = new restore_controller($bc->get_backupid(), $newcourse->id, backup::INTERACTIVE_NO,
            backup::MODE_GENERAL, $USER->id, backup::TARGET_EXISTING_DELETING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // We should have a new task.
        $tasks = \core\task\manager::get_adhoc_tasks(migrate_course_completion::class);
        $this->assertCount(1, $tasks);

        // Task custom data should refer to the restored course.
        $this->assertEquals((object) ['courseid' => $newcourse->id], end($tasks)->get_custom_data());
    }
}
