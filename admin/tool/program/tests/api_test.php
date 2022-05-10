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

namespace tool_program;

use advanced_testcase;
use completion_completion;
use completion_info;
use context_course;
use context_system;
use core_tag_tag;
use Exception;
use moodle_url;
use phpunit_util;
use ReflectionClass;
use stdClass;
use tool_program_generator;
use tool_tenant_generator;
use tool_certification_generator;
use core\event\base;
use tool_program\event\program_completed;
use tool_program\event\program_course_updated;
use tool_program\event\program_set_completed;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\event\program_course_created;
use tool_program\event\program_course_deleted;
use tool_program\event\program_created;
use tool_program\event\program_deleted;
use tool_program\event\program_updated;
use tool_program\event\program_set_created;
use tool_program\event\program_set_deleted;
use tool_program\event\program_set_updated;
use tool_program\event\user_allocation_created;
use tool_program\event\user_allocation_deleted;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;
use tool_tenant\tenancy;

/**
 * API tests.
 *
 * @covers     \tool_program\api
 * @package    tool_program
 * @group      tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class api_test extends advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;
    /** @var tool_certification_generator */
    protected $certificationgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test create program
     */
    public function test_create_program(): void {
        global $DB;
        self::setAdminUser();
        $programdata = $this->generator->get_dummy_program_data();
        $program = api::create_program($programdata);

        // Test program has been created.
        $this->assertNotEmpty($program->get('id'));

        // Test program has been created with the passed data.
        $record = $DB->get_record(program::TABLE, ['id' => $program->get('id')]);
        $this->assertEquals($programdata->tenantid, $record->tenantid);
        $this->assertEquals($programdata->fullname, $record->fullname);
        $this->assertEquals($programdata->idnumber, $record->idnumber);
        $this->assertEquals($programdata->description, $record->description);
        $this->assertEquals($programdata->descriptionformat, $record->descriptionformat);
        $this->assertEquals($programdata->startdatetype, $record->startdatetype);
        $this->assertEquals($programdata->startdateabsolute, $record->startdateabsolute);
        $this->assertEquals($programdata->enddatetype, $record->enddatetype);
        $this->assertEquals($programdata->enddateabsolute, $record->enddateabsolute);
        $this->assertEquals($programdata->archived, $record->archived);
        $this->assertEquals($programdata->visible, $record->visible);
        $this->assertEquals($programdata->shared, $record->shared);

        // Test program base set created.
        $this->assertNotFalse($DB->record_exists(program_set::TABLE, [
            'programid' => $program->get('id'),
            'parent' => 0,
        ]));

        // TODO SP-85: test description correctly saved, including embedded files.

        // TODO SP-85: test program image saved.

        // Check that program tags are saved correctly.
        $tags = core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $program->get('id'));
        $this->assertEqualsCanonicalizing($programdata->program_tags, $tags);

        // Test exception thrown when missing required data to create a program.
        $programdata->fullname = null;
        try {
            api::create_program($programdata);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // Test defaults to current user tenantid if none provided.
        $programdata = $this->generator->get_dummy_program_data();
        unset($programdata->tenantid);
        // Test defaults to empty array if no program_tags provided.
        unset($programdata->program_tags);
        $program = api::create_program($programdata);

        $expectedtenantid = tenancy::get_tenant_id();
        $this->assertSame($expectedtenantid, (int) $program->get('tenantid'));
        $emptytags = core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $program->get('id'));
        $this->assertEmpty($emptytags);
    }

    /**
     * Test triggers event for create program
     */
    public function test_create_program_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $programdata = $this->generator->get_dummy_program_data();

        $sink = $this->redirectEvents();
        $program = api::create_program($programdata);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_created::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($program->get('id'), $event->objectid);

        // Test event get_name().
        $eventname = get_string('eventprogramcreated', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' created " .
            "the program with id '" . $program->get('id') . "'. The program has been created visible.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Program created in the tenant is always not shared, in the shared space is always shared
     */
    public function test_create_shared_program(): void {
        self::setAdminUser();
        $tenant = $this->tenantgenerator->create_tenant();
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();

        // Program created in a tenant is always not shared.
        $programdata = ['tenantid' => $tenant->id, 'shared' => 1] + (array)$this->generator->get_dummy_program_data();
        $program1 = api::create_program((object)$programdata);
        $this->assertEquals(0, $program1->get('shared'));

        // Program created in a shared space is always shared.
        $programdata = ['tenantid' => $sharedspaceid, 'shared' => 0] + (array)$this->generator->get_dummy_program_data();
        $program2 = api::create_program((object)$programdata);
        $this->assertEquals(1, $program2->get('shared'));

        // Shared program duplicated into a tenant is not shared.
        tenancy::set_switched_tenant_id($tenant->id);
        $newprogram = api::duplicate_program($program2);
        $this->assertEquals($tenant->id, $newprogram->get('tenantid'));
        $this->assertEquals(0, $newprogram->get('shared'));
    }

    /**
     * Test update program details
     */
    public function test_update_program_details(): void {
        global $DB;
        self::setAdminUser();
        $originaldata = $this->generator->get_dummy_program_data();
        $program = $this->generator->generate_program($originaldata);

        $programdata = $program->to_record();
        $programdata->id = $program->get('id');
        $programdata->fullname = 'Updated program name';
        $programdata->idnumber = '22';
        $programdata->description = 'Updated description';
        $programdata->descriptionformat = FORMAT_PLAIN;
        $programdata->visible = 0;
        $programdata->allowdirectallocation = 0;
        $programdata->autocreategroups = api::GROUPS_TENANT;
        $programdata->program_tags = [
            'blue',
            'sky',
        ];

        api::update_program_details($programdata);

        $record = $DB->get_record('tool_program', ['id' => $programdata->id]);
        $this->assertEquals($programdata->fullname, $record->fullname);
        $this->assertEquals($programdata->idnumber, $record->idnumber);
        $this->assertEquals($programdata->description, $record->description);
        $this->assertEquals($programdata->descriptionformat, $record->descriptionformat);
        $this->assertEquals($programdata->visible, $record->visible);
        $this->assertEquals($programdata->allowdirectallocation, $record->allowdirectallocation);

        // TODO SP-85: test embedded files in description updated.

        // TODO SP-85: test program image updated.

        // Check that program tags have been updated.
        $tags = core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $program->get('id'));
        $this->assertEqualsCanonicalizing($programdata->program_tags, $tags);
    }

    /**
     * Test trigger event for update program details
     */
    public function test_update_program_details_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program();

        $programdata = new stdClass();
        $programdata->id = $program->get('id');
        $programdata->fullname = 'Updated program Name';
        $programdata->idnumber = '1';
        $programdata->allowdirectallocation = '1';
        $programdata->visible = '1';
        $programdata->autocreategroups = api::GROUPS_TENANT;
        $programdata->description_editor = [
            'itemid' => 1,
            'text' => $programdata->description ?? 'A program description',
            'format' => $programdata->descriptionformat ?? FORMAT_HTML,
        ];
        $programdata->program_tags = [
            'hello',
            'world',
        ];

        $sink = $this->redirectEvents();
        api::update_program_details($programdata);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_updated::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($program->get('id'), $event->objectid);

        // Test event get_name().
        $eventname = get_string('eventprogramupdated', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' updated " .
            "the program with id '" . $program->get('id') . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test update program calendar
     */
    public function test_update_program_calendar(): void {
        global $DB;
        self::setAdminUser();
        $originaldata = $this->generator->get_dummy_program_data();
        $program = $this->generator->generate_program($originaldata);

        $programdata = new stdClass();
        $programdata->id = $program->get('id');
        $programdata->startdatetype = constants::DATE_NONE;
        $programdata->startdateabsolute = strtotime('-2 day');
        $programdata->startdaterelative = '1 year';
        $programdata->duedatetype = constants::DATE_NONE;
        $programdata->duedateabsolute = strtotime('+2 day');
        $programdata->duedaterelative = '1 year';
        $programdata->enddatetype = constants::DATE_NONE;
        $programdata->enddateabsolute = strtotime('+2 day');
        $programdata->enddaterelative = '1 year';
        $programdata->allocationstartdatetype = constants::DATE_NONE;
        $programdata->allocationstartdateabsolute = strtotime('-2 day');
        $programdata->allocationenddatetype = constants::DATE_NONE;
        $programdata->allocationenddateabsolute = strtotime('+2 day');
        $programdata->allocationenddaterelative = '1 year';

        api::update_program_calendar($programdata);

        $record = $DB->get_record('tool_program', ['id' => $programdata->id]);
        $this->assertEquals($programdata->startdatetype, $record->startdatetype);
        $this->assertEquals($programdata->startdateabsolute, $record->startdateabsolute);
        $this->assertEquals($programdata->startdaterelative, $record->startdaterelative);
        $this->assertEquals($programdata->duedatetype, $record->duedatetype);
        $this->assertEquals($programdata->duedateabsolute, $record->duedateabsolute);
        $this->assertEquals($programdata->duedaterelative, $record->duedaterelative);
        $this->assertEquals($programdata->enddatetype, $record->enddatetype);
        $this->assertEquals($programdata->enddateabsolute, $record->enddateabsolute);
        $this->assertEquals($programdata->enddaterelative, $record->enddaterelative);
        $this->assertEquals($programdata->allocationstartdatetype, $record->allocationstartdatetype);
        $this->assertEquals($programdata->allocationstartdateabsolute, $record->allocationstartdateabsolute);
        $this->assertEquals($programdata->allocationenddatetype, $record->allocationenddatetype);
        $this->assertEquals($programdata->allocationenddateabsolute, $record->allocationenddateabsolute);
        $this->assertEquals($programdata->allocationenddaterelative, $record->allocationenddaterelative);

        // Test on update program calendar, if user allocation source is certification, then user dates are not recalculated.
        $user = self::getDataGenerator()->create_user();
        $programuserdata = $this->generator->get_dummy_program_user_data(
            ['userid' => $user->id, 'programid' => $program->get('id'), 'certificationid' => 15]);
        $programuser = new program_user(0, $programuserdata);
        $programuser->create();

        api::update_program_calendar($programdata);

        $record = $DB->get_record('tool_program_users',
            ['userid' => $user->id, 'programid' => $program->get('id'), 'certificationid' => 15]);
        $this->assertEquals($programuser->get('startdate'), $record->startdate);
        $this->assertEquals($programuser->get('duedate'), $record->duedate);
        $this->assertEquals($programuser->get('enddate'), $record->enddate);

        // But it user allocation is not certification, then it should trigger the recalculation of user dates.
        $programuserdata = $this->generator->get_dummy_program_user_data(
            ['userid' => $user->id, 'programid' => $program->get('id')]);
        $programuser = new program_user(0, $programuserdata);
        $programuser->create();

        api::update_program_calendar($programdata);

        $programuser = $programuser->read(); // Re-fetch this program user data.
        $this->assertEquals(0, $programuser->get('startdate'));
        $this->assertEquals(0, $programuser->get('duedate'));
        $this->assertEquals(0, $programuser->get('enddate'));
    }

    /**
     * Test trigger event for update program calendar
     */
    public function test_update_program_calendar_triggers_event(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();

        $programdata = new stdClass();
        $programdata->id = $program->get('id');
        $programdata->startdatetype = constants::DATE_NONE;
        $programdata->startdateabsolute = strtotime('-3 day');
        $programdata->startdaterelative = '2 year';
        $programdata->duedatetype = constants::DATE_NONE;
        $programdata->duedateabsolute = strtotime('+3 day');
        $programdata->duedaterelative = '3 year';
        $programdata->enddatetype = constants::DATE_NONE;
        $programdata->enddateabsolute = strtotime('+4 day');
        $programdata->enddaterelative = '4 year';
        $programdata->allocationstartdatetype = constants::DATE_NONE;
        $programdata->allocationstartdateabsolute = strtotime('-3 day');
        $programdata->allocationenddatetype = constants::DATE_NONE;
        $programdata->allocationenddateabsolute = strtotime('+5 day');
        $programdata->allocationenddaterelative = '5 year';

        $sink = $this->redirectEvents();
        api::update_program_calendar($programdata);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_updated::class, $event);
    }

    /**
     * Test delete program
     */
    public function test_delete_program(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $course = self::getDataGenerator()->create_course();
        $courseid = $course->id;
        $programcourse = $this->generator->add_course_to_set($courseid, $basesetid);
        $programcourseid = $programcourse->get('id');
        $user = self::getDataGenerator()->create_and_enrol($course);
        $userid = $user->id;
        $programuser = $this->generator->allocate_user_to_program($programid, $userid);
        $programuserid = $programuser->get('id');
        self::getDataGenerator()->enrol_user($userid, $courseid, 'student', 'program');

        // We test to delete without archiving first (it must fail).
        try {
            api::delete_program($program);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // We mark the program as archived now.
        $program->set('archived', 1);
        $program->update();

        // We try to delete again.
        api::delete_program($program);

        // We check program is deleted.
        $this->assertFalse($DB->record_exists('tool_program', ['id' => $programid]));

        // We check base set is deleted.
        $this->assertFalse($DB->record_exists('tool_program_sets', ['id' => $basesetid]));

        // We check if program course has been deleted.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourseid]));

        // We check if program user has been deleted.
        $this->assertFalse($DB->record_exists('tool_program_users', ['id' => $programuserid]));

        // We check if program enrol instance has been deleted.
        $enrolparams = ['courseid' => $courseid, 'enrol' => 'program', 'customint1' => $programid];
        $this->assertFalse($DB->get_record('enrol', $enrolparams));

        // Create a new program with 2 courses inside, delete one course and then delete program.
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        // We need to test also that deletes associated dynamic rules.
        api::add_default_dynamicrule_conditions_to_program($programid, $program->get('tenantid'));
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $courseid1 = $course1->id;
        $courseid2 = $course2->id;
        $programcourse1 = $this->generator->add_course_to_set($courseid1, $basesetid);
        $programcourse2 = $this->generator->add_course_to_set($courseid2, $basesetid);
        $programcourseid1 = $programcourse1->get('id');
        $programcourseid2 = $programcourse2->get('id');
        $user = self::getDataGenerator()->create_and_enrol($course1);
        $userid = $user->id;
        $programuser = $this->generator->allocate_user_to_program($programid, $userid);
        $programuserid = $programuser->get('id');
        self::getDataGenerator()->enrol_user($userid, $courseid, 'student', 'program');

        // Check program enrol instance exists for course1.
        $enrolparams = [
            'courseid' => $courseid1,
            'enrol' => 'program',
            'customint1' => $programid,
        ];
        $this->assertCount(1, $DB->get_records('enrol', $enrolparams));

        // Delete one course.
        delete_course($courseid1, false);
        // We mark the program as archived now.
        $program->set('archived', 1);
        $program->update();

        $params = [
            'component' => 'tool_program',
            'componentarea' => 'program',
            'itemid' => $programid,
        ];
        $this->assertTrue($DB->record_exists('tool_dynamicrule', $params));

        // We try to delete program.
        api::delete_program($program);

        // We check program is deleted.
        $this->assertFalse($DB->record_exists('tool_program', ['id' => $programid]));
        $this->assertFalse($DB->record_exists('tool_dynamicrule', $params));

        // We check base set is deleted.
        $this->assertFalse($DB->record_exists('tool_program_sets', ['id' => $basesetid]));

        // We check if program courses have been deleted.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourseid1]));
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourseid2]));

        // We check if program user has been deleted.
        $this->assertFalse($DB->record_exists('tool_program_users', ['id' => $programuserid]));

        // Check program enrol instance for course1 has been deleted.
        $this->assertCount(0, $DB->get_records('enrol', $enrolparams));
    }

    /**
     * Test trigger event for delete program.
     */
    public function test_delete_program_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program((object) ['archived' => 1]);
        $programrecord = $program->to_record();

        $sink = $this->redirectEvents();
        api::delete_program($program);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_deleted::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($programrecord->id, $event->objectid);

        // Test event get_name().
        $eventname = get_string('eventprogramdeleted', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' deleted " .
            "the program with id '$programrecord->id'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/index.php');
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test update set.
     */
    public function test_update_set(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $newset = $this->generator->generate_set((object)['programid' => $program->get('id')]);
        $originalset = $newset->to_record();

        $setdata = new stdClass();
        $setdata->id = $newset->get('id');
        $setdata->parent = 123;
        $setdata->name = 'New set name';
        $setdata->sortorder = 654;
        api::update_set($setdata);

        $updatedset = new program_set($newset->get('id'));
        $this->assertEquals((int) $originalset->id, (int) $updatedset->get('id'));
        $this->assertEquals($setdata->name, $updatedset->get('name'));
        $this->assertEquals($setdata->parent, (int) $updatedset->get('parent'));
        $this->assertEquals($setdata->sortorder, (int) $updatedset->get('sortorder'));
    }

    /**
     * Test trigger event for update set
     */
    public function test_update_set_triggers_event(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $newset = $this->generator->generate_set((object)['programid' => $program->get('id')]);
        $setdata = new stdClass();
        $setdata->id = $newset->get('id');
        $setdata->name = 'New set name';
        $setdata->parent = 123;
        $setdata->sortorder = 654;

        $sink = $this->redirectEvents();
        api::update_set($setdata);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_set_updated::class, $event);
    }

    /**
     * Test update set completion criteria
     */
    public function test_update_set_completion_criteria(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $set = $this->generator->generate_set((object)['programid' => $program->get('id')]);
        $originalset = $set->to_record();

        // Test update completioncriteria.
        $data = new stdClass();
        $data->setid = $originalset->id;
        $data->completioncriteria = program_set::COMPLETION_ALL_IN_ANY_ORDER;
        api::update_set_completion_criteria($data);

        $updatedset = new program_set($data->setid);
        $this->assertEquals((string) $data->completioncriteria, (string) $updatedset->get('completioncriteria'));

        // Test update completion criteria and completion at least.
        $data = new stdClass();
        $data->setid = $originalset->id;
        $data->completioncriteria = program_set::COMPLETION_AT_LEAST;
        $data->completionatleast = 4;
        api::update_set_completion_criteria($data);

        $updatedset = new program_set($data->setid);
        $this->assertEquals((string) $data->completioncriteria, (string) $updatedset->get('completioncriteria'));
        $this->assertEquals((string) $data->completionatleast, (string) $updatedset->get('completionatleast'));
    }

    /**
     * Test trigger event for update set completion criteria
     */
    public function test_update_set_completion_criteria_triggers_event(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $set = $this->generator->generate_set((object)['programid' => $program->get('id')]);
        $data = new stdClass();
        $data->setid = $set->get('id');
        $data->completioncriteria = program_set::COMPLETION_ALL_IN_ANY_ORDER;

        $sink = $this->redirectEvents();
        api::update_set_completion_criteria($data);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_set_updated::class, $event);
    }

    /**
     * Test delete set
     */
    public function test_delete_set(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $parentset = $this->generator->generate_set((object) ['programid' => $programid]);
        $parentsetid = $parentset->get('id');
        $course1 = self::getDataGenerator()->create_course();
        $course1id = $course1->id;
        $programcourse1 = $this->generator->add_course_to_set($course1id, $parentsetid);
        $programcourse1id = $programcourse1->get('id');
        $childset = $this->generator->generate_set((object) ['programid' => $programid, 'parent' => $parentsetid]);
        $childsetid = $childset->get('id');
        $course2 = self::getDataGenerator()->create_course();
        $course2id = $course2->id;
        $programcourse2 = $this->generator->add_course_to_set($course2id, $childsetid);
        $programcourse2id = $programcourse2->get('id');

        api::delete_set($parentset);

        // Check parent set deleted.
        $this->assertFalse($DB->record_exists('tool_program_sets', ['id' => $parentsetid]));

        // Check child set deleted.
        $this->assertFalse($DB->record_exists('tool_program_sets', ['id' => $childsetid]));

        // Check courses of parent set (course1) deleted.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourse1id]));

        // Check courses of child set (course2) deleted.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourse2id]));
    }

    /**
     * Test trigger event for delete set
     */
    public function test_delete_set_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $programset = $this->generator->generate_set((object) ['programid' => $programid, 'parent' => $basesetid]);
        $programsetrecord = $programset->to_record();

        $sink = $this->redirectEvents();
        api::delete_set($programset);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_set_deleted::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($programsetrecord->id, $event->objectid);
        $this->assertEquals($program->get('id'), $event->other['programid']);

        // Test event get_name().
        $eventname = get_string('eventsetdeleted', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' deleted " .
            "the set with id '$programsetrecord->id'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test add set to base set
     */
    public function test_add_set_to_base_set(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $basesetid = $program->get_base_set()->get('id');

        $programset = api::add_set_to_base_set($programid, 'A set 1');

        // Check new set has been created.
        $this->assertNotEmpty($programset->get('id'));
        $this->assertTrue($DB->record_exists('tool_program_sets', [
            'id' => $programset->get('id'),
            'programid' => $programid,
            'parent' => $basesetid,
        ]));

        // Check new set sortorder starts at 1.
        $this->assertSame(1, $programset->get('sortorder'));

        // Check adding sets at the same level increases their sortorder.
        $programset2 = api::add_set_to_base_set($programid, 'A set 2');
        $this->assertSame(2, $programset2->get('sortorder'));

        // Check adding more child elements and then a set, at the same level, increases its sortorder properly.
        $course1 = self::getDataGenerator()->create_course();
        $course1id = $course1->id;
        $this->generator->add_course_to_set($course1id, $basesetid, 3);
        $programset2 = api::add_set_to_base_set($programid, 'A set 3');
        $this->assertSame(4, $programset2->get('sortorder'));

        // Check throws exception when trying to add a set to a program that does not exist.
        try {
            api::add_set_to_base_set(0, 'A set 3');
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // Check throws exception when trying to add a set to a program that does not have a base set.
        $program2 = new program(0, $this->generator->get_dummy_program_data());
        try {
            api::add_set_to_base_set($program2->get('id'), 'A set 4');
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }
    }

    /**
     * Test trigger event add set to base set
     */
    public function test_add_set_to_base_set_triggers_event(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');

        $sink = $this->redirectEvents();
        api::add_set_to_base_set($programid, 'A child set name');
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_set_created::class, $event);
    }

    /**
     * Test add course to base set
     */
    public function test_add_course_to_base_set(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $basesetid = $program->get_base_set()->get('id');
        $course = self::getDataGenerator()->create_course();

        $programcourse = api::add_course_to_base_set($programid, $course->id);

        // Check course is added to the base set.
        $this->assertNotEmpty($programcourse->get('id'));
        $this->assertTrue($DB->record_exists('tool_program_courses', [
            'id' => $programcourse->get('id'),
            'setid' => $basesetid,
        ]));

        // Check sortorder starts counting from 1.
        $this->assertSame(1, $programcourse->get('sortorder'));

        // We add another course to base set to test sortorder increases.
        $course2 = self::getDataGenerator()->create_course();
        $programcourse2 = api::add_course_to_base_set($programid, $course2->id);
        $this->assertSame(2, $programcourse2->get('sortorder'));

        // Check after adding sets at the same level, a new added course increases its sortorder properly.
        $this->generator->generate_set((object) ['programid' => $programid, 'parent' => $basesetid, 'sortorder' => 3]);
        $course3 = self::getDataGenerator()->create_course();
        $programcourse3 = api::add_course_to_base_set($programid, $course3->id);
        $this->assertSame(4, $programcourse3->get('sortorder'));

        // We check if program enrolment instance linked to the added course has been enabled.
        $enrolinstance = $DB->get_record('enrol', [
            'courseid' => $course->id,
            'enrol' => 'program',
            'customint1' => $programid,
        ]);
        $this->assertNotFalse($enrolinstance);
        $this->assertSame((string) ENROL_INSTANCE_ENABLED, $enrolinstance->status);

        // We test exception trying to add a course to a program that does not exist.
        try {
            api::add_course_to_base_set(0, $course->id);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // We test exception trying to add a course that does not exist.
        try {
            api::add_course_to_base_set($programid, 0);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // We test exception trying to add a course to a program with no base set.
        $program2 = new program(0, $this->generator->get_dummy_program_data());
        try {
            api::add_course_to_base_set($program2->get('id'), $course->id);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }
    }

    /**
     * Test trigger event for add course to base set
     */
    public function test_add_course_to_base_set_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();
        $programid = $program->get('id');
        $course = self::getDataGenerator()->create_course();

        $sink = $this->redirectEvents();
        $programcourse = api::add_course_to_base_set($programid, $course->id);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_course_created::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($programcourse->get('id'), $event->objectid);
        $this->assertEquals($program->get('id'), $event->other['programid']);
        $this->assertEquals($course->id, $event->other['courseid']);
        $this->assertEquals($baseset->get('id'), $event->other['setid']);

        // Test event get_name().
        $eventname = get_string('eventcourseadded', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' added " .
            "to the program set '" . $baseset->get('id') . "' " .
            "the course with id '" . $course->id . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/index.php');
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test remove course from program.
     */
    public function test_delete_program_course(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $basesetid = $program->get_base_set()->get('id');
        $course = self::getDataGenerator()->create_course();
        $programcourse = $this->generator->add_course_to_set($course->id, $basesetid);

        api::delete_program_course($programcourse);

        // Check course does not exist anymore.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourse->get('id')]));

        // Check if program enrol instance linked to the added course has been deleted.
        $enrolinstance = $DB->get_record('enrol', [
            'courseid' => $course->id,
            'enrol' => 'program',
            'customint1' => $programid,
        ]);
        $this->assertFalse($enrolinstance);

        // Check that if we add the same course several times, when removed one of them, the enrol instance is not disabled.
        $program2 = $this->generator->generate_program();
        $program2id = $program2->get('id');
        $baseset2id = $program2->get_base_set()->get('id');
        $course2 = self::getDataGenerator()->create_course();
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $baseset2id);
        $parentset2 = $this->generator->generate_set((object) ['programid' => $program2id, 'parent' => $baseset2id]);
        $parentset2id = $parentset2->get('id');
        $programcourse3 = $this->generator->add_course_to_set($course2->id, $parentset2id);

        api::delete_program_course($programcourse3);

        $enrolinstance2 = $DB->get_record('enrol', [
            'courseid' => $course2->id,
            'enrol' => 'program',
            'customint1' => $program2id,
        ]);
        $this->assertNotFalse($enrolinstance2);
        $this->assertSame((string) ENROL_INSTANCE_ENABLED, $enrolinstance2->status);
    }

    /**
     * Test remove course from program on course deletion.
     */
    public function test_delete_program_course_when_course_deleted(): void {
        global $DB;
        self::setAdminUser();

        // Create a program with 2 courses.
        $program = $this->generator->generate_program();
        $basesetid = $program->get_base_set()->get('id');
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $programcourse1 = $this->generator->add_course_to_set($course1->id, $basesetid);
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $basesetid);

        // Delete one course and check program_course instance has been deleted.
        delete_course($course1->id, false);

        // Check course1 does not exist in program anymore.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourse1->get('id')]));
        $this->assertTrue($DB->record_exists('tool_program_courses', ['id' => $programcourse2->get('id')]));
    }

    /**
     * Test trigger event for remove course from program.
     */
    public function test_delete_program_course_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $basesetid = $program->get_base_set()->get('id');
        $course = self::getDataGenerator()->create_course();
        $programcourse = $this->generator->add_course_to_set($course->id, $basesetid);
        $programcourserecord = $programcourse->to_record();

        $sink = $this->redirectEvents();
        api::delete_program_course($programcourse);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_course_deleted::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($programcourserecord->id, $event->objectid);
        $this->assertEquals($program->get('id'), $event->other['programid']);
        $this->assertEquals($course->id, $event->other['courseid']);
        $this->assertEquals($basesetid, $event->other['setid']);

        // Test event get_name().
        $eventname = get_string('eventcourseremoved', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' removed " .
            "from the program set '$basesetid' " .
            "the course with id '$course->id'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/index.php');
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test allocate user.
     *
     * @covers \tool_program\api::enrol_in_program_course_if_user_completed_or_enrolled
     */
    public function test_allocate_user(): void {
        global $DB;
        self::setAdminUser();
        $programstartdate = strtotime('-7 day');
        $programduedate = strtotime('+7 day');
        $programenddate = strtotime('+7 day');
        $program = $this->generator->generate_program((object) [
            'startdateabsolute' => $programstartdate,
            'duedateabsolute' => $programduedate,
            'enddateabsolute' => $programenddate,
        ]);
        $programid = $program->get('id');
        $user = self::getDataGenerator()->create_user();
        $baseset = $program->get_base_set();
        $course = self::getDataGenerator()->create_course();
        $this->generator->add_course_to_set($course->id, $baseset->get('id'));

        $programuser = api::allocate_user($program, (object) [
            'programid' => $programid,
            'userid' => $user->id,
            'certificationid' => 0,
        ]);

        // Check program user exists.
        $this->assertNotEmpty($programuser->get('id'));
        $this->assertTrue($DB->record_exists('tool_program_users', [
            'id' => $programuser->get('id'),
            'programid' => $programid,
            'userid' => $user->id,
            'certificationid' => 0,
        ]));

        // Check dates on user allocated (it should have inherited the program dates).
        $this->assertSame($programstartdate, $programuser->get('startdate'));
        $this->assertSame($programduedate, $programuser->get('duedate'));
        $this->assertSame($programenddate, $programuser->get('enddate'));

        // Check enrolment does not exist.
        $sql = "SELECT ue.*
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0 AND e.enrol = 'program')
                 WHERE ue.userid = :userid";
        $params = ['userid' => $user->id, 'courseid' => $course->id];
        $this->assertCount(0, $DB->get_records_sql($sql, $params));
    }

    /**
     * Test allocate user who enrolled in the course already.
     *
     * @covers \tool_program\api::enrol_in_program_course_if_user_completed_or_enrolled
     */
    public function test_allocate_active_user_enrolled(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program((object) [
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $programid = $program->get('id');
        $user = self::getDataGenerator()->create_user();
        // Add course and enrol user to course manually.
        $baseset = $program->get_base_set();
        $course = self::getDataGenerator()->create_course();
        self::getDataGenerator()->enrol_user($user->id, $course->id);
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));

        // Test program enrol instance is automatically created if user is already enroled with any other enrol method.
        $programuser = api::allocate_user($program, (object) [
            'programid' => $programid,
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ]);

        // Check program user exists.
        $this->assertNotEmpty($programuser->get('id'));
        $this->assertTrue($DB->record_exists('tool_program_users', [
            'id' => $programuser->get('id'),
            'programid' => $programid,
            'userid' => $user->id,
            'certificationid' => 0,
        ]));
        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $programuser->get('status'));
        $this->assertEmpty($programuser->get('timesuspended'));

        // Check enrolment exists.
        $sql = "SELECT ue.*
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0 AND e.enrol = 'program')
                 WHERE ue.userid = :userid AND ue.status = :status";
        $params = ['userid' => $user->id, 'courseid' => $course->id, 'status' => ENROL_USER_ACTIVE];
        $this->assertCount(1, $DB->get_records_sql($sql, $params));

        // Test user was added to group.
        $usergroups = api::get_user_groups_in_course($programid, $course, $user->id);
        $this->assertCount(1, $usergroups);
        $this->assertEquals("A program name", groups_get_group($usergroups[0])->name);
        $this->assertTrue(groups_is_member($usergroups[0], $user->id));
    }

    /**
     * Test allocate user who enrolled in the course already with suspended status.
     *
     * @covers \tool_program\api::enrol_in_program_course_if_user_completed_or_enrolled
     */
    public function test_allocate_suspended_user_enrolled(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $user = self::getDataGenerator()->create_user();
        // Add course and enrol user to course manually.
        $baseset = $program->get_base_set();
        $course = self::getDataGenerator()->create_course();
        self::getDataGenerator()->enrol_user($user->id, $course->id);
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));

        // Test program enrol instance is automatically created if user is already enroled with any other enrol method.
        $programuser = api::allocate_user($program, (object) [
            'programid' => $programid,
            'userid' => $user->id,
            'certificationid' => 0,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
        ]);

        // Test if user is allocated with status suspended, enrolment is suspended.
        // Check program user exists.
        $this->assertNotEmpty($programuser->get('id'));
        $this->assertTrue($DB->record_exists('tool_program_users', [
            'id' => $programuser->get('id'),
            'programid' => $programid,
            'userid' => $user->id,
            'certificationid' => 0,
        ]));
        $this->assertEquals(constants::STATUS_OVERRIDE_SUSPENDED, $programuser->get('status'));
        $this->assertGreaterThan(0, $programuser->get('timesuspended'));

        $sql = "SELECT ue.*
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0 AND e.enrol = 'program')
                 WHERE ue.userid = :userid AND ue.status = :status";
        $params = ['userid' => $user->id, 'courseid' => $course->id, 'status' => ENROL_USER_SUSPENDED];
        $this->assertCount(1, $DB->get_records_sql($sql, $params));

        // Test user was not added to group.
        $usergroups = api::get_user_groups_in_course($programid, $course, $user->id);
        $this->assertCount(0, $usergroups);
    }

    /**
     * Test allocate user who completed the course.
     *
     * @covers \tool_program\api::enrol_in_program_course_if_user_completed_or_enrolled
     */
    public function test_allocate_user_completed_course(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $user = self::getDataGenerator()->create_user();
        // Add course and mark as complete for user.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        $baseset = $program->get_base_set();
        $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $this->generator->complete_courses([$course->id], $user->id);

        // Test program enrol instance is automatically created if user completed the course.
        $programuser = api::allocate_user($program, (object) [
            'programid' => $programid,
            'userid' => $user->id,
            'certificationid' => 0,
        ]);

        // Check program user exists.
        $this->assertNotEmpty($programuser->get('id'));
        $this->assertTrue($DB->record_exists('tool_program_users', [
            'id' => $programuser->get('id'),
            'programid' => $programid,
            'userid' => $user->id,
            'certificationid' => 0,
        ]));

        // Check enrolment exists.
        $sql = "SELECT ue.*
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0 AND e.enrol = 'program')
                 WHERE ue.userid = :userid AND ue.status = :status";
        $params = ['userid' => $user->id, 'courseid' => $course->id, 'status' => ENROL_USER_ACTIVE];
        $this->assertCount(1, $DB->get_records_sql($sql, $params));
    }

    /**
     * Test allocate user recalculates program progress.
     */
    public function test_allocate_user_recalculates_program_progress(): void {
        self::setAdminUser();

        // Create course and complete it with a new user.
        $user = self::getDataGenerator()->create_user();
        $course = self::getDataGenerator()->create_course(['enablecompletion' => true]);
        $assign = self::getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);
        $cmassign = get_coursemodule_from_id('assign', $assign->cmid);
        $completion = new completion_info($course);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);
        $ccompletion = new completion_completion(['course' => $course->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();

        // Create program with the completed course.
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();
        api::add_course_to_parent_set($baseset->get('id'), $course->id);

        // Allocate user and check that the program is automatically marked as completed.
        $sink = $this->redirectEvents();
        api::allocate_user($program, (object) ['userid' => $user->id, 'certificationid' => 0]);
        $events = $sink->get_events();
        $sink->close();

        $events = array_filter($events, static function($event) {
            return $event instanceof program_completed;
        });
        $this->assertNotEmpty($events);
        $this->assertCount(1, $events);
    }

    /**
     * Test trigger event for allocate user.
     */
    public function test_allocate_user_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $user = self::getDataGenerator()->create_user();
        $userid = $user->id;

        $sink = $this->redirectEvents();
        $programuser = api::allocate_user($program, (object) ['userid' => $userid, 'certificationid' => 0]);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(user_allocation_created::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($userid, $event->relateduserid);
        $this->assertEquals($programuser->get('id'), $event->objectid);
        $this->assertEquals($program->get('id'), $event->other['programid']);

        // Test event get_name().
        $eventname = get_string('eventuserallocated', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' allocated " .
            "the user with id '$userid' " .
            "to the program with id '" . $program->get('id') . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test deallocate user.
     */
    public function test_deallocate_user(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program((object) [
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT)
        ]);
        $programid = $program->get('id');
        $user = self::getDataGenerator()->create_user();
        $baseset = $program->get_base_set();
        $course = self::getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $userid = $user->id;
        $programuser = $this->generator->allocate_user_to_program($programid, $userid);
        $programuserid = $programuser->get('id');
        $this->generator->enrol_user_to_program_course($programcourse, $programuser);

        // Enrolment should be active.
        $this->assertTrue(is_enrolled($coursecontext, $user->id, '', true));

        // User was added to group.
        $usergroups = api::get_user_groups_in_course($programid, $course, $userid);
        $this->assertCount(1, $usergroups);
        $this->assertEquals("A program name", groups_get_group($usergroups[0])->name);
        $usergroup = $usergroups[0];
        $this->assertTrue(groups_is_member($usergroup, $userid));

        // Deallocate user.
        api::deallocate_user($programid, $userid);

        // Check program user record is deleted.
        $this->assertFalse($DB->record_exists('tool_program_users', ['id' => $programuserid]));

        // Check that user enrolment has been deleted.
        $this->assertFalse(is_enrolled($coursecontext, $user->id, '', false));

        // Check user was removed from group.
        $usergroups = api::get_user_groups_in_course($programid, $course, $userid);
        $this->assertCount(0, $usergroups);
        $this->assertFalse(groups_is_member($usergroup, $userid));

        // Test unknown allocation params return false.
        $return = api::deallocate_user(-2, -2, -2);
        $this->assertFalse($return);

        // Test it only deallocates considering the provided allocation type.
        $certification = $this->certificationgenerator->generate_certification([
            'program' => $program->get('id'),
        ]);
        $this->certificationgenerator->allocate_user($user->id, $certification->get('id'));

        api::deallocate_user($programid, $userid, $certification->get('id'), constants::ALLOCATION_DYNAMIC);

        // Since we allocated as "manual" and tried to deallocated a "dynamic" allocation instance, the record must remain.
        $params = ['programid' => $programid, 'userid' => $userid, 'certificationid' => $certification->get('id')];
        $this->assertTrue($DB->record_exists('tool_program_users', $params));
    }

    /**
     * Test deallocate user and expect tenant group membership to stay.
     *
     * @covers \enrol_program_plugin::unenrol_user
     */
    public function test_deallocate_user_tenant_group(): void {
        global $DB;
        self::setAdminUser();
        // Create course.
        $course = self::getDataGenerator()->create_course();
        // Create another tenant.
        $cat2 = $this->getDataGenerator()->create_category();
        $this->tenantgenerator->create_tenant(['categoryid' => $cat2->id]);

        // Create program.
        $program = $this->generator->generate_program((object) ['autocreategroups' => api::GROUPS_TENANT]);
        $baseset = $program->get_base_set();
        $this->generator->add_course_to_set($course->id, $baseset->get('id'));

        // Another program with the same course.
        $program1 = $this->generator->generate_program((object) ['autocreategroups' => api::GROUPS_TENANT]);
        $baseset = $program1->get_base_set();
        $this->generator->add_course_to_set($course->id, $baseset->get('id'));

        // Create user, allocate it to the first program and add manual enrolment.
        $newuser = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($newuser->id, $course->id);
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $newuser->id);

        // Expect two active enrolments.
        $sql = "SELECT ue.*
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0)
                 WHERE ue.userid = :userid AND ue.status = :status";
        $params = ['userid' => $newuser->id, 'courseid' => $course->id, 'status' => ENROL_USER_ACTIVE];
        $this->assertCount(2, $DB->get_records_sql($sql, $params));

        // User was added to group.
        $usergroups = api::get_user_groups_in_course($program->get('id'), $course, $newuser->id);
        $usergroup = $usergroups[0];
        $this->assertCount(1, $usergroups);
        $this->assertEquals("Default tenant", groups_get_group($usergroup)->name);
        $this->assertTrue(groups_is_member($usergroup, $newuser->id));

        // Allocate user to second program.
        $this->generator->allocate_user_to_program($program1->get('id'), $newuser->id);

        // Expect three active enrolments.
        $sql = "SELECT ue.*
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0)
                 WHERE ue.userid = :userid AND ue.status = :status";
        $params = ['userid' => $newuser->id, 'courseid' => $course->id, 'status' => ENROL_USER_ACTIVE];
        $this->assertCount(3, $DB->get_records_sql($sql, $params));

        // User is supposed to be in the same group in the other program.
        $usergroups = api::get_user_groups_in_course($program1->get('id'), $course, $newuser->id);
        $usergroup1 = $usergroups[0];
        $this->assertCount(1, $usergroups);
        $this->assertEquals("Default tenant", groups_get_group($usergroup)->name);
        $this->assertEquals($usergroup, $usergroup1);
        // And user is still in the same group.
        $this->assertTrue(groups_is_member($usergroup, $newuser->id));

        // Deallocate user.
        api::deallocate_user($program->get('id'), $newuser->id);

        // Check program user record is deleted.
        $this->assertFalse($DB->record_exists('tool_program_users', ['id' => $programuser->get('id')]));

        // Check that user enrolment via program has been deleted.
        $sql = "SELECT ue.*
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0 AND e.enrol = 'program')
                 WHERE ue.userid = :userid";
        $params = ['userid' => $newuser->id, 'courseid' => $course->id];
        $this->assertCount(1, $DB->get_records_sql($sql, $params));

        // This removed from group from program perspective.
        $usergroups = api::get_user_groups_in_course($program->get('id'), $course, $newuser->id);
        $this->assertCount(0, $usergroups);
        // But still in the same group according to another program.
        $usergroups = api::get_user_groups_in_course($program1->get('id'), $course, $newuser->id);
        $this->assertCount(1, $usergroups);
        $this->assertEquals($usergroup, $usergroups[0]);
        // Check user is still a member of the same Tenant group.
        $this->assertTrue(groups_is_member($usergroup, $newuser->id));

        // Now deallocate from another program.
        api::deallocate_user($program1->get('id'), $newuser->id);
        // This removed from group from another program perspective.
        $usergroups = api::get_user_groups_in_course($program1->get('id'), $course, $newuser->id);
        $this->assertCount(0, $usergroups);
        // Check user is no longer a member of the same Tenant group.
        $this->assertFalse(groups_is_member($usergroup, $newuser->id));
    }

    /**
     * Test trigger event for deallocate user.
     */
    public function test_deallocate_user_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $user = self::getDataGenerator()->create_user();
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $user->id);
        $programuserrecord = $programuser->to_record();

        $sink = $this->redirectEvents();
        api::deallocate_user($program->get('id'), $user->id);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(user_allocation_deleted::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals($programuserrecord->id, $event->objectid);
        $this->assertEquals($program->get('id'), $event->other['programid']);

        // Test event get_name().
        $eventname = get_string('eventuserdeallocated', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' deallocated " .
            "the user with id '$user->id' " .
            "from program with id '" . $program->get('id') . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test add set to parent set
     */
    public function test_add_set_to_parent_set(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $parentsetid = $program->get_base_set()->get('id');
        $childsetname = 'A child set';
        $completion = (object) [
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
            'completionatleast' => 1
        ];

        $childset = api::add_set_to_parent_set($parentsetid, $childsetname, $completion);

        // Check that the new set is added and start counting sortorder by 1.
        $this->assertNotEmpty($childset->get('id'));
        $this->assertNotFalse($DB->record_exists('tool_program_sets', [
            'id' => $childset->get('id'),
            'programid' => $programid,
            'parent' => $parentsetid,
            'name' => $childsetname,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
            'sortorder' => 1,
        ]));

        // Test exception when passing a parent set that does not exist.
        try {
            api::add_set_to_parent_set(0, 'A child set name', $completion);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // Test exception when passing a set without program.
        $orphanedset = new program_set(0, (object) ['programid' => 0, 'name' => 'A set']);
        $orphanedset->create();
        try {
            api::add_set_to_parent_set($orphanedset->get('id'), 'A child set name', $completion);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // Check adding a new set adds set and increases sortorder.
        $childsetname2 = 'Another child set';
        $completion = (object) [
            'completioncriteria' => program_set::COMPLETION_AT_LEAST,
            'completionatleast' => 2
        ];
        $childset2 = api::add_set_to_parent_set($parentsetid, $childsetname2, $completion);
        $this->assertNotEmpty($childset2->get('id'));
        $this->assertNotFalse($DB->record_exists('tool_program_sets', [
            'id' => $childset2->get('id'),
            'programid' => $programid,
            'parent' => $parentsetid,
            'name' => $childsetname2,
            'completioncriteria' => program_set::COMPLETION_AT_LEAST,
            'completionatleast' => 2,
            'sortorder' => 2,
        ]));
    }

    /**
     * Test trigger event for add set ti parent set
     */
    public function test_add_set_to_parent_set_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $parentsetid = $program->get_base_set()->get('id');
        $completion = (object) [
            'completioncriteria' => program_set::COMPLETION_AT_LEAST,
            'completionatleast' => 2,
        ];

        $sink = $this->redirectEvents();
        $childset = api::add_set_to_parent_set($parentsetid, 'A child set name', $completion);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_set_created::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($childset->get('id'), $event->objectid);
        $this->assertEquals($program->get('id'), $event->other['programid']);

        // Test event get_name().
        $eventname = get_string('eventsetcreated', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' created " .
            "the set with id '" . $childset->get('id') . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test add course to parent set.
     */
    public function test_add_course_to_parent_set(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $parentset = api::add_set_to_base_set($programid, 'A parent set');
        $parentsetid = $parentset->get('id');
        $course = self::getDataGenerator()->create_course();
        $courseid = $course->id;

        $programcourse = api::add_course_to_parent_set($parentsetid, $courseid);

        // Check course added to program and sortorder starts with 1.
        $this->assertNotEmpty($programcourse->get('id'));
        $this->assertNotFalse($DB->record_exists('tool_program_courses', [
            'id' => $programcourse->get('id'),
            'setid' => $parentsetid,
            'courseid' => $courseid,
            'sortorder' => 1,
        ]));

        // Test exception trying to add the course to a set that does not exist.
        try {
            api::add_course_to_parent_set(0, $courseid);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // Test exception when trying to add the course to a set without program.
        $orphanedset = new program_set(0, (object) ['programid' => 0, 'name' => 'A set']);
        $orphanedset->create();
        try {
            api::add_course_to_parent_set($orphanedset->get('id'), $courseid);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // Test add another course to check if sortorder is correctly increased by 1.
        $course2 = self::getDataGenerator()->create_course();
        $course2id = $course2->id;
        $programcourse2 = api::add_course_to_parent_set($parentsetid, $course2id);
        $this->assertNotEmpty($programcourse2->get('id'));
        $this->assertNotFalse($DB->record_exists('tool_program_courses', [
            'id' => $programcourse2->get('id'),
            'setid' => $parentsetid,
            'courseid' => $course2id,
            'sortorder' => 2,
        ]));

        // Add course after deleting it from the course to test that the enrol program instance is re-enabled.
        api::delete_program_course($programcourse);

        // Check that the enrol program instance for this course has been disabled.
        $enrolparams = ['courseid' => $courseid, 'enrol' => 'program', 'customint1' => $programid];
        $this->assertFalse($DB->get_record('enrol', $enrolparams));

        api::add_course_to_parent_set($parentsetid, $courseid);

        // Check that the enrol program instance for this course has been re-enabled.
        $enrolinstance = $DB->get_record('enrol', $enrolparams, '*', MUST_EXIST);
        $this->assertEquals(ENROL_INSTANCE_ENABLED, $enrolinstance->status);
    }

    /**
     * Test trigger event for add course to parent set
     */
    public function test_add_course_to_parent_set_triggers_event(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $parentset = api::add_set_to_base_set($programid, 'A parent set');
        $parentsetid = $parentset->get('id');
        $course = self::getDataGenerator()->create_course();

        $sink = $this->redirectEvents();
        api::add_course_to_parent_set($parentsetid, $course->id);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_course_created::class, $event);
    }

    /**
     * Test move_item_to_new_position method.
     */
    public function test_move_item_to_new_position(): void {
        global $DB, $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');

        // Items within the base set.
        $course11 = self::getDataGenerator()->create_course();
        $course11id = $course11->id;
        $programcourse11 = $this->generator->add_course_to_set($course11id, $basesetid, 1);
        $programcourse11id = $programcourse11->get('id');
        $childset12 = $this->generator->generate_set((object) [
            'programid' => $programid,
            'parent' => $basesetid,
            'sortorder' => 2,
        ]);
        $childset12id = $childset12->get('id');
        $course13 = self::getDataGenerator()->create_course();
        $course13id = $course13->id;
        $programcourse13 = $this->generator->add_course_to_set($course13id, $basesetid, 3);
        $programcourse13id = $programcourse13->get('id');

        // Items within a child set.
        $childset21 = $this->generator->generate_set((object) [
            'programid' => $programid,
            'parent' => $childset12id,
            'sortorder' => 1,
        ]);
        $childset21id = $childset21->get('id');
        $course22 = self::getDataGenerator()->create_course();
        $course22id = $course22->id;
        $programcourse22 = $this->generator->add_course_to_set($course22id, $childset12id, 2);
        $programcourse22id = $programcourse22->get('id');

        /*
         * Initial situation:
         *
         * 1 Base set
         *      - 1 course11
         *      - 2 childset12
         *          - 1 childset21
         *          - 2 course22
         *      - 3 course13
         */

        // Move item before another item (same set).
        api::move_item_to_new_position($programcourse13id, false, $basesetid, $basesetid, $programcourse11id, false);

        $programcourse13record = $DB->get_record(program_course::TABLE, ['id' => $programcourse13id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $programcourse13record->setid);
        $this->assertEquals(1, $programcourse13record->sortorder);
        $programcourse11record = $DB->get_record(program_course::TABLE, ['id' => $programcourse11id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $programcourse11record->setid);
        $this->assertEquals(2, $programcourse11record->sortorder);
        $childset12record = $DB->get_record(program_set::TABLE, ['id' => $childset12id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $childset12record->parent);
        $this->assertEquals(3, $childset12record->sortorder);

        /*
         * 1 Base set
         *      - 1 course13 (moved before course11)
         *      - 2 course11 (re-ordered)
         *      - 3 childset12 (re-ordered)
         *          - 1 childset21
         *          - 2 course22
         */

        // Move item to last position (same set).
        api::move_item_to_new_position($programcourse13id, false, $basesetid, $basesetid, 0, false);

        $programcourse11record = $DB->get_record(program_course::TABLE, ['id' => $programcourse11id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $programcourse11record->setid);
        $this->assertEquals(1, $programcourse11record->sortorder);
        $childset12record = $DB->get_record(program_set::TABLE, ['id' => $childset12id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $childset12record->parent);
        $this->assertEquals(2, $childset12record->sortorder);
        $programcourse13record = $DB->get_record(program_course::TABLE, ['id' => $programcourse13id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $programcourse13record->setid);
        $this->assertEquals(3, $programcourse13record->sortorder);

        /*
         * 1 Base set
         *      - 1 course11 (re-ordered)
         *      - 2 childset12 (re-ordered)
         *          - 1 childset21
         *          - 2 course22
         *      - 3 course13 (moved to last position of its current set)
         */

        // Move item before another item (different set).
        api::move_item_to_new_position($programcourse11id, false, $basesetid, $childset12id, $childset21id, true);
        // Check source set items changes.
        $childset12record = $DB->get_record(program_set::TABLE, ['id' => $childset12id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $childset12record->parent);
        $this->assertEquals(1, $childset12record->sortorder);
        $programcourse13record = $DB->get_record(program_course::TABLE, ['id' => $programcourse13id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $programcourse13record->setid);
        $this->assertEquals(2, $programcourse13record->sortorder);
        // Check target set items.
        $programcourse11record = $DB->get_record(program_course::TABLE, ['id' => $programcourse11id], '*', MUST_EXIST);
        $this->assertEquals($childset12id, $programcourse11record->setid);
        $this->assertEquals(1, $programcourse11record->sortorder);
        $childset21record = $DB->get_record(program_set::TABLE, ['id' => $childset21id], '*', MUST_EXIST);
        $this->assertEquals($childset12id, $childset21record->parent);
        $this->assertEquals(2, $childset21record->sortorder);
        $programcourse22record = $DB->get_record(program_course::TABLE, ['id' => $programcourse22id], '*', MUST_EXIST);
        $this->assertEquals($childset12id, $programcourse22record->setid);
        $this->assertEquals(3, $programcourse22record->sortorder);

        /*
         * 1 Base set
         *      - 1 childset12 (re-ordered)
         *          - 1 course11 (moved to childset12, before childset21)
         *          - 2 childset21 (re-ordered)
         *          - 3 course22 (re-ordered)
         *      - 2 course13 (re-ordered)
         */

        // Move item to last position (different set).
        api::move_item_to_new_position($childset21id, true, $childset12id, $basesetid, 0, false);
        // Check source set items changes.
        $programcourse11record = $DB->get_record(program_course::TABLE, ['id' => $programcourse11id], '*', MUST_EXIST);
        $this->assertEquals($childset12id, $programcourse11record->setid);
        $this->assertEquals(1, $programcourse11record->sortorder);
        $programcourse22record = $DB->get_record(program_course::TABLE, ['id' => $programcourse22id], '*', MUST_EXIST);
        $this->assertEquals($childset12id, $programcourse22record->setid);
        $this->assertEquals(2, $programcourse22record->sortorder);
        // Check target set items changes.
        $childset12record = $DB->get_record(program_set::TABLE, ['id' => $childset12id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $childset12record->parent);
        $this->assertEquals(1, $childset12record->sortorder);
        $programcourse13record = $DB->get_record(program_course::TABLE, ['id' => $programcourse13id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $programcourse13record->setid);
        $this->assertEquals(2, $programcourse13record->sortorder);
        $childset21record = $DB->get_record(program_set::TABLE, ['id' => $childset21id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $childset21record->parent);
        $this->assertEquals(3, $childset21record->sortorder);

        /*
         * 1 Base set
         *      - 1 childset12
         *          - 1 course11
         *          - 2 course22 (re-ordered)
         *      - 2 course13
         *      - 3 childset21 (moved to base set, last position)
         */

        $sink = $this->redirectEvents();

        // Move item to empty set (should end being the first item in that set).
        api::move_item_to_new_position($childset12id, true, $basesetid, $childset21id, 0, false);
        // Check source set items changes.
        $programcourse13record = $DB->get_record(program_course::TABLE, ['id' => $programcourse13id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $programcourse13record->setid);
        $this->assertEquals(1, $programcourse13record->sortorder);
        $childset21record = $DB->get_record(program_set::TABLE, ['id' => $childset21id], '*', MUST_EXIST);
        $this->assertEquals($basesetid, $childset21record->parent);
        $this->assertEquals(2, $childset21record->sortorder);
        // Check target set items changes.
        $childset12record = $DB->get_record(program_set::TABLE, ['id' => $childset12id], '*', MUST_EXIST);
        $this->assertEquals($childset21id, $childset12record->parent);
        $this->assertEquals(1, $childset12record->sortorder);

        /*
         * 1 Base set
         *      - 1 course13 (re-ordered)
         *      - 2 childset21 (re-ordered)
         *          - 1 childset12 (moved to childset21)
         *              - 1 course11
         *              - 2 course22
         */

        // Check move item triggers program course and program set update events.
        $events = $sink->get_events();
        $sink->close();

        // Check program course updated event.
        $event = array_shift($events);
        $this->assertInstanceOf(program_course_updated::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($programcourse13record->id, $event->objectid);
        $this->assertEquals($basesetid, $event->other['setid']);
        $this->assertEquals($course13id, $event->other['courseid']);
        $this->assertEquals($programid, $event->other['programid']);

        // Test event get_name().
        $eventname = get_string('eventcourseupdated', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' updated " .
            "the course with id '" . $course13id . "' " .
            "within the program set '" . $basesetid . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/index.php');
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();

        // Check program set updated event.
        $event = array_shift($events);
        $this->assertInstanceOf(program_set_updated::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($childset21record->id, $event->objectid);
        $this->assertEquals($programid, $event->other['programid']);

        // Test event get_name().
        $eventname = get_string('eventsetupdated', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$USER->id' updated " .
            "the set with id '$childset21record->id'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $programid]);
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();

        // Check invalid move: Course already exists in the target set.
        try {
            api::move_item_to_new_position($programcourse11id, false, $basesetid, $childset12id, $childset21id, true);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // Check invalid move: Item does not belong to the provided source set.
        try {
            api::move_item_to_new_position($childset21id, true, $childset12id, $childset12id, 0, false);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // Check invalid move: Next item does not belong to the provided target set.
        try {
            api::move_item_to_new_position($programcourse11id, false, $childset12id, $basesetid, $programcourse11id, false);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        // Check passed "bad" params throw error.
        try {
            api::move_item_to_new_position(-2, true, 1, 1, 1, true);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }
        try {
            api::move_item_to_new_position(1, true, -2, 1, 1, true);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }
        try {
            api::move_item_to_new_position(1, true, 1, -2, 1, true);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }
        try {
            api::move_item_to_new_position(1, true, 1, 1, -2, true);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }
    }

    /**
     * Test is course in program api method.
     */
    public function test_is_course_in_program(): void {
        $course = self::getDataGenerator()->create_course();
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();
        $this->generator->add_course_to_set($course->id, $baseset->get('id'));

        $isinprogram = api::is_course_in_program($program, $course->id);

        $this->assertTrue($isinprogram);
    }

    /**
     * Test self enrol to course.
     */
    public function test_self_enrol_to_course(): void {
        global $DB;
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $course = self::getDataGenerator()->create_course();
        $program = $this->generator->generate_program();
        api::add_course_to_base_set($program->get('id'), $course->id);
        $this->generator->allocate_user_to_program($program->get('id'), $user->id);
        $sql = "SELECT e.*
                  FROM {enrol} e
            INNER JOIN {user_enrolments} ue
                    ON e.id = ue.enrolid
                 WHERE e.customint1 = :programid
                   AND e.courseid = :courseid
                   AND ue.userid = :userid
                   AND ue.status = :status
                   AND e.enrol = 'program' ";
        $params = ['programid' => $program->get('id'), 'courseid' => $course->id,
            'userid' => $user->id, 'status' => ENROL_USER_ACTIVE];

        // Test user enrol instance correctly created and active.
        api::self_enrol_to_course($course->id, $program->get('id'));
        $this->assertCount(1, $DB->get_records_sql($sql, $params));

        // Test self enrol after re-allocation (enrol instance re-activation).
        api::deallocate_user($program->get('id'), $user->id);
        $this->assertCount(0, $DB->get_records_sql($sql, $params));

        api::self_enrol_to_course($course->id, $program->get('id'));
        $this->assertCount(1, $DB->get_records_sql($sql, $params));
    }

    /**
     * Test update program visibility api method.
     */
    public function test_update_program_visibility(): void {
        global $DB;
        $program = $this->generator->generate_program((object) [
            'visible' => constants::VISIBILITY_HIDDEN
        ]);
        $programrecord = $program->to_record();
        $this->assertSame(constants::VISIBILITY_HIDDEN, (int) $programrecord->visible);

        api::update_program_visibility($program, constants::VISIBILITY_AVAILABLE);
        $programrecord = $program->to_record();
        $this->assertSame(constants::VISIBILITY_AVAILABLE, (int) $programrecord->visible);

        // When program gets hidden course enrolments are disabled in the program.
        $tenant = $this->tenantgenerator->create_tenant();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $program = $this->generator->generate_program((object) [
            'archived' => 0,
            'tenantid' => $tenant->id,
            'visible' => constants::VISIBILITY_AVAILABLE
        ]);
        $course = $this->generator->generate_course_with_completion_self();
        $coursecontext = context_course::instance($course->id);
        $baseset = $program->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser2);

        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user1->id);
        $this->assertCount(1, $enrolments);
        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user2->id);
        $this->assertCount(1, $enrolments);

        // Hide program.
        api::update_program_visibility($program, constants::VISIBILITY_HIDDEN);

        // Make sure that both user1 and user2 now have suspended enrolment in the course.
        $this->assertFalse(is_enrolled($coursecontext, $user1->id, '', true));
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', false));
        $this->assertFalse(is_enrolled($coursecontext, $user2->id, '', true));
        $this->assertTrue(is_enrolled($coursecontext, $user2->id, '', false));

        // Show the program.
        api::update_program_visibility($program, constants::VISIBILITY_AVAILABLE);

        // Make sure that both user1 and user2 now have active enrolment in the course.
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', true));
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', false));
        $this->assertTrue(is_enrolled($coursecontext, $user2->id, '', true));
        $this->assertTrue(is_enrolled($coursecontext, $user2->id, '', false));

        // Hide program to check program is not marked as completed for user.
        api::update_program_visibility($program, constants::VISIBILITY_HIDDEN);

        // As a user1 complete the course.
        $this->generator->complete_courses([$course->id], $user1->id);

        // Make sure program is NOT marked as completed for this user1.
        $this->assertFalse($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset->get('id'), 'userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset->get('id'), 'userid' => $user2->id]));

        // Show the program.
        api::update_program_visibility($program, constants::VISIBILITY_AVAILABLE);

        // As a user2 complete the course.
        $this->generator->complete_courses([$course->id], $user2->id);

        // Make sure program IS marked as completed for this user2.
        $this->assertTrue($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset->get('id'), 'userid' => $user2->id]));
    }

    /**
     * Test archive a program.
     *
     * @covers \tool_program\api::restore_program
     */
    public function test_archive_program(): void {
        global $DB;
        self::setAdminUser();

        $tenant = $this->tenantgenerator->create_tenant();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $program = $this->generator->generate_program((object) ['archived' => 0, 'tenantid' => $tenant->id]);
        $course = $this->generator->generate_course_with_completion_self();
        $coursecontext = context_course::instance($course->id);
        $baseset = $program->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser2);

        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user1->id);
        $this->assertCount(1, $enrolments);
        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user2->id);
        $this->assertCount(1, $enrolments);

        // Suspend program allocation of user2.
        $data = $programuser2->to_record();
        $data->status = constants::STATUS_SUSPENDED;
        api::update_program_user_dates_and_status($programuser2, $data);

        // Make sure that user1 has active enrolment in the course and user2 has suspended enrolment in the course.
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', true));
        $this->assertFalse(is_enrolled($coursecontext, $user2->id, '', true));
        $this->assertTrue(is_enrolled($coursecontext, $user2->id, '', false));

        // Archive the program.
        api::archive_program($program);

        $this->assertSame('1', $DB->get_field(program::TABLE, 'archived', ['id' => $program->get('id')]));

        // Make sure that both user1 and user2 now have suspended enrolment in the course.
        $this->assertFalse(is_enrolled($coursecontext, $user1->id, '', true));
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', false));
        $this->assertFalse(is_enrolled($coursecontext, $user2->id, '', true));
        $this->assertTrue(is_enrolled($coursecontext, $user2->id, '', false));

        // Restore the program.
        api::restore_program($program);

        // Make sure that user1 has active enrolment in the course and user2 has suspended enrolment in the course.
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', true));
        $this->assertFalse(is_enrolled($coursecontext, $user2->id, '', true));
        $this->assertTrue(is_enrolled($coursecontext, $user2->id, '', false));
    }

    /**
     * Test trigger event for archive a program.
     */
    public function test_archive_program_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program((object) ['archived' => 0]);

        $sink = $this->redirectEvents();
        api::archive_program($program);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_updated::class, $event);
        $eventdescription = "The user with id '$USER->id' has archived the program with id '" . $program->get('id') . "'.";
        $this->assertEquals($eventdescription, $event->get_description());
    }

    /**
     * Test restore a program
     */
    public function test_restore_program(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program((object) ['archived' => 1]);
        $programid = $program->get('id');

        api::restore_program($program);

        $this->assertSame('0', $DB->get_field(program::TABLE, 'archived', ['id' => $programid]));
    }

    /**
     * Test restore a program recalculates program progress for the allocated users.
     */
    public function test_restore_program_recalculates_program_progress_for_the_allocated_users(): void {
        self::setAdminUser();

        // Create course and complete it with a new user.
        $user = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $course = self::getDataGenerator()->create_course(['enablecompletion' => true]);
        $assign = self::getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);
        $cmassign = get_coursemodule_from_id('assign', $assign->cmid);
        $completion = new completion_info($course);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);
        $ccompletion = new completion_completion(['course' => $course->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();
        $completion = new completion_info($course);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user2->id);
        $ccompletion = new completion_completion(['course' => $course->id, 'userid' => $user2->id]);
        $ccompletion->mark_complete();

        // Create __archived__ program with the completed course.
        $program = $this->generator->generate_program((object)['archived' => 1]);
        $baseset = $program->get_base_set();
        api::add_course_to_parent_set($baseset->get('id'), $course->id);

        // Allocate user1 and user2 (while program is archived it should not trigger the set completions).
        api::allocate_user($program, (object) ['userid' => $user->id, 'certificationid' => 0]);
        api::allocate_user($program, (object) ['userid' => $user2->id, 'certificationid' => 0]);

        // Restore program and check that triggers program completion.
        $sink = $this->redirectEvents();
        api::restore_program($program);
        $events = $sink->get_events();
        $sink->close();

        $events = array_filter($events, static function($event) {
            return $event instanceof program_completed;
        });
        $this->assertNotEmpty($events);
        $this->assertCount(2, $events);
    }

    /**
     * Test trigger event for restore a program
     */
    public function test_restore_program_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $program = $this->generator->generate_program((object) ['archived' => 1]);

        $sink = $this->redirectEvents();
        api::restore_program($program);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_updated::class, $event);
        $eventdescription = "The user with id '$USER->id' has restored the program with id '" . $program->get('id') . "'.";
        $this->assertEquals($eventdescription, $event->get_description());
    }

    /**
     * Test duplicate program.
     */
    public function test_duplicate_program(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();
        $programid = $program->get('id');
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $course3 = self::getDataGenerator()->create_course();
        $childset11 = $this->generator->generate_set((object) [
            'name' => 'My child set 11',
            'programid' => $programid,
            'parent' => $baseset->get('id'),
            'sortorder' => 1,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ANY_ORDER,
            'completionatleast' => 1,
        ]);
        $childset12 = $this->generator->generate_set((object) [
            'name' => 'My child set 12',
            'programid' => $programid,
            'parent' => $baseset->get('id'),
            'sortorder' => 2,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
            'completionatleast' => 1,
        ]);
        $childset21 = $this->generator->generate_set((object) [
            'name' => 'My child set 21',
            'programid' => $programid,
            'parent' => $childset12->get('id'),
            'sortorder' => 1,
            'completioncriteria' => program_set::COMPLETION_AT_LEAST,
            'completionatleast' => 32,
        ]);
        $programcourse1 = $this->generator->add_course_to_set($course1->id, $baseset->get('id'), 3);
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $childset21->get('id'), 1);
        $programcourse3 = $this->generator->add_course_to_set($course3->id, $childset21->get('id'), 2);

        /*
         * Original program content structure:
         * 1 baseset
         *      1 My child set 11
         *      2 My child set 12
         *          1 My child test 21
         *              1 programcourse2/course2
         *              2 programcourse3/course3
         *      3 programcourse1/course1
         */

        $duplicatedprogram = api::duplicate_program($program);

        // Check duplicates program record data properly.
        $this->assertNotSame($program->get('id'), $duplicatedprogram->get('id'));
        $expectednewfullname = $program->get('fullname') . ' ' . get_string('copy', 'tool_program');
        $this->assertSame($expectednewfullname, $duplicatedprogram->get('fullname'));
        $this->assertSame($program->get('description'), $duplicatedprogram->get('description'));
        $this->assertSame($program->get('startdateabsolute'), $duplicatedprogram->get('startdateabsolute'));
        $this->assertSame($program->get('visible'), $duplicatedprogram->get('visible'));
        $this->assertSame($program->get('enddatetype'), $duplicatedprogram->get('enddatetype'));
        $this->assertSame($duplicatedprogram->get('idnumber'), '');

        // Check duplicated program has its own base set.
        $newbaseset = $duplicatedprogram->get_base_set();
        $this->assertInstanceOf(program_set::class, $newbaseset);
        $this->assertTrue($newbaseset->is_valid());

        // Check duplicated program duplicated the original content structure.

        // Check children of the base set.
        $containedsets = $newbaseset->get_subsets();
        $this->assertCount(2, $containedsets);

        $duplicatedchildset11 = $containedsets[1];
        $this->assertEquals($duplicatedprogram->get('id'), $duplicatedchildset11->get('programid'));
        $this->assertEquals($childset11->get('name'), $duplicatedchildset11->get('name'));
        $this->assertEquals($newbaseset->get('id'), $duplicatedchildset11->get('parent'));
        $this->assertEquals($childset11->get('sortorder'), $duplicatedchildset11->get('sortorder'));
        $this->assertEquals($childset11->get('completioncriteria'), $duplicatedchildset11->get('completioncriteria'));
        $this->assertEquals($childset11->get('completionatleast'), $duplicatedchildset11->get('completionatleast'));

        $duplicatedchildset12 = $containedsets[0];
        $this->assertEquals($duplicatedprogram->get('id'), $duplicatedchildset12->get('programid'));
        $this->assertEquals($childset12->get('name'), $duplicatedchildset12->get('name'));
        $this->assertEquals($newbaseset->get('id'), $duplicatedchildset12->get('parent'));
        $this->assertEquals($childset12->get('sortorder'), $duplicatedchildset12->get('sortorder'));
        $this->assertEquals($childset12->get('completioncriteria'), $duplicatedchildset12->get('completioncriteria'));
        $this->assertEquals($childset12->get('completionatleast'), $duplicatedchildset12->get('completionatleast'));

        $containedprogramcourses = $newbaseset->get_program_courses();
        $this->assertCount(1, $containedprogramcourses);
        $duplicatedprogramcourse1 = $containedprogramcourses[0];
        $this->assertEquals($newbaseset->get('id'), $duplicatedprogramcourse1->get('setid'));
        $this->assertEquals($course1->id, $duplicatedprogramcourse1->get('courseid'));
        $this->assertEquals($programcourse1->get('sortorder'), $duplicatedprogramcourse1->get('sortorder'));

        // Check children of duplicated child set 11.
        $containedsets = $duplicatedchildset11->get_subsets();
        $this->assertEmpty($containedsets);
        $containedprogramcourses = $duplicatedchildset11->get_program_courses();
        $this->assertEmpty($containedprogramcourses);

        // Check children of duplicated child set 12.
        $containedprogramcourses = $duplicatedchildset12->get_program_courses();
        $this->assertEmpty($containedprogramcourses);

        $containedsets = $duplicatedchildset12->get_subsets();
        $this->assertCount(1, $containedsets);
        $duplicatedchildset21 = $containedsets[0];
        $this->assertEquals($duplicatedprogram->get('id'), $duplicatedchildset21->get('programid'));
        $this->assertEquals($childset21->get('name'), $duplicatedchildset21->get('name'));
        $this->assertEquals($duplicatedchildset12->get('id'), $duplicatedchildset21->get('parent'));
        $this->assertEquals($childset21->get('sortorder'), $duplicatedchildset21->get('sortorder'));
        $this->assertEquals($childset21->get('completioncriteria'), $duplicatedchildset21->get('completioncriteria'));
        $this->assertEquals($childset21->get('completionatleast'), $duplicatedchildset21->get('completionatleast'));

        // Check children of duplicated child set 21.
        $containedsets = $duplicatedchildset21->get_subsets();
        $this->assertEmpty($containedsets);

        $containedprogramcourses = $duplicatedchildset21->get_program_courses();
        $this->assertCount(2, $containedprogramcourses);

        $duplicatedprogramcourse2 = $containedprogramcourses[0];
        $this->assertEquals($duplicatedchildset21->get('id'), $duplicatedprogramcourse2->get('setid'));
        $this->assertEquals($course2->id, $duplicatedprogramcourse2->get('courseid'));
        $this->assertEquals($programcourse2->get('sortorder'), $duplicatedprogramcourse2->get('sortorder'));

        $duplicatedprogramcourse3 = $containedprogramcourses[1];
        $this->assertEquals($duplicatedchildset21->get('id'), $duplicatedprogramcourse3->get('setid'));
        $this->assertEquals($course3->id, $duplicatedprogramcourse3->get('courseid'));
        $this->assertEquals($programcourse3->get('sortorder'), $duplicatedprogramcourse3->get('sortorder'));

        // Check that program tags have been copied from original program.
        $originaltags = core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $program->get('id'));
        $duplicatedtags = core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $duplicatedprogram->get('id'));
        $this->assertEqualsCanonicalizing($originaltags, $duplicatedtags);

        // TODO SP-85: check duplicated program copies dynamic rules.

        // TODO SP-85: check duplicated program copies image.
    }

    /**
     * Test trigger event for duplicate program.
     */
    public function test_duplicate_program_triggers_event(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();

        $sink = $this->redirectEvents();
        api::duplicate_program($program);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(program_created::class, $event);
    }

    /**
     * Test get user allocation name.
     */
    public function test_get_user_allocation_name(): void {
        $expectedmanualstr = get_string('manual', 'tool_program');
        $expecteddynamicstr = get_string('dynamic', 'tool_program');
        $expectedcertificationstr = get_string('certification', 'tool_program');

        $this->assertSame($expectedmanualstr, api::get_user_allocation_name(constants::ALLOCATION_MANUAL));
        $this->assertSame($expecteddynamicstr, api::get_user_allocation_name(constants::ALLOCATION_DYNAMIC));
        $this->assertSame($expectedcertificationstr, api::get_user_allocation_name(constants::ALLOCATION_CERTIFICATION));

        // Test throw error on passing a non existent allocation type.
        try {
            api::get_user_allocation_name(123456);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }
    }

    /**
     * Test is active allocation api method.
     */
    public function test_is_active_allocation(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $user = self::getDataGenerator()->create_user();
        $userid = $user->id;
        $programuser = $this->generator->allocate_user_to_program($programid, $userid);

        $isactive = api::is_active_allocation($programid, $userid);

        $this->assertTrue($isactive);

        // We disable user for this program.
        $programuser->set('status', '0');
        $programuser->update();
        $this->assertFalse(api::is_active_allocation($programid, $userid));

        // We enable user for this program.
        $programuser->set('status', '1');
        $programuser->update();
        $this->assertTrue(api::is_active_allocation($programid, $userid));

        // We set an allocation end date that surely has passed already.
        $programuser->set('enddate', 1); // 1 second after 1970.
        $programuser->update();
        $this->assertFalse(api::is_active_allocation($programid, $userid));

        // Test before end date, no start date set.
        $programuser->set('startdate', 0);
        $programuser->set('enddate', strtotime('+7 day'));
        $programuser->update();
        $this->assertTrue(api::is_active_allocation($programid, $userid));

        // Test after start date, no end date set.
        $programuser->set('startdate', strtotime('-7 day'));
        $programuser->set('enddate', 0);
        $programuser->update();
        $this->assertTrue(api::is_active_allocation($programid, $userid));

        // Test neither start date, nor end date set.
        $programuser->set('startdate', 0);
        $programuser->set('enddate', 0);
        $programuser->update();
        $this->assertTrue(api::is_active_allocation($programid, $userid));
    }

    /**
     * Test update program user dates and status.
     */
    public function test_update_program_user_dates_and_status(): void {
        global $DB;
        // Test status suspended and absolute program dates.
        $program = $this->generator->generate_program((object) [
            'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => strtotime('-7 day'),
            'startdaterelative' => null,
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => strtotime('+7 day'),
            'duedaterelative' => null,
            'enddatetype' => constants::DATE_ABSOLUTE,
            'enddateabsolute' => strtotime('+7 day'),
            'enddaterelative' => null,
        ]);

        $course = self::getDataGenerator()->create_course(['enablecompletion' => true]);
        $baseset = $program->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $user = self::getDataGenerator()->create_user();
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $user->id);
        $userallocationtimestamp = (int) $programuser->get('timecreated');
        $this->generator->enrol_user_to_program_course($programcourse, $programuser);

        // Test dates not overriden, suspended status.
        $unlockeddatesparams = (object) [
            'startdatelocked' => false,
            'startdate' => 11,
            'duedatelocked' => false,
            'duedate' => 21,
            'enddatelocked' => false,
            'enddate' => 31,
        ];
        $unlockeddatesparams->status = constants::STATUS_OVERRIDE_SUSPENDED;

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $this->assertSame(constants::STATUS_OVERRIDE_SUSPENDED, (int) $programuser->get('status'));
        $this->assertNotEquals(11, (int) $programuser->get('startdate'));
        $this->assertSame($program->get('startdateabsolute'), $programuser->get('startdate'));
        $this->assertNotEquals(21, (int) $programuser->get('duedate'));
        $this->assertSame($program->get('duedateabsolute'), $programuser->get('duedate'));
        $this->assertNotEquals(31, (int) $programuser->get('enddate'));
        $this->assertSame($program->get('enddateabsolute'), $programuser->get('enddate'));

        // Test status not suspended and program dates are set to "none" value.
        $unlockeddatesparams->status = constants::STATUS_OVERRIDE_DEFAULT;
        $program = $programuser->get_program();
        $program->set('startdatetype', constants::DATE_NONE);
        $program->set('duedatetype', constants::DATE_NONE);
        $program->set('enddatetype', constants::DATE_NONE);
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $this->assertSame(constants::STATUS_OVERRIDE_DEFAULT, (int) $programuser->get('status'));
        $this->assertSame(0, (int) $programuser->get('startdate'));
        $this->assertSame(0, (int) $programuser->get('duedate'));
        $this->assertSame(0, (int) $programuser->get('enddate'));

        // Test relative program dates.

        // Case start date relative to user allocation date.
        $program->set('startdatetype', constants::DATE_AFTER_USER_ALLOCATION);
        $program->set('startdaterelative', '5 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expectedusertstartdate = strtotime('+5 day', $userallocationtimestamp);
        $this->assertSame($expectedusertstartdate, (int) $programuser->get('startdate'));

        // Case start due date relative to start date.
        $program->set('duedatetype', constants::DATE_AFTER_START);
        $program->set('duedaterelative', '9 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserduedate = strtotime('+9 day', $expectedusertstartdate);
        $this->assertSame($expecteduserduedate, (int) $programuser->get('duedate'));

        // Case start due date relative to start date, but start date set to "none".
        $program->set('startdatetype', constants::DATE_NONE);
        $program->set('duedatetype', constants::DATE_AFTER_START);
        $program->set('duedaterelative', '6 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserduedate = 0;
        $this->assertSame($expecteduserduedate, (int) $programuser->get('duedate'));

        // Case due date relative to user allocation.
        $program->set('duedatetype', constants::DATE_AFTER_USER_ALLOCATION);
        $program->set('duedaterelative', '2 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expectedusertstartdate = strtotime('+2 day', $userallocationtimestamp);
        $this->assertSame($expectedusertstartdate, (int) $programuser->get('duedate'));

        // Case due date relative to end date, but end date set to "none".
        $program->set('enddatetype', constants::DATE_NONE);
        $program->set('duedatetype', constants::DATE_BEFORE_END);
        $program->set('duedaterelative', '5 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserduedate = 0;
        $this->assertSame($expecteduserduedate, (int) $programuser->get('duedate'));

        // Case due date relative to end date.
        $program->set('enddatetype', constants::DATE_ABSOLUTE);
        $now = time();
        $program->set('enddateabsolute', $now);
        $program->set('duedatetype', constants::DATE_BEFORE_END);
        $program->set('duedaterelative', '4 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserduedate = strtotime('-4 day', $now);
        $this->assertSame($expecteduserduedate, (int) $programuser->get('duedate'));

        // Case due date relative to end date, end date relative to start date, but start set to "none".
        $program->set('duedatetype', constants::DATE_BEFORE_END);
        $program->set('duedaterelative', '5 day');
        $program->set('enddatetype', constants::DATE_AFTER_START);
        $program->set('enddaterelative', '6 day');
        $program->set('startdatetype', constants::DATE_NONE);
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $this->assertSame(0, (int) $programuser->get('startdate'));
        $this->assertSame(0, (int) $programuser->get('duedate'));
        $this->assertSame(0, (int) $programuser->get('enddate'));

        // Case due date relative to end date, end date relative to start date.
        $program->set('duedatetype', constants::DATE_BEFORE_END);
        $program->set('duedaterelative', '5 day');
        $program->set('enddatetype', constants::DATE_AFTER_START);
        $program->set('enddaterelative', '6 day');
        $now = time();
        $program->set('startdatetype', constants::DATE_ABSOLUTE);
        $program->set('startdateabsolute', $now);
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expectedusertstartdate = $now;
        $expecteduserenddate = strtotime('+6 day', $expectedusertstartdate);
        $expecteduserduedate = strtotime('-5 day', $expecteduserenddate);
        $this->assertSame($expectedusertstartdate, (int) $programuser->get('startdate'));
        $this->assertSame($expecteduserduedate, (int) $programuser->get('duedate'));
        $this->assertSame($expecteduserenddate, (int) $programuser->get('enddate'));

        // Case due date relative to end date, end date relative to due date.
        $program->set('enddatetype', constants::DATE_AFTER_DUE);
        $program->set('enddaterelative', '3 day');
        $program->set('duedatetype', constants::DATE_BEFORE_END);
        $program->set('duedaterelative', '4 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserduedate = 0;
        $expecteduserenddate = 0;
        $this->assertSame($expecteduserduedate, (int) $programuser->get('duedate'));
        $this->assertSame($expecteduserenddate, (int) $programuser->get('enddate'));

        // Case due date relative to end date, end date relative to user allocation.
        $program->set('duedatetype', constants::DATE_BEFORE_END);
        $program->set('duedaterelative', '3 day');
        $program->set('enddatetype', constants::DATE_AFTER_USER_ALLOCATION);
        $program->set('enddaterelative', '5 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserenddate = strtotime('+5 day', $userallocationtimestamp);
        $expecteduserduedate = strtotime('-3 day', $expecteduserenddate);
        $this->assertSame($expecteduserduedate, (int) $programuser->get('duedate'));
        $this->assertSame($expecteduserenddate, (int) $programuser->get('enddate'));

        // Case end date relative to start date, but start date set to "none".
        $program->set('startdatetype', constants::DATE_NONE);
        $program->set('enddatetype', constants::DATE_AFTER_START);
        $program->set('enddaterelative', '3 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserduedate = 0;
        $this->assertSame($expecteduserduedate, (int) $programuser->get('enddate'));

        // Case end date relative to start date.
        $program->set('enddatetype', constants::DATE_AFTER_START);
        $program->set('enddaterelative', '3 day');
        $now = time();
        $program->set('startdatetype', constants::DATE_ABSOLUTE);
        $program->set('startdateabsolute', $now);
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserenddate = strtotime('+3 day', $now);
        $this->assertSame($expecteduserenddate, (int) $programuser->get('enddate'));

        // Case end date relative to user allocation.
        $program->set('enddatetype', constants::DATE_AFTER_USER_ALLOCATION);
        $program->set('enddaterelative', '3 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserenddate = strtotime('+3 day', $userallocationtimestamp);
        $this->assertSame($expecteduserenddate, (int) $programuser->get('enddate'));

        // Case end date relative to due date, but due date set to "none".
        $program->set('duedatetype', constants::DATE_NONE);
        $program->set('enddatetype', constants::DATE_AFTER_DUE);
        $program->set('enddaterelative', '4 day');
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserduedate = 0;
        $this->assertSame($expecteduserduedate, (int) $programuser->get('enddate'));

        // Case end date relative to due date.
        $program->set('enddatetype', constants::DATE_AFTER_DUE);
        $program->set('enddaterelative', '3 day');
        $now = time();
        $program->set('duedatetype', constants::DATE_ABSOLUTE);
        $program->set('duedateabsolute', $now);
        $program->update();

        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $expecteduserenddate = strtotime('+3 day', $now);
        $this->assertSame($expecteduserenddate, (int) $programuser->get('enddate'));

        // Case unkown type of start date.
        $program->set('startdatetype', -4);
        $program->set('duedatetype', constants::DATE_NONE);
        $program->set('enddatetype', constants::DATE_NONE);
        $program->update();
        try {
            api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }

        // Case unkown type of due date.
        $program->set('startdatetype', constants::DATE_NONE);
        $program->set('duedatetype', -4);
        $program->set('enddatetype', constants::DATE_NONE);
        $program->update();
        try {
            api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }

        // Case unkown type of end date.
        $program->set('startdatetype', constants::DATE_NONE);
        $program->set('duedatetype', constants::DATE_NONE);
        $program->set('enddatetype', -4);
        $program->update();
        try {
            api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }

        // Case unkown type of end date and due date set to be relative to end date.
        $program->set('startdatetype', constants::DATE_NONE);
        $program->set('duedatetype', constants::DATE_BEFORE_END);
        $program->set('enddatetype', -4);
        $program->update();
        try {
            api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }

        // Case overriden user dates.
        $program->set('startdatetype', constants::DATE_NONE);
        $program->set('duedatetype', constants::DATE_NONE);
        $program->set('enddatetype', constants::DATE_NONE);
        $program->update();

        api::update_program_user_dates_and_status($programuser, (object) [
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'startdatelocked' => true,
            'startdate' => 11,
            'duedatelocked' => true,
            'duedate' => 21,
            'enddatelocked' => true,
            'enddate' => 31,
        ]);

        $this->assertSame(11, (int) $programuser->get('startdate'));
        $this->assertSame(21, (int) $programuser->get('duedate'));
        $this->assertSame(31, (int) $programuser->get('enddate'));

        // Test changing the value of suspended status, updates the suspended timestamp.
        $programuser->set('status', constants::STATUS_OVERRIDE_DEFAULT);
        $programuser->set('timesuspended', $previousvalue = strtotime('-1 day'));
        $programuser->update();

        $unlockeddatesparams->status = constants::STATUS_OVERRIDE_SUSPENDED;
        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $this->assertGreaterThan($previousvalue, $currentvalue = $programuser->get('timesuspended'));

        // Test not changing the value of suspended status, does not update the suspended timestamp.
        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $this->assertEquals($currentvalue, $programuser->get('timesuspended'));

        // Test changing to not suspended status, does not update the suspended timestamp.
        $unlockeddatesparams->status = constants::STATUS_OVERRIDE_DEFAULT;
        api::update_program_user_dates_and_status($programuser, $unlockeddatesparams);

        $this->assertEquals($currentvalue, $programuser->get('timesuspended'));

        // Test user course enrolment dates.
        $userenrolment = $DB->get_record('user_enrolments', ['userid' => $user->id]);
        $this->assertEquals('11', $userenrolment->timestart);
        $this->assertEquals('31', $userenrolment->timeend);

        api::update_program_user_dates_and_status($programuser, (object) [
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'startdatelocked' => true,
            'startdate' => 1111,
            'duedatelocked' => true,
            'duedate' => 2111,
            'enddatelocked' => true,
            'enddate' => 3111,
        ]);

        $userenrolment = $DB->get_record('user_enrolments', ['userid' => $user->id]);
        $this->assertEquals('1111', $userenrolment->timestart);
        $this->assertEquals('3111', $userenrolment->timeend);
    }

    /**
     * Test get certifications by user id.
     */
    public function test_get_certifications_by_userid(): void {
        $user = self::getDataGenerator()->create_user();
        $certification1 = $this->certificationgenerator->generate_certification();
        $this->certificationgenerator->allocate_user($user->id, $certification1->get('id'));
        $certification2 = $this->certificationgenerator->generate_certification();
        $certification3 = $this->certificationgenerator->generate_certification();
        $this->certificationgenerator->allocate_user($user->id, $certification3->get('id'));

        $certifications = api::get_certifications_by_userid($user->id);

        $this->assertCount(2, $certifications);
        $this->assertArrayHasKey($certification1->get('id'), $certifications);
        $this->assertArrayNotHasKey($certification2->get('id'), $certifications);
        $this->assertArrayHasKey($certification3->get('id'), $certifications);
    }

    /**
     * Test calculate user program completions given a related course and a user.
     */
    public function test_calculate_programs_completion_by_courseid_and_userid(): void {
        global $DB, $CFG, $USER;
        $CFG->enablecompletion = true;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();
        $course = self::getDataGenerator()->create_course(['enablecompletion' => true]);
        $assign = self::getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $user = self::getDataGenerator()->create_user();
        // Allocate user to program.
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $user->id);
        // Enrol user to program course (start course).
        $this->generator->enrol_user_to_program_course($programcourse, $programuser);

        // Create another program with same course and user.
        $program2 = $this->generator->generate_program();
        $program2course = $this->generator->add_course_to_set($course->id, $program2->get_base_set()->get('id'));
        $program2user = $this->generator->allocate_user_to_program($program2->get('id'), $user->id);

        // Since base set only has one course and we have not completed, test the base set is not marked as completed.
        api::recalculate_program_progress_by_courseid_and_userid($course->id, $user->id);

        $params = ['userid' => $user->id, 'setid' => $baseset->get('id')];
        $setcompletions = $DB->get_records(program_set_completion::TABLE, $params);
        $this->assertCount(0, $setcompletions);

        // Test program2 program enrolment is created for the user.
        $enrolments = api::get_all_courses_actively_enrolled_with_enrol_program($program2->get('id'),
            $user->id, $course->id);
        $this->assertCount(1, $enrolments);

        $sink = $this->redirectEvents();

        // Complete course, then since base set has only this course, test that the base set (=program) is marked as completed.
        $cmassign = get_coursemodule_from_id('assign', $assign->cmid);
        $completion = new completion_info($course);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);
        $ccompletion = new completion_completion(['course' => $course->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();

        api::recalculate_program_progress_by_courseid_and_userid($course->id, $user->id);
        $events = $sink->get_events();
        $sink->close();

        // Check completion has been recorded.
        $setcompletions = $DB->get_records(program_set_completion::TABLE, $params);
        $this->assertCount(1, $setcompletions);
        $setcompletion = reset($setcompletions);
        $this->assertEquals($baseset->get('id'), $setcompletion->setid);

        // Check program completed event was triggered.
        $completedevents = array_values(array_filter($events, function($e) use ($program) {
            return $e->eventname === '\tool_program\event\program_completed' && $e->other['programid'] === $program->get('id');
        }));
        $event = $completedevents[0];
        $this->assertInstanceOf(program_completed::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($setcompletion->id, $event->objectid);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals($program->get('id'), $event->other['programid']);

        // Test event get_name().
        $eventname = get_string('eventprogramcompleted', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '" . $user->id . "' completed the program with id '" . $program->get('id') . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/index.php');
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();

        // Check program set completed event was triggered.
        $setcompletedevents = array_values(array_filter($events, function($e) use ($program) {
            return $e->eventname === '\tool_program\event\program_set_completed' && $e->other['programid'] === $program->get('id');
        }));
        $event = $setcompletedevents[0];
        $this->assertInstanceOf(program_set_completed::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($setcompletion->id, $event->objectid);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals($baseset->get('id'), $event->other['setid']);
        $this->assertEquals($program->get('id'), $event->other['programid']);

        // Test event get_name().
        $eventname = get_string('eventsetcompleted', 'tool_program');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '$user->id' completed " .
            "the set with id '" . $baseset->get('id') . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test get user groups in course.
     */
    public function test_get_user_groups_in_course(): void {
        // Create program with group auto-creation.
        $data = $this->generator->generate_program_filled_with_user_completion();
        // Create another tenant.
        $cat2 = $this->getDataGenerator()->create_category();
        $this->tenantgenerator->create_tenant(['categoryid' => $cat2->id]);
        // Test program without autocreategroups configuration returns nothing.
        $this->assertEmpty(api::get_user_groups_in_course($data->program->get('id'), $data->course1, $data->user->id));

        $data->program->set('autocreategroups', api::GROUPS_TENANT_PROGRAM);
        $data->program->update();

        $newuser = self::getDataGenerator()->create_user();
        // Test user without allocation returns nothing.
        $this->assertEmpty(api::get_user_groups_in_course($data->program->get('id'), $data->course1, $newuser->id));

        $this->generator->allocate_user_to_program($data->program->get('id'), $newuser->id);
        $usergroups = api::get_user_groups_in_course($data->program->get('id'), $data->course1, $newuser->id);
        // Test groupname includes tenant and program names.
        $this->assertEquals("Default tenant - A program name", groups_get_group($usergroups[0])->name);

        $data->program->set('autocreategroups', api::GROUPS_TENANT);
        $data->program->update();
        $usergroups = api::get_user_groups_in_course($data->program->get('id'), $data->course1, $newuser->id);
        // Test groupname includes tenant name.
        $this->assertEquals("Default tenant", groups_get_group($usergroups[0])->name);

        $data->program->set('autocreategroups', api::GROUPS_PROGRAM);
        $data->program->update();
        $usergroups = api::get_user_groups_in_course($data->program->get('id'), $data->course1, $newuser->id);
        // Test groupname includes program name.
        $this->assertEquals("A program name", groups_get_group($usergroups[0])->name);

        $usergroups = api::get_user_groups_in_course($data->program->get('id'), $data->course1, $newuser->id);
        $this->assertCount(1, $usergroups);

        // Add a certification course group.
        $certification = $this->certificationgenerator->generate_certification([
            'program' => $data->program->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);
        $certificationuser2a = $this->certificationgenerator->allocate_user($newuser->id, $certification->get('id'));
        $programusercert2a = \tool_program\persistent\program_user::get_record([
            'userid' => $newuser->id,
            'certificationid' => $certification->get('id'),
        ]);
        $programcourse = program_course::get_record(['courseid' => $data->course1->id]);
        $this->generator->enrol_user_to_program_course($programcourse, $programusercert2a);

        $usergroups = api::get_user_groups_in_course($data->program->get('id'), $data->course1, $newuser->id);
        $this->assertCount(2, $usergroups);

        $certification->set('archived', 1);
        $certification->update();

        // Certification is archived and get_user_groups_in_course should only return the program group.
        $usergroups = api::get_user_groups_in_course($data->program->get('id'), $data->course1, $newuser->id);
        $this->assertCount(1, $usergroups);
    }

    /**
     * Test get programs by courseid and userid
     */
    public function test_get_programs_by_courseid_and_userid(): void {
        $course = self::getDataGenerator()->create_course();
        $program1 = $this->generator->generate_program();
        $baseset1 = $program1->get_base_set();
        $this->generator->add_course_to_set($course->id, $baseset1->get('id'));
        $program2 = $this->generator->generate_program();
        $baseset2 = $program2->get_base_set();
        $this->generator->add_course_to_set($course->id, $baseset2->get('id'));
        $user1 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $user2 = self::getDataGenerator()->create_user();

        $programs = api::get_programs_by_courseid_and_userid($course->id, $user1->id);

        // User1 was allocated to program1 only.
        $this->assertCount(1, $programs);
        $this->assertArrayHasKey($program1->get('id'), $programs);
        $this->assertArrayNotHasKey($program2->get('id'), $programs);

        $programs = api::get_programs_by_courseid_and_userid($course->id, $user2->id);

        // User2 was not allocated to any program.
        $this->assertCount(0, $programs);
        $this->assertArrayNotHasKey($program1->get('id'), $programs);
        $this->assertArrayNotHasKey($program2->get('id'), $programs);
    }

    /**
     * Test get user allocation statuses.
     */
    public function test_get_user_allocation_statuses(): void {
        $user = self::getDataGenerator()->create_user();
        $program = $this->generator->generate_program_with_course();
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $user->id);

        // When program completed, completed must be one of the statuses returned.
        $this->generator->complete_program($program, $user->id);

        $statuses = api::get_user_allocation_statuses($program->get('id'), $user->id, 0);

        $this->assertCount(1, $statuses);
        $this->assertContains('program_user_status_completed', array_column($statuses, 'status'));

        // If status is overriden as suspended, suspended must be one of the statuses returned.
        $programuser->set('status', constants::STATUS_OVERRIDE_SUSPENDED);
        $programuser->update();

        $statuses = api::get_user_allocation_statuses($program->get('id'), $user->id, 0);

        $this->assertCount(2, $statuses);
        $this->assertContains('program_user_status_suspended', array_column($statuses, 'status'));
        $this->assertContains('program_user_status_completed', array_column($statuses, 'status'));

        // If neither completed, not suspended and...
        $program = $this->generator->generate_program();
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $user->id);

        // ...If start date in future, future allocation must be returned.
        $programuser->set('startdate', strtotime('+7 day'));
        $programuser->update();

        $statuses = api::get_user_allocation_statuses($program->get('id'), $user->id, 0);

        $this->assertCount(1, $statuses);
        $this->assertContains('program_user_status_futureallocation', array_column($statuses, 'status'));

        // ...If due date in the past, overdue must be returned.
        $programuser->set('startdate', strtotime('-7 day'));
        $programuser->set('duedate', strtotime('-5 day'));
        $programuser->update();

        $statuses = api::get_user_allocation_statuses($program->get('id'), $user->id, 0);

        $this->assertCount(1, $statuses);
        $this->assertContains('program_user_status_overdue', array_column($statuses, 'status'));

        // If within start and due date (or dates not set), open must be returned.
        $programuser->set('startdate', 0);
        $programuser->set('duedate', 0);
        $programuser->update();

        $statuses = api::get_user_allocation_statuses($program->get('id'), $user->id, 0);

        $this->assertCount(1, $statuses);
        $this->assertContains('program_user_status_open', array_column($statuses, 'status'));

        $programuser->set('startdate', strtotime('-7 day'));
        $programuser->set('duedate', 0);
        $programuser->update();

        $statuses = api::get_user_allocation_statuses($program->get('id'), $user->id, 0);

        $this->assertCount(1, $statuses);
        $this->assertContains('program_user_status_open', array_column($statuses, 'status'));

        $programuser->set('startdate', 0);
        $programuser->set('duedate', strtotime('+7 day'));
        $programuser->update();

        $statuses = api::get_user_allocation_statuses($program->get('id'), $user->id, 0);

        $this->assertCount(1, $statuses);
        $this->assertContains('program_user_status_open', array_column($statuses, 'status'));

        $programuser->set('startdate', strtotime('-7 day'));
        $programuser->set('duedate', strtotime('+7 day'));
        $programuser->update();

        $statuses = api::get_user_allocation_statuses($program->get('id'), $user->id, 0);

        $this->assertCount(1, $statuses);
        $this->assertContains('program_user_status_open', array_column($statuses, 'status'));
    }

    /**
     * Test get user accessible programs.
     */
    public function test_get_user_accessible_programs(): void {
        $user = self::getDataGenerator()->create_user();
        $defaulttenantid = tenancy::get_tenant_id($user->id);
        $tenant2 = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $defaulttenantid]);
        $program2 = $this->generator->generate_program((object)['tenantid' => $defaulttenantid]);
        $program3 = $this->generator->generate_program((object)['tenantid' => $defaulttenantid]);
        $program4 = $this->generator->generate_program((object)['tenantid' => $defaulttenantid]);
        $program5 = $this->generator->generate_program((object)['tenantid' => $defaulttenantid]);
        $this->generator->allocate_user_to_program($program1->get('id'), $user->id);
        $this->generator->allocate_user_to_program($program2->get('id'), $user->id);
        $programuser3 = $this->generator->allocate_user_to_program($program3->get('id'), $user->id);
        $this->generator->allocate_user_to_program($program4->get('id'), $user->id);
        $certification5 = $this->certificationgenerator->generate_certification();
        $this->generator->allocate_user_to_program($program5->get('id'), $user->id, $certification5->get('id'));

        $accessibleprograms = api::get_user_accessible_programs($user->id);

        // User was allocated to all programs, then it can access all of them.
        $this->assertCount(5, $accessibleprograms);
        $this->assertArrayHasKey($program1->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program2->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program3->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program4->get('id'), $accessibleprograms);

        // Archive program1, then it should not be accessible.
        $program1->set('archived', true);
        $program1->update();

        $accessibleprograms = api::get_user_accessible_programs($user->id);

        $this->assertCount(4, $accessibleprograms);
        $this->assertArrayNotHasKey($program1->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program2->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program3->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program4->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program5->get('id'), $accessibleprograms);

        // Hide program2, then it should not be accessible.
        $program2->set('visible', constants::VISIBILITY_HIDDEN);
        $program2->update();

        $accessibleprograms = api::get_user_accessible_programs($user->id);

        $this->assertCount(3, $accessibleprograms);
        $this->assertArrayNotHasKey($program1->get('id'), $accessibleprograms);
        $this->assertArrayNotHasKey($program2->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program3->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program4->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program5->get('id'), $accessibleprograms);

        // Suspend user allocation to program3, then program3 should not be accessible.
        $programuser3->set('status', constants::STATUS_SUSPENDED);
        $programuser3->update();

        $accessibleprograms = api::get_user_accessible_programs($user->id);

        $this->assertCount(2, $accessibleprograms);
        $this->assertArrayNotHasKey($program1->get('id'), $accessibleprograms);
        $this->assertArrayNotHasKey($program2->get('id'), $accessibleprograms);
        $this->assertArrayNotHasKey($program3->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program4->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program5->get('id'), $accessibleprograms);

        // If program4 was from another tenant, then program4 should still be accesible from the Learning tab.
        $program4->set('tenantid', $tenant2->id);
        $program4->update();

        $accessibleprograms = api::get_user_accessible_programs($user->id);

        $this->assertCount(2, $accessibleprograms);
        $this->assertArrayNotHasKey($program1->get('id'), $accessibleprograms);
        $this->assertArrayNotHasKey($program2->get('id'), $accessibleprograms);
        $this->assertArrayNotHasKey($program3->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program4->get('id'), $accessibleprograms);
        $this->assertArrayHasKey($program5->get('id'), $accessibleprograms);

        // Archive certification5, then program5 should not be accessible since we only had an allocation from that certification.
        $certification5->set('archived', true);
        $certification5->update();

        $accessibleprograms = api::get_user_accessible_programs($user->id);

        $this->assertCount(1, $accessibleprograms);
    }

    /**
     * Test get unlocked courses ids.
     */
    public function test_get_unlocked_courses_ids(): void {
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();
        // Items within the base set.
        $course11 = self::getDataGenerator()->create_course();
        $this->generator->add_course_to_set($course11->id, $baseset->get('id'), 1);
        $childset12 = $this->generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'sortorder' => 2,
        ]);
        $course13 = self::getDataGenerator()->create_course();
        $this->generator->add_course_to_set($course13->id, $baseset->get('id'), 3);
        $this->generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $childset12->get('id'),
            'sortorder' => 1,
        ]);
        $course22 = self::getDataGenerator()->create_course();
        $this->generator->add_course_to_set($course22->id, $childset12->get('id'), 2);
        $user = self::getDataGenerator()->create_user();
        $this->generator->allocate_user_to_program($program->get('id'), $user->id);

        /*
         *                          Completion      Locked
         * Structure                criteria        status
         * * * * * * * * * * * * * * * * * * * * * * * * * * *
         * 1 Base set               All in order    Unlocked
         *      - 1 course11        -               Unlocked
         *      - 2 childset12      All in order    Locked
         *          - 1 childset21  -               Locked
         *          - 2 course22    -               Locked
         *      - 3 course13        -               Locked
         */

        // Test course11 is unlocked in the initial situation (see above).
        $unlockedcoursesids = api::get_unlocked_courses_ids($program, $user->id);
        $this->assertCount(1, $unlockedcoursesids);
        $this->assertContains((int) $course11->id, $unlockedcoursesids);

        // Change base set completion criteria.
        $baseset->set('completioncriteria', program_set::COMPLETION_ALL_IN_ANY_ORDER);
        $baseset->update();

        /*
         *                          Completion      Locked
         * Structure                criteria        status
         * * * * * * * * * * * * * * * * * * * * * * * * * * *
         * 1 Base set               Any order       Unlocked
         *      - 1 course11        -               Unlocked
         *      - 2 childset12      All in order    Unlocked
         *          - 1 childset21  -               Unlocked
         *          - 2 course22    -               Locked
         *      - 3 course13        -               Unlocked
         */

        // Test course11 and course13 are unlocked in the new situation (see above).
        $unlockedcoursesids = api::get_unlocked_courses_ids($program, $user->id);
        $this->assertCount(2, $unlockedcoursesids);
        $this->assertContains((int) $course11->id, $unlockedcoursesids);
        $this->assertContains((int) $course13->id, $unlockedcoursesids);

        // Change child set completion criteria.
        $childset12->set('completioncriteria', program_set::COMPLETION_ALL_IN_ANY_ORDER);
        $childset12->update();

        /*
         *                          Completion      Locked
         * Structure                criteria        status
         * * * * * * * * * * * * * * * * * * * * * * * * * * *
         * 1 Base set               Any order       Unlocked
         *      - 1 course11        -               Unlocked
         *      - 2 childset12      Any order       Unlocked
         *          - 1 childset21  -               Unlocked
         *          - 2 course22    -               Unlocked
         *      - 3 course13        -               Unlocked
         */

        // Test course11, course13 and course22 are unlocked in the new situation (see above).
        $unlockedcoursesids = api::get_unlocked_courses_ids($program, $user->id);
        $this->assertCount(3, $unlockedcoursesids);
        $this->assertContains((int) $course11->id, $unlockedcoursesids);
        $this->assertContains((int) $course13->id, $unlockedcoursesids);
        $this->assertContains((int) $course22->id, $unlockedcoursesids);
    }

    /**
     * Test is allocation window open.
     */
    public function test_is_allocation_window_open(): void {
        $program = $this->generator->generate_program();

        // No dates set, then window should be open.
        $program->set('allocationstartdatetype', constants::DATE_NONE);
        $program->set('allocationenddatetype', constants::DATE_NONE);
        $program->update();
        $this->assertTrue(api::is_allocation_window_open($program));

        // Start date set and in the future, then window should be closed.
        $program->set('allocationstartdatetype', constants::DATE_ABSOLUTE);
        $program->set('allocationstartdateabsolute', strtotime('+7 day'));
        $program->update();
        $this->assertFalse(api::is_allocation_window_open($program));

        // Start date set and in the past, then window should be open.
        $program->set('allocationstartdateabsolute', strtotime('-7 day'));
        $program->update();
        $this->assertTrue(api::is_allocation_window_open($program));

        // End date set and in the past, then window should be closed.
        $program->set('allocationenddatetype', constants::DATE_ABSOLUTE);
        $program->set('allocationenddateabsolute', strtotime('-7 day'));
        $program->update();
        $this->assertFalse(api::is_allocation_window_open($program));

        // End date set and in the future, then window should be open.
        $program->set('allocationenddatetype', constants::DATE_ABSOLUTE);
        $program->set('allocationenddateabsolute', strtotime('+7 day'));
        $program->update();
        $this->assertTrue(api::is_allocation_window_open($program));

        // Keep end date set in the future, no start date, then window should be open.
        $program->set('allocationstartdatetype', constants::DATE_NONE);
        $program->update();
        $this->assertTrue(api::is_allocation_window_open($program));

        // End date set relative to start date, no start date, then window should be open.
        $program->set('allocationenddatetype', constants::DATE_AFTER_ALLOCATION_STARTS);
        $program->set('allocationenddaterelative', '+7 day');
        $program->update();
        $this->assertTrue(api::is_allocation_window_open($program));

        // Set start date in the past, end date relative to start date resulting in the past, then window should be closed.
        $program->set('allocationstartdatetype', constants::DATE_ABSOLUTE);
        $program->set('allocationstartdateabsolute', strtotime('-7 day'));
        $program->set('allocationenddatetype', constants::DATE_AFTER_ALLOCATION_STARTS);
        $program->set('allocationenddaterelative', '+6 day');
        $program->update();
        $this->assertFalse(api::is_allocation_window_open($program));

        // Set start date in the past, end date relative to start date resulting in the future, then window should be open.
        $program->set('allocationstartdatetype', constants::DATE_ABSOLUTE);
        $program->set('allocationstartdateabsolute', strtotime('-7 day'));
        $program->set('allocationenddatetype', constants::DATE_AFTER_ALLOCATION_STARTS);
        $program->set('allocationenddaterelative', '+8 day');
        $program->update();
        $this->assertTrue(api::is_allocation_window_open($program));
    }

    /**
     * Test api method get_course_ids_with_only_enrol_program_instance.
     */
    public function test_get_course_ids_with_only_enrol_program_instance(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $course1 = self::getDataGenerator()->create_course();
        $courseid1 = $course1->id;
        $course2 = self::getDataGenerator()->create_course();
        $courseid2 = $course2->id;
        $programcourse1 = $this->generator->add_course_to_set($courseid1, $basesetid);
        $programcourse2 = $this->generator->add_course_to_set($courseid2, $basesetid);
        $user = self::getDataGenerator()->create_user();
        $userid = $user->id;
        $programuser = $this->generator->allocate_user_to_program($programid, $userid);
        $this->generator->enrol_user_to_program_course($programcourse1, $programuser);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser);

        // Test empty result with theme_workplace/hideprogramcourses setting disabled.
        set_config('hideprogramcourses', 0, 'theme_workplace');
        $courseids = api::get_course_ids_with_only_enrol_program_instance($userid);
        $this->assertEmpty($courseids);

        set_config('hideprogramcourses', 1, 'theme_workplace');
        $courseids = api::get_course_ids_with_only_enrol_program_instance($userid);

        // Test when user has two instances of enrol program related to different courses.
        $this->assertContains($courseid1, $courseids);
        $this->assertContains($courseid2, $courseids);

        // Test user has two instances of enrol program and one instance of another kind related to one of the courses.
        self::getDataGenerator()->enrol_user($userid, $courseid1);

        $courseids = api::get_course_ids_with_only_enrol_program_instance($userid);

        $this->assertNotContains($courseid1, $courseids);
        $this->assertContains($courseid2, $courseids);
    }

    /**
     * Test add default dynamicrule conditions to program.
     *
     * @covers \tool_program\api::add_default_dynamicrule_conditions_to_program
     * @uses \tool_program\api::create_program
     */
    public function test_add_default_dynamicrule_conditions_to_program(): void {
        global $DB;
        self::setAdminUser();

        // No rules.
        $rules = $DB->get_records('tool_dynamicrule');
        $this->assertCount(0, $rules);

        // Create program.
        $programdata = $this->generator->get_dummy_program_data();
        $programdata->tenantid = tenancy::get_tenant_id();
        $program = api::create_program($programdata);

        $params = [
            'component' => 'tool_program',
            'componentarea' => 'program',
            'itemid' => $program->get('id'),
        ];

        // Check this created relevant rules.
        $rulerecords = $DB->get_records('tool_dynamicrule', $params);
        $this->assertCount(6, $rulerecords);
        foreach ($rulerecords as $rulerecord) {
            $this->assertEquals($program->get('tenantid'), $rulerecord->tenantid);
            $conditions = $DB->get_records('tool_dynamicrule_condition', ['ruleid' => $rulerecord->id]);
            $this->assertCount(1, $conditions);
        }
    }

    /**
     * Test get program pattern.
     */
    public function test_get_program_pattern(): void {
        $program = $this->generator->generate_program();

        $svgdatauri = api::get_program_pattern($program->get('id'));

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $svgdatauri);
    }

    /**
     * Test belongs to non archived certification.
     */
    public function test_belongs_to_non_archived_certification(): void {
        $tenant1 = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object) [
            'tenantid' => $tenant1->id,
        ]);
        $program2 = $this->generator->generate_program((object) [
            'tenantid' => $tenant1->id,
        ]);
        $this->certificationgenerator->generate_certification([
            'tenantid' => $tenant1->id,
            'program' => $program1->get('id')
        ]);
        $this->certificationgenerator->generate_certification([
            'tenantid' => $tenant1->id,
            'program' => $program2->get('id'),
            'archived' => 1,
            'timearchived' => time(),
        ]);

        $this->assertTrue(api::belongs_to_non_archived_certification($program1));
        $this->assertFalse(api::belongs_to_non_archived_certification($program2));
    }

    /**
     * Test program exists in tenant
     */
    public function test_program_exists_in_tenant(): void {
        $tenant1 = $this->tenantgenerator->create_tenant();
        $tenant2 = $this->tenantgenerator->create_tenant();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant1->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant2->id);

        self::setUser($user1);
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant1->id]);

        $res = api::program_exists_in_tenant($program1->get('id'));
        $this->assertTrue($res);
        $res = api::program_exists_in_tenant($program1->get('id'), $user1->id);
        $this->assertTrue($res);
        $res = api::program_exists_in_tenant($program1->get('id'), $user2->id);
        $this->assertFalse($res);
        self::setUser($user2);
        $res = api::program_exists_in_tenant($program1->get('id'));
        $this->assertFalse($res);
    }

    /**
     * Test get programs in tenant fieldset
     */
    public function test_get_programs_in_tenant_fieldset(): void {
        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        $othertenant = $this->tenantgenerator->create_tenant();
        self::setUser($user);

        // Default tenant (where admin belongs to) has no programs.
        $res = api::get_programs_in_tenant_fieldset($user->id);
        $this->assertEmpty($res);

        $programdata = $this->generator->get_dummy_program_data();
        $program1 = $this->generator->generate_program($programdata);
        $this->generator->allocate_user_to_program($program1->get('id'), $user->id);
        $programdata->fullname = 'Program number two';
        $program2 = $this->generator->generate_program($programdata);
        $this->generator->allocate_user_to_program($program2->get('id'), $user->id);
        $programdata->fullname = 'Program number three';
        $program3 = $this->generator->generate_program($programdata);
        $this->generator->allocate_user_to_program($program3->get('id'), $user->id);
        // Create a program in another tenant and allocate the same user to it.
        $programdata->fullname = 'Program number four';
        $programdata->tenantid = $othertenant->id;
        $program4 = $this->generator->generate_program($programdata);
        $this->generator->allocate_user_to_program($program4->get('id'), $user->id);
        $this->generator->assign_edit_capability($user->id, context_system::instance());

        $res = api::get_programs_in_tenant_fieldset($user->id);
        $this->assertNotEmpty($res);
        $this->assertCount(3, $res);
        $this->assertArrayHasKey($program1->get('id'), $res);
        $this->assertArrayHasKey($program2->get('id'), $res);
        $this->assertArrayHasKey($program3->get('id'), $res);
        $this->assertArrayNotHasKey($program4->get('id'), $res);
        $this->assertEquals($program1->get('fullname'), $res[$program1->get('id')]);
        $this->assertEquals($program2->get('fullname'), $res[$program2->get('id')]);
        $this->assertEquals($program3->get('fullname'), $res[$program3->get('id')]);

        // Test returns also shared programs from parent tenants.
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $programdata = ['tenantid' => $sharedspaceid, 'shared' => 1] + (array)$this->generator->get_dummy_program_data();
        $sharedprogram = api::create_program((object)$programdata);
        $res = api::get_programs_in_tenant_fieldset($user->id);
        $this->assertCount(4, $res);
        $this->assertEquals($sharedprogram->get('fullname'), $res[$sharedprogram->get('id')]);
    }

    /**
     * Test get program statuses fieldset
     */
    public function test_get_program_statuses_fieldset(): void {
        $statuses = [
            constants::STATUS_SUSPENDED => get_string('suspended', 'tool_program'),
            constants::STATUS_COMPLETED => get_string('completed', 'tool_program'),
            constants::STATUS_FUTUREALLOCATION => get_string('futureallocation', 'tool_program'),
            constants::STATUS_OVERDUE => get_string('overdue', 'tool_program'),
            constants::STATUS_OPEN => get_string('open', 'tool_program'),
        ];

        $res = api::get_program_statuses_fieldset();
        $this->assertNotEmpty($res);
        $this->assertEquals($statuses, $res);
    }

    /**
     * Test get status sql join.
     */
    public function test_get_status_sql_join(): void {
        // Table aliases.
        $pu = 'pu';
        $u = 'u';
        $p = 'p';
        $ps = 'ps';
        $psc = 'psc';
        // Named param for program id field.
        $pid = 'pid';

        $nonspecificprogramjoin = api::get_status_sql_join($u, $pu, $p, $ps, $psc);

        $this->assertNotFalse(strpos($nonspecificprogramjoin, 'INNER JOIN {' . program_user::TABLE . '} ' . $pu));
        $this->assertNotFalse(strpos($nonspecificprogramjoin, 'INNER JOIN {' . program::TABLE . '} ' . $p));
        $this->assertNotFalse(strpos($nonspecificprogramjoin, 'INNER JOIN {' . program_set::TABLE . '} ' . $ps));
        $this->assertNotFalse(strpos($nonspecificprogramjoin, 'LEFT JOIN {' . program_set_completion::TABLE . '} ' . $psc));
        $this->assertFalse(strpos($nonspecificprogramjoin, $pu . 'programid = :' . $pid));

        $specificprogramjoin = api::get_status_sql_join($u, $pu, $p, $ps, $psc, $pid);

        $this->assertNotFalse(strpos($specificprogramjoin, 'INNER JOIN {' . program_user::TABLE . '} ' . $pu));
        $this->assertNotFalse(strpos($specificprogramjoin, 'INNER JOIN {' . program::TABLE . '} ' . $p));
        $this->assertNotFalse(strpos($specificprogramjoin, 'INNER JOIN {' . program_set::TABLE . '} ' . $ps));
        $this->assertNotFalse(strpos($specificprogramjoin, 'LEFT JOIN {' . program_set_completion::TABLE . '} ' . $psc));
        $this->assertNotFalse(strpos($specificprogramjoin, 'programid = :' . $pid));
    }

    /**
     * Test get status sql cases.
     */
    public function test_get_status_sql_cases(): void {
        // Table aliases.
        $pu = 'pu';
        $psc = 'psc';
        // Possible status values.
        $suspended = constants::STATUS_SUSPENDED;
        $completed = constants::STATUS_COMPLETED;
        $futureallocation = constants::STATUS_FUTUREALLOCATION;
        $overdue = constants::STATUS_OVERDUE;
        $open = constants::STATUS_OPEN;
        $other = constants::STATUS_UNKNOWN;

        $cases = api::get_status_sql_cases($completed, $pu, $psc);

        $this->assertNotFalse(strpos($cases, 'CASE'));
        $this->assertNotFalse(strpos($cases, 'WHEN'));
        $this->assertNotFalse(strpos($cases, 'END'));
        $this->assertNotFalse(strpos($cases, 'THEN ' . $suspended));
        $this->assertNotFalse(strpos($cases, 'THEN ' . $completed));
        $this->assertNotFalse(strpos($cases, 'THEN ' . $futureallocation));
        $this->assertNotFalse(strpos($cases, 'THEN ' . $overdue));
        $this->assertNotFalse(strpos($cases, 'THEN ' . $open));
        $this->assertNotFalse(strpos($cases, 'ELSE ' . $other));
    }

    /**
     * Test get program status sql query.
     */
    public function test_get_program_status_sql_query(): void {
        $program = $this->generator->generate_program();
        $programid = $program->get('id');
        // Table aliases.
        $pu = 'pu';
        $u = 'u';
        $p = 'p';
        $ps = 'ps';
        $psc = 'psc';
        // Named param for program id field.
        $pid = 'pid';

        // Check equals to status completed.
        $status = constants::STATUS_COMPLETED;
        $isnegated = false;

        [, $where, $params] = api::get_program_status_sql_query($programid, $status, $isnegated, $u, $pu, $p, $ps, $psc, $pid);

        $this->assertNotFalse(strpos($where, ' = ' . $status));
        $this->assertArrayHasKey($pid, $params);
        $this->assertContains($programid, $params);

        // Check not equal to status overdue.
        $status = constants::STATUS_OVERDUE;
        $isnegated = true;

        [, $where, $params] = api::get_program_status_sql_query($programid, $status, $isnegated, $u, $pu, $p, $ps, $psc, $pid);

        $this->assertNotFalse(strpos($where, ' <> ' . $status));
        $this->assertArrayHasKey($pid, $params);
        $this->assertContains($programid, $params);
    }

    /**
     * Test get programs by status and userid.
     */
    public function test_get_programs_by_status_and_userid(): void {
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $tenant1 = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($user1->id, $tenant1->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant1->id);
        $this->tenantgenerator->allocate_user($user3->id, $tenant1->id);
        $program1 = $this->generator->generate_program_with_course((object) ['tenantid' => $tenant1->id]);
        $program2 = $this->generator->generate_program_with_course((object) ['tenantid' => $tenant1->id]);
        $program3 = $this->generator->generate_program_with_course((object) ['tenantid' => $tenant1->id]);
        $programuser11 = $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $programuser12 = $this->generator->allocate_user_to_program($program2->get('id'), $user1->id);
        $programuser13 = $this->generator->allocate_user_to_program($program3->get('id'), $user1->id);
        $programuser21 = $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);
        $programuser22 = $this->generator->allocate_user_to_program($program2->get('id'), $user2->id);
        $programuser23 = $this->generator->allocate_user_to_program($program3->get('id'), $user2->id);
        $programuser31 = $this->generator->allocate_user_to_program($program1->get('id'), $user3->id);
        $programuser32 = $this->generator->allocate_user_to_program($program2->get('id'), $user3->id);
        $programuser33 = $this->generator->allocate_user_to_program($program3->get('id'), $user3->id);

        // Mark user1 as suspended in program1 and program2, mark user3 as suspended in program3.
        $programuser11->set('status', constants::STATUS_OVERRIDE_SUSPENDED);
        $programuser11->update();
        $programuser12->set('status', constants::STATUS_OVERRIDE_SUSPENDED);
        $programuser12->update();
        $programuser33->set('status', constants::STATUS_OVERRIDE_SUSPENDED);
        $programuser33->update();

        // Check get programs by suspended status.
        $status = constants::STATUS_SUSPENDED;

        $programs = api::get_programs_by_status_and_userid($status, $user1->id);
        $this->assertCount(2, $programs);
        $this->assertArrayHasKey($program1->get('id'), $programs);
        $this->assertArrayHasKey($program2->get('id'), $programs);

        $programs = api::get_programs_by_status_and_userid($status, $user2->id);
        $this->assertCount(0, $programs);

        $programs = api::get_programs_by_status_and_userid($status, $user3->id);
        $this->assertCount(1, $programs);
        $this->assertArrayHasKey($program3->get('id'), $programs);

        // Mark user2 as completed program1 and program3, mark user3 as completed program2.
        $this->generator->complete_program($program1, $user2->id);
        $this->generator->complete_program($program3, $user2->id);
        $this->generator->complete_program($program2, $user3->id);

        // Check get programs by completed status.
        $status = constants::STATUS_COMPLETED;

        $programs = api::get_programs_by_status_and_userid($status, $user1->id);
        $this->assertCount(0, $programs);

        $programs = api::get_programs_by_status_and_userid($status, $user2->id);
        $this->assertCount(2, $programs);
        $this->assertArrayHasKey($program1->get('id'), $programs);
        $this->assertArrayHasKey($program3->get('id'), $programs);

        $programs = api::get_programs_by_status_and_userid($status, $user3->id);
        $this->assertCount(1, $programs);
        $this->assertArrayHasKey($program2->get('id'), $programs);

        // Check that suspended does not override completed status (both statuses can "coexist").
        $programuser21->set('status', constants::STATUS_OVERRIDE_SUSPENDED);
        $programuser21->update();

        $programs = api::get_programs_by_status_and_userid(constants::STATUS_COMPLETED, $user2->id);
        $this->assertCount(2, $programs);
        $this->assertArrayHasKey($program1->get('id'), $programs);
        $this->assertArrayHasKey($program3->get('id'), $programs);

        $programs = api::get_programs_by_status_and_userid(constants::STATUS_SUSPENDED, $user2->id);
        $this->assertCount(1, $programs);
        $this->assertArrayHasKey($program1->get('id'), $programs);

        // Mark user2 as allocation with future start date in program2.
        $programuser22->set('startdate', strtotime('+3 day'));
        $programuser22->update();

        // Check get programs by future allocation status.
        $status = constants::STATUS_FUTUREALLOCATION;

        $programs = api::get_programs_by_status_and_userid($status, $user1->id);
        $this->assertCount(0, $programs);

        $programs = api::get_programs_by_status_and_userid($status, $user2->id);
        $this->assertCount(1, $programs);
        $this->assertArrayHasKey($program2->get('id'), $programs);

        $programs = api::get_programs_by_status_and_userid($status, $user3->id);
        $this->assertCount(0, $programs);

        // Check that suspended has precedence over future allocation.
        $programuser22->set('status', constants::STATUS_OVERRIDE_SUSPENDED);
        $programuser22->update();

        $programs = api::get_programs_by_status_and_userid($status, $user2->id);
        $this->assertCount(0, $programs);

        $programs = api::get_programs_by_status_and_userid(constants::STATUS_SUSPENDED, $user2->id);
        $this->assertCount(2, $programs);
        $this->assertArrayHasKey($program1->get('id'), $programs);
        $this->assertArrayHasKey($program2->get('id'), $programs);

        // Mark user1 as allocation overdue in program3.
        $programuser13->set('duedate', strtotime('-5 day'));
        $programuser13->update();

        // Check get programs by overdue status.
        $status = constants::STATUS_OVERDUE;

        $programs = api::get_programs_by_status_and_userid($status, $user1->id);
        $this->assertCount(1, $programs);
        $this->assertArrayHasKey($program3->get('id'), $programs);

        $programs = api::get_programs_by_status_and_userid($status, $user2->id);
        $this->assertCount(0, $programs);

        $programs = api::get_programs_by_status_and_userid($status, $user3->id);
        $this->assertCount(0, $programs);

        // Mark user3 as allocation open in program1.
        $programuser31->set('startdate', strtotime('-5 day'));
        $programuser31->set('duedate', strtotime('+5 day'));
        $programuser31->update();

        // Check get programs by open status.
        $status = constants::STATUS_OPEN;

        $programs = api::get_programs_by_status_and_userid($status, $user2->id);
        $this->assertCount(0, $programs);

        $programs = api::get_programs_by_status_and_userid($status, $user1->id);
        $this->assertCount(0, $programs);

        $programs = api::get_programs_by_status_and_userid($status, $user3->id);
        $this->assertCount(1, $programs);
        $this->assertArrayHasKey($program1->get('id'), $programs);

        // Check that completed has precedence over overdue status.
        $this->generator->complete_program($program3, $user1->id);

        $programs = api::get_programs_by_status_and_userid(constants::STATUS_OVERDUE, $user1->id);
        $this->assertCount(0, $programs);

        $programs = api::get_programs_by_status_and_userid(constants::STATUS_COMPLETED, $user1->id);
        $this->assertCount(1, $programs);
        $this->assertArrayHasKey($program3->get('id'), $programs);

        // Check that completed has precedence over open status.
        $this->generator->complete_program($program1, $user3->id);

        $programs = api::get_programs_by_status_and_userid(constants::STATUS_OPEN, $user3->id);
        $this->assertCount(0, $programs);

        $programs = api::get_programs_by_status_and_userid(constants::STATUS_COMPLETED, $user3->id);
        $this->assertCount(2, $programs);
        $this->assertArrayHasKey($program1->get('id'), $programs);
        $this->assertArrayHasKey($program2->get('id'), $programs);

        // Check that completed has precedence over future allocation status.
        $programuser23->set('startdate', strtotime('+8 day'));
        $programuser23->update();

        $programs = api::get_programs_by_status_and_userid(constants::STATUS_FUTUREALLOCATION, $user2->id);
        $this->assertCount(0, $programs);

        $programs = api::get_programs_by_status_and_userid(constants::STATUS_COMPLETED, $user2->id);
        $this->assertCount(2, $programs);
        $this->assertArrayHasKey($program1->get('id'), $programs);
        $this->assertArrayHasKey($program3->get('id'), $programs);

        // Check that completed status persists independently of end date changes.
        $programuser31->set('enddate', strtotime('+7 day'));
        $programuser31->update();
        $programuser32->set('enddate', strtotime('-7 day'));
        $programuser32->update();

        $programs = api::get_programs_by_status_and_userid(constants::STATUS_COMPLETED, $user3->id);
        $this->assertCount(2, $programs);
        $this->assertArrayHasKey($program1->get('id'), $programs);
        $this->assertArrayHasKey($program2->get('id'), $programs);
    }

    /**
     * Test is actively enrolled with enrol program.
     */
    public function test_is_actively_enrolled_with_enrol_program(): void {
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();
        $course = self::getDataGenerator()->create_course();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));

        // User 1 is allocated to program and enrolled to the program course.
        $user1 = self::getDataGenerator()->create_user();
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);

        $this->assertTrue(api::is_actively_enrolled_with_enrol_program($program->get('id'), $course->id, $user1->id));

        // User 2 is only allocated to program and enrolled to the course with another enrol plugin.
        $user2 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        self::getDataGenerator()->enrol_user($user2->id, $course->id);

        $this->assertFalse(api::is_actively_enrolled_with_enrol_program($program->get('id'), $course->id, $user2->id));

        // User 3 is only allocated to program.
        $user3 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user_to_program($program->get('id'), $user3->id);

        $this->assertFalse(api::is_actively_enrolled_with_enrol_program($program->get('id'), $course->id, $user3->id));
    }

    /**
     * Test is course in set.
     */
    public function test_is_course_in_set(): void {
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();
        $childset = $this->generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'sortorder' => 1,
        ]);
        $course = self::getDataGenerator()->create_course();
        $this->generator->add_course_to_set($course->id, $childset->get('id'));

        $this->assertFalse(api::is_course_in_set($baseset->get('id'), $course->id));
        $this->assertTrue(api::is_course_in_set($childset->get('id'), $course->id));
    }

    /**
     * Test program calendar event is created.
     */
    public function test_add_calendar_event(): void {
        global $DB;
        $program = $this->generator->generate_program();
        $user = self::getDataGenerator()->create_user();
        $duedate = time() + DAYSECS;

        $data = (object) [
            'userid' => $user->id,
            'name' => $program->get_formatted_name(),
            'programid' => $program->get('id'),
            'timestart' => $duedate,
            'programdatetype' => constants::CALENDAR_EVENT_DUE_DATE,
        ];

        $result = $DB->get_records('event');
        $this->assertEmpty($result);

        api::add_calendar_event($data);

        $result = $DB->get_records('event');
        $this->assertCount(1, $result);
        $result = reset($result);
        $str = get_string('calendarduedate', 'tool_program', $program->get_formatted_name());
        $this->assertEquals($str, $result->name);
        $this->assertEquals($str, $result->description);
        $this->assertEquals($user->id, $result->userid);
        $this->assertEquals('tool_program', $result->component);
        $this->assertEquals($program->get('id'), $result->instance);
        $this->assertEquals('tool_program' . constants::CALENDAR_EVENT_DUE_DATE, $result->eventtype);
        $this->assertEquals($duedate, $result->timestart);
        $this->assertEquals(1, $result->visible);
    }

    /**
     * Test program calendar event is updated.
     */
    public function test_update_calendar_event(): void {
        global $DB;
        $program = $this->generator->generate_program();
        $user = self::getDataGenerator()->create_user();
        $enddate = time() + DAYSECS;

        $data = (object)[
            'userid' => $user->id,
            'name' => $program->get_formatted_name(),
            'programid' => $program->get('id'),
            'timestart' => $enddate,
            'programdatetype' => constants::CALENDAR_EVENT_END_DATE,
        ];

        api::add_calendar_event($data);

        $result = $DB->get_records('event');
        $this->assertCount(1, $result);
        $result = reset($result);
        $str = get_string('calendarenddate', 'tool_program', $program->get_formatted_name());
        $this->assertEquals($str, $result->name);
        $this->assertEquals($str, $result->description);
        $this->assertEquals($user->id, $result->userid);
        $this->assertEquals('tool_program', $result->component);
        $this->assertEquals($program->get('id'), $result->instance);
        $this->assertEquals('tool_program' . constants::CALENDAR_EVENT_END_DATE, $result->eventtype);
        $this->assertEquals($enddate, $result->timestart);

        $newenddate = $enddate + DAYSECS;
        $data = (object)[
            'userid' => $user->id,
            'programid' => $program->get('id'),
            'name' => $program->get_formatted_name(),
            'timestart' => $newenddate,
            'programdatetype' => constants::CALENDAR_EVENT_END_DATE,
        ];
        api::update_calendar_event($data);

        $result = $DB->get_records('event');
        $this->assertCount(1, $result);
        $result = reset($result);
        $this->assertEquals($user->id, $result->userid);
        $this->assertEquals('tool_program', $result->component);
        $this->assertEquals($program->get('id'), $result->instance);
        $this->assertEquals('tool_program' . constants::CALENDAR_EVENT_END_DATE, $result->eventtype);
        $this->assertEquals($newenddate, $result->timestart);

        // Try to update a non existent event (CALENDAR_EVENT_DUE_DATE) and it will create it.
        $data->programdatetype = constants::CALENDAR_EVENT_DUE_DATE;
        $newdate = time() + 3 * DAYSECS;
        $data->timestart = $newdate;
        api::update_calendar_event($data);

        $result = $DB->get_records('event');
        $this->assertCount(2, $result);
    }

    /**
     * Test program calendar events are deleted.
     */
    public function test_delete_calendar_events(): void {
        global $DB;
        $program = $this->generator->generate_program();
        $user = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $duedate = time() + DAYSECS;
        $enddate = time() + 2 * DAYSECS;

        $data = (object)[
            'userid' => $user->id,
            'name' => $program->get_formatted_name(),
            'programid' => $program->get('id'),
            'timestart' => $duedate,
            'programdatetype' => constants::CALENDAR_EVENT_DUE_DATE,
        ];
        api::add_calendar_event($data);

        $data = (object)[
            'userid' => $user->id,
            'name' => $program->get_formatted_name(),
            'programid' => $program->get('id'),
            'timestart' => $enddate,
            'programdatetype' => constants::CALENDAR_EVENT_END_DATE,
        ];
        api::add_calendar_event($data);

        $this->assertCount(2, $DB->get_records('event'));

        // Try to delete non existent events.
        $data = (object) [
            'userid' => $user2->id,
            'programid' => $program->get('id'),
        ];
        api::delete_calendar_events($data);

        $this->assertCount(2, $DB->get_records('event'));

        // Delete both user events.
        $data = (object) [
            'userid' => $user->id,
            'programid' => $program->get('id'),
        ];
        api::delete_calendar_events($data);

        $this->assertEmpty($DB->get_records('event'));

        // Test to remove just one specific event type.
        $data = (object)[
            'userid' => $user->id,
            'name' => $program->get_formatted_name(),
            'programid' => $program->get('id'),
            'timestart' => $duedate,
            'programdatetype' => constants::CALENDAR_EVENT_DUE_DATE,
        ];
        api::add_calendar_event($data);

        $data = (object)[
            'userid' => $user->id,
            'name' => $program->get_formatted_name(),
            'programid' => $program->get('id'),
            'timestart' => $enddate,
            'programdatetype' => constants::CALENDAR_EVENT_END_DATE,
        ];
        api::add_calendar_event($data);

        $this->assertCount(2, $DB->get_records('event'));

        // Delete just the CALENDAR_EVENT_DUE_DATE event.
        $data = (object) [
            'userid' => $user->id,
            'programid' => $program->get('id'),
        ];
        api::delete_calendar_events($data, constants::CALENDAR_EVENT_DUE_DATE);

        $result = $DB->get_records('event');
        $this->assertCount(1, $result);
        $result = reset($result);
        $this->assertEquals($user->id, $result->userid);
        $this->assertEquals('tool_program', $result->component);
        $this->assertEquals($program->get('id'), $result->instance);
        $this->assertEquals('tool_program' . constants::CALENDAR_EVENT_END_DATE, $result->eventtype);
    }

    /**
     * Test send program completed notification.
     */
    public function test_send_program_completed_notification(): void {
        global $SITE;
        $user = self::getDataGenerator()->create_user();
        $program = $this->generator->generate_program();
        $programname = $program->get_formatted_name();
        $subject = get_string('notificationsubjectprogramcompleted', 'tool_program', $programname);
        $contextid = context_system::instance()->id;

        $a = [
            'userfullname' => fullname($user),
            'programname' => $programname,
            'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
            'siteurl' => (new \moodle_url('/'))->out(false),
        ];
        $fullmessage = get_string('notificationprogramcompleted', 'tool_program', $a);

        // Sink should catch messages.
        $sink = $this->redirectMessages();
        api::send_program_completed_notification($user->id, $program->get('id'));
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($user->id, $messages[0]->useridto);
        $this->assertEquals($subject, $messages[0]->subject);
        $this->assertStringContainsString($fullmessage, $messages[0]->fullmessagehtml);
    }

    /**
     * Test send program allocated notification (program with a due date).
     */
    public function test_send_program_user_allocated_notification(): void {
        global $SITE;
        $user = self::getDataGenerator()->create_user();
        $program = $this->generator->generate_program();
        $contextid = context_system::instance()->id;
        $programname = $program->get_formatted_name();
        $due = userdate($program->get('duedateabsolute'), get_string('strftimedatefullshort'));
        $duedate = get_string('notificationduedate', 'tool_program', $due);
        $subject = get_string('notificationsubjectprogramuserallocated', 'tool_program', $programname);
        $a = [
            'userfullname' => fullname($user),
            'programname' => $programname,
            'duedatemsg' => $duedate,
            'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
            'siteurl' => (new \moodle_url('/'))->out(false),
        ];
        $fullmessage = get_string('notificationprogramuserallocated', 'tool_program', $a);

        // Sink should catch messages.
        $sink = $this->redirectMessages();
        $this->generator->allocate_user_to_program($program->get('id'), $user->id);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($user->id, $messages[0]->useridto);
        $this->assertEquals($subject, $messages[0]->subject);
        $this->assertStringContainsString($fullmessage, $messages[0]->fullmessagehtml);
    }

    /**
     * Test send program allocated notification (program without a due date).
     */
    public function test_send_program_user_allocated_notification_without_due_date(): void {
        global $SITE;
        $program = $this->generator->generate_program((object)['duedatetype' => constants::DATE_NONE]);
        $contextid = context_system::instance()->id;
        $programname = $program->get_formatted_name();

        $user2 = self::getDataGenerator()->create_user();
        $subject = get_string('notificationsubjectprogramuserallocated', 'tool_program', $programname);
        $a = [
            'userfullname' => fullname($user2),
            'programname' => $programname,
            'duedatemsg' => '',
            'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
            'siteurl' => (new \moodle_url('/'))->out(false),
        ];
        $fullmessage = get_string('notificationprogramuserallocated', 'tool_program', $a);

        // Sink should catch messages.
        $sink = $this->redirectMessages();
        $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($user2->id, $messages[0]->useridto);
        $this->assertEquals($subject, $messages[0]->subject);
        $this->assertStringContainsString($fullmessage, $messages[0]->fullmessagehtml);
    }

    /**
     * Test send program user deallocated notification.
     */
    public function test_send_program_user_deallocated_notification(): void {
        global $SITE;
        $user = self::getDataGenerator()->create_user();
        $program = $this->generator->generate_program();
        $contextid = context_system::instance()->id;
        $programname = $program->get_formatted_name();
        $subject = get_string('notificationsubjectprogramuserdeallocated', 'tool_program', $programname);
        $a = [
            'userfullname' => fullname($user),
            'programname' => $programname,
            'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
            'siteurl' => (new \moodle_url('/'))->out(false),
        ];
        $fullmessage = get_string('notificationprogramuserdeallocated', 'tool_program', $a);

        // Sink should catch messages.
        $sink = $this->redirectMessages();
        api::send_program_user_deallocated_notification($user->id, $program->get('id'));
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($user->id, $messages[0]->useridto);
        $this->assertEquals($subject, $messages[0]->subject);
        $this->assertStringContainsString($fullmessage, $messages[0]->fullmessagehtml);
    }

    /**
     * Test reset program progress.
     */
    public function test_reset_program_progress(): void {
        global $DB;

        // Create a program and complete it as a user.
        $data = $this->generator->generate_program_filled_with_user_completion();
        $programuser = $this->generator->allocate_user_to_program($data->program->get('id'), $data->user->id);

        $this->generator->assign_edit_capability($data->user->id, context_system::instance());
        self::setUser($data->user);
        $basesetid = $data->baseset->get('id');
        $parentsetid = $data->parentset->get('id');
        $childsetid = $data->childset->get('id');

        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $childsetid, 'userid' => $data->user->id]);
        $this->assertTrue($res);
        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $parentsetid, 'userid' => $data->user->id]);
        $this->assertTrue($res);
        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $basesetid, 'userid' => $data->user->id]);
        $this->assertTrue($res);

        // Test with default marknotcompleted and resetcourses.
        $res = api::reset_program_progress($programuser);
        $this->assertTrue($res);
        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $childsetid, 'userid' => $data->user->id]);
        $this->assertFalse($res);
        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $parentsetid, 'userid' => $data->user->id]);
        $this->assertFalse($res);
        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $basesetid, 'userid' => $data->user->id]);
        $this->assertFalse($res);

        // Test with marknotcompleted=false and resetcourses=false.
        $this->generator->complete_courses([$data->course1->id, $data->course2->id], $data->user->id);

        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $childsetid, 'userid' => $data->user->id]);
        $this->assertTrue($res);
        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $parentsetid, 'userid' => $data->user->id]);
        $this->assertTrue($res);
        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $basesetid, 'userid' => $data->user->id]);
        $this->assertTrue($res);

        $res = api::reset_program_progress($programuser, false);
        $this->assertTrue($res);

        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $childsetid, 'userid' => $data->user->id]);
        $this->assertFalse($res);
        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $parentsetid, 'userid' => $data->user->id]);
        $this->assertFalse($res);
        $res = $DB->record_exists(program_set_completion::TABLE, ['setid' => $basesetid, 'userid' => $data->user->id]);
        $this->assertTrue($res);
    }

    public function test_get_default_program_dates(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();

        $strdate = get_string('strftimedatefullshort');
        $startdate = strtotime('-7 day');
        $duedate = strtotime('+6 day');
        $enddate = strtotime('+7 day');
        $program->set('startdateabsolute', $startdate);
        $program->set('duedateabsolute', $duedate);
        $program->set('enddateabsolute', $enddate);
        $program->update();

        $dates = api::get_default_program_dates($program);
        $this->assertObjectHasAttribute('startdate', $dates);
        $this->assertObjectHasAttribute('duedate', $dates);
        $this->assertObjectHasAttribute('enddate', $dates);
        $this->assertEquals(userdate($startdate, $strdate), $dates->startdate);
        $this->assertEquals(userdate($duedate, $strdate), $dates->duedate);
        $this->assertEquals(userdate($enddate, $strdate), $dates->enddate);

        $program->set('startdatetype', constants::DATE_AFTER_USER_ALLOCATION);
        $program->set('startdaterelative', '1 week');
        $program->set('duedatetype', constants::DATE_AFTER_START);
        $program->set('duedaterelative', '2 week');
        $program->set('enddatetype', constants::DATE_AFTER_START);
        $program->set('enddaterelative', '3 week');
        $program->update();

        $dates = api::get_default_program_dates($program);
        $this->assertEquals('1 week after user allocation date', $dates->startdate);
        $this->assertEquals('2 weeks after start date', $dates->duedate);
        $this->assertEquals('3 weeks after start date', $dates->enddate);

        $program->set('duedatetype', constants::DATE_AFTER_USER_ALLOCATION);
        $program->set('duedaterelative', '2 week');
        $program->set('enddatetype', constants::DATE_AFTER_DUE);
        $program->set('enddaterelative', '3 week');
        $program->update();

        $dates = api::get_default_program_dates($program);
        $this->assertEquals('2 weeks after user allocation date', $dates->duedate);
        $this->assertEquals('3 weeks after due date', $dates->enddate);

        $program->set('duedatetype', constants::DATE_BEFORE_END);
        $program->set('duedaterelative', '2 week');
        $program->set('enddatetype', constants::DATE_AFTER_USER_ALLOCATION);
        $program->set('enddaterelative', '3 week');
        $program->update();

        $dates = api::get_default_program_dates($program);
        $this->assertEquals('2 weeks before end date', $dates->duedate);
        $this->assertEquals('3 weeks after user allocation date', $dates->enddate);

        $program->set('duedaterelative', '1 month');
        $program->set('enddaterelative', '1 year');
        $program->update();

        $dates = api::get_default_program_dates($program);
        $this->assertEquals('1 month before end date', $dates->duedate);
        $this->assertEquals('1 year after user allocation date', $dates->enddate);

        $program->set('startdatetype', constants::DATE_NONE);
        $program->set('duedatetype', constants::DATE_NONE);
        $program->set('enddatetype', constants::DATE_NONE);
        $program->update();

        $dates = api::get_default_program_dates($program);
        $this->assertEquals('Not set', $dates->startdate);
        $this->assertEquals('Not set', $dates->duedate);
        $this->assertEquals('Not set', $dates->enddate);
    }

    public function test_user_has_unsuspended_allocations(): void {
        $program = $this->generator->generate_program();
        $certification = $this->certificationgenerator->generate_certification();
        $user = self::getDataGenerator()->create_user();

        // Test user has none, since it has not even been allocated yet.
        $this->assertFalse(api::has_unsuspended_allocations($program->get('id'), $user->id));

        // Test user has one when it has been allocated at least once.
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user->id);
        $this->assertTrue(api::has_unsuspended_allocations($program->get('id'), $user->id));

        // Keeps having at least one unsuspended allocation when allocated more than once.
        $programuser2 = $this->generator->allocate_user_to_program($program->get('id'), $user->id, $certification->get('id'));
        $this->assertTrue(api::has_unsuspended_allocations($program->get('id'), $user->id));

        // Keeps having at least one unsuspended allocation when one of them is suspended.
        $programuser1->set('status', constants::STATUS_OVERRIDE_SUSPENDED);
        $programuser1->update();
        $this->assertTrue(api::has_unsuspended_allocations($program->get('id'), $user->id));

        // Test is has none if all of them are suspended.
        $programuser2->set('status', constants::STATUS_OVERRIDE_SUSPENDED);
        $programuser2->update();
        $this->assertFalse(api::has_unsuspended_allocations($program->get('id'), $user->id));
    }

    public function test_duplicate_program_dynamicrule(): void {
        global $DB;
        self::setAdminUser();
        $programdata = $this->generator->get_dummy_program_data(false);
        $program1 = new program(0, $programdata);
        $program1->create();
        $program2 = new program(0, $programdata);
        $program2->create();
        $component = 'tool_program';
        $componentarea = 'program';

        $programid1 = $program1->get('id');
        $programid2 = $program2->get('id');

        $params1 = [
            'component' => 'tool_program',
            'componentarea' => 'program',
            'itemid' => $programid1
        ];
        $params2 = [
            'component' => 'tool_program',
            'componentarea' => 'program',
            'itemid' => $programid2
        ];

        // Rules don't exist yet.
        $programrules1 = $DB->get_record('tool_dynamicrule', $params1);
        $this->assertEmpty($programrules1);
        $programrules2 = $DB->get_record('tool_dynamicrule', $params2);
        $this->assertEmpty($programrules2);

        $configdata = ['programid' => $programid1];
        $name = get_string('programrules', 'tool_program');

        // Create one rule.
        $ruleid = \tool_dynamicrule\api::create_rule_for_component($component, $componentarea,
            $programid1, $program1->get('tenantid'), $name, false);
        $programrules1 = $DB->get_record('tool_dynamicrule', $params1);
        $this->assertNotEmpty($programrules1);

        // Create condition on program1.
        $conditionclass = 'tool_program\tool_dynamicrule\condition\program_completed';
        \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata, true);

        $condition = $DB->get_record('tool_dynamicrule_condition', ['ruleid' => $ruleid]);
        $this->assertNotEmpty($condition);

        // Create outcome on program1.
        $outcomeclass = 'tool_dynamicrule\tool_dynamicrule\outcome\notification';
        \tool_dynamicrule\api::create_rule_outcome($ruleid, $outcomeclass, $configdata, true);

        $outcome = $DB->get_record('tool_dynamicrule_outcome', ['ruleid' => $ruleid]);
        $this->assertNotEmpty($outcome);

        // Duplicate dynamicrule.
        api::duplicate_program_dynamicrules($programid1, $programid2);

        // Check data has been duplicated.
        $programrule2 = $DB->get_record('tool_dynamicrule', $params2);
        $this->assertNotEmpty($programrule2);
        $condition = $DB->get_record('tool_dynamicrule_condition', ['ruleid' => $programrule2->id]);
        $this->assertNotEmpty($condition);
        $this->assertEquals($conditionclass, $condition->classname);
        $outcome = $DB->get_record('tool_dynamicrule_outcome', ['ruleid' => $programrule2->id]);
        $this->assertNotEmpty($outcome);
        $this->assertEquals($outcomeclass, $outcome->classname);
        $this->assertEquals($configdata, json_decode($outcome->configdata, true));
    }

    public function test_remove_deleted_course_from_programs(): void {
        global $DB;
        self::setAdminUser();

        $course1 = self::getDataGenerator()->create_course();
        $courseid1 = $course1->id;
        $course2 = self::getDataGenerator()->create_course();
        $courseid2 = $course2->id;

        $program1 = $this->generator->generate_program();
        $baseset1 = $program1->get_base_set();
        $programcourse1 = $this->generator->add_course_to_set($courseid1, $baseset1->get('id'));
        $programcourseid1 = $programcourse1->get('id');
        $programcourse3 = $this->generator->add_course_to_set($courseid2, $baseset1->get('id'));
        $programcourseid3 = $programcourse3->get('id');

        $program2 = $this->generator->generate_program();
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->generator->add_course_to_set($courseid1, $baseset2->get('id'));
        $programcourseid2 = $programcourse1->get('id');

        // We check if program course instance exists.
        $this->assertTrue($DB->record_exists('tool_program_courses', ['id' => $programcourseid1]));
        $this->assertTrue($DB->record_exists('tool_program_courses', ['id' => $programcourseid2]));
        $this->assertTrue($DB->record_exists('tool_program_courses', ['id' => $programcourseid3]));

        delete_course($courseid1, false);

        // We check if program course instance exists.
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourseid1]));
        $this->assertFalse($DB->record_exists('tool_program_courses', ['id' => $programcourseid2]));
        $this->assertTrue($DB->record_exists('tool_program_courses', ['id' => $programcourseid3]));
    }

    public function test_remove_deleted_user_from_programs(): void {
        global $DB;
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $program1 = $this->generator->generate_program();
        $programid1 = $program1->get('id');
        $this->generator->allocate_user_to_program($programid1, $user1->id);
        $this->generator->allocate_user_to_program($programid1, $user2->id);

        $program2 = $this->generator->generate_program();
        $programid2 = $program2->get('id');
        $this->generator->allocate_user_to_program($programid2, $user1->id);

        $exists = $DB->record_exists('tool_program_users', ['programid' => $programid1, 'userid' => $user1->id]);
        $this->assertTrue($exists);
        $exists = $DB->record_exists('tool_program_users', ['programid' => $programid1, 'userid' => $user2->id]);
        $this->assertTrue($exists);
        $exists = $DB->record_exists('tool_program_users', ['programid' => $programid2, 'userid' => $user1->id]);
        $this->assertTrue($exists);

        delete_user($user1);

        $exists = $DB->record_exists('tool_program_users', ['programid' => $programid1, 'userid' => $user1->id]);
        $this->assertFalse($exists);
        $exists = $DB->record_exists('tool_program_users', ['programid' => $programid1, 'userid' => $user2->id]);
        $this->assertTrue($exists);
        $exists = $DB->record_exists('tool_program_users', ['programid' => $programid2, 'userid' => $user1->id]);
        $this->assertFalse($exists);
    }

    public function test_is_idnumber_unique(): void {
        self::setAdminUser();
        $program1 = $this->generator->generate_program((object)['idnumber' => 'num1']);
        $program2 = $this->generator->generate_program((object)['idnumber' => 'num2']);

        $this->assertTrue(api::is_idnumber_unique($program1->get('id'), 'num1'));
        $this->assertTrue(api::is_idnumber_unique($program1->get('id'), 'num3'));
        $this->assertFalse(api::is_idnumber_unique($program1->get('id'), 'num2'));
        $this->assertFalse(api::is_idnumber_unique($program1->get('id'), 'NUM2'));

        $program1->set('idnumber', '');
        $program1->update();
        $this->assertTrue(api::is_idnumber_unique($program2->get('id'), ''));
    }

    public function test_delete_program_dynamic_rules(): void {
        global $DB;
        $program = $this->generator->generate_program();
        // We need to test also that deletes associated dynamic rules.
        api::add_default_dynamicrule_conditions_to_program($program->get('id'), $program->get('tenantid'));

        $params = [
            'component' => 'tool_program',
            'componentarea' => 'program',
            'itemid' => $program->get('id')
        ];
        $this->assertTrue($DB->record_exists('tool_dynamicrule', $params));
        api::delete_program_dynamic_rules($program->get('id'));
        $this->assertFalse($DB->record_exists('tool_dynamicrule', $params));
    }

    public function test_get_potential_programs(): void {
        // We retrieve default tenant.
        $defaulttenantid = tenancy::get_default_tenant_id();
        // Create one user, he will be allocated to default tenant.
        $user = self::getDataGenerator()->create_user();
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = phpunit_util::get_data_generator()->get_plugin_generator('tool_tenant');
        // Create one more tenant.
        $othertenant = $tenantgenerator->create_tenant([]);

        $program = $this->generator->generate_program((object)['tenantid' => $defaulttenantid]);
        $programdata = $program->to_record();
        unset($programdata->id);

        $programdata->fullname = 'program number 2';
        $programdata->idnumber = 'idnumber_2';
        $program2 = $this->generator->generate_program($programdata);

        $programdata->fullname = 'program number 3';
        $programdata->idnumber = 'idnumber_3';
        $programdata->tenantid = $othertenant->id;
        $program3 = $this->generator->generate_program($programdata);

        $search = 'program';
        $progslist = api::get_potential_programs($search);

        // Regular user can't search programs.
        $this->assertEmpty($progslist);

        // Assign user capability to edit certifications, then he will be able to search programs.
        $this->generator->assign_edit_capability($user->id, context_system::instance());
        $this->setUser($user);

        $progslist = api::get_potential_programs($search);
        $this->assertCount(2, $progslist);
        $this->assertEqualsCanonicalizing(['A program name', 'program number 2'], array_column($progslist, 'fullname'));

        $search = 'name';
        $progslist = api::get_potential_programs($search);
        $this->assertCount(1, $progslist);
        $this->assertEquals('A program name', $progslist[$program->get('id')]->fullname);

        $search = 'team';
        $progslist = api::get_potential_programs($search);
        $this->assertEmpty($progslist);

        $search = 'idnumber_2';
        $progslist = api::get_potential_programs($search);
        $this->assertCount(1, $progslist);
        $this->assertEquals('program number 2', $progslist[$program2->get('id')]->fullname);
    }

    public function test_update_program_name_in_calendar_events(): void {
        global $DB;
        $user1 = self::getDataGenerator()->create_user();
        $program1 = $this->generator->generate_program((object)['fullname' => 'Program ABC']);
        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);

        $params = [
            'component' => 'tool_program',
            'instance' => $program1->get('id'),
        ];
        $events = $DB->get_records('event', $params, 'eventtype');

        $expected = get_string('calendarduedate', 'tool_program', 'Program ABC');
        $this->assertEquals($expected, reset($events)->name);
        $this->assertEquals($expected, reset($events)->description);
        $expected = get_string('calendarenddate', 'tool_program', 'Program ABC');
        $this->assertEquals($expected, end($events)->name);
        $this->assertEquals($expected, end($events)->description);

        api::update_program_name_in_calendar_events($program1->get('id'), 'New program name');

        $events = $DB->get_records('event', $params, 'eventtype');

        $expected = get_string('calendarduedate', 'tool_program', 'New program name');
        $this->assertEquals($expected, reset($events)->name);
        $this->assertEquals($expected, reset($events)->description);
        $expected = get_string('calendarenddate', 'tool_program', 'New program name');
        $this->assertEquals($expected, end($events)->name);
        $this->assertEquals($expected, end($events)->description);
    }

    public function test_hide_program_user_calendar_events(): void {
        global $DB;
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $program1 = $this->generator->generate_program();
        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);

        $params = [
            'component' => 'tool_program',
            'instance' => $program1->get('id'),
            'visible' => 1,
        ];
        $this->assertCount(4, $DB->get_records('event', $params));

        api::hide_program_user_calendar_events($program1);

        $this->assertCount(0, $DB->get_records('event', $params));
        $params['visible'] = 0;
        $this->assertCount(4, $DB->get_records('event', $params));
    }

    public function test_show_program_user_calendar_events(): void {
        global $DB;
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $program1 = $this->generator->generate_program();
        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);
        api::archive_program($program1);

        $params = [
            'component' => 'tool_program',
            'instance' => $program1->get('id'),
            'visible' => 0,
        ];
        $this->assertCount(4, $DB->get_records('event', $params));

        api::show_program_user_calendar_events($program1);

        $this->assertCount(0, $DB->get_records('event', $params));
        $params['visible'] = 1;
        $this->assertCount(4, $DB->get_records('event', $params));
    }

    /**
     * Test for suspend, reactivate and delete program user enrolments.
     *
     * @covers \tool_program\api::suspend_allocated_user_enrolments
     * @covers \tool_program\api::reactivate_allocated_user_program_enrolments
     * @covers \tool_program\api::remove_user_from_program_courses
     */
    public function test_suspend_reactivate_delete_program_user_enrolments(): void {
        $program = $this->generator->generate_program((object)
            ['autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT)]
        );
        $baseset = $program->get_base_set();
        $course = self::getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));

        // User 1 is allocated to program and enrolled to the program course.
        $user1 = self::getDataGenerator()->create_user();
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);

        // Enrolment should be active and group membership is in place.
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', true));
        $usergroups = api::get_user_groups_in_course($program->get('id'), $course, $user1->id);
        $this->assertCount(1, $usergroups);
        $usergroup = $usergroups[0];
        $this->assertTrue(groups_is_member($usergroup, $user1->id));

        // Try suspending without disabling program user allocation.
        api::suspend_allocated_user_enrolments($programuser1);

        // Enrolment should be still active.
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', true));
        $this->assertTrue(groups_is_member($usergroup, $user1->id));

        // Disable program user and suspend user enrolment.
        $programuser1->set('status', constants::STATUS_SUSPENDED);
        $programuser1->update();
        $this->assertTrue(api::can_user_be_removed_from_group($program->get('id'), $user1->id, 0));
        api::suspend_allocated_user_enrolments($programuser1);

        // Enrolment should be suspended.
        $this->assertFalse(is_enrolled($coursecontext, $user1->id, '', true));
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', false));
        $this->assertFalse(groups_is_member($usergroup, $user1->id));

        // Try reactivating without enabling program user allocation.
        api::reactivate_allocated_user_program_enrolments($programuser1);

        // Enrolment should be still suspended.
        $this->assertFalse(is_enrolled($coursecontext, $user1->id, '', true));
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', false));
        $this->assertFalse(groups_is_member($usergroup, $user1->id));

        // Reactivate program user and user enrolment.
        $programuser1->set('status', constants::STATUS_OVERRIDE_DEFAULT);
        $programuser1->update();
        api::reactivate_allocated_user_program_enrolments($programuser1);

        // Enrolment should be active again.
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', true));
        $this->assertTrue(groups_is_member($usergroup, $user1->id));
    }

    /**
     * Test test_remove_user_from_program_courses.
     */
    public function test_remove_user_from_program_courses(): void {
        $program = $this->generator->generate_program((object)
            ['autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT)]
        );
        $baseset = $program->get_base_set();
        $course = self::getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));

        // User 1 is allocated to program and enrolled to the program course.
        $user1 = self::getDataGenerator()->create_user();
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);

        // Enrolment should be active and group membership is in place.
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', true));
        $usergroups = api::get_user_groups_in_course($program->get('id'), $course, $user1->id);
        $this->assertCount(1, $usergroups);
        $usergroup = $usergroups[0];
        $this->assertTrue(groups_is_member($usergroup, $user1->id));

        api::remove_user_from_program_courses($programuser1);

        // Check that user enrolment has been deleted.
        $this->assertFalse(is_enrolled($coursecontext, $user1->id, '', false));
        $this->assertFalse(groups_is_member($usergroup, $user1->id));

        // Now remove from groups.
        api::remove_user_from_program_course_groups($programuser1);

        $this->assertFalse(groups_is_member($usergroup, $user1->id));
    }

    /**
     * Test test_remove_user_from_program_courses.
     */
    public function test_remove_user_from_program_course_groups(): void {
        $program = $this->generator->generate_program((object)
            ['autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT)]
        );
        $baseset = $program->get_base_set();
        $course = self::getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));

        // User 1 is allocated to program and enrolled to the program course.
        $user1 = self::getDataGenerator()->create_user();
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);

        // Enrolment should be active and group membership is in place.
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', true));
        $usergroups = api::get_user_groups_in_course($program->get('id'), $course, $user1->id);
        $this->assertCount(1, $usergroups);
        $usergroup = $usergroups[0];
        $this->assertTrue(groups_is_member($usergroup, $user1->id));

        // Now remove from groups.
        api::remove_user_from_program_course_groups($programuser1);

        // Check that user enrolment is in place.
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', true));

        // But user is not in group.
        $this->assertFalse(groups_is_member($usergroup, $user1->id));
    }

    public function test_delete_program_course_enrol_instance(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program();
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $course = self::getDataGenerator()->create_course();
        $this->generator->add_course_to_set( $course->id, $basesetid);

        // Check program enrol instance exists for course1.
        $enrolparams = [
            'courseid' => $course->id,
            'enrol' => 'program',
            'customint1' => $program->get('id'),
        ];
        $this->assertCount(1, $DB->get_records('enrol', $enrolparams));

        api::delete_program_course_enrol_instance($program->get('id'), $course->id);

        $this->assertCount(0, $DB->get_records('enrol', $enrolparams));
    }

    public function test_delete_component_course_tenant_group(): void {
        global $DB;
        self::setAdminUser();
        $program = $this->generator->generate_program((object)[
            'fullname' => 'Test program groups',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $course = self::getDataGenerator()->create_course();
        $programcourse = $this->generator->add_course_to_set( $course->id, $basesetid);
        $user1 = self::getDataGenerator()->create_user();

        $this->assertEmpty($DB->get_records('tool_tenant_group'));

        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser);

        // Tenant group has been created and contains proper info.
        $tenantgroups = $DB->get_records('tool_tenant_group');
        $this->assertCount(1, $tenantgroups);
        $tenantgroup = reset($tenantgroups);
        $this->assertEquals('tool_program', $tenantgroup->component);
        $this->assertEquals('tool_program', $tenantgroup->area);
        $this->assertEquals($program->get('id'), $tenantgroup->itemid);
        $this->assertEquals($course->id, $tenantgroup->courseid);

        // Group has been created too and contains proper info.
        $group = $DB->get_record('groups', ['id' => $tenantgroup->groupid]);
        $this->assertEquals('Test program groups', $group->name);

        api::delete_component_course_tenant_group('tool_program', 'tool_program', $program->get('id'), $course->id);

        // Assert that tenant group for this program course has been deleted.
        $this->assertEmpty($DB->get_records('tool_tenant_group'));
        // Assert that related group has been deleted.
        $this->assertFalse($DB->get_record('groups', ['id' => $tenantgroup->groupid]));
    }

    /**
     * Test to check adding and removing users from groups when archiving/restoring/deleting program
     * and suspending/restoring user allocations.
     *
     * @covers \tool_program\api::archive_program
     * @covers \tool_program\api::restore_program
     * @covers \tool_program\api::update_program_user_dates_and_status
     * @covers \tool_program\api::delete_program
     */
    public function test_archive_program_and_suspend_user_groups(): void {
        global $DB;
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records());
        $this->assertCount(0, $DB->get_records('groups'));

        $tenant = $this->tenantgenerator->create_tenant();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $program = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser2);

        // There should be 2 group members.
        $this->assertCount(2, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        // Suspend program allocation of user2.
        $data = $programuser2->to_record();
        $data->status = constants::STATUS_SUSPENDED;
        api::update_program_user_dates_and_status($programuser2, $data);

        // There should be just 1 group member because the other allocation has been suspended.
        $this->assertCount(1, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        // Archive the program.
        api::archive_program($program);

        // There should be 0 group members. All allocations have been removed from groups.
        $this->assertCount(0, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        // Restore the program.
        api::restore_program($program);

        // There should be 1 group members.
        $this->assertCount(1, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        $data->status = constants::STATUS_OVERRIDE_DEFAULT;
        api::update_program_user_dates_and_status($programuser2, $data);

        // There should be 2 group members.
        $this->assertCount(2, $DB->get_records('groups_members', ['component' => 'enrol_program']));
        // There should be tenant group.
        $this->assertCount(1, \tool_tenant\tenant_group::get_records());
        // There should be 1 group.
        $this->assertCount(1, $DB->get_records('groups'));

        // Delete the program.
        api::archive_program($program);
        api::delete_program($program);

        // Tenant group and group should be deleted.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records());
        $this->assertCount(0, $DB->get_records('groups'));
    }

    public function test_get_tenant_group_records(): void {
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records());

        $tenant = $this->tenantgenerator->create_tenant();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $program = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser2);

        $this->assertCount(1, \tool_tenant\tenant_group::get_records());
        // Check passing program id zero does return empty array.
        $this->assertEmpty(api::get_tenant_group_records(0, 0));

        // Check program tenant group.
        $tenantgroups = api::get_tenant_group_records($program->get('id'), 0);
        $this->assertCount(1, $tenantgroups);
        $this->assertEquals($course->id, $tenantgroups[0]->get('courseid'));
        $this->assertEquals('tool_program', $tenantgroups[0]->get('component'));
        $this->assertEquals('tool_program', $tenantgroups[0]->get('area'));
        $this->assertEquals($program->get('id'), $tenantgroups[0]->get('itemid'));

        $certification = $this->certificationgenerator->generate_certification([
            'program' => $program->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);
        $certificationuser1 = $this->certificationgenerator->allocate_user($user1->id, $certification->get('id'));
        $certificationuser2 = $this->certificationgenerator->allocate_user($user2->id, $certification->get('id'));

        $programuser1 = program_user::get_record(['userid' => $user1->id, 'certificationid' => $certification->get('id')]);
        $programuser2 = program_user::get_record(['userid' => $user2->id, 'certificationid' => $certification->get('id')]);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser2);

        $this->assertCount(2, \tool_tenant\tenant_group::get_records());

        // Check certification tenant group.
        $tenantgroups = api::get_tenant_group_records($program->get('id'), $certification->get('id'));
        $this->assertCount(1, $tenantgroups);
        $this->assertEquals($course->id, $tenantgroups[0]->get('courseid'));
        $this->assertEquals('tool_certification', $tenantgroups[0]->get('component'));
        $this->assertEquals('tool_certification', $tenantgroups[0]->get('area'));
        $this->assertEquals($certification->get('id'), $tenantgroups[0]->get('itemid'));
    }

    public function test_can_user_be_removed_from_group(): void {
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records());

        $tenant = $this->tenantgenerator->create_tenant();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $program = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser2);

        // There is one active program allocation for this user and can not be removed from group.
        $this->assertFalse(api::can_user_be_removed_from_group($program->get('id'), $user1->id, 0));

        $programuser1->set('status', constants::STATUS_SUSPENDED);
        $programuser1->update();

        // There is no active program allocation for this user and can be removed from group.
        $this->assertTrue(api::can_user_be_removed_from_group($program->get('id'), $user1->id, 0));

        // Certification to check setting GROUPS_AS_IN_PROGRAMS.
        $certification1 = $this->certificationgenerator->generate_certification([
            'program' => $program->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_AS_IN_PROGRAMS),
        ]);
        $certificationuser1 = $this->certificationgenerator->allocate_user($user1->id, $certification1->get('id'));
        $programusercert1 = \tool_program\persistent\program_user::get_record([
            'userid' => $user1->id,
            'certificationid' => $certification1->get('id'),
        ]);
        $this->generator->enrol_user_to_program_course($programcourse, $programusercert1);

        // There is one active program allocation for this user and can not be removed from group.
        $this->assertFalse(api::can_user_be_removed_from_group($program->get('id'), $user1->id, $certification1->get('id')));

        \tool_certification\api::update_certification_user_dates_and_status($certificationuser1,
            (object)['status' => \tool_certification\constants::STATUS_SUSPENDED]);

        // There is no active program allocation for this user and can be removed from group.
        $this->assertTrue(api::can_user_be_removed_from_group($program->get('id'), $user1->id, $certification1->get('id')));

        // Certification to check setting GROUPS_CERTIFICATION.
        $certification2 = $this->certificationgenerator->generate_certification([
            'program' => $program->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);
        $certificationuser2 = $this->certificationgenerator->allocate_user($user1->id, $certification2->get('id'));
        $programusercert2 = \tool_program\persistent\program_user::get_record([
            'userid' => $user1->id,
            'certificationid' => $certification2->get('id'),
        ]);
        $this->generator->enrol_user_to_program_course($programcourse, $programusercert2);

        // There is one active program allocation for this user and can not be removed from group.
        $this->assertFalse(api::can_user_be_removed_from_group($program->get('id'), $user1->id, $certification2->get('id')));

        \tool_certification\api::update_certification_user_dates_and_status($certificationuser1,
            (object)['status' => \tool_certification\constants::STATUS_OVERRIDE_DEFAULT]);

        \tool_certification\api::update_certification_user_dates_and_status($certificationuser2,
            (object)['status' => \tool_certification\constants::STATUS_SUSPENDED]);

        // There is one active program allocation for this user and can not be removed from group.
        $this->assertFalse(api::can_user_be_removed_from_group($program->get('id'), $user1->id, $certification1->get('id')));

        // There is no active program allocation for this user and can be removed from group.
        $this->assertTrue(api::can_user_be_removed_from_group($program->get('id'), $user1->id, $certification2->get('id')));

        $certification1->set('archived', 1);
        $certification1->update();

        // Certification is archived and user can be removed.
        $this->assertTrue(api::can_user_be_removed_from_group($program->get('id'), $user1->id, $certification1->get('id')));
    }

    public function test_remove_user_from_previous_tenant_groups(): void {
        global $DB;
        $this->resetAfterTest();
        self::setAdminUser();

        // We retrieve default tenant.
        $defaulttenantid = tenancy::get_default_tenant_id();
        $tenant2 = $this->tenantgenerator->create_tenant();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant2->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant2->id);
        $this->tenantgenerator->allocate_user($user3->id, $tenant2->id);
        $this->tenantgenerator->allocate_user($user4->id, $tenant2->id);

        $program1 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant2->id,
            'fullname' => 'Test tenant groups',
            'autocreategroups' => (api::GROUPS_NONE + api::GROUPS_TENANT),
        ]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program1->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);
        $programuser3 = $this->generator->allocate_user_to_program($program1->get('id'), $user3->id);
        $programuser4 = $this->generator->allocate_user_to_program($program1->get('id'), $user4->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser2);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser3);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser4);

        // There should be 1 tenant group for program1 associated to one group and with 4 group members.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => null,
            'area' => null,
            'itemid' => null,
            'tenantid' => $tenant2->id,
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(4, $groupmembers);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($groupmembers, 'userid'));

        $tenantuser1 = \tool_tenant\tenant_user::get_record(['userid' => $user1->id]);
        $tenantuser1->set('tenantid', $defaulttenantid);
        $tenantuser1->update();

        // Call method passing one userid.
        api::remove_user_from_previous_tenant_groups($user1->id);

        // User1 should not be a member of the tenant group.
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers);
        $this->assertEqualsCanonicalizing([$user2->id, $user3->id, $user4->id], array_column($groupmembers, 'userid'));

        // Testing that method is called from the event observer.
        $manager = new \tool_tenant\manager();
        $manager->allocate_user($user2->id, $defaulttenantid, 'tool_program', '');
        $manager->allocate_user($user3->id, $defaulttenantid, 'tool_program', '');

        // User4 should be the only member of the tenant group.
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers);
        $this->assertEqualsCanonicalizing([$user4->id], array_column($groupmembers, 'userid'));
    }

    public function test_filter_by_hideprogramcourses(): void {
        $defaulttenantid = tenancy::get_default_tenant_id();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $this->tenantgenerator->allocate_user($user->id, $defaulttenantid);

        $program1 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 1',
            'tenantid' => $defaulttenantid,
        ]);
        $program2 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 2',
            'tenantid' => $defaulttenantid,
        ]);
        $program3 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 3',
            'tenantid' => $defaulttenantid,
        ]);

        // Add course1 to program1.
        $course1 = $this->getDataGenerator()->create_course();
        $programcourse1 = $this->generator->add_course_to_set($course1->id, $program1->get_base_set()->get('id'));

        // Add course2 to program2.
        $course2 = $this->getDataGenerator()->create_course();
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $program2->get_base_set()->get('id'));

        // Add course3 to program3.
        $course3 = $this->getDataGenerator()->create_course();
        $programcourse3 = $this->generator->add_course_to_set($course3->id, $program3->get_base_set()->get('id'));

        // Allocate user into program1, program2 and program3.
        $userdata = (object) [
            'userid' => $user->id,
            'certificationid' => 0,
        ];
        $programuser1 = api::allocate_user($program1, $userdata);
        $programuser2 = api::allocate_user($program2, $userdata);
        $programuser3 = api::allocate_user($program3, $userdata);
        $this->generator->enrol_user_to_program_course($programcourse1, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2);
        $this->generator->enrol_user_to_program_course($programcourse3, $programuser3);

        // Enrol user into two separate courses that do not belong to a program.
        $course4 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course4->id);
        $course5 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course5->id);

        $allcourses = [$course1, $course2, $course3, $course4, $course5];

        // Test with hideprogramcourses set to false. Should return all 5 courses.
        set_config('hideprogramcourses', 0, 'theme_workplace');
        $filteredcourses = api::filter_by_hideprogramcourses($allcourses);
        $this->assertEqualsCanonicalizing([$course1->id, $course2->id, $course3->id, $course4->id, $course5->id],
            array_column($filteredcourses, 'id'));

        // Test with hideprogramcourses set to true. Should return only course4 and course5 enrolments.
        set_config('hideprogramcourses', 1, 'theme_workplace');
        $filteredcourses = api::filter_by_hideprogramcourses($allcourses);
        $this->assertEqualsCanonicalizing([$course4->id, $course5->id], array_column($filteredcourses, 'id'));

        // Manual enrol user into course3, which belongs also to a program.
        $this->getDataGenerator()->enrol_user($user->id, $course3->id);
        $filteredcourses = api::filter_by_hideprogramcourses($allcourses);
        $this->assertEqualsCanonicalizing([$course3->id, $course4->id, $course5->id], array_column($filteredcourses, 'id'));
    }

    /**
     * Test for enable_program_course_enrol_instance()
     */
    public function test_enable_program_course_enrol_instance(): void {
        global $DB;

        // Method enable_program_course_enrol_instance is a private method.
        $reflector = new ReflectionClass('\tool_program\api');
        $method = $reflector->getMethod('enable_program_course_enrol_instance');
        $method->setAccessible(true);

        $defaulttenantid = tenancy::get_default_tenant_id();
        $course = self::getDataGenerator()->create_course();
        $program = $this->generator->generate_program((object) [
            'tenantid' => $defaulttenantid,
        ]);
        $params = [
            'courseid' => $course->id,
            'enrol' => 'program',
            'customint1' => $program->get('id'),
        ];

        // Assert program course enrol instance still does not exist.
        $currentenrolinstance = $DB->get_record('enrol', $params);
        $this->assertEmpty($currentenrolinstance);

        $instance = $method->invokeArgs(null, [$program->get('id'), $course]);
        $this->assertEquals('program', $instance->enrol);
        $this->assertEquals($course->id, $instance->courseid);

        // Assert program course enrol instance has been created and is enabled.
        $currentenrolinstance = $DB->get_record('enrol', $params);
        $this->assertEquals(ENROL_INSTANCE_ENABLED, $currentenrolinstance->status);

        // Disable existing program course enrol instance.
        $enrolplugin = enrol_get_plugin('program');
        $enrolplugin->update_status($currentenrolinstance, ENROL_INSTANCE_DISABLED);
        $currentenrolinstance = $DB->get_record('enrol', $params);
        $this->assertEquals(ENROL_INSTANCE_DISABLED, $currentenrolinstance->status);

        // Assert program course enrol instance has been enabled again.
        $instance = $method->invokeArgs(null, [$program->get('id'), $course]);
        $this->assertNotEmpty($instance);
        $currentenrolinstance = $DB->get_record('enrol', $params);
        $this->assertEquals(ENROL_INSTANCE_ENABLED, $currentenrolinstance->status);
    }

    /**
     * Test for enable_program_course_enrol_instance() when no student roles exist in the instance
     */
    public function test_enable_program_course_enrol_instance_no_student_role(): void {
        global $DB;

        $DB->delete_records('role', ['archetype' => 'student']);

        // Method enable_program_course_enrol_instance is a private method.
        $reflector = new ReflectionClass('\tool_program\api');
        $method = $reflector->getMethod('enable_program_course_enrol_instance');
        $method->setAccessible(true);

        $defaulttenantid = tenancy::get_default_tenant_id();
        $course = self::getDataGenerator()->create_course();
        $program = $this->generator->generate_program((object)[
            'tenantid' => $defaulttenantid,
        ]);

        $instance = $method->invokeArgs(null, [$program->get('id'), $course]);
        $this->assertNull($instance);
    }

    /**
     * Test recalculate_program_user_completion
     */
    public function test_recalculate_program_user_completion(): void {
        global $DB;
        $this->resetAfterTest();
        self::setAdminUser();

        $program = $this->generator->generate_program();
        $set = $this->generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $program->get_base_set()->get('id'),
            'sortorder' => 1,
        ]);

        // Add course1 to program base set.
        $course1 = $this->getDataGenerator()->create_course();
        $this->generator->add_course_to_set($course1->id, $program->get_base_set()->get('id'));

        // Add course2 to program set.
        $course2 = $this->getDataGenerator()->create_course();
        $this->generator->add_course_to_set($course2->id, $set->get('id'));

        $user = self::getDataGenerator()->create_user();
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user->id);
        $this->generator->complete_program($program, $user->id);

        // Set custom completion date to ensure program recompletion keeps the stored dates.
        $yesterday = time() - DAYSECS;
        $DB->set_field('tool_program_set_completion', 'completeddate', $yesterday, ['userid' => $user->id]);

        $completions = program_set_completion::get_records(['userid' => $user->id]);
        $this->assertCount(2, $completions);
        $this->assertEquals($yesterday, $completions[0]->get('completeddate'));
        $this->assertEquals($yesterday, $completions[1]->get('completeddate'));

        // The program and the set are marked as completed. Let's add a new course inside the set so the recaulculation removes
        // the completion from both sets.
        $course3 = $this->getDataGenerator()->create_course();
        $this->generator->add_course_to_set($course3->id, $set->get('id'));

        api::recalculate_program_user_completion($programuser1);

        // Both (program and set) completions have been removed. Course3 is now needed to be able to mark program as completed.
        $completions = program_set_completion::get_records(['userid' => $user->id]);
        $this->assertCount(0, $completions);
    }
}
