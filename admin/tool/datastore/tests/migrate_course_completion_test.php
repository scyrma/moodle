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
 * File containing tests for migration of course completion data
 *
 * @package     tool_datastore
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_datastore;

use advanced_testcase;
use completion_completion;
use stdClass;
use tool_datastore\local\models\action;
use tool_datastore\local\models\entity;
use tool_datastore\local\models\field;
use tool_datastore\task\migrate_course_completion;

/**
 * Test class
 *
 * @package     tool_datastore
 * @group       tool_datastore
 * @category    test
 * @covers      \tool_datastore\task\migrate_course_completion
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class migrate_course_completion_test extends advanced_testcase {

    /** @var stdClass $course */
    protected $course;

    /** @var stdClass $user */
    protected $user;

    /*
     * Test setup
     *
     * @return void
     */
    public function setUp(): void {
        global $CFG;

        $this->resetAfterTest();

        $CFG->enablecompletion = true;

        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        $this->user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Confirm that course completion data that isn't marked as completed is skipped
     *
     * @return void
     */
    public function test_migrate_course_completion_skip_non_completed() {
        $this->create_completion_instance($this->course->id, $this->user->id)->insert();

        // Sanity check.
        $this->assertEquals(0, action::count_records());

        // Execute the migration task.
        (new migrate_course_completion())->execute();

        $this->assertEquals(0, action::count_records());
    }

    /**
     * Confirm that course completion data that is marked as completed is migrated
     *
     * @return void
     */
    public function test_migrate_course_completion_migrate_completed() {
        global $DB;

        // We need to manually set the completion timecompleted field to simulate old/existing data (pre-datastore).
        $completionid = $this->create_completion_instance($this->course->id, $this->user->id)->insert();
        $DB->set_field('course_completions', 'timecompleted', 54321, ['id' => $completionid]);

        // Sanity check.
        $this->assertEquals(0, action::count_records());

        // Execute the migration task.
        (new migrate_course_completion())->execute();

        // Confirm we now have a single stored action.
        $actions = action::get_records([
            'action' => 'course_completed',
            'originalcourseid' => $this->course->id,
            'relateduserid' => $this->user->id,
        ]);

        $this->assertCount(1, $actions);
        $action = reset($actions);

        // Check we migrated the correct course.
        $entitycourse = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course',
            'originalid' => $action->get('originalcourseid'),
        ]);

        $this->assertEquals($this->course->shortname, field::get_record([
            'entityid' => $entitycourse->get('id'),
            'name' => 'shortname',
        ])->get('value'));

        // We should have migrated the correct user.
        $entityuser = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'user',
            'originalid' => $action->get('relateduserid'),
        ]);

        $this->assertEquals($this->user->username, field::get_record([
            'entityid' => $entityuser->get('id'),
            'name' => 'username',
        ])->get('value'));

        // Check stored completion date.
        $entitycompletion = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course_completion',
            'originalid' => $completionid,
        ]);

        $this->assertEquals(54321, field::get_record([
            'entityid' => $entitycompletion->get('id'),
            'name' => 'timecompleted',
        ])->get('value'));

        // Make sure the correct number of fields have been stored in the fields table.
        $this->assertCount(count(\tool_datastore\local\action\course_completed::get_fields_to_index()['course']),
            field::get_records(['entityid' => $entitycourse->get('id')]));
        $this->assertCount(count(\tool_datastore\local\action\course_completed::get_fields_to_index()['user']),
            field::get_records(['entityid' => $entityuser->get('id')]));
        $this->assertCount(count(\tool_datastore\local\action\course_completed::get_fields_to_index()['course_completion']),
            field::get_records(['entityid' => $entitycompletion->get('id')]));
    }

    /**
     * Confirm that course completion data that already exists in the datastore isn't re-created
     *
     * @return void
     */
    public function test_migrate_course_completion_skip_existing() {
        $this->create_completion_instance($this->course->id, $this->user->id)->mark_complete();

        // Sanity check.
        $actioncount = action::count_records([
            'action' => 'course_completed',
            'originalcourseid' => $this->course->id,
            'relateduserid' => $this->user->id,
        ]);

        $this->assertEquals(1, $actioncount);

        // Execute the migration task.
        (new migrate_course_completion())->execute();

        // Confirm we still have just a single stored action.
        $actioncount = action::count_records([
            'action' => 'course_completed',
            'originalcourseid' => $this->course->id,
            'relateduserid' => $this->user->id,
        ]);

        $this->assertEquals(1, $actioncount);
    }

    /**
     * Test migration of a single course by passing it to the migration task
     *
     * @return void
     */
    public function test_migrate_single_course() {
        global $DB;

        // We need to manually set the completion timecompleted field to simulate old/existing data (pre-datastore).
        $completionid1 = $this->create_completion_instance($this->course->id, $this->user->id)->insert();
        $DB->set_field('course_completions', 'timecompleted', 54321, ['id' => $completionid1]);

        // Create second course, with manually set completion timecompleted field.
        $course2 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->user->id, $course2->id);

        $completionid2 = $this->create_completion_instance($course2->id, $this->user->id)->insert();
        $DB->set_field('course_completions', 'timecompleted', 65432, ['id' => $completionid2]);

        // Sanity check.
        $this->assertEquals(0, action::count_records());

        $task = new migrate_course_completion();
        $task->set_custom_data(['courseid' => $this->course->id]);
        $task->execute();

        // Confirm we now have a single stored action.
        $actions = action::get_records([
            'action' => 'course_completed',
            'relateduserid' => $this->user->id,
        ]);

        $this->assertCount(1, $actions);
        $action = reset($actions);

        // Check we migrated the correct course.
        $entitycourse = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course',
            'originalid' => $action->get('originalcourseid'),
        ]);

        $this->assertEquals($this->course->shortname, field::get_record([
            'entityid' => $entitycourse->get('id'),
            'name' => 'shortname',
        ])->get('value'));
    }

    /**
     * Helper method for creating a completion instance
     *
     * @param int $courseid
     * @param int $userid
     * @return completion_completion
     */
    private function create_completion_instance(int $courseid, int $userid): completion_completion {
        $timenow = time();

        return new completion_completion([
            'course' => $courseid,
            'userid' => $userid,
            'timeenrolled' => $timenow,
            'timestarted' => $timenow,
            'reaggregate' => $timenow,
        ]);
    }

    /**
     * Test queuing completion migration
     */
    public function test_queue() {
        global $DB;

        // Create two completion records in two courses and an extra course with completion record that is not completed.
        $completionid1 = $this->create_completion_instance($this->course->id, $this->user->id)->insert();
        $DB->set_field('course_completions', 'timecompleted', 54321, ['id' => $completionid1]);
        $course2 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->user->id, $course2->id);
        $completionid2 = $this->create_completion_instance($course2->id, $this->user->id)->insert();
        $DB->set_field('course_completions', 'timecompleted', 65432, ['id' => $completionid2]);

        $course3 = $this->getDataGenerator()->create_course();
        $this->create_completion_instance($course3->id, $this->user->id)->insert();

        // Queue migration.
        migrate_course_completion::queue();

        // Make sure two tasks were queued.
        $this->assertEquals(2,
            $DB->count_records('task_adhoc', ['classname' => '\\'.migrate_course_completion::class]));
    }
}
