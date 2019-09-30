<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * File containing tests for helper class
 *
 * @package     tool_datastore
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_datastore\action;
use tool_datastore\api;
use tool_datastore\entity;
use tool_datastore\fields;
use tool_datastore\helper;
use tool_datastore\snapshot;
use tool_datastore\action\course_completed;

/**
 * Test class
 *
 * @package     tool_datastore
 * @group       tool_datastore
 * @category    test
 * @covers      \tool_datastore\helper
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_datastore_helper_testcase extends advanced_testcase {

    /** @var stdClass $course */
    protected $course;

    /** @var stdClass $teacher */
    protected $teacher;

    /** @var stdClass $student */
    protected $student;

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp() {
        $this->resetAfterTest();

        // Enable datastore.
        set_config('enabled', 1, 'tool_datastore');

        // Create our test course and users.
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->setUser($this->teacher);
    }

    /**
     * Test class add_course_completed_record method
     *
     * @return void
     */
    public function test_add_course_completed_record() {
        $timecomplete = time() - 20;
        helper::add_course_completion($this->student->id, $this->course->id, $timecomplete);

        // Ensure action is registered.
        $action = action::get_record([
            'action' => 'course_completed',
            'originalcourseid' => $this->course->id,
            'relateduserid' => $this->student->id,
        ]);

        $this->assertInstanceOf(action::class, $action);

        // Course entity.
        $entitycourse = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course',
            'originalid' => $action->get('originalcourseid'),
        ]);

        $this->assertInstanceOf(entity::class, $entitycourse);

        // Course snapshot.
        $course = api::get_course($entitycourse->get('originalid'));
        $coursejson = json_encode($course);

        $coursesnapshot = new snapshot($entitycourse->get('snapshotid'));
        $this->assertEquals($coursejson, $coursesnapshot->get('data'));
        $this->assertEquals(md5($coursejson), $coursesnapshot->get('hash'));

        // Course fields.
        $coursefields = explode(',', course_completed::get_fields_to_index()['course']);
        foreach ($coursefields as $coursefield) {
            $field = fields::get_record([
                'actionid' => $action->get('id'),
                'entityid' => $entitycourse->get('id'),
                'name' => $coursefield,
            ]);

            $this->assertInstanceOf(fields::class, $field);
            $this->assertEquals($course[$coursefield], $field->get('value'));
        }

        // Related user entity.
        $entityrelateduser = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'user',
            'originalid' => $action->get('relateduserid'),
        ]);

        $this->assertInstanceOf(entity::class, $entityrelateduser);

        // Related user snapshot.
        $relatedusersnapshot = new snapshot($entityrelateduser->get('snapshotid'));
        $relatedusersnapshotdata = $relatedusersnapshot->get('data');

        // When getting the related user, we need to reset the lastloaded preference to the snapshot value.
        $relateduser = api::get_user($entityrelateduser->get('originalid'));
        $relateduser['preference'] = json_decode($relatedusersnapshotdata)->preference;

        $relateduserjson = json_encode($relateduser);
        $this->assertEquals($relateduserjson, $relatedusersnapshotdata);
        $this->assertEquals(md5($relateduserjson), $relatedusersnapshot->get('hash'));

        // Related user fields.
        $relateduserfields = explode(',', course_completed::get_fields_to_index()['user']);
        foreach ($relateduserfields as $relateduserfield) {
            $field = fields::get_record([
                'actionid' => $action->get('id'),
                'entityid' => $entityrelateduser->get('id'),
                'name' => $relateduserfield,
            ]);

            $this->assertInstanceOf(fields::class, $field);
            $this->assertEquals($relateduser[$relateduserfield], $field->get('value'));
        }

        // Course completion entity.
        $entitycoursecompletion = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course_completion',
        ]);

        $this->assertInstanceOf(entity::class, $entitycoursecompletion);

        // Course completion snapshot.
        $coursecompletion = api::get_course_completion($entitycoursecompletion->get('originalid'));
        $coursecompletionjson = json_encode($coursecompletion);

        // Assert time complete matches our initial value.
        $this->assertEquals($timecomplete, $coursecompletion['timecompleted']);

        $coursecompletionsnapshot = new snapshot($entitycoursecompletion->get('snapshotid'));
        $this->assertEquals($coursecompletionjson, $coursecompletionsnapshot->get('data'));
        $this->assertEquals(md5($coursecompletionjson), $coursecompletionsnapshot->get('hash'));

        // Course completion fields.
        $coursecompletionfields = explode(',', course_completed::get_fields_to_index()['course_completion']);
        $this->assertEquals(['timeenrolled', 'timestarted', 'timecompleted', 'reaggregate'], $coursecompletionfields);

        $fieldparams = [
            'actionid' => $action->get('id'),
            'entityid' => $entitycoursecompletion->get('id'),
        ];

        foreach ($coursecompletionfields as $coursecompletionfield) {
            $field = fields::get_record($fieldparams + ['name' => $coursecompletionfield]);

            $this->assertInstanceOf(fields::class, $field);
            $this->assertEquals($coursecompletion[$coursecompletionfield], $field->get('value'));
        }
    }
}