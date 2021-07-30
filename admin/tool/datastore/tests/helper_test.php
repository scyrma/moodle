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
 * File containing tests for helper class
 *
 * @package     tool_datastore
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_datastore\api;
use tool_datastore\helper;
use tool_datastore\local\action\course_completed;
use tool_datastore\local\models\action;
use tool_datastore\local\models\entity;
use tool_datastore\local\models\field;
use tool_datastore\local\models\snapshot;


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
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
    public function setUp(): void {
        $this->resetAfterTest();

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

        $actionfields = course_completed::get_fields_to_index();

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

        $coursesnapshot = snapshot::from_entity($entitycourse);
        $this->assertEquals($coursejson, $coursesnapshot->get('data'));
        $this->assertEquals(md5($coursejson), $coursesnapshot->get('hash'));

        // Course fields.
        foreach ($actionfields['course'] as $coursefield) {
            $field = field::get_record([
                'entityid' => $entitycourse->get('id'),
                'name' => $coursefield,
            ]);

            $this->assertInstanceOf(field::class, $field);
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
        $relatedusersnapshot = snapshot::from_entity($entityrelateduser);
        $relatedusersnapshotdata = $relatedusersnapshot->get('data');

        // When getting the related user, we need to reset the lastloaded preference to the snapshot value.
        $relateduser = api::get_user($entityrelateduser->get('originalid'));
        $relateduser['preference'] = json_decode($relatedusersnapshotdata)->preference;

        $relateduserjson = json_encode($relateduser);
        $this->assertEquals($relateduserjson, $relatedusersnapshotdata);
        $this->assertEquals(md5($relateduserjson), $relatedusersnapshot->get('hash'));

        // Related user fields.
        foreach ($actionfields['user'] as $relateduserfield) {
            $field = field::get_record([
                'entityid' => $entityrelateduser->get('id'),
                'name' => $relateduserfield,
            ]);

            $this->assertInstanceOf(field::class, $field);
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

        $coursecompletionsnapshot = snapshot::from_entity($entitycoursecompletion);
        $this->assertEquals($coursecompletionjson, $coursecompletionsnapshot->get('data'));
        $this->assertEquals(md5($coursecompletionjson), $coursecompletionsnapshot->get('hash'));

        // Course completion fields.
        foreach ($actionfields['course_completion'] as $coursecompletionfield) {
            $field = field::get_record([
                'entityid' => $entitycoursecompletion->get('id'),
                'name' => $coursecompletionfield,
            ]);

            $this->assertInstanceOf(field::class, $field);
            $this->assertEquals($coursecompletion[$coursecompletionfield], $field->get('value'));
        }
    }
}
