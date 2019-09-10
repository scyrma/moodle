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
 * Tests for the course completed event.
 *
 * @package   tool_datastore
 * @category  test
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_datastore\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests.
 *
 * @package   tool_datastore
 * @group     tool_datastore
 * @category  test
 * @covers    \tool_datastore\action\course_completed
 * @covers    \tool_datastore\action
 * @covers    \tool_datastore\entity
 * @covers    \tool_datastore\fields
 * @covers    \tool_datastore\snapshot
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_datastore_course_completed_testcase extends advanced_testcase {
    /** @var stdClass $course */
    protected $course;
    /** @var stdClass $user User that completed the course */
    protected $user;
    /** @var int $actionid */
    protected $actionid;
    /** @var stdClass User that triggered the course completion */
    protected $otheruser;

    /**
     * Set up the test.
     *
     * @throws coding_exception
     */
    protected function setUp() {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Enable completion before creating modules, otherwise the completion data is not written in DB.
        $CFG->enablecompletion = true;

        // Enable datastore.
        set_config('enabled', 1, 'tool_datastore');

        $this->course = $this->getDataGenerator()->create_course(
            array(
                'enablecompletion' => true,
                'fullname'         => 'Test course datastore',
                'tags'             => ['hello', 'world']
            )
        );

        // We log in with an alternative user to account for the fact that some times the user that triggers a course completion
        // is not the same than the user who has completed the course.
        $this->otheruser = $this->getDataGenerator()->create_user([
            'interests' => ['music', 'running', 'swimming']
        ]);
        $this->setUser($this->otheruser);

        $this->user = $this->getDataGenerator()->create_user([
            'interests' => ['astronomy', 'travel']
        ]);
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id);
        $ccompletion = new completion_completion(array('course' => $this->course->id, 'userid' => $this->user->id));
        $ccompletion->mark_complete();
    }

    /**
     * Ensure that when a course is completed the action is registered in the datastore action table.
     */
    public function test_get_data_in_action_table() {
        $action = \tool_datastore\action::get_record(
            array(
                'originalcourseid' => $this->course->id,
                'relateduserid' => $this->user->id
            )
        );

        $actionname = $action->get('action');
        $userid = $action->get('relateduserid');
        $courseid = $action->get('originalcourseid');

        $this->assertEquals('course_completed', $actionname);
        $this->assertEquals($this->user->id, $userid);
        $this->assertEquals($this->course->id, $courseid);
    }

    /**
     * Ensure that the entities related to the course completed event are registered.
     */
    public function test_get_data_in_entity_table() {
        $action = \tool_datastore\action::get_record(
            array(
                'originalcourseid' => $this->course->id,
                'relateduserid' => $this->user->id
            )
        );

        $entitycourse = \tool_datastore\entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'course',
                'originalid' => $action->get('originalcourseid')
            )
        );

        $this->assertInstanceOf( '\tool_datastore\entity', $entitycourse);

        $entityuser = \tool_datastore\entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'user',
                'originalid' => $action->get('relateduserid')
            )
        );

        $this->assertInstanceOf( '\tool_datastore\entity', $entityuser);

        $entityrelateduser = \tool_datastore\entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'user',
                'originalid' => $action->get('usermodified')
            )
        );

        $this->assertInstanceOf( '\tool_datastore\entity', $entityrelateduser);

        // Course completion entity.
        $entitycoursecompletion = \tool_datastore\entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course_completion',
        ]);

        $this->assertInstanceOf(\tool_datastore\entity::class, $entitycoursecompletion);
    }

    /**
     * Ensure that the snapshot data has been created with a correct hash.
     */
    public function test_get_data_in_snapshot_table() {
        $action = \tool_datastore\action::get_record(
            array(
                'originalcourseid' => $this->course->id,
                'relateduserid' => $this->user->id
            )
        );

        $entitycourse = \tool_datastore\entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'course',
                'originalid' => $action->get('originalcourseid')
            )
        );

        $snapshotid = $entitycourse->get('snapshotid');

        $snapshot = new \tool_datastore\snapshot($snapshotid, null);
        $data = $snapshot->get('data');
        $hash = $snapshot->get('hash');

        $coursedata = \tool_datastore\api::get_course($action->get('originalcourseid'));
        $this->assertEquals(json_encode($coursedata), $data);
        $this->assertEquals(md5(json_encode($coursedata)), $hash);

        // Check that snapshot contains course tags.
        $this->assertContains(json_encode(['hello', 'world']), $data);

        $entityuser = \tool_datastore\entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'user',
                'originalid' => $action->get('relateduserid')
            )
        );

        $snapshotid = $entityuser->get('snapshotid');

        $snapshot = new \tool_datastore\snapshot($snapshotid, null);
        $data = $snapshot->get('data');
        $hash = $snapshot->get('hash');
        $userdata = \tool_datastore\api::get_user($action->get('relateduserid'));
        // We copy _lastloaded before comparison to avoid timming failure.
        $userdata['preference'] = json_decode($data)->preference;
        $this->assertEquals(json_encode($userdata), $data);
        $this->assertEquals(md5(json_encode($userdata)), $hash);

        // Check that snapshot contains user interests.
        $this->assertContains(json_encode(['astronomy', 'travel']), $data);

        $entityuser = \tool_datastore\entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'user',
                'originalid' => $action->get('usermodified')
            )
        );

        $snapshotid = $entityuser->get('snapshotid');

        $snapshot = new \tool_datastore\snapshot($snapshotid, null);
        $data = $snapshot->get('data');
        $hash = $snapshot->get('hash');
        $userdata = \tool_datastore\api::get_user($action->get('usermodified'));
        // We copy _lastloaded before comparison to avoid timming failure.
        $userdata['preference'] = json_decode($data)->preference;
        $this->assertEquals(json_encode($userdata), $data);
        $this->assertEquals(md5(json_encode($userdata)), $hash);

        // Check that snapshot contains user interests.
        $this->assertContains(json_encode(['music', 'running', 'swimming']), $data);

        // Course completion snapshot.
        $entitycoursecompletion = \tool_datastore\entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course_completion',
        ]);

        $coursecompletion = \tool_datastore\api::get_course_completion($entitycoursecompletion->get('originalid'));
        $coursecompletionjson = json_encode($coursecompletion);

        $coursecompletionsnapshot = new \tool_datastore\snapshot($entitycoursecompletion->get('snapshotid'));
        $this->assertEquals($coursecompletionjson, $coursecompletionsnapshot->get('data'));
        $this->assertEquals(md5($coursecompletionjson), $coursecompletionsnapshot->get('hash'));
    }

    /**
     * Ensure that all fields defined for each entity has been stored.
     */
    public function test_get_data_in_fields_table() {
        global $DB;

        $action = \tool_datastore\action::get_record(
            array(
                'originalcourseid' => $this->course->id,
                'relateduserid' => $this->user->id
            )
        );

        $actionid = $action->get('id');
        $actionfields = \tool_datastore\action\course_completed::get_fields_to_index();

        $entitycourse = \tool_datastore\entity::get_record(
            array(
                'actionid' => $actionid,
                'type' => 'course',
                'originalid' => $this->course->id
            )
        );

        $course = get_course($this->course->id);
        $entityid = $entitycourse->get('id');

        $coursefields = explode(',', $actionfields['course']);
        foreach ($coursefields as $field) {
            $fieldindex = \tool_datastore\fields::get_record(
                array('actionid' => $actionid, 'entityid' => $entityid, 'name' => $field)
            );
            $this->assertInstanceOf('\tool_datastore\fields', $fieldindex);
            $this->assertEquals($fieldindex->get('value'), $course->{$field});
        }

        // User fields (the user and the related user is the same).
        $entityuser = \tool_datastore\entity::get_record(
            array(
                'actionid' => $actionid,
                'type' => 'user',
                'originalid' => $this->user->id
            )
        );

        $user = get_complete_user_data('id', $this->user->id);
        $entityid = $entityuser->get('id');

        $userfields = explode(',', $actionfields['user']);
        foreach ($userfields as $field) {
            $fieldindex = \tool_datastore\fields::get_record(
                array('actionid' => $actionid, 'entityid' => $entityid, 'name' => $field)
            );
            $this->assertInstanceOf('\tool_datastore\fields', $fieldindex);
            $this->assertEquals($fieldindex->get('value'), $user->{$field});
        }

        // Course completion fields.
        $entitycoursecompletion = \tool_datastore\entity::get_record([
            'actionid' => $actionid,
            'type' => 'course_completion',
        ]);

        $coursecompletion = \tool_datastore\api::get_course_completion($entitycoursecompletion->get('originalid'));

        $coursecompletionfields = explode(',', $actionfields['course_completion']);
        $this->assertEquals(['timeenrolled', 'timestarted', 'timecompleted', 'reaggregate'], $coursecompletionfields);

        $allfieldssql = [];
        $allparams = [];
        foreach ($coursecompletionfields as $coursecompletionfield) {
            $field = \tool_datastore\fields::get_record([
                'actionid' => $actionid,
                'entityid' => $entitycoursecompletion->get('id'),
                'name' => $coursecompletionfield,
            ]);

            $this->assertInstanceOf(\tool_datastore\fields::class, $field);
            $this->assertEquals($coursecompletion[$coursecompletionfield], $field->get('value'));

            list($fieldsql, $params) = api::get_datasource_field_sql('course_completion', $coursecompletionfield, 'dsa');
            $sql = "SELECT {$fieldsql} AS {$coursecompletionfield}
                FROM {tool_datastore_action} dsa WHERE dsa.id = {$action->get('id')}";
            $this->assertEquals($field->get('value'), $DB->get_field_sql($sql, $params));

            $allfieldssql[] = "{$fieldsql} AS $coursecompletionfield";
            $allparams += $params;
        }

        // Selecting multiple fields in one SQL query.
        $sql = "SELECT " . join(',', $allfieldssql) . " FROM {tool_datastore_action} dsa WHERE dsa.id = {$action->get('id')}";
        $record = $DB->get_record_sql($sql, $allparams);
        $this->assertEquals($coursecompletionfields, array_keys((array)$record));
    }
}
