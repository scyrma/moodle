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
 * File containing tests for badge_awarded event
 *
 * @package     tool_datastore
 * @category    test
 * @copyright   2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \tool_datastore\action;
use \tool_datastore\api;
use \tool_datastore\entity;
use \tool_datastore\fields;
use \tool_datastore\snapshot;
use \tool_datastore\action\badge_awarded;

global $CFG;
require_once($CFG->libdir . '/badgeslib.php');

/**
 * Test class
 *
 * @package     tool_datastore
 * @group       tool_datastore
 * @category    test
 * @covers      \tool_datastore\action\badge_awarded
 * @covers      \tool_datastore\action
 * @covers      \tool_datastore\entity
 * @covers      \tool_datastore\fields
 * @covers      \tool_datastore\snapshot
 * @copyright   2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_datastore_badge_awarded_testcase extends advanced_testcase {

    /** @var stdClass $course */
    protected $course;

    /** @var stdClass $teacher */
    protected $teacher;

    /** @var stdClass $student */
    protected $student;

    /** @var stdClass $badge */
    protected $badge;

    /**
     * Test setup
     *
     * @return void
     */
    protected function setUp() {
        global $DB;

        $this->resetAfterTest();

        // Enable datastore.
        set_config('enabled', 1, 'tool_datastore');

        // Create our test course and users.
        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->setUser($this->teacher);

        // Create a course badge record.
        $time = time();
        $this->badge = (object) [
            'name' => 'Course badge',
            'description' => 'Testing course badges',
            'type' => BADGE_TYPE_COURSE,
            'courseid' => $this->course->id,
            'timecreated' => $time,
            'timemodified' => $time,
            'usercreated' => $this->teacher->id,
            'usermodified' => $this->teacher->id,
            'issuername' => 'Test issuer',
            'issuerurl' => 'http://issuer-url.domain.co.nz',
            'issuercontact' => 'issuer@example.com',
            'expiredate' => null,
            'expireperiod' => null,
            'messagesubject' => 'Test message subject for badge',
            'message' => 'Test message body for badge',
            'attachment' => 1,
            'notification' => 0,
            'status' => BADGE_STATUS_ACTIVE,
            'version' => '1',
            'language' => 'en',
            'imageauthorname' => 'Image author',
            'imageauthoremail' => 'imageauthor@example.com',
            'imageauthorurl' => 'http://image-author-url.domain.co.nz',
            'imagecaption' => 'Caption',
        ];

        // Create badge instance and issue to student.
        $this->badge->id = $DB->insert_record('badge', $this->badge);
        (new badge($this->badge->id))->issue($this->student->id, true);
    }

    /**
     * Ensure that when a badge is awarded the action is registered in the datastore action table
     *
     * @return void
     */
    public function test_get_data_in_action_table() {
        $action = action::get_record([
            'originalcourseid' => $this->course->id,
            'relateduserid' => $this->student->id,
        ]);

        $this->assertEquals('badge_awarded', $action->get('action'));
        $this->assertEquals($this->teacher->id, $action->get('usermodified'));
    }

    /**
     * Ensure that the entities related to the badge awarded event are stored
     *
     * @return void
     */
    public function test_get_data_in_entity_table() {
        $action = action::get_record([
            'originalcourseid' => $this->course->id,
            'relateduserid' => $this->student->id,
        ]);

        // Course entity.
        $entitycourse = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course',
        ]);

        $this->assertInstanceOf(entity::class, $entitycourse);
        $this->assertEquals($this->course->id, $entitycourse->get('originalid'));

        // Related user entity.
        $entityrelateduser = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'user',
            'originalid' => $action->get('relateduserid'),
        ]);

        $this->assertInstanceOf(entity::class, $entityrelateduser);
        $this->assertEquals($this->student->id, $entityrelateduser->get('originalid'));

        // User modified entity.
        $entityusermodified = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'user',
            'originalid' => $action->get('usermodified'),
        ]);

        $this->assertInstanceOf(entity::class, $entityusermodified);
        $this->assertEquals($this->teacher->id, $entityusermodified->get('originalid'));

        // Badge entity.
        $entitybadge = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'badge',
        ]);

        $this->assertInstanceOf(entity::class, $entitybadge);
        $this->assertEquals($this->badge->id, $entitybadge->get('originalid'));
    }

    /**
     * Ensure that the snapshot data has been created with a correct hash
     *
     * @return void
     */
    public function test_get_data_in_snapshot_table() {
        $action = action::get_record([
            'originalcourseid' => $this->course->id,
            'relateduserid' => $this->student->id,
        ]);

        // Course entity.
        $entitycourse = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course',
        ]);

        $course = api::get_course($entitycourse->get('originalid'));
        $coursejson = json_encode($course);

        $coursesnapshot = new snapshot($entitycourse->get('snapshotid'));
        $this->assertEquals($coursejson, $coursesnapshot->get('data'));
        $this->assertEquals(md5($coursejson), $coursesnapshot->get('hash'));

        // Related user entity.
        $entityrelateduser = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'user',
            'originalid' => $action->get('relateduserid'),
        ]);

        $relateduser = api::get_user($entityrelateduser->get('originalid'));
        $relateduserjson = json_encode($relateduser);

        $relatedusersnapshot = new snapshot($entityrelateduser->get('snapshotid'));
        $this->assertEquals($relateduserjson, $relatedusersnapshot->get('data'));
        $this->assertEquals(md5($relateduserjson), $relatedusersnapshot->get('hash'));

        // User modified entity.
        $entityusermodified = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'user',
            'originalid' => $action->get('usermodified'),
        ]);

        $usermodified = api::get_user($entityusermodified->get('originalid'));
        $usermodifiedjson = json_encode($usermodified);

        $usermodifiedsnapshot = new snapshot($entityusermodified->get('snapshotid'));
        $this->assertEquals($usermodifiedjson, $usermodifiedsnapshot->get('data'));
        $this->assertEquals(md5($usermodifiedjson), $usermodifiedsnapshot->get('hash'));

        // Badge entity.
        $entitybadge = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'badge',
        ]);

        // When getting the badge, we need to reset the status/timemodified to the snapshot value.
        $badge = api::get_badge($entitybadge->get('originalid'));
        $badge['status'] = (string)($this->badge->status);
        $badge['timemodified'] = (string)($this->badge->timemodified);

        $badgejson = json_encode($badge);

        $badgesnapshot = new snapshot($entitybadge->get('snapshotid'));
        $this->assertEquals($badgejson, $badgesnapshot->get('data'));
        $this->assertEquals(md5($badgejson), $badgesnapshot->get('hash'));
    }

    /**
     * Assert contents of field returned by get_datasource_field_sql
     *
     * @param mixed $expected
     * @param string $entitytype
     * @param string $fieldname
     * @param int $actionid
     * @param string|null $actionfield
     */
    private function assert_datasource_field_sql($expected, string $entitytype, string $fieldname, int $actionid,
            string $actionfield = null) {
        global $DB;

        list($fieldsql, $params) = api::get_datasource_field_sql($entitytype, $fieldname, 'dsa', $actionfield);
        $sql = "SELECT {$fieldsql} AS value FROM {tool_datastore_action} dsa WHERE dsa.id = {$actionid}";

        $this->assertEquals($expected, $DB->get_field_sql($sql, $params));
    }

    /**
     * Ensure that all fields defined for each entity have been stored
     *
     * @return void
     */
    public function test_get_data_in_fields_table() {
        $action = action::get_record([
            'originalcourseid' => $this->course->id,
            'relateduserid' => $this->student->id,
        ]);

        // Course entity.
        $entitycourse = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'course',
        ]);

        $course = api::get_course($entitycourse->get('originalid'));

        $coursefields = explode(',', badge_awarded::get_fields_to_index()['course']);
        foreach ($coursefields as $coursefield) {
            $field = fields::get_record([
                'actionid' => $action->get('id'),
                'entityid' => $entitycourse->get('id'),
                'name' => $coursefield,
            ]);

            $this->assertInstanceOf(fields::class, $field);
            $this->assertEquals($course[$coursefield], $field->get('value'));
            $this->assert_datasource_field_sql($field->get('value'), 'course', $coursefield, $action->get('id'));
        }

        // Related user entity.
        $entityrelateduser = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'user',
            'originalid' => $action->get('relateduserid'),
        ]);

        $relateduser = api::get_user($entityrelateduser->get('originalid'));

        $relateduserfields = explode(',', badge_awarded::get_fields_to_index()['user']);
        foreach ($relateduserfields as $relateduserfield) {
            $field = fields::get_record([
                'actionid' => $action->get('id'),
                'entityid' => $entityrelateduser->get('id'),
                'name' => $relateduserfield,
            ]);

            $this->assertInstanceOf(fields::class, $field);
            $this->assertEquals($relateduser[$relateduserfield], $field->get('value'));
            $this->assert_datasource_field_sql($field->get('value'), 'user', $relateduserfield,
                $action->get('id'), 'relateduserid');
        }

        // User modified entity.
        $entityusermodified = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'user',
            'originalid' => $action->get('usermodified'),
        ]);

        $usermodified = api::get_user($entityusermodified->get('originalid'));

        $usermodifiedfields = explode(',', badge_awarded::get_fields_to_index()['user']);
        foreach ($usermodifiedfields as $usermodifiedfield) {
            $field = fields::get_record([
                'actionid' => $action->get('id'),
                'entityid' => $entityusermodified->get('id'),
                'name' => $usermodifiedfield,
            ]);

            $this->assertInstanceOf(fields::class, $field);
            $this->assertEquals($usermodified[$usermodifiedfield], $field->get('value'));
            $this->assert_datasource_field_sql($field->get('value'), 'user', $usermodifiedfield,
                $action->get('id'), 'usermodified');
        }

        // Badge entity.
        $entitybadge = entity::get_record([
            'actionid' => $action->get('id'),
            'type' => 'badge',
        ]);

        $badge = api::get_badge($entitybadge->get('originalid'));

        $badgefields = explode(',', badge_awarded::get_fields_to_index()['badge']);
        foreach ($badgefields as $badgefield) {
            $field = fields::get_record([
                'actionid' => $action->get('id'),
                'entityid' => $entitybadge->get('id'),
                'name' => $badgefield,
            ]);

            $this->assertInstanceOf(fields::class, $field);
            $this->assertEquals($badge[$badgefield], $field->get('value'));
            $this->assert_datasource_field_sql($field->get('value'), 'badge', $badgefield, $action->get('id'));
        }
    }
}