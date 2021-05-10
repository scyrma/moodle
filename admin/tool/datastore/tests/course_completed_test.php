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
 * Tests for the course completed event.
 *
 * @package   tool_datastore
 * @category  test
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_datastore\api;
use tool_datastore\local\models\action;
use tool_datastore\local\models\entity;
use tool_datastore\local\models\field;
use tool_datastore\local\models\snapshot;
use tool_reportbuilder\constants;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests.
 *
 * @package   tool_datastore
 * @group     tool_datastore
 * @category  test
 * @covers    \tool_datastore\local\action\course_completed
 * @covers    \tool_datastore\local\models\action
 * @covers    \tool_datastore\local\models\entity
 * @covers    \tool_datastore\local\models\field
 * @covers    \tool_datastore\local\models\snapshot
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_datastore_course_completed_testcase extends advanced_testcase {
    /** @var stdClass $course */
    protected $course;
    /** @var stdClass $user User that completed the course */
    protected $user;
    /** @var int $actionid */
    protected $actionid;

    /**
     * Set up the test.
     *
     * @throws coding_exception
     */
    protected function setUp(): void {
        global $CFG;

        $this->resetAfterTest();

        // Enable completion before creating modules, otherwise the completion data is not written in DB.
        $CFG->enablecompletion = true;

        $this->course = $this->getDataGenerator()->create_course(
            array(
                'enablecompletion' => true,
                'fullname'         => 'Test course datastore',
                'tags'             => ['hello', 'world']
            )
        );

        // Admin user will belong to a different tenant from the user we are about to create.
        $this->setAdminUser();

        $this->user = $this->getDataGenerator()->create_and_enrol($this->course, 'student', [
            'interests' => ['astronomy', 'travel']
        ]);

        // Add out test user to a new tenant.
        $usertenant = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($this->user->id, $usertenant->id);

        $ccompletion = new completion_completion(array('course' => $this->course->id, 'userid' => $this->user->id));
        $ccompletion->mark_complete();

        // Reset current user.
        $this->setUser(null);
    }

    /**
     * Ensure that when a course is completed the action is registered in the datastore action table.
     */
    public function test_get_data_in_action_table() {
        $action = action::get_record(
            array(
                'action' => 'course_completed',
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

        // The stored tenantid should be that of the related user.
        $this->assertEquals(tenancy::get_tenant_id($this->user->id), $action->get('tenantid'));
    }

    /**
     * Ensure that the entities related to the course completed event are registered.
     */
    public function test_get_data_in_entity_table() {
        $action = action::get_record(
            array(
                'action' => 'course_completed',
                'originalcourseid' => $this->course->id,
                'relateduserid' => $this->user->id
            )
        );

        $entitycourse = entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'course',
                'originalid' => $action->get('originalcourseid')
            )
        );
        $this->assertInstanceOf(entity::class, $entitycourse);

        $entityuser = entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'user',
                'originalid' => $action->get('relateduserid')
            )
        );
        $this->assertInstanceOf(entity::class, $entityuser);

        $entityrelateduser = entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'user',
                'originalid' => $action->get('usermodified')
            )
        );
        $this->assertInstanceOf(entity::class, $entityrelateduser);

        // Course completion entity.
        $entitycoursecompletion = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course_completion',
        ]);

        $this->assertInstanceOf(entity::class, $entitycoursecompletion);
    }

    /**
     * Ensure that the snapshot data has been created with a correct hash.
     */
    public function test_get_data_in_snapshot_table() {
        $action = action::get_record(
            array(
                'action' => 'course_completed',
                'originalcourseid' => $this->course->id,
                'relateduserid' => $this->user->id
            )
        );

        $entitycourse = entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'course',
                'originalid' => $action->get('originalcourseid')
            )
        );

        $snapshot = snapshot::from_entity($entitycourse);
        $data = $snapshot->get('data');
        $hash = $snapshot->get('hash');

        $coursedata = \tool_datastore\api::get_course($action->get('originalcourseid'));
        $this->assertEquals(json_encode($coursedata), $data);
        $this->assertEquals(md5(json_encode($coursedata)), $hash);

        // Check that snapshot contains course tags.
        $this->assertStringContainsString(json_encode(['hello', 'world']), $data);

        $entityuser = entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'user',
                'originalid' => $action->get('relateduserid')
            )
        );

        $snapshot = snapshot::from_entity($entityuser);
        $data = $snapshot->get('data');
        $hash = $snapshot->get('hash');
        $userdata = \tool_datastore\api::get_user($action->get('relateduserid'));
        // We copy _lastloaded before comparison to avoid timming failure.
        $userdata['preference'] = json_decode($data)->preference;
        $this->assertEquals(json_encode($userdata), $data);
        $this->assertEquals(md5(json_encode($userdata)), $hash);

        // Check that snapshot contains user interests.
        $this->assertStringContainsString(json_encode(['astronomy', 'travel']), $data);

        $entityuser = entity::get_record(
            array(
                'actionid' => $action->get('id'),
                'type' => 'user',
                'originalid' => $action->get('usermodified')
            )
        );

        $snapshot = snapshot::from_entity($entityuser);
        $data = $snapshot->get('data');
        $hash = $snapshot->get('hash');
        $userdata = \tool_datastore\api::get_user($action->get('usermodified'));
        // We copy _lastloaded before comparison to avoid timming failure.
        $userdata['preference'] = json_decode($data)->preference;
        $this->assertEquals(json_encode($userdata), $data);
        $this->assertEquals(md5(json_encode($userdata)), $hash);

        // Course completion snapshot.
        $entitycoursecompletion = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course_completion',
        ]);

        $coursecompletion = \tool_datastore\api::get_course_completion($entitycoursecompletion->get('originalid'));
        $coursecompletionjson = json_encode($coursecompletion);

        $coursecompletionsnapshot = snapshot::from_entity($entitycoursecompletion);
        $this->assertEquals($coursecompletionjson, $coursecompletionsnapshot->get('data'));
        $this->assertEquals(md5($coursecompletionjson), $coursecompletionsnapshot->get('hash'));
    }

    /**
     * Ensure that all fields defined for each entity has been stored.
     */
    public function test_get_data_in_fields_table() {
        global $DB;

        $action = action::get_record(
            array(
                'action' => 'course_completed',
                'originalcourseid' => $this->course->id,
                'relateduserid' => $this->user->id
            )
        );

        $actionid = $action->get('id');
        $actionfields = \tool_datastore\local\action\course_completed::get_fields_to_index();

        $entitycourse = entity::get_record(
            array(
                'actionid' => $actionid,
                'type' => 'course',
                'originalid' => $this->course->id
            )
        );

        $course = get_course($this->course->id);
        $entityid = $entitycourse->get('id');

        foreach ($actionfields['course'] as $field) {
            $fieldindex = field::get_record([
                'entityid' => $entityid,
                'name' => $field,
            ]);
            $this->assertInstanceOf(field::class, $fieldindex);
            $this->assertEquals($fieldindex->get('value'), $course->{$field});
        }

        // User fields (the user and the related user is the same).
        $entityuser = entity::get_record(
            array(
                'actionid' => $actionid,
                'type' => 'user',
                'originalid' => $this->user->id
            )
        );

        $user = get_complete_user_data('id', $this->user->id);
        $entityid = $entityuser->get('id');

        foreach ($actionfields['user'] as $field) {
            $fieldindex = field::get_record([
                'entityid' => $entityid,
                'name' => $field,
            ]);
            $this->assertInstanceOf(field::class, $fieldindex);
            $this->assertEquals($fieldindex->get('value'), $user->{$field});
        }

        // Course completion fields.
        $entitycoursecompletion = entity::get_record([
            'actionid' => $actionid,
            'type' => 'course_completion',
        ]);

        $coursecompletion = \tool_datastore\api::get_course_completion($entitycoursecompletion->get('originalid'));

        $coursecompletionfields = $actionfields['course_completion'];
        $this->assertEquals(['timeenrolled', 'timestarted', 'timecompleted', 'reaggregate'], $coursecompletionfields);

        $allfieldssql = [];
        $allparams = [];
        foreach ($coursecompletionfields as $coursecompletionfield) {
            $field = field::get_record([
                'entityid' => $entitycoursecompletion->get('id'),
                'name' => $coursecompletionfield,
            ]);

            $this->assertInstanceOf(field::class, $field);
            $this->assertEquals($coursecompletion[$coursecompletionfield], $field->get('value'));

            list($fieldsql, $params) = api::get_datasource_field_sql('course_completion', $coursecompletionfield, 'dsa', null,
                constants::DB_TYPE_TIMESTAMP);

            $sql = "SELECT {$fieldsql} AS {$coursecompletionfield}
                      FROM {tool_datastore_action} dsa WHERE dsa.id = {$actionid}";
            $this->assertEquals($field->get('value'), $DB->get_field_sql($sql, $params));

            $allfieldssql[] = "{$fieldsql} AS $coursecompletionfield";
            $allparams += $params;
        }

        // Selecting multiple fields in one SQL query.
        $sql = "SELECT " . join(',', $allfieldssql) . " FROM {tool_datastore_action} dsa WHERE dsa.id = {$action->get('id')}";
        $record = $DB->get_record_sql($sql, $allparams);
        $this->assertEquals($coursecompletionfields, array_keys((array)$record));
    }

    /**
     * Returns the tenant plugin generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}
