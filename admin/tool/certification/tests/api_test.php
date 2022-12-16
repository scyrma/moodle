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

namespace tool_certification;

use advanced_testcase;
use context_system;
use Exception;
use moodle_url;
use tool_certification_generator;
use tool_program_generator;
use tool_tenant_generator;
use core\event\base;
use core\event\calendar_event_created;
use tool_certification\event\certification_created;
use tool_certification\event\certification_deleted;
use tool_certification\event\certification_updated;
use tool_certification\event\user_allocation_created;
use tool_certification\event\user_allocation_deleted;
use tool_certification\event\certification_completion_created;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;
use tool_tenant\tenancy;

/**
 * API tests.
 *
 * @package    tool_certification
 * @covers     \tool_certification\api
 * @author     2018 Mitxel Moriana
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class api_test extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');

        // Stop clock during test execution if uopz extension is available.
        if (extension_loaded('uopz')) {
            // Use today midnight timestamp.
            $timestamp = strtotime('today');

            // Override time().
            uopz_set_return('time', $timestamp);

            // Override strtotime().
            $strtotime = function($t, $n = null){
                $n = $n ?? uopz_get_return('time');
                return strtotime($t, $n);
            };
            uopz_set_return('strtotime', $strtotime, true);
        }

        $this->resetAfterTest();
    }

    /**
     * tearDown.
     */
    public function tearDown(): void {
        if (extension_loaded('uopz')) {
            // Revert function overrides.
            uopz_unset_return('time');
            uopz_unset_return('strtotime');
        }
    }

    public function test_create_certification(): void {
        global $DB;
        self::setAdminUser();

        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certification = api::create_certification($certificationdata);

        $record = $DB->get_record('tool_certification', ['id' => $certification->get('id')]);
        $this->assertSame('A certification fullname', $record->fullname);
        $this->assertSame('1', $record->idnumber);
        $this->assertEquals('0', $record->archived);
        $this->assertEquals($certificationdata->shared, $record->shared);

        // Create from duplicate certification.
        $certificationdata->duplicatecertification = $certification->get('id');
        $certificationdata->allocationstartdatetype = null;
        $certificationdata->allocationstartdateabsolute = null;
        $certificationdata->allocationenddatetype = null;
        $certificationdata->allocationenddateabsolute = null;
        $certification2 = api::create_certification($certificationdata);
        $this->assertNotEquals($certification->get('id'), $certification2->get('id'));

        $record2 = $DB->get_record('tool_certification', ['id' => $certification2->get('id')]);
        $this->assertSame('A certification fullname', $record2->fullname);
        $this->assertEquals(1, $record2->idnumber);
        $this->assertEquals(0, $record2->archived);
        $this->assertSame($record->allocationstartdatetype, $record2->allocationstartdatetype);
        $this->assertSame($record->allocationstartdateabsolute, $record2->allocationstartdateabsolute);
        $this->assertEquals($record->allocationenddatetype, $record2->allocationenddatetype);
        $this->assertEquals($record->allocationenddateabsolute, $record2->allocationenddateabsolute);
    }

    public function test_create_certification_triggers_event(): void {
        global $USER;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        self::setUser($user);
        $programdata = $this->generator->get_dummy_program(['tenantid' => $tenant->id]);
        $program = \tool_program\api::create_program($programdata);

        $certificationdata = $this->generator->get_dummy_certificationdata(['tenantid' => $tenant->id]);
        $certificationdata->program = $program->get('id');

        $sink = $this->redirectEvents();
        $certification = api::create_certification($certificationdata);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(certification_created::class, $event);

        $program = $certification->get_certification_program();
        $certificationid = $certification->get('id');

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($certification->get('id'), $event->objectid);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals($program->get('id'), $event->other['programid']);

        // Test event get_name().
        $eventname = get_string('eventcertificationcreated', 'tool_certification');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '" . $user->id . "' created the certification with id '"
            . $certificationid . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/certification/index.php', ['id' => $certificationid]);
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    public function test_delete_certification(): void {
        global $DB;
        self::setAdminUser();
        $certification = $this->generator->generate_certification();
        $certificationid = $certification->get('id');

        // We allocate two users.
        $user1 = self::getDataGenerator()->create_user();
        $userdata1 = (object) [
            'certificationid' => $certificationid,
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
        ];

        $user2 = self::getDataGenerator()->create_user();
        $userdata2 = (object) [
            'certificationid' => $certificationid,
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
        ];

        api::allocate_user($certification, $userdata1);
        api::allocate_user($certification, $userdata2);

        // We test to delete without archiving first.
        try {
            api::delete_certification($certification);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $str = get_string('errorcantdeletenotarchivedcertification', 'tool_certification');
            $this->assertStringContainsString($str, $e->getMessage());
        }

        // We marked as archived.
        $certification->set('archived', 1);
        $certification->update();

        $params = [
            'component' => 'tool_certification',
            'componentarea' => 'certification',
            'itemid' => $certificationid,
        ];
        $this->assertTrue($DB->record_exists('tool_dynamicrule', $params));

        // We try to delete again.
        api::delete_certification($certification);

        $this->assertFalse($DB->record_exists('tool_certification', ['id' => $certificationid]));
        $this->assertFalse($DB->record_exists('tool_dynamicrule', $params));
    }

    public function test_delete_certification_triggers_event(): void {
        global $USER;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        self::setUser($user);
        $programdata = $this->generator->get_dummy_program(['tenantid' => $tenant->id]);
        $program = \tool_program\api::create_program($programdata);

        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certificationdata->program = $program->get('id');
        $certification = api::create_certification($certificationdata);

        $sink = $this->redirectEvents();

        // We test to delete without archiving first.
        try {
            api::delete_certification($certification);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $str = get_string('errorcantdeletenotarchivedcertification', 'tool_certification');
            $this->assertStringContainsString($str, $e->getMessage());
        }

        // We marked as archived.
        $certification->set('archived', 1);
        $certification->update();

        // We try to delete again.
        $certificationid = $certification->get('id');
        api::delete_certification($certification);

        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(certification_deleted::class, $event);

        $program = $certification->get_certification_program();

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($certificationid, $event->objectid);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals($program->get('id'), $event->other['programid']);

        // Test event get_name().
        $eventname = get_string('eventcertificationdeleted', 'tool_certification');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '" . $user->id . "' deleted the certification with id '"
            . $certificationid . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/certification/index.php');
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    public function test_allocate_user(): void {
        global $DB;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $certificationid = $certification->get('id');

        $originalstartdate = strtotime('-7 day');
        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
            'startdate' => $originalstartdate,
            'duedate' => strtotime('+7 day'),
        ];

        $certificationuser = api::allocate_user($certification, $userdata);
        $record = $DB->get_record('tool_certification_users', ['id' => $certificationuser->get('id')], '*', MUST_EXIST);
        $this->assertEquals($certificationid, $record->certificationid);
        $this->assertEquals($user->id, $record->userid);

        // Check allocation to related program has been also created.
        $params = [
            'programid' => $certification->get('program'),
            'certificationid' => $certificationid,
            'userid' => $user->id,
        ];
        $this->assertEquals(1, $DB->count_records('tool_program_users', $params));

        // Check that we cannot allocate same user again.
        try {
            api::allocate_user($certification, $userdata);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $str = get_string('erroruseralreadyallocatedincertification', 'tool_certification');
            $this->assertEquals($str, $e->getMessage());
        }

        // Check exception if program_user already exists.
        $certificationuser->delete();
        try {
            api::allocate_user($certification, $userdata);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $str = get_string('erroruseralreadyallocatedinprogram', 'tool_certification');
            $this->assertEquals($e->getMessage(), $str);
        }

        // Test recalculation of user start dates.
        $certdata = $certification->to_record();
        $certdata->certification_tags = ['hello', 'world'];
        $certdata->startdatetype = constants::DATE_USER_ALLOCATION_DATE;
        api::update_certification_calendar($certdata);
        $record = $DB->get_record('tool_program_users', ['userid' => $user->id, 'certificationid' => $certificationid]);
        // We cannot change startdate after it is in the past.
        $this->assertEquals($originalstartdate, $record->startdate);

        $programuser = program_user::get_record($params);
        $programuser->delete();

        $originalstartdate = strtotime('+1 day');
        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
            'startdate' => $originalstartdate,
            'duedate' => strtotime('+7 day'),
        ];

        $certification->set('startdatetype', constants::DATE_RELATIVE_TO_ALLOCATION_DATE);
        $certification->set('startdaterelative', '1 week');
        $certification->update();

        $certificationuser = api::allocate_user($certification, $userdata);

        // Current programid should be the initial one.
        $this->assertEquals($certification->get('program'), $certificationuser->get('currentprogramid'));

        $certdata->startdatetype = constants::DATE_RELATIVE_TO_ALLOCATION_DATE;
        $certdata->startdaterelative = '1 week';
        api::update_certification_calendar($certdata);
        $record = $DB->get_record('tool_program_users', ['userid' => $user->id, 'certificationid' => $certificationid]);
        $userstartdate = strtotime('+1 week', $record->timecreated);
        $this->assertEquals($userstartdate, $record->startdate);

        $certdata->startdatetype = 99;
        try {
            api::update_certification_calendar($certdata);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }

        // We certify and deallocate user.
        api::set_user_as_certified($user->id, $certificationid);
        api::deallocate_user($certificationid, $user->id);

        $certificationuser = api::allocate_user($certification, $userdata);

        // User is certified. Current programid should be null.
        $this->assertNull($certificationuser->get('currentprogramid'));
        $lastcompletion = api::get_last_completion_record($user->id, $certificationid);
        $nextstartdate = strtotime('-' . $certification->get('recertstartdaterelative'), $lastcompletion->get('expirydate'));
        $this->assertEquals($nextstartdate, $certificationuser->get('nextstartdate'));
    }

    public function test_allocate_user_and_immediately_certify(): void {
        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);

        // Create a certification, program and a course.
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $program = new \tool_program\persistent\program($certification->get('program'));

        // Allocate user1 to the program directly.
        $this->programgenerator->allocate_user_to_program($program->get('id'), $user1->id);
        // Make user1 complete the course.
        $this->programgenerator->complete_program($program, $user1->id);

        // Allocate user1 and user2 to the certification, user1 will immediately be marked as certified.
        $status = constants::STATUS_OVERRIDE_DEFAULT;
        $user1params = (object) ['userid' => $user1->id, 'certificationid' => $certification->get('id'), 'status' => $status];
        $user2params = (object) ['userid' => $user2->id, 'certificationid' => $certification->get('id'), 'status' => $status];

        api::allocate_user($certification, $user1params);
        api::allocate_user($certification, $user2params);

        $this->assertTrue(api::is_user_certified($user1->id, $certification->get('id')));
        $this->assertFalse(api::is_user_certified($user2->id, $certification->get('id')));
    }

    public function test_allocate_user_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $certification = $this->generator->generate_certification();
        $user = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
        ];

        $sink = $this->redirectEvents();
        $certuser = api::allocate_user($certification, $userdata);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(user_allocation_created::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($certuser->get('id'), $event->objectid);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals($certification->get('id'), $event->other['certificationid']);

        // Test event get_name().
        $eventname = get_string('eventuserallocated', 'tool_certification');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '" . $USER->id . "' allocated the user with id '" . $certuser->get('id') . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/certification/edit.php', ['id' => $certification->get('id')]);
        $this->assertEquals($eventurl, $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(base::NOT_MAPPED, $event::get_objectid_mapping());
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    public function test_deallocate_user(): void {
        global $DB;
        self::setAdminUser();
        $certification = $this->generator->generate_certification();
        $user = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        $certificationuser = api::allocate_user($certification, $userdata);
        $certificationuserid = $certificationuser->get('id');

        api::deallocate_user($certification->get('id'), $user->id);

        $this->assertFalse($DB->record_exists('tool_certification_users', ['id' => $certificationuserid]));
    }

    public function test_deallocate_user_triggers_event(): void {
        self::setAdminUser();
        $certification = $this->generator->generate_certification();
        $user = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        api::allocate_user($certification, $userdata);

        $sink = $this->redirectEvents();
        api::deallocate_user($certification->get('id'), $user->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertInstanceOf(\tool_program\event\user_allocation_deleted::class, $events[0]);
        $this->assertInstanceOf(user_allocation_deleted::class, $events[1]);
    }

    public function test_archive_certification(): void {
        self::setAdminUser();
        $certification = $this->generator->generate_certification(['archived' => false]);
        $this->assertFalse($certification->get('archived'));

        $return = api::archive_certification($certification->get('id'));
        $this->assertTrue($return);

        $certification->read();
        $this->assertTrue($certification->get('archived'));

        // Test that user can not get certified if certification is archived.
        [$tenant1, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);

        $course1 = $this->programgenerator->generate_course_with_completion_self();
        $program1 = $this->programgenerator->generate_program((object)['tenantid' => $tenant1->id]);
        $programcourse12 = $this->programgenerator->add_course_to_set($course1->id, $program1->get_base_set()->get('id'));

        $certification3 = $this->generator->generate_certification([
            'tenantid' => $tenant1->id,
            'program' => $program1->get('id'),
        ]);

        $this->generator->allocate_user($user1->id, $certification3->get('id'));
        $this->generator->allocate_user($user2->id, $certification3->get('id'));
        \tool_program\api::enrol_in_program_course($program1->get('id'), $programcourse12->get_course(), $user1->id);
        \tool_program\api::enrol_in_program_course($program1->get('id'), $programcourse12->get_course(), $user2->id);

        // Archive certification.
        api::archive_certification($certification3->get('id'));

        // As a user1 complete the course.
        $this->programgenerator->complete_courses([$course1->id], $user1->id);

        // Make sure user1 is NOT certified in certification3 (program was hidden).
        $this->assertFalse(api::is_user_certified($user1->id, $certification3->get('id')));

        // Restore certification.
        api::restore_certification($certification3->get('id'));

        // As a user2 complete the course.
        $this->programgenerator->complete_courses([$course1->id], $user2->id);

        // Make sure user2 IS certified in certification3.
        $this->assertTrue(api::is_user_certified($user2->id, $certification3->get('id')));
    }

    public function test_archive_certification_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $certification = $this->generator->generate_certification(['archived' => false]);
        $certificationid = $certification->get('id');

        $sink = $this->redirectEvents();
        api::archive_certification($certificationid);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(certification_updated::class, $event);
        $eventdescription = "The user with id '$USER->id' has archived the certification with id '$certificationid'.";
        $this->assertEquals($eventdescription, $event->get_description());
    }

    public function test_restore_certification(): void {
        self::setAdminUser();
        $certification = $this->generator->generate_certification(['archived' => 1]);
        $certificationid = $certification->get('id');

        $this->assertTrue($certification->get('archived'));

        $return = api::restore_certification($certificationid);
        $this->assertTrue($return);

        $certification = new certification($certificationid);
        $this->assertFalse($certification->get('archived'));
    }

    public function test_restore_certification_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $certification = $this->generator->generate_certification(['archived' => 1]);
        $certificationid = $certification->get('id');

        $sink = $this->redirectEvents();
        api::restore_certification($certificationid);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(certification_updated::class, $event);
        $eventdescription = "The user with id '$USER->id' has restored the certification with id '$certificationid'.";
        $this->assertEquals($eventdescription, $event->get_description());
    }

    public function test_restore_certification_triggers_certification_completion_calculations(): void {
        // Create certification and allocate users to the certification.
        $certification = $this->generator->generate_certification();
        $program = $certification->get_certification_program();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        api::allocate_user($certification, (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ]);
        api::allocate_user($certification, (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ]);

        // Archive the certification.
        $certification->set('archived', 1);
        $certification->update();

        // Mark the program related to the certification as completed by the users.
        $this->programgenerator->complete_program($program, $user1->id);
        $this->programgenerator->complete_program($program, $user2->id);

        // Restoring the certification should trigger the certification completions for both allocated users.
        $sink = $this->redirectEvents();
        api::restore_certification($certification->get('id'));
        $events = $sink->get_events();
        $sink->close();
        $events = array_filter($events, static function($event) {
            return $event instanceof certification_completion_created;
        });
        $this->assertNotEmpty($events);
        $this->assertCount(2, $events);
    }

    public function test_update_certification_calendar(): void {
        self::setAdminUser();
        $certificationdata = $this->generator->get_dummy_certificationdata();

        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');

        $startdatetype = $certification->get('allocationstartdatetype');
        $this->assertEquals(constants::DATE_NONE, $startdatetype);
        $startdateabsolute = $certification->get('allocationstartdateabsolute');
        $this->assertEquals('0', $startdateabsolute);
        $enddatetype = $certification->get('allocationenddatetype');
        $this->assertEquals(constants::ALLOCATION_NOT_SET, $enddatetype);
        $enddateabsolute = $certification->get('allocationenddateabsolute');
        $this->assertEquals('0', $enddateabsolute);

        $now = time();
        $newdates = (object) [
            'id' => $certificationid,
            'allocationstartdatetype' => constants::DATE_ABSOLUTE,
            'allocationstartdateabsolute' => $now,
            'allocationenddatetype' => constants::ALLOCATION_SET,
            'allocationenddateabsolute' => $now,
            'startdatetype' => constants::DATE_NONE,
            'startdaterelative' => '1 day',
            'startdateabsolute' => $now,
            'duedatetype' => constants::DATE_AFTER_START_DATE,
            'duedaterelative' => '1 week',
            'duedateabsolute' => $now,
            'expirydatetype' => constants::DATE_NONE,
            'expirydaterelative' => '1 year',
            'expirydateabsolute' => $now,
            'program' => $certificationdata->program,
            'autocreategroups' => 0,
        ];
        api::update_certification_calendar($newdates);

        $certification = new certification($certificationid);

        $this->assertEquals(constants::DATE_ABSOLUTE, $certification->get('allocationstartdatetype'));
        $this->assertEquals($now, $certification->get('allocationstartdateabsolute'));
        $this->assertEquals(constants::ALLOCATION_SET, $certification->get('allocationenddatetype'));
        $this->assertEquals($now, $certification->get('allocationenddateabsolute'));
        $this->assertEquals(constants::DATE_NONE, $certification->get('startdatetype'));
        $this->assertEquals(constants::DATE_AFTER_START_DATE, $certification->get('duedatetype'));
        $this->assertEquals(constants::DATE_NONE, $certification->get('expirydatetype'));
        $this->assertEquals($now, $certification->get('startdateabsolute'));
        $this->assertEquals($now, $certification->get('expirydateabsolute'));
        $this->assertEquals('1 day', $certification->get('startdaterelative'));
        $this->assertEquals('1 week', $certification->get('duedaterelative'));
        $this->assertEquals('1 year', $certification->get('expirydaterelative'));
    }

    public function test_update_certification_calendar_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $certificationdata = $this->generator->get_dummy_certificationdata();

        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');

        $now = time();
        $newdates = (object) [
            'id' => $certificationid,
            'allocationstartdatetype' => constants::DATE_ABSOLUTE,
            'allocationstartdateabsolute' => $now,
            'allocationenddatetype' => constants::ALLOCATION_SET,
            'allocationenddateabsolute' => $now,
            'startdatetype' => $certificationdata->startdatetype,
            'startdaterelative' => $certificationdata->startdaterelative,
            'startdateabsolute' => $certificationdata->startdateabsolute,
            'duedatetype' => $certificationdata->duedatetype,
            'duedaterelative' => $certificationdata->duedaterelative,
            'duedateabsolute' => $certificationdata->duedateabsolute,
            'expirydatetype' => $certificationdata->expirydatetype,
            'expirydaterelative' => $certificationdata->expirydaterelative,
            'expirydateabsolute' => $certificationdata->expirydateabsolute,
            'program' => $certificationdata->program,
            'autocreategroups' => 0,
        ];

        $sink = $this->redirectEvents();
        api::update_certification_calendar($newdates);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(certification_updated::class, $event);
        $eventdescription = "The user with id '$USER->id' updated the certification with id '$certificationid'.";
        $this->assertEquals($eventdescription, $event->get_description());
    }

    public function test_user_can_be_allocated(): void {
        self::setAdminUser();

        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certification = api::create_certification($certificationdata);

        $canbeallocated = api::is_certification_allocation_open($certification);
        $this->assertTrue($canbeallocated);

        // Allocation starts in 1 day.
        $onedaymore = strtotime(' +1 day');
        $twodaysmore = strtotime(' +2 day');
        $certification->set('allocationstartdatetype', constants::ALLOCATION_SET);
        $certification->set('allocationstartdateabsolute', $onedaymore);
        $certification->set('allocationenddatetype', constants::ALLOCATION_SET);
        $certification->set('allocationenddateabsolute', $twodaysmore);
        $certification->update();

        $canbeallocated = api::is_certification_allocation_open($certification);
        $this->assertFalse($canbeallocated);

        // Allocation started 1 day ago.
        $onedayless = strtotime(' -1 day');
        $twodaysmore = strtotime(' +2 day');
        $certification->set('allocationstartdatetype', constants::ALLOCATION_SET);
        $certification->set('allocationstartdateabsolute', $onedayless);
        $certification->set('allocationenddatetype', constants::ALLOCATION_SET);
        $certification->set('allocationenddateabsolute', $twodaysmore);
        $certification->update();

        $canbeallocated = api::is_certification_allocation_open($certification);
        $this->assertTrue($canbeallocated);

        // Allocation start date is not set.
        $twodaysmore = strtotime(' +2 day');
        $certification->set('allocationstartdatetype', constants::ALLOCATION_NOT_SET);
        $certification->set('allocationstartdateabsolute', '0');
        $certification->set('allocationenddatetype', constants::ALLOCATION_SET);
        $certification->set('allocationenddateabsolute', $twodaysmore);
        $certification->update();

        $canbeallocated = api::is_certification_allocation_open($certification);
        $this->assertTrue($canbeallocated);

        // Allocation end date is not set.
        $onedayless = strtotime(' -1 day');
        $certification->set('allocationstartdatetype', constants::ALLOCATION_SET);
        $certification->set('allocationstartdateabsolute', $onedayless);
        $certification->set('allocationenddatetype', constants::ALLOCATION_NOT_SET);
        $certification->set('allocationenddateabsolute', '0');
        $certification->update();

        $canbeallocated = api::is_certification_allocation_open($certification);
        $this->assertTrue($canbeallocated);
    }

    public function test_set_user_as_certified_triggers_event(): void {
        global $DB;
        self::setAdminUser();

        $certification = $this->generator->generate_certification();
        $certificationid = $certification->get('id');
        $user = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];

        $certuser = api::allocate_user($certification, $userdata);
        $params = ['certificationid' => $certificationid, 'userid' => $user->id, 'timerevoked' => 0];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertFalse($record);

        $sink = $this->redirectEvents();
        api::set_user_as_certified($user->id, $certificationid);
        $events = $sink->get_events();
        $sink->close();

        $this->assertInstanceOf(certification_completion_created::class, $events[0]);
        $this->assertInstanceOf(calendar_event_created::class, $events[1]);
    }

    public function test_set_user_as_certified(): void {
        global $DB;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id], true);
        $certificationid = $certification->get('id');

        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];

        $certuser = api::allocate_user($certification, $userdata);

        // We try with currentprogramid null.
        $certuser->set('currentprogramid', null);
        $certuser->update();
        try {
            api::set_user_as_certified($user->id, $certificationid);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
            $this->assertStringContainsString('current program id should not be null', $e->getMessage());
        }

        $certuser->set('currentprogramid', $certification->get('program'));
        $certuser->update();

        api::set_user_as_certified($user->id, $certificationid);

        $params = ['userid' => $user->id, 'certificationid' => $certificationid, 'timerevoked' => 0, 'islast' => 1];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertNotEmpty($record);

        $params = ['userid' => $user->id, 'certificationid' => $certificationid, 'programid' => $certification->get('program')];
        $proguser = program_user::get_record($params);
        $this->assertNotEmpty($proguser);

        // Test passing an expirydate.
        $certuser->set('currentprogramid', $certification->get('program'));
        $certuser->update();
        $expirydate = strtotime('+2 day');
        $certified = strtotime('-2 day');
        $certifiedby = 33;
        $params = ['userid' => $user->id, 'certificationid' => $certificationid, 'timerevoked' => 0, 'islast' => 1];
        $DB->delete_records('tool_certification_compltion', $params);

        api::set_user_as_certified($user->id, $certificationid, $expirydate, $certified, $certifiedby);

        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertEquals($expirydate, $record->expirydate);
        $this->assertEquals($certified, $record->timecertified);
        $this->assertEquals($certifiedby, $record->certifiedby);
        $this->assertEquals($certification->get('program'), $record->programid);

        $userdata = ['userid' => $user->id, 'certificationid' => $certificationid];
        $certificationuser = $DB->get_record('tool_certification_users', $userdata);
        $this->assertNull($certificationuser->currentprogramid);
        $nextstartdate = strtotime('-' . $certification->get('recertstartdaterelative'), $expirydate);
        $this->assertEquals($nextstartdate, $certificationuser->nextstartdate);

        // Recertification.
        api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user->id,
            constants::STATUS_OVERRIDE_DEFAULT);
        api::set_user_as_certified($user->id, $certificationid, $expirydate, $certified, $certifiedby);

        $params2 = ['userid' => $user->id, 'certificationid' => $certificationid, 'timerevoked' => 0, 'islast' => 0];
        $firstcompletion = $DB->get_record('tool_certification_compltion', $params2);
        $this->assertEquals($record->id, $firstcompletion->id);
        $secondcompletion = $DB->get_record('tool_certification_compltion', $params);
        $this->assertEquals($certification->get('recertificationprogram'), $secondcompletion->programid);
    }

    public function test_set_user_as_certified_after_prev_completion_date(): void {
        global $DB;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id], true);
        $certification->set('expirydatetype', constants::DATE_AFTER_COMPLETION);
        $certification->set('expirydaterelative', '1 day');
        $certification->set('recertexpirydatetype', constants::RECERT_EXPIRY_DATE_AFTR_PREV_COMPL);
        $certification->set('recertexpirydaterelative', '2 day');
        $certification->update();
        $certificationid = $certification->get('id');

        $userdata = (object)[
            'certificationid' => $certificationid,
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        api::allocate_user($certification, $userdata);

        // Complete certification (first round).
        api::set_user_as_certified($user->id, $certificationid);

        $params = ['islast' => 1, 'certificationid' => $certificationid, 'userid' => $user->id];
        $record = $DB->get_record('tool_certification_compltion', $params);
        // Expiry date should be set 1 day from now (which is the stored timecertified).
        $this->assertEquals(strtotime('+1 day', $record->timecertified), $record->expirydate);

        // Complete recertification (second round).
        api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user->id,
            constants::STATUS_OVERRIDE_DEFAULT);
        // Now recertification settings should take in place when calculating expiry date.
        api::set_user_as_certified($user->id, $certificationid);

        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertEquals(strtotime('+2 day', $record->timecertified), $record->expirydate);

        // Complete recertification (third round).
        api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user->id,
            constants::STATUS_OVERRIDE_DEFAULT);
        $onedayless = strtotime('-1 day');
        api::set_user_as_certified($user->id, $certificationid, null, $onedayless);

        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertEquals(strtotime('+2 day', $onedayless), $record->expirydate);
    }

    /**
     * Test for 'recertification expiry date after previous expiry date' option
     */
    public function test_set_user_as_certified_after_prev_expiry_date(): void {
        global $DB;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id], true);
        $certification->set('expirydatetype', constants::DATE_AFTER_COMPLETION);
        $certification->set('expirydaterelative', '1 day');
        $certification->set('recertexpirydatetype', constants::RECERT_EXPIRY_DATE_AFTR_PREV_EXP);
        $certification->set('recertexpirydaterelative', '2 day');
        $certification->update();
        $certificationid = $certification->get('id');

        $userdata = (object)[
            'certificationid' => $certificationid,
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        api::allocate_user($certification, $userdata);

        // Complete certification (first round).
        api::set_user_as_certified($user->id, $certificationid);

        $params = ['islast' => 1, 'certificationid' => $certificationid, 'userid' => $user->id];
        $record = $DB->get_record('tool_certification_compltion', $params);
        // Expiry date should be set 1 day from now (which is the stored timecertified).
        $expectedexpirydate = strtotime('+1 day', $record->timecertified);
        $this->assertEquals($expectedexpirydate, $record->expirydate);

        // Complete recertification (second round).
        api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user->id,
            constants::STATUS_OVERRIDE_DEFAULT);
        // Now recertification settings should take in place when calculating expiry date.
        api::set_user_as_certified($user->id, $certificationid);

        $record = $DB->get_record('tool_certification_compltion', $params);
        // Expiry date should be set 2 days from last expiry date.
        $expectedexpirydate = strtotime('+2 day', $expectedexpirydate);
        $this->assertEquals($expectedexpirydate, $record->expirydate);

        // Complete recertification (third round).
        api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user->id,
            constants::STATUS_OVERRIDE_DEFAULT);
        $onedayless = strtotime('-1 day');
        api::set_user_as_certified($user->id, $certificationid, null, $onedayless);

        $record = $DB->get_record('tool_certification_compltion', $params);
        // Expiry date should be set 2 days from last expiry date.
        $expectedexpirydate = strtotime('+2 day', $expectedexpirydate);
        $this->assertEquals($expectedexpirydate, $record->expirydate);

        // Complete recertification (fourth round changing certification settings).
        $certification->set('recertexpirydaterelative', '7 day');
        $certification->update();
        api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user->id,
            constants::STATUS_OVERRIDE_DEFAULT);
        $onedayless = strtotime('-1 day');
        api::set_user_as_certified($user->id, $certificationid, null, $onedayless);

        $record = $DB->get_record('tool_certification_compltion', $params);
        // Expiry date should be set 7 days from last expiry date.
        $expectedexpirydate = strtotime('+7 day', $expectedexpirydate);
        $this->assertEquals($expectedexpirydate, $record->expirydate);
    }

    /**
     * Test for 'recertification expiry date After the latter of the current completion or expiration' option
     */
    public function test_set_user_as_certified_after_latter_or_current_completion(): void {
        global $DB;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $params = [
            'tenantid' => $tenant->id,
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertexpirydatetype' => constants::RECERT_EXPIRY_DATE_AFTR_LATEST,
            'recertexpirydaterelative' => '5 day',
        ];
        $certification = $this->generator->generate_certification($params, true);
        $certificationid = $certification->get('id');

        $userdata = (object)[
            'certificationid' => $certificationid,
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        api::allocate_user($certification, $userdata);

        // Complete certification (first round).
        api::set_user_as_certified($user->id, $certificationid);

        $params = ['islast' => 1, 'certificationid' => $certificationid, 'userid' => $user->id];
        $lastcompletion = certification_completion::get_record($params);
        // Expiry date should be set 1 day from now (which is the stored timecertified).
        $expectedexpirydate = strtotime('+1 day', $lastcompletion->get('timecertified'));
        $this->assertEquals($expectedexpirydate, $lastcompletion->get('expirydate'));

        // Complete recertification (second round).
        api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user->id,
            constants::STATUS_OVERRIDE_DEFAULT);
        // Now recertification settings should take in place when calculating expiry date.
        api::set_user_as_certified($user->id, $certificationid);

        $lastcompletion = certification_completion::get_record($params);
        // Expiry date should be set 5 days from last expiry date (Expiry date is later than timecertified).
        $expectedexpirydate = strtotime('+5 day', $expectedexpirydate);
        $this->assertEquals($expectedexpirydate, $lastcompletion->get('expirydate'));

        // Set manually a new timecertified 12 days in the future so is later than expirydate.
        $newtimecertified = time() + 12 * DAYSECS;
        $DB->set_field('tool_certification_compltion', 'timecertified', $newtimecertified, $params);

        // Complete recertification (third round).
        api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user->id,
            constants::STATUS_OVERRIDE_DEFAULT);
        api::set_user_as_certified($user->id, $certificationid, null, strtotime('-1 day'));

        $lastcompletion = certification_completion::get_record($params);
        // Now timecertified is later than expirydate and should have be taken into consideration to calculate the new expiry date.
        $newtimecertified = strtotime('+5 day', $newtimecertified);
        $this->assertEquals($newtimecertified, $lastcompletion->get('expirydate'));
    }

    public function test_set_certification_completed_by_user_and_program(): void {
        global $DB;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);

        $programdata = $this->generator->get_dummy_program(['tenantid' => $tenant->id]);
        $program = new program(0, $programdata);
        $program->create();
        $programid = $program->get('id');

        $baseset = (object) [
            'programid' => $programid,
            'parent' => 0,
            'name' => 'A base set',
            'sortorder' => 1,
        ];
        $set = new program_set(0, $baseset);
        $set->create();

        $params = ['tenantid' => $tenant->id, 'program' => $programid];
        $certification1 = $this->generator->generate_certification($params);
        $certification2 = $this->generator->generate_certification($params);

        $userdata = (object) [
            'certificationid' => $certification1->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        api::allocate_user($certification1, $userdata);
        $userdata2 = (object) [
            'certificationid' => $certification2->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        api::allocate_user($certification2, $userdata2);

        api::set_certification_completed_by_user_and_program($user->id, $program->get('id'));
        $params = ['userid' => $user->id, 'certificationid' => $certification1->get('id'), 'timerevoked' => 0];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertNotEmpty($record);
        $params = ['userid' => $user->id, 'certificationid' => $certification2->get('id'), 'timerevoked' => 0];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertNotEmpty($record);
    }

    public function test_update_certification_user_dates_and_status(): void {
        $certification = $this->generator->generate_certification();
        $user = self::getDataGenerator()->create_user();
        $sevendays = strtotime('-7 day');
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'startdate' => $sevendays,
            'startdatelocked' => constants::DATE_LOCKED,
        ];
        $certificationuser = api::allocate_user($certification, $userdata);
        $programuser = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user->id]);

        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $certificationuser->get('status'));
        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $programuser->get('status'));
        $this->assertEquals($sevendays, $programuser->get('startdate'));
        $this->assertEquals(constants::DATE_LOCKED, (int)$programuser->get('startdatelocked'));

        $now = time();
        $onedaymore = strtotime('+1 day', $now);
        // Test with fixed absolute dates on certification.
        $onedayless = strtotime(' -1 day');

        $data = (object) [
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdatelocked' => constants::DATE_LOCKED,
            'startdate' => $onedayless,
            'duedatelocked' => constants::DATE_LOCKED,
            'duedate' => $onedaymore,
            'currentprogramid' => $certificationuser->get('currentprogramid'),
            'isrecertification' => $certificationuser->get('isrecertification'),
        ];
        api::update_certification_user_dates_and_status($certificationuser, $data);

        $certificationid = $certification->get('id');
        $certificationuser = certification_user::get_record(['certificationid' => $certificationid, 'userid' => $user->id]);
        $programuser = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user->id]);
        $this->assertEquals(constants::STATUS_OVERRIDE_SUSPENDED, $certificationuser->get('status'));
        $this->assertEquals(constants::STATUS_OVERRIDE_SUSPENDED, $programuser->get('status'));
        $this->assertEquals($onedayless, $programuser->get('startdate'));
        $this->assertEquals(constants::DATE_LOCKED, $programuser->get('startdatelocked'));
        $this->assertEquals($onedaymore, $programuser->get('duedate'));
        $this->assertEquals(constants::DATE_LOCKED, $programuser->get('duedatelocked'));

        // Test with relative dates on certification.
        $certification->set('startdatetype', constants::DATE_ABSOLUTE);
        $certification->set('startdateabsolute', $now);
        $certification->set('duedatetype', constants::DATE_AFTER_START_DATE);
        $certification->set('duedaterelative', '1 day');
        $certification->update();

        $data = (object) [
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'startdatelocked' => constants::DATE_UNLOCKED,
            'startdate' => 0,
            'duedatelocked' => constants::DATE_UNLOCKED,
            'duedate' => 0,
            'currentprogramid' => $certificationuser->get('currentprogramid'),
            'isrecertification' => $certificationuser->get('isrecertification'),
        ];
        api::update_certification_user_dates_and_status($certificationuser, $data);

        $certificationid = $certification->get('id');
        $certificationuser = certification_user::get_record(['certificationid' => $certificationid, 'userid' => $user->id]);
        $programuser = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user->id]);
        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $certificationuser->get('status'));
        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $programuser->get('status'));
        $this->assertEquals($now, $programuser->get('startdate'));
        $this->assertEquals(constants::DATE_UNLOCKED, $programuser->get('startdatelocked'));
        $this->assertEquals($onedaymore, $programuser->get('duedate'));
        $this->assertEquals(constants::DATE_UNLOCKED, $programuser->get('duedatelocked'));
    }

    public function test_reactivate_user_triggers_certification_completion_calculation(): void {
        // Create certification and allocate user to the certification as a __suspended__ allocation.
        $certification = $this->generator->generate_certification();
        $program = $certification->get_certification_program();
        $user = self::getDataGenerator()->create_user();
        $certificationuser = api::allocate_user($certification, (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
        ]);

        // Complete all courses in the program.
        $this->programgenerator->complete_program($program, $user->id);
        // At this moment program is not completed because the allocation was not active.
        $this->assertFalse(api::is_program_completed($program->get('id'), $user->id));

        // Updating user allocation while keeping status as suspended should not trigger the certification completion.
        $sink = $this->redirectEvents();
        api::update_certification_user_dates_and_status($certificationuser, (object) [
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdate' => 12345,
            'startdatelocked' => constants::DATE_LOCKED,
            'duedate' => 67890,
            'duedatelocked' => constants::DATE_LOCKED,
            'currentprogramid' => $certificationuser->get('currentprogramid'),
            'isrecertification' => $certificationuser->get('isrecertification'),
        ]);
        $events = $sink->get_events();
        $sink->close();
        $events = array_filter($events, static function($event) {
            return $event instanceof certification_completion_created;
        });
        $this->assertEmpty($events);

        // Updating user allocation and re-activating the user allocation should trigger the certification completion.
        $sink = $this->redirectEvents();
        api::update_certification_user_dates_and_status($certificationuser, (object) [
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'startdate' => 0,
            'startdatelocked' => constants::DATE_LOCKED,
            'duedate' => 0,
            'duedatelocked' => constants::DATE_LOCKED,
            'currentprogramid' => $certificationuser->get('currentprogramid'),
            'isrecertification' => $certificationuser->get('isrecertification'),
        ]);
        $events = $sink->get_events();
        $sink->close();
        $events = array_filter($events, static function($event) {
            return $event instanceof certification_completion_created;
        });
        $this->assertNotEmpty($events);
        $this->assertCount(1, $events);
    }

    /**
     * Test get_user_allocation_status api method.
     * NOTE: this unittest cannot be modified without making sure that block_mylearning continues working.
     */
    public function test_get_user_allocation_status(): void {
        $user = self::getDataGenerator()->create_user();
        $certification = $this->generator->generate_certification();

        $now = time();
        $twodaysless = strtotime('-2 day', $now);
        $onedaymore = strtotime('+1 day', $now);
        $certification->set('startdatetype', constants::DATE_ABSOLUTE);
        $certification->set('startdateabsolute', $now);
        $certification->set('startdaterelative', '0');
        $certification->set('duedatetype', constants::DATE_AFTER_START_DATE);
        $certification->set('duedateabsolute', 0);
        $certification->set('duedaterelative', '1 day');
        $certification->set('expirydatetype', constants::DATE_AFTER_ALLOCATION_DATE);
        $certification->set('expirydateabsolute', 0);
        $certification->set('expirydaterelative', '1 year');
        $certification->update();

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        api::allocate_user($certification, $userdata);

        $userstatus = api::get_user_allocation_status($certification->get('id'), $user->id);
        // Status should be Open.
        $this->assertEquals(constants::STATUS_OPEN, $userstatus[0]['status']);
        $this->assertEquals('open', $userstatus[0]['stringid']);

        // Future allocation status.
        $certification->set('startdateabsolute', $onedaymore);
        $certification->update();

        $user2 = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        api::allocate_user($certification, $userdata);
        $userstatus = api::get_user_allocation_status($certification->get('id'), $user2->id);
        $this->assertEquals(constants::STATUS_FUTUREALLOCATION, $userstatus[0]['status']);
        $this->assertEquals('futureallocation', $userstatus[0]['stringid']);

        // Overdue status.
        $certification->set('startdateabsolute', $twodaysless);
        $certification->update();

        $user3 = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user3->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        $certuser = api::allocate_user($certification, $userdata);
        $userstatus = api::get_user_allocation_status($certification->get('id'), $user3->id);
        $this->assertEquals(constants::STATUS_OVERDUE, $userstatus[0]['status']);
        $this->assertEquals('overdue', $userstatus[0]['stringid']);

        // Suspended status.
        $data = (object) [
            'status' => constants::STATUS_OVERRIDE_SUSPENDED,
            'startdatelocked' => 0,
            'startdate' => 0,
            'duedatelocked' => 0,
            'duedate' => 0,
            'currentprogramid' => $certuser->get('currentprogramid'),
            'isrecertification' => $certuser->get('isrecertification'),
        ];
        api::update_certification_user_dates_and_status($certuser, $data);

        $userstatus = api::get_user_allocation_status($certification->get('id'), $user3->id);
        $this->assertEquals(constants::STATUS_SUSPENDED, $userstatus[0]['status']);
        $this->assertEquals('suspended', $userstatus[0]['stringid']);

        // Certified status.
        $user4 = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user4->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'expirydate' => 0,
            'timerevoked' => 0,
        ];
        api::allocate_user($certification, $userdata);
        api::set_user_as_certified($user4->id, $certification->get('id'));
        $userstatus = api::get_user_allocation_status($certification->get('id'), $user4->id);
        $this->assertEquals(constants::STATUS_CERTIFIED, $userstatus[0]['status']);
        $this->assertEquals('certified', $userstatus[0]['stringid']);

        // Certified and supended status.
        $user4 = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user4->id,
            'status' => constants::STATUS_SUSPENDED,
            'expirydate' => 0,
        ];
        api::allocate_user($certification, $userdata);
        api::set_user_as_certified($user4->id, $certification->get('id'));
        $userstatus = api::get_user_allocation_status($certification->get('id'), $user4->id);
        $this->assertEquals(constants::STATUS_SUSPENDED, $userstatus[0]['status']);
        $this->assertEquals('suspended', $userstatus[0]['stringid']);
        $this->assertEquals(constants::STATUS_CERTIFIED, $userstatus[1]['status']);
        $this->assertEquals('certified', $userstatus[1]['stringid']);

        // Expired status.
        $certification->set('expirydatetype', constants::DATE_ABSOLUTE);
        $certification->set('expirydateabsolute', strtotime(' -2 day'));
        $certification->set('expirydaterelative', '1 year');
        $certification->update();
        $user5 = self::getDataGenerator()->create_user();
        $expirydate = strtotime(' -2 day');
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user5->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'expirydate' => $expirydate,
        ];
        api::allocate_user($certification, $userdata);
        api::set_user_as_certified($user5->id, $certification->get('id'), $expirydate);
        $userstatus = api::get_user_allocation_status($certification->get('id'), $user5->id);
        $this->assertEquals(constants::STATUS_EXPIRED, $userstatus[0]['status']);
        $this->assertEquals('expired', $userstatus[0]['stringid']);
    }

    /**
     * Test get_user_allocations api method.
     * NOTE: this unittest cannot be modified without making sure that block_mylearning continues working.
     */
    public function test_get_user_allocations(): void {
        $certification = $this->generator->generate_certification();
        $certificationid = $certification->get('id');
        $user = self::getDataGenerator()->create_user();
        $userid = $user->id;
        $certificationusers = $this->generator->allocate_users_to_certification($certificationid, [$userid]);
        $certificationuser = $certificationusers[$userid];

        $userallocations = api::get_user_allocations($userid);
        $this->assertCount(1, $userallocations);
        $userallocation = reset($userallocations);

        $this->assertEquals($certificationuser->get('id'), $userallocation->get('id'));
    }

    public function test_certification_exists_in_tenant(): void {
        /** @var tool_tenant_generator $datagenerator */
        $datagenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');

        $defaulttenantid = tenancy::get_default_tenant_id();
        $othertenantid = $datagenerator->create_tenant()->id;
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $datagenerator->allocate_user($user1->id, $defaulttenantid);
        $datagenerator->allocate_user($user2->id, $othertenantid);

        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certificationdata->tenantid = $defaulttenantid;
        $certification = api::create_certification($certificationdata);

        $exists = api::certification_exists_in_tenant($certification->get('id'), $user1->id);
        $this->assertTrue($exists);
        $exists = api::certification_exists_in_tenant($certification->get('id'), $user2->id);
        $this->assertFalse($exists);
    }

    public function test_is_user_certified(): void {
        self::setAdminUser();
        $certification = $this->generator->generate_certification();
        $certificationid = $certification->get('id');
        $user = self::getDataGenerator()->create_user();

        $iscertified = api::is_user_certified($user->id, $certificationid);
        $this->assertFalse($iscertified);

        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];

        api::allocate_user($certification, $userdata);
        api::set_user_as_certified($user->id, $certificationid);

        $iscertified = api::is_user_certified($user->id, $certificationid);
        $this->assertTrue($iscertified);

        // Test that user can not get certified if program is hidden.
        [$tenant1, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        $course1 = $this->programgenerator->generate_course_with_completion_self();
        $program1 = $this->programgenerator->generate_program((object)['tenantid' => $tenant1->id]);
        $programcourse12 = $this->programgenerator->add_course_to_set($course1->id, $program1->get_base_set()->get('id'));

        $certification3 = $this->generator->generate_certification([
            'tenantid' => $tenant1->id,
            'program' => $program1->get('id')
        ]);

        $this->generator->allocate_user($user1->id, $certification3->get('id'));
        $this->generator->allocate_user($user2->id, $certification3->get('id'));
        \tool_program\api::enrol_in_program_course($program1->get('id'), $programcourse12->get_course(), $user1->id);
        \tool_program\api::enrol_in_program_course($program1->get('id'), $programcourse12->get_course(), $user2->id);

        // Hide program.
        \tool_program\api::update_program_visibility($program1, \tool_program\constants::VISIBILITY_HIDDEN);

        // As a user1 complete the course.
        $this->programgenerator->complete_courses([$course1->id], $user1->id);

        // Make sure user1 is NOT certified in certification3 (program was hidden).
        $this->assertFalse(api::is_user_certified($user1->id, $certification3->get('id')));

        // Show the program.
        \tool_program\api::update_program_visibility($program1, \tool_program\constants::VISIBILITY_AVAILABLE);

        // As a user2 complete the course.
        $this->programgenerator->complete_courses([$course1->id], $user2->id);

        // Make sure user2 IS certified in certification3.
        $this->assertTrue(api::is_user_certified($user2->id, $certification3->get('id')));
    }

    public function test_update_certification_details(): void {
        $data = $this->generator->get_dummy_certificationdata();
        $certification = api::create_certification($data);
        $data->id = $certification->get('id');

        $this->assertEquals('A certification fullname', $data->fullname);
        $this->assertEquals('1', $data->idnumber);

        $data->fullname = 'Name changed!';
        $data->idnumber = '33';

        api::update_certification_details($data);

        $certification = new certification($data->id);
        $this->assertEquals('Name changed!', $certification->get('fullname'));
        $this->assertEquals('33', $certification->get('idnumber'));
        $this->assertEquals($data->startdatetype, $certification->get('startdatetype'));
        $this->assertEquals($data->duedatetype, $certification->get('duedatetype'));
        $this->assertEquals($data->expirydatetype, $certification->get('expirydatetype'));
        $this->assertEquals($data->startdateabsolute, $certification->get('startdateabsolute'));
        $this->assertEquals($data->expirydateabsolute, $certification->get('expirydateabsolute'));
        $this->assertEquals($data->startdaterelative, $certification->get('startdaterelative'));
        $this->assertEquals($data->duedaterelative, $certification->get('duedaterelative'));
        $this->assertEquals($data->expirydaterelative, $certification->get('expirydaterelative'));
    }

    public function test_update_certification_details_triggers_event(): void {
        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');

        $sink = $this->redirectEvents();
        $certificationdata->id = $certification->get('id');
        $certificationdata->fullname = 'TEST IT!';
        api::update_certification_details($certificationdata);

        $certification = new certification($certificationid);
        $this->assertInstanceOf(certification::class, $certification);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(certification_updated::class, $event);
    }

    public function test_revoke_certification_from_user(): void {
        global $DB, $USER;

        $certification = $this->generator->generate_certification([], true);
        $certification->set('expirydatetype', constants::DATE_NEVER);
        $certification->update();
        $certificationid = $certification->get('id');

        $user = self::getDataGenerator()->create_user();

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'expirydate' => 0,
            'timerevoked' => 0,
        ];
        api::allocate_user($certification, $userdata);

        $params = ['certificationid' => $certificationid, 'userid' => $user->id];
        $paramslastcompletion = ['certificationid' => $certificationid, 'userid' => $user->id, 'timerevoked' => 0, 'islast' => 1];

        $record = $DB->get_record('tool_certification_compltion', $paramslastcompletion);
        $this->assertFalse($record);

        api::set_user_as_certified($user->id, $certificationid);

        $record = $DB->get_record('tool_certification_compltion', $paramslastcompletion);
        $this->assertEquals($certificationid, $record->certificationid);
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals('0', $record->expirydate);

        api::revoke_certification_from_user($user->id, $certificationid);

        $record = $DB->get_record('tool_certification_compltion', $paramslastcompletion);
        $this->assertFalse($record);

        // We check with recertification. We certify initial certification.
        api::set_user_as_certified($user->id, $certificationid);
        api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user->id,
            constants::STATUS_OVERRIDE_DEFAULT);

        // User is assigned.
        $record = $DB->get_record('tool_certification_users', $params);
        $this->assertEquals($certification->get('recertificationprogram'), $record->currentprogramid);

        // We certifiy user in the recertification.
        api::set_user_as_certified($user->id, $certificationid);

        $record = $DB->count_records('tool_certification_compltion', $params);
        $this->assertEquals(3, $record);
        $record = $DB->get_record('tool_certification_compltion', $paramslastcompletion);
        $this->assertNotEmpty($record);
        $this->assertEquals($record->revokedby, $USER->id);
        $recordprogramuser = (api::get_latest_programuser_allocation($user->id, $certificationid))->to_record();
        $this->assertEquals($certification->get('recertificationprogram'), $recordprogramuser->programid);

        api::revoke_certification_from_user($user->id, $certificationid);

        $record = $DB->count_records('tool_certification_compltion', $params);
        $this->assertEquals(4, $record);
        $record = $DB->get_record('tool_certification_users', $params);
        $this->assertNull($record->currentprogramid);

        $record = $DB->count_records('tool_certification_compltion', $paramslastcompletion);
        $this->assertEquals(1, $record);

        api::revoke_certification_from_user($user->id, $certificationid);

        $record = $DB->count_records('tool_certification_compltion', $params);
        $this->assertEquals(4, $record);
        $record = $DB->count_records('tool_certification_compltion', $paramslastcompletion);
        $this->assertEquals(0, $record);
        // Check user reallocated initial.
        $recordprogramuser = $DB->get_record('tool_program_users', $params);
        $this->assertEquals($certification->get('program'), $recordprogramuser->programid);
    }

    public function test_get_certifications_in_tenant_fieldset(): void {
        /** @var tool_tenant_generator $datagenerator */
        $datagenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');

        $defaulttenantid = tenancy::get_default_tenant_id();
        $user1 = self::getDataGenerator()->create_user();
        $datagenerator->allocate_user($user1->id, $defaulttenantid);

        $results = api::get_certifications_in_tenant_fieldset(0, $user1->id);
        $this->assertEmpty($results);

        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certificationdata->tenantid = $defaulttenantid;
        $certification = api::create_certification($certificationdata);
        $certid1 = $certification->get('id');
        $certificationdata->fullname = 'Certification 2';
        $certification2 = api::create_certification($certificationdata);
        $certid2 = $certification2->get('id');

        $results = api::get_certifications_in_tenant_fieldset(0, $user1->id);
        $this->assertNotEmpty($results);
        $this->assertCount(2, $results);

        $this->assertArrayHasKey($certid1, $results);
        $this->assertArrayHasKey($certid2, $results);
        $this->assertEquals('A certification fullname', $results[$certid1]);
        $this->assertEquals('Certification 2', $results[$certid2]);
    }

    public function test_get_certification_statuses_fieldset(): void {
        $statuses = api::get_certification_statuses_fieldset();

        $this->assertEquals($statuses[constants::STATUS_OVERRIDE_SUSPENDED], get_string('suspended', 'tool_certification'));
        $this->assertEquals($statuses[constants::STATUS_FUTUREALLOCATION], get_string('futureallocation', 'tool_certification'));
        $this->assertEquals($statuses[constants::STATUS_EXPIRED], get_string('expired', 'tool_certification'));
        $this->assertEquals($statuses[constants::STATUS_CERTIFIED], get_string('certified', 'tool_certification'));
        $this->assertEquals($statuses[constants::STATUS_OPEN], get_string('open', 'tool_certification'));
        $this->assertEquals($statuses[constants::STATUS_OVERDUE], get_string('overdue', 'tool_certification'));
    }

    /**
     * Test add default dynamicrule conditions to certification.
     *
     * @covers \tool_certification\api::add_default_dynamicrule_conditions_to_certification
     * @uses \tool_certification\api::create_certification
     */
    public function test_add_default_dynamicrule_conditions_to_certification(): void {
        global $DB;

        // No rules.
        $rules = $DB->get_records('tool_dynamicrule');
        $this->assertCount(0, $rules);

        // Create certification.
        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certificationdata->tenantid = tenancy::get_tenant_id();
        $certification = api::create_certification($certificationdata);

        $params = [
            'component' => 'tool_certification',
            'componentarea' => 'certification',
            'itemid' => $certification->get('id'),
        ];

        // Check this created relevant rules.
        $rulerecords = $DB->get_records('tool_dynamicrule', $params);
        $this->assertCount(8, $rulerecords);
        foreach ($rulerecords as $rulerecord) {
            $this->assertEquals($certification->get('tenantid'), $rulerecord->tenantid);
            $conditions = $DB->get_records('tool_dynamicrule_condition', ['ruleid' => $rulerecord->id]);
            $this->assertCount(1, $conditions);
        }
    }

    public function test_get_default_certification_dates(): void {
        self::setAdminUser();
        $certification = $this->generator->generate_certification([], true);

        $strdate = get_string('strftimedatefullshort');
        $startdate = strtotime('-7 day');
        $expirydate = strtotime('+7 day');
        $duedate = '1 week';
        $certification->set('startdateabsolute', $startdate);
        $certification->set('duedaterelative', $duedate);
        $certification->set('expirydateabsolute', $expirydate);
        $certification->update();

        $dates = api::get_default_certification_dates($certification);
        $this->assertObjectHasAttribute('startdate', $dates);
        $this->assertObjectHasAttribute('duedate', $dates);
        $this->assertObjectHasAttribute('expirydate', $dates);
        $this->assertEquals(userdate($startdate, $strdate), $dates->startdate);
        $this->assertEquals('1 week after start date', $dates->duedate);
        $this->assertEquals(userdate($expirydate, $strdate), $dates->expirydate);
        $this->assertEquals('1 week after current certification completion', $dates->expirydaterecertification);
        $this->assertEquals('1 week after previous certification expiry date', $dates->graceperiod);

        $certification->set('startdatetype', constants::DATE_USER_ALLOCATION_DATE);
        $certification->set('duedatetype', constants::DATE_NEVER);
        $certification->set('expirydatetype', constants::DATE_NEVER);
        $certification->set('recertexpirydatetype', constants::RECERT_EXPIRY_DATE_NEVER_DATE);
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('Allocation date', $dates->startdate);
        $this->assertEquals('Never', $dates->duedate);
        $this->assertEquals('Never', $dates->expirydate);
        $this->assertEquals('Never', $dates->expirydaterecertification);

        $certification->set('startdatetype', constants::DATE_RELATIVE_TO_ALLOCATION_DATE);
        $certification->set('startdaterelative', '1 month');
        $certification->set('expirydatetype', constants::DATE_AFTER_DUE_DATE);
        $certification->set('expirydaterelative', '1 week');
        $certification->set('recertexpirydatetype', constants::RECERT_EXPIRY_DATE_AFTR_PREV_EXP);
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('1 month after allocation date', $dates->startdate);
        $this->assertEquals('1 week after due date', $dates->expirydate);
        $this->assertEquals('1 week after previous certification expiry date', $dates->expirydaterecertification);

        $certification->set('startdatetype', constants::DATE_RELATIVE_TO_ALLOCATION_DATE);
        $certification->set('startdaterelative', '3 month');
        $certification->set('expirydatetype', constants::DATE_AFTER_DUE_DATE);
        $certification->set('expirydaterelative', '2 week');
        $certification->set('recertexpirydatetype', constants::RECERT_EXPIRY_DATE_AFTR_PREV_EXP);
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('3 months after allocation date', $dates->startdate);
        $this->assertEquals('2 weeks after due date', $dates->expirydate);

        $certification->set('expirydatetype', constants::DATE_AFTER_ALLOCATION_DATE);
        $certification->set('expirydaterelative', '2 week');
        $certification->set('recertexpirydatetype', constants::RECERT_EXPIRY_DATE_AFTR_LATEST);
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('2 weeks after allocation date', $dates->expirydate);
        $str = '1 week after the latter of the current completion or expiration';
        $this->assertEquals($str, $dates->expirydaterecertification);

        $certification->set('expirydatetype', constants::DATE_AFTER_COMPLETION);
        $certification->set('expirydaterelative', '2 year');
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('2 years after completion', $dates->expirydate);
    }

    /**
     * Test get_certifications_by_userid api method.
     * NOTE: this unittest cannot be modified without making sure that block_mylearning continues working.
     */
    public function test_get_certifications_by_userid(): void {
        $certification = $this->generator->generate_certification();
        $certification2 = $this->generator->generate_certification();
        $certificationid = $certification->get('id');
        $certification2id = $certification2->get('id');
        $user = self::getDataGenerator()->create_user();
        $userid = $user->id;
        $this->generator->allocate_users_to_certification($certificationid, [$userid]);

        $usercertifications = api::get_certifications_by_userid($userid);
        $this->assertCount(1, $usercertifications);
        $usercertification = reset($usercertifications);

        $this->assertEquals($certificationid, $usercertification->get('id'));

        $this->generator->allocate_users_to_certification($certification2id, [$userid]);

        $usercertifications = api::get_certifications_by_userid($userid);
        $this->assertCount(2, $usercertifications);
        $certificationids = array_keys($usercertifications);

        $this->assertEqualsCanonicalizing($certificationids, [$certificationid, $certification2id]);
    }

    public function test_remove_deleted_user_from_certifications(): void {
        global $DB;
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $certification1 = $this->generator->generate_certification();
        $certificationid1 = $certification1->get('id');
        $this->generator->allocate_user($user1->id, $certificationid1);
        $this->generator->allocate_user($user2->id, $certificationid1);

        $certification2 = $this->generator->generate_certification();
        $certificationid2 = $certification2->get('id');
        $this->generator->allocate_user($user1->id, $certificationid2);

        $exists = $DB->record_exists('tool_certification_users', ['certificationid' => $certificationid1, 'userid' => $user1->id]);
        $this->assertTrue($exists);
        $exists = $DB->record_exists('tool_certification_users', ['certificationid' => $certificationid1, 'userid' => $user2->id]);
        $this->assertTrue($exists);
        $exists = $DB->record_exists('tool_certification_users', ['certificationid' => $certificationid2, 'userid' => $user1->id]);
        $this->assertTrue($exists);

        delete_user($user1);

        $exists = $DB->record_exists('tool_certification_users', ['certificationid' => $certificationid1, 'userid' => $user1->id]);
        $this->assertFalse($exists);
        $exists = $DB->record_exists('tool_certification_users', ['certificationid' => $certificationid1, 'userid' => $user2->id]);
        $this->assertTrue($exists);
        $exists = $DB->record_exists('tool_certification_users', ['certificationid' => $certificationid2, 'userid' => $user1->id]);
        $this->assertFalse($exists);
    }

    public function test_is_idnumber_unique(): void {
        self::setAdminUser();
        $certification1 = $this->generator->generate_certification(['idnumber' => 'num1']);
        $certification2 = $this->generator->generate_certification(['idnumber' => 'num2']);

        $this->assertTrue(api::is_idnumber_unique($certification1->get('id'), 'num1'));
        $this->assertTrue(api::is_idnumber_unique($certification1->get('id'), 'num3'));
        $this->assertFalse(api::is_idnumber_unique($certification1->get('id'), 'num2'));
        $this->assertFalse(api::is_idnumber_unique($certification1->get('id'), 'NUM2'));

        $certification1->set('idnumber', '');
        $certification1->update();
        $this->assertTrue(api::is_idnumber_unique($certification2->get('id'), ''));
    }

    /**
     * Data provider for test_recalculate_nextstartdate
     *
     * @return array
     */
    public function recalculate_nextstartdate_provider(): array {
        return [
            ['0', '0', '0'],
            ['01-01-2019 00:00 UTC', '31-12-2018 00:00 UTC', '25-12-2018 00:00 UTC'],
            ['01-01-2019 23:59 UTC', '31-12-2018 23:59 UTC', '25-12-2018 23:59 UTC'],
            ['31-12-2020 00:00 UTC', '30-12-2020 00:00 UTC', '24-12-2020 00:00 UTC'],
            ['31-12-2020 23:59 UTC', '30-12-2020 23:59 UTC', '24-12-2020 23:59 UTC'],
            ['01-03-2019 00:00 UTC', '28-02-2019 00:00 UTC', '22-02-2019 00:00 UTC'],
            ['01-03-2019 23:59 UTC', '28-02-2019 23:59 UTC', '22-02-2019 23:59 UTC'],
            ['21-11-2019 00:00 UTC', '20-11-2019 00:00 UTC', '14-11-2019 00:00 UTC'],
            ['21-11-2019 23:59 UTC', '20-11-2019 23:59 UTC', '14-11-2019 23:59 UTC'],
            ['21-07-2019 08:59 UTC', '20-07-2019 08:59 UTC', '14-07-2019 08:59 UTC'],
        ];
    }

    /**
     * Recalculate nextstartdate.
     *
     * @param string $expirydate
     * @param string $expected
     * @param string $expected2
     *
     * @dataProvider recalculate_nextstartdate_provider
     */
    public function test_recalculate_nextstartdate(string $expirydate, string $expected, string $expected2): void {
        $certification = $this->generator->generate_certification(['recertstartdaterelative' => '1 day']);
        $nextstartdate = api::recalculate_nextstartdate($certification, strtotime($expirydate));
        $this->assertEquals(strtotime($expected), $nextstartdate);

        $certification->set('recertstartdaterelative', '1 week');
        $certification->update();
        $nextstartdate = api::recalculate_nextstartdate($certification, strtotime($expirydate));
        $this->assertEquals(strtotime($expected2), $nextstartdate);
    }

    /**
     * Test get_last_completion_record api method.
     * NOTE: this unittest cannot be modified without making sure that block_mylearning continues working.
     */
    public function test_get_last_completion_record(): void {
        $certification = $this->generator->generate_certification();
        $certificationid = $certification->get('id');
        $user1 = self::getDataGenerator()->create_user();

        $lastcompletion = api::get_last_completion_record($user1->id, $certificationid);
        $this->assertFalse($lastcompletion);

        $programid = $certification->get('program');
        $completiondata = $this->generator->get_dummy_completion_data($certificationid, $user1->id, $programid);
        $completion1 = new certification_completion(0, $completiondata);
        $completion1->create();
        $completiondata->islast = 1;
        $completiondata->expirydate = strtotime('+7 day');
        $completion2 = new certification_completion(0, $completiondata);
        $completion2->create();

        $lastcompletion = api::get_last_completion_record($user1->id, $certificationid);
        $this->assertEquals($completion2->get('id'), $lastcompletion->get('id'));

        $completion2->set('islast', 0);
        $completion2->update();
        $lastcompletion = api::get_last_completion_record($user1->id, $certificationid);
        $this->assertFalse($lastcompletion);

        $completion2->set('timerevoked', time());
        $completion2->update();
        $completion1->set('islast', 1);
        $completion1->update();
        $lastcompletion = api::get_last_completion_record($user1->id, $certificationid);
        $this->assertEquals($completion1->get('id'), $lastcompletion->get('id'));

        $completion1->set('timerevoked', time());
        $completion1->update();
        $lastcompletion = api::get_last_completion_record($user1->id, $certificationid);
        $this->assertFalse($lastcompletion);
    }

    public function test_recalculate_recertification_user_due_date(): void {
        $certification = $this->generator->generate_certification();
        $certificationid = $certification->get('id');
        $user1 = self::getDataGenerator()->create_user();
        $programid = $certification->get('program');
        $certificationuser = $this->generator->allocate_user($user1->id, $certificationid);

        try {
            api::recalculate_recertification_user_due_date($certificationuser);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
            $this->assertStringContainsString('Completion records should exist if is recertification', $e->getMessage());
        }

        $completiondata = $this->generator->get_dummy_completion_data($certificationid, $user1->id, $programid, 1);
        $completion1 = new certification_completion(0, $completiondata);
        $completion1->create();
        $duedate = api::recalculate_recertification_user_due_date($certificationuser);
        $this->assertEquals($duedate, $completion1->get('expirydate'));
    }

    public function test_get_previous_valid_completion_record(): void {
        $certification = $this->generator->generate_certification();
        $certificationid = $certification->get('id');
        $user1 = self::getDataGenerator()->create_user();

        $lastvalid = api::get_previous_valid_completion_record($user1->id, $certificationid);
        $this->assertFalse($lastvalid);

        $programid = $certification->get('program');
        $completiondata = $this->generator->get_dummy_completion_data($certificationid, $user1->id, $programid);
        $completion1 = new certification_completion(0, $completiondata);
        $completion1->create();
        $completiondata->islast = 0;
        $completiondata->timerevoked = time();
        $completiondata->expirydate = strtotime('+1 day');
        $completion2 = new certification_completion(0, $completiondata);
        $completion2->create();

        $lastvalid = api::get_previous_valid_completion_record($user1->id, $certificationid);
        $this->assertEquals($lastvalid->id, $completion1->get('id'));
    }

    public function test_is_recertification_program_different(): void {
        $certification = $this->generator->generate_certification([], true);
        $certificationid = $certification->get('id');
        $user1 = self::getDataGenerator()->create_user();
        $programid = $certification->get('program');
        $certificationuser = $this->generator->allocate_user($user1->id, $certificationid);
        $this->assertFalse(api::is_recertification_program_different($certification, $certificationuser));
        $certificationuser->set('currentprogramid', null);
        $certificationuser->update();
        $this->assertTrue(api::is_recertification_program_different($certification, $certificationuser));
        $certificationuser->set('currentprogramid', $programid + 1);
        $certificationuser->update();
        $this->assertTrue(api::is_recertification_program_different($certification, $certificationuser));
    }

    /**
     * Data provider for test_recalculate_graceperiodends
     *
     * @return array
     */
    public function recalculate_graceperiodends_provider(): array {
        return [
            ['0', '0', '0'],
            ['01-01-2019 00:00 UTC', '04-01-2019 00:00 UTC', '01-02-2019 00:00 UTC'],
            ['01-01-2019 23:59 UTC', '04-01-2019 23:59 UTC', '01-02-2019 23:59 UTC'],
            ['31-12-2020 00:00 UTC', '03-01-2021 00:00 UTC', '31-01-2021 00:00 UTC'],
            ['31-12-2020 23:59 UTC', '03-01-2021 23:59 UTC', '31-01-2021 23:59 UTC'],
            ['01-03-2019 00:00 UTC', '04-03-2019 00:00 UTC', '01-04-2019 00:00 UTC'],
            ['01-03-2019 23:59 UTC', '04-03-2019 23:59 UTC', '01-04-2019 23:59 UTC'],
            ['21-11-2019 00:00 UTC', '24-11-2019 00:00 UTC', '21-12-2019 00:00 UTC'],
            ['21-11-2019 23:59 UTC', '24-11-2019 23:59 UTC', '21-12-2019 23:59 UTC'],
            ['21-07-2019 08:59 UTC', '24-07-2019 08:59 UTC', '21-08-2019 08:59 UTC'],
        ];
    }

    /**
     * Test recalculate_graceperiodends
     *
     * @param string $expirydate
     * @param string $expected
     * @param string $expected2
     *
     * @dataProvider recalculate_graceperiodends_provider
     */
    public function test_recalculate_graceperiodends(string $expirydate, string $expected, string $expected2): void {
        $certification = $this->generator->generate_certification(['recertgraceperiod' => '3 day'], true);

        $graceperiod = api::recalculate_graceperiodends($certification, strtotime($expirydate));
        $this->assertEquals($graceperiod, strtotime($expected));

        $certification->set('recertgraceperiod', '1 month');
        $certification->update();

        $graceperiod = api::recalculate_graceperiodends($certification, strtotime($expirydate));
        $this->assertEquals($graceperiod, strtotime($expected2));
    }

    public function test_reallocate_user_into_initial_program(): void {
        global $DB;
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day'
        ];
        $certification = $this->generator->generate_certification($params, true);
        $certificationid = $certification->get('id');
        $user1 = self::getDataGenerator()->create_user();
        $programid = $certification->get('program');
        $certificationuser = $this->generator->allocate_user($user1->id, $certificationid);
        $params = ['certificationid' => $certificationid, 'userid' => $user1->id];

        // User is allocated to initial program.
        $recordprogramuser = (api::get_latest_programuser_allocation($user1->id, $certificationid))->to_record();
        $this->assertEquals($programid, $recordprogramuser->programid);

        api::set_user_as_certified($user1->id, $certificationid);
        api::allocate_recertification_users();

        // User is allocated into recertification program. Recertification program is different from the initial one.
        $recordprogramuser = (api::get_latest_programuser_allocation($user1->id, $certificationid))->to_record();
        $this->assertNotEquals($programid, $recordprogramuser->programid);
        $this->assertEquals($recordprogramuser->programid, $certification->get('recertificationprogram'));

        api::reallocate_user_into_initial_program($certification, $certificationuser);

        // User is allocated into initial program.
        $recordprogram = $DB->get_record('tool_program_users', $params);
        $this->assertEquals($programid, $recordprogram->programid);
    }

    public function test_allocate_recertification_users(): void {
        global $DB;
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day',
        ];
        $certification = $this->generator->generate_certification($params, true);
        $user1 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user1->id, $certification->get('id'));

        api::set_user_as_certified($user1->id, $certification->get('id'));

        // User is allocated to initial program.
        $params = ['certificationid' => $certification->get('id'), 'userid' => $user1->id];
        $recordprogramuser = (api::get_latest_programuser_allocation($user1->id, $certification->get('id')))->to_record();
        $this->assertEquals($certification->get('program'), $recordprogramuser->programid);

        $lastcompletion = api::get_last_completion_record($user1->id, $certification->get('id'));
        $nextstartdate = strtotime('-' . $certification->get('recertstartdaterelative'), $lastcompletion->get('expirydate'));
        $graceperiod = strtotime('+' . $certification->get('recertgraceperiod'), $lastcompletion->get('expirydate'));

        $recordcertificationuser = $DB->get_record('tool_certification_users', $params);
        $this->assertNull($recordcertificationuser->currentprogramid);
        $this->assertEquals(0, $recordcertificationuser->isrecertification);
        $this->assertEquals(0, $recordcertificationuser->graceperiodends);
        $this->assertEquals($nextstartdate, $recordcertificationuser->nextstartdate);

        api::allocate_recertification_users();

        $recordprogramuser = (api::get_latest_programuser_allocation($user1->id, $certification->get('id')))->to_record();
        $this->assertEquals($certification->get('recertificationprogram'), $recordprogramuser->programid);

        $recordcertificationuser = $DB->get_record('tool_certification_users', $params);
        $this->assertNotNull($recordcertificationuser->currentprogramid);
        $this->assertEquals(1, $recordcertificationuser->isrecertification);
        $this->assertEquals($graceperiod, $recordcertificationuser->graceperiodends);
        $this->assertEquals($nextstartdate, $recordcertificationuser->nextstartdate);
    }

    /**
     * Test: When users start the recertification round, if for some reason they have any progress in a course
     * part of the recertification program, we need to clean this progress before we allocate the user into it
     * in order to avoid autocompletion of the program.
     *
     * Example:
     *    User has previous completion data, when recertification period starts if recertification
     *    program progress is reset then user can begin from the scratch and dates workflow works as
     *    setted.
     */
    public function test_recertification_program_not_autocompleted(): void {
        global $DB;
        // Skipping this test if 'uopz' extension is not loaded.
        if (!extension_loaded('uopz')) {
            // Leave here if uopz is not loaded, as remaining test scenario depends on it.
            $this->markTestSkipped('This test requires uopz php extension.');
        }

        // Set configuration for certification that require recertification with different program.
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '2 day',
            'duedaterelative' => '1 day',
            'duedatetype' => constants::DATE_AFTER_START_DATE,
            'recertstartdaterelative' => '1 day',
            'recertexpirydatetype' => constants::RECERT_EXPIRY_DATE_AFTR_PREV_COMPL,
            'recertexpirydaterelative' => '2 day',
        ];

        $certification = $this->generator->generate_certification($params, true);
        $user = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user->id, $certification->get('id'));

        // Certify current user manually.
        api::set_user_as_certified($user->id, $certification->get('id'));
        $this->assertTrue(api::is_user_certified($user->id, $certification->get('id')));

        // Assert that user is still allocated into the initial program.
        $recordprogramuser = (api::get_latest_programuser_allocation($user->id, $certification->get('id')))->to_record();
        $this->assertEquals($certification->get('program'), $recordprogramuser->programid);

        // Complete course related to recertification program.
        $recertprogram = new program($certification->get('recertificationprogram'));
        $recertprogramcourses = $recertprogram->get_program_courses();
        $recertprogramcourse = reset($recertprogramcourses);
        $this->programgenerator->complete_courses([$recertprogramcourse->get('courseid')], $user->id);

        // Verify that course related to recertification program is completed.
        $sql = "SELECT id
                 FROM {course_completions}
                WHERE course = :courseid
                  AND userid = :userid
                  AND timecompleted IS NOT NULL";
        $params = ['courseid' => $recertprogramcourse->get('courseid'), 'userid' => $user->id];
        $this->assertCount(1, $DB->get_records_sql($sql, $params));

        // Set datetime to +1 day from the certification allocation to force the start of
        // recertification period.
        uopz_set_return('time', $recordprogramuser->timecreated + DAYSECS);

        // Get certification user allocation.
        $cuparams = ['userid' => $user->id, 'certificationid' => $certification->get('id')];
        $certificationuser = certification_user::get_record($cuparams);

        // This method by default resets the recertification program.
        api::allocate_recertification_users($certificationuser);

        // Now that user starts a recertification program, course completion progress should be empty.
        $this->assertCount(0, $DB->get_records_sql($sql, $params));

        // Check if user is allocated in recertification program.
        $recordprogramuser = (api::get_latest_programuser_allocation($user->id, $certification->get('id')))->to_record();
        $this->assertEquals($certification->get('recertificationprogram'), $recordprogramuser->programid);

        // Latest certification completion should be for the initial program and certification is still completed.
        $lastcompletion = api::get_last_completion_record($user->id, $certification->get('id'));
        $this->assertEquals($certification->get('program'), $lastcompletion->get('programid'));
        $this->assertTrue(api::is_user_certified($user->id, $certification->get('id')));
        $this->assertFalse(api::is_program_completed($certification->get('recertificationprogram'), $user->id));

        // The 'uopz' extension keeps the system datetime in the previous +1 day.
        // 3 days after complete the certification, certification is expired and recertification program should not be completed.
        uopz_set_return('time', time() + 2 * DAYSECS);
        $this->assertTrue(api::is_user_certification_expired($user->id, $certification->get('id')));
        $this->assertFalse(api::is_program_completed($certification->get('recertificationprogram'), $user->id));
    }

    public function test_allocate_user_recertification(): void {
        global $DB;
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day',
        ];
        $certification = $this->generator->generate_certification($params, true);
        $user1 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user1->id, $certification->get('id'));

        try {
            api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user1->id,
                constants::STATUS_OVERRIDE_DEFAULT);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
            $this->assertStringContainsString('Previous completion should exist', $e->getMessage());
        }

        api::set_user_as_certified($user1->id, $certification->get('id'));

        // User is allocated to initial program.
        $params = ['certificationid' => $certification->get('id'), 'userid' => $user1->id];
        $recordprogramuser = $DB->get_record('tool_program_users', $params);
        $this->assertEquals($certification->get('program'), $recordprogramuser->programid);

        $lastcompletion = api::get_last_completion_record($user1->id, $certification->get('id'));
        $nextstartdate = strtotime('-' . $certification->get('recertstartdaterelative'), $lastcompletion->get('expirydate'));
        $graceperiod = strtotime('+' . $certification->get('recertgraceperiod'), $lastcompletion->get('expirydate'));

        $recordcertificationuser = $DB->get_record('tool_certification_users', $params);
        $this->assertNull($recordcertificationuser->currentprogramid);
        $this->assertEquals(0, $recordcertificationuser->isrecertification);
        $this->assertEquals(0, $recordcertificationuser->graceperiodends);
        $this->assertEquals($nextstartdate, $recordcertificationuser->nextstartdate);

        $certificationuser = api::allocate_user_recertification($certification, $certification->get('recertificationprogram'),
            $user1->id, constants::STATUS_OVERRIDE_DEFAULT);

        $this->assertNotNull($certificationuser->get('currentprogramid'));
        $this->assertTrue($certificationuser->get('isrecertification'));
        $this->assertEquals($graceperiod, $certificationuser->get('graceperiodends'));
        $this->assertEquals($nextstartdate, $certificationuser->get('nextstartdate'));
        $recordprogramuser = (api::get_latest_programuser_allocation($user1->id, $certification->get('id')))->to_record();
        $this->assertEquals($certification->get('recertificationprogram'), $recordprogramuser->programid);
        $this->assertEquals($nextstartdate, $recordprogramuser->startdate);
        $this->assertEquals(0, $recordprogramuser->startdatelocked);
        $this->assertEquals($lastcompletion->get('expirydate'), $recordprogramuser->duedate);
        $this->assertEquals(0, $recordprogramuser->duedatelocked);
        $this->assertEquals(0, $recordprogramuser->enddate);
        $this->assertEquals(1, $recordprogramuser->status);
    }

    /**
     * Test for allocate_user_recertification() using a default nextstartdate
     */
    public function test_allocate_user_recertification_default_nextstartdate(): void {
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '12 hour',
            'recertdifferentprogram' => false,
            'recertstartdaterelative' => '3 hour',
            'recertexpirydaterelative' => '12 hour',
        ];
        $certification = $this->generator->generate_certification($params, true);
        $user = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user->id, $certification->get('id'));

        api::set_user_as_certified($user->id, $certification->get('id'));

        // Reallocate User with the default dates.
        api::allocate_user_recertification($certification, $certification->get('program'),
            $user->id, constants::STATUS_OVERRIDE_DEFAULT);

        $programuser = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user->id]);
        $lastcompletion = api::get_last_completion_record($user->id, $certification->get('id'));
        $nextstartdate = strtotime('-' . $certification->get('recertstartdaterelative'), $lastcompletion->get('expirydate'));
        $this->assertEquals($nextstartdate, $programuser->get('startdate'));

        // Set a next start date to 0 and check if it calculates the default next start date correctly.
        api::set_user_as_certified($user->id, $certification->get('id'));
        $params1 = ['certificationid' => $certification->get('id'), 'userid' => $user->id];
        $certificationuser = certification_user::get_record($params1);
        $certificationuser->set('nextstartdate', 0);
        $certificationuser->update();

        api::allocate_user_recertification($certification, $certification->get('program'),
            $user->id, constants::STATUS_OVERRIDE_DEFAULT);

        $programuser = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user->id]);
        $lastcompletion = api::get_last_completion_record($user->id, $certification->get('id'));
        $nextstartdate = strtotime('-' . $certification->get('recertstartdaterelative'), $lastcompletion->get('expirydate'));
        $this->assertEquals($nextstartdate, $programuser->get('startdate'));
    }

    /**
     * Test for allocate_user_recertification() using a custom nextstartdate
     */
    public function test_allocate_user_recertification_custom_nextstartdate(): void {
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '12 hour',
            'recertdifferentprogram' => false,
            'recertstartdaterelative' => '3 hour',
            'recertexpirydaterelative' => '12 hour',
        ];
        $certification = $this->generator->generate_certification($params, true);

        $user = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user->id, $certification->get('id'));

        api::set_user_as_certified($user->id, $certification->get('id'));

        // Set a custom next start date for User allocation and reallocate User.
        $params1 = ['certificationid' => $certification->get('id'), 'userid' => $user->id];
        $certificationuser = certification_user::get_record($params1);
        $expectednextstartdate = time() + DAYSECS * 10;
        $certificationuser->set('nextstartdate', $expectednextstartdate);
        $certificationuser->update();

        api::allocate_user_recertification($certification, $certification->get('program'),
            $user->id, constants::STATUS_OVERRIDE_DEFAULT);

        $programuser = program_user::get_record($params1);
        // Check that program start date is the custom date we set as nextstartdate for our certification allocation.
        $this->assertEquals($expectednextstartdate, $programuser->get('startdate'));
        // Check that is different from the certification default date for this allocation.
        $lastcompletion = api::get_last_completion_record($user->id, $certification->get('id'));
        $defaultnextstartdate = strtotime('-' . $certification->get('recertstartdaterelative'),
            $lastcompletion->get('expirydate'));
        $this->assertNotEquals($defaultnextstartdate, $programuser->get('startdate'));
    }

    public function test_deallocate_users_after_grace_period_end(): void {
        global $DB;
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day',
        ];
        $certification = $this->generator->generate_certification($params, true);
        $user1 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user1->id, $certification->get('id'));

        api::set_user_as_certified($user1->id, $certification->get('id'));
        $certificationuser = api::allocate_user_recertification($certification, $certification->get('recertificationprogram'),
            $user1->id, constants::STATUS_OVERRIDE_DEFAULT);

        // User is allocated to recertification program.
        $params = ['certificationid' => $certification->get('id'), 'userid' => $user1->id];
        $recordprogramuser = (api::get_latest_programuser_allocation($user1->id, $certification->get('id')))->to_record();
        $this->assertEquals($certification->get('recertificationprogram'), $recordprogramuser->programid);

        $now = time();
        $lastcompletion = api::get_last_completion_record($user1->id, $certification->get('id'));
        $lastcompletion->set('expirydate', $now);
        $lastcompletion->update();
        $certificationuser->set('graceperiodends', $now);
        $certificationuser->update();

        api::deallocate_users_after_grace_period_end();

        $recordprogramuser = $DB->get_record('tool_program_users', $params);
        $this->assertEquals($certification->get('program'), $recordprogramuser->programid);
        $recordcertificationuser = $DB->get_record('tool_certification_users', $params);
        $this->assertEquals($certification->get('program'), $recordcertificationuser->currentprogramid);
    }

    /**
     * Test: When recertification grace period end, users certification allocation should rollback to the initial program.
     */
    public function test_rollback_initial_certification_after_grace_period_end(): void {
        global $DB;

        // Skipping this test if 'uopz' extension is not loaded.
        if (!extension_loaded('uopz')) {
            // Leave here if uopz is not loaded, as remaining test scenario depends on it.
            $this->markTestSkipped('This test requires uopz php extension.');
        }

        // Set configuration for certification that require recertification.
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '2 day',
            'duedaterelative' => '1 day',
            'duedatetype' => constants::DATE_AFTER_START_DATE,
            'recertstartdaterelative' => '1 day',
            'recertexpirydatetype' => constants::RECERT_EXPIRY_DATE_AFTR_PREV_COMPL,
            'recertexpirydaterelative' => '2 day',
            'recertgraceperiod' => '1 day',
        ];

        $certification = $this->generator->generate_certification($params, true);
        $user1 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user1->id, $certification->get('id'));

        // Check that user is allocated into initial program.
        $recordprogramuserinitial = (api::get_latest_programuser_allocation($user1->id, $certification->get('id')))->to_record();
        $this->assertEquals($certification->get('program'), $recordprogramuserinitial->programid);

        // Certify user manually.
        api::set_user_as_certified($user1->id, $certification->get('id'));
        $this->assertTrue(api::is_user_certified($user1->id, $certification->get('id')));

        // Set datetime to +1 day from the certification allocation to force the start of recertification period. We will add
        // a few seconds (MINSECS) more because in order that allocate_recertification_users query works well we need this time
        // to be higher than the time set in recertstartdaterelative (1 day).
        uopz_set_return('time', $recordprogramuserinitial->timecreated + DAYSECS + MINSECS);

        // Allocate user into recertification program.
        api::allocate_recertification_users();

        // Check that user is allocated into the recertification program.
        $recordprogramuser = (api::get_latest_programuser_allocation($user1->id, $certification->get('id')))->to_record();
        $this->assertEquals($certification->get('recertificationprogram'), $recordprogramuser->programid);

        // Move system datetime +1 day after previous certification expiry date as is configured in recertification grace period.
        $lastcompletion = api::get_last_completion_record($user1->id, $certification->get('id'));
        uopz_set_return('time', $lastcompletion->get('expirydate') + DAYSECS);

        // The recertification period ended, so the deallocation process for not completed program starts.
        api::deallocate_users_after_grace_period_end(null, true, true);

        // Retrieve data from 'certificaton_compltion', only one certification completion should exist with the initial dates.
        $params = ['certificationid' => $certification->get('id'), 'userid' => $user1->id];
        $recordcertificationcompltion = $DB->get_records('tool_certification_compltion', $params);
        $this->assertCount(1, $recordcertificationcompltion);
        $recordcertificationcompltion = reset($recordcertificationcompltion);
        $this->assertEquals($certification->get('program'), $recordcertificationcompltion->programid);
        $this->assertEquals($lastcompletion->get('expirydate'), $recordcertificationcompltion->expirydate);
        $this->assertEquals($lastcompletion->get('timecertified'), $recordcertificationcompltion->timecertified);

        // Retrieve program current allocation to check if keeps the initial data.
        $recordprogramuser = $DB->get_record('tool_program_users', $params);
        $this->assertEquals($certification->get('program'), $recordprogramuser->programid);
        $this->assertEquals($recordprogramuserinitial->timecreated, $recordprogramuser->timecreated);
        $this->assertEquals($recordprogramuserinitial->startdate, $recordprogramuser->startdate);
        $this->assertEquals($recordprogramuserinitial->duedate, $recordprogramuser->duedate);
    }

    public function test_update_certification_recertification(): void {
        global $DB;
        $certification = $this->generator->generate_certification([], true);

        $data = (object)[
            'id' => $certification->get('id'),
            'requirerecertification' => 0,
        ];

        // If requirerecertification is zero it just updates this one, does not need any other field.
        api::update_certification_recertification($data);

        $certificationrecord = $DB->get_record('tool_certification', ['id' => $certification->get('id')]);
        $this->assertEquals(0, $certificationrecord->requirerecertification);

        $now = time();
        $data->requirerecertification = 1;
        $data->recertdifferentprogram = 1;
        $data->recertificationprogram = 5;
        $data->recertstartdaterelative = '4 year';
        $data->recertgraceperiod = $now;
        $data->recertexpirydatetype = constants::RECERT_EXPIRY_DATE_AFTR_LATEST;
        $data->recertexpirydaterelative = '3 month';
        api::update_certification_recertification($data);

        $certificationrecord = $DB->get_record('tool_certification', ['id' => $certification->get('id')]);
        $this->assertEquals(1, $certificationrecord->requirerecertification);
        $this->assertEquals(1, $certificationrecord->recertdifferentprogram);
        $this->assertEquals(5, $certificationrecord->recertificationprogram);
        $this->assertEquals('4 year', $certificationrecord->recertstartdaterelative);
        $this->assertEquals($now, $certificationrecord->recertgraceperiod);
        $this->assertEquals(constants::RECERT_EXPIRY_DATE_AFTR_LATEST, $certificationrecord->recertexpirydatetype);
        $this->assertEquals('3 month', $certificationrecord->recertexpirydaterelative);
    }

    public function test_recalculate_user_expiry_date(): void {
        $params = [
            'expirydatetype' => constants::DATE_NEVER,
            'duedatetype' => constants::DATE_AFTER_START_DATE,
            'duedaterelative' => '1 day',
            'startdatetype' => constants::DATE_RELATIVE_TO_ALLOCATION_DATE,
            'startdaterelative' => '0 day',
        ];
        $certification = $this->generator->generate_certification($params, true);
        $user1 = self::getDataGenerator()->create_user();
        $certificationuser = $this->generator->allocate_user($user1->id, $certification->get('id'));

        $userallocationdate = $certificationuser->get('timecreated');
        $programuser = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user1->id]);
        $userduedate = $programuser->get('duedate');
        $expirydate = api::recalculate_user_expiry_date($certification, $certificationuser, $userallocationdate, $userduedate);

        $this->assertEquals(constants::DATE_NONE, $expirydate);

        $oneyearmore = strtotime(' +1 year');
        $certification->set('expirydatetype', constants::DATE_ABSOLUTE);
        $certification->set('expirydateabsolute', $oneyearmore);
        $certification->update();

        $expirydate = api::recalculate_user_expiry_date($certification, $certificationuser, $userallocationdate, $userduedate);
        $this->assertEquals($oneyearmore, $expirydate);

        $certification->set('expirydatetype', constants::DATE_AFTER_ALLOCATION_DATE);
        $certification->set('expirydaterelative', '1 day');
        $certification->update();

        $expirydate = api::recalculate_user_expiry_date($certification, $certificationuser, $userallocationdate, $userduedate);
        $this->assertEquals(strtotime('+1 day', $userallocationdate), $expirydate);

        $certification->set('expirydatetype', constants::DATE_AFTER_DUE_DATE);
        $certification->set('expirydaterelative', '1 day');
        $certification->update();

        $expirydate = api::recalculate_user_expiry_date($certification, $certificationuser, $userallocationdate, $userduedate);
        $this->assertEquals(strtotime('+2 day', $userallocationdate), $expirydate);

        api::set_user_as_certified($user1->id, $certification->get('id'));
        $certification->set('expirydatetype', constants::DATE_AFTER_COMPLETION);
        $certification->set('expirydaterelative', '5 day');
        $certification->update();

        $lastcompletion = api::get_last_completion_record($user1->id, $certification->get('id'));
        $expirydate = api::recalculate_user_expiry_date($certification, $certificationuser, $userallocationdate, $userduedate);
        $expected = strtotime('+5 day', $lastcompletion->get('timecertified'));
        $this->assertEquals($expected, $expirydate);

        // User completes the program.
        if (!extension_loaded('uopz')) {
            // Leave here if uopz is not loaded, as remaining test scenario depends on it.
            $this->markTestIncomplete('Recalculation test using DATE_AFTER_COMPLETION type requires uopz php extension.');
        }

        $params['expirydatetype'] = constants::DATE_AFTER_COMPLETION;
        $params['expirydaterelative'] = '8 day';
        $certification2 = $this->generator->generate_certification($params, true);
        $certificationuser = $this->generator->allocate_user($user1->id, $certification2->get('id'));

        $userallocationdate = $certificationuser->get('timecreated');
        $programuser = program_user::get_record(['certificationid' => $certification2->get('id'), 'userid' => $user1->id]);
        $userduedate = $programuser->get('duedate');
        $expirydate = api::recalculate_user_expiry_date($certification2, $certificationuser, $userallocationdate, $userduedate);
        $this->assertEquals(strtotime('+8 day', $userallocationdate), $expirydate);

        $program = new program($certification2->get('program'));
        $this->programgenerator->complete_program($program, $user1->id);

        $expirydate = api::recalculate_user_expiry_date($certification2, $certificationuser, $userallocationdate, $userduedate);
        $baseset = $program->get_base_set();
        $programcompletion = program_set_completion::get_record([
            'setid' => $baseset->get('id'),
            'userid' => $user1->id,
        ]);
        $this->assertEquals(strtotime('+8 day', $programcompletion->get('timecreated')), $expirydate);
    }

    public function test_delete_certification_dynamic_rules(): void {
        global $DB;
        $certification = $this->generator->generate_certification();

        $params = [
            'component' => 'tool_certification',
            'componentarea' => 'certification',
            'itemid' => $certification->get('id'),
        ];
        $this->assertTrue($DB->record_exists('tool_dynamicrule', $params));
        api::delete_certification_dynamic_rules($certification->get('id'));
        $this->assertFalse($DB->record_exists('tool_dynamicrule', $params));
    }

    public function test_get_latest_programuser_allocation(): void {
        global $DB;
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day',
        ];
        $certification = $this->generator->generate_certification($params, true);
        $user1 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user1->id, $certification->get('id'));

        // User is allocated to initial program.
        $programuser = api::get_latest_programuser_allocation($user1->id, $certification->get('id'));
        $this->assertEquals($certification->get('program'), $programuser->get('programid'));
        $params = ['userid' => $user1->id, 'certificationid' => $certification->get('id')];
        $this->assertEquals(1, $DB->count_records('tool_program_users', $params));

        api::set_user_as_certified($user1->id, $certification->get('id'));
        api::allocate_recertification_users();

        // User is allocated into the recertification program (different than the initial).
        $programuser = api::get_latest_programuser_allocation($user1->id, $certification->get('id'));
        $this->assertNotEquals($certification->get('program'), $programuser->get('programid'));
        $this->assertEquals($certification->get('recertificationprogram'), $programuser->get('programid'));
        $this->assertEquals(2, $DB->count_records('tool_program_users', $params));
    }

    /**
     * Test send certification user deallocated notification.
     */
    public function test_send_certification_user_deallocated_notification(): void {
        global $SITE;

        $user = self::getDataGenerator()->create_user();
        $programname = 'Program1';
        $program = $this->programgenerator->generate_program((object)['fullname' => $programname]);

        $subject = get_string('notificationsubjectcertificationuserdeallocated', 'tool_certification', $programname);
        $a = [
            'userfullname' => fullname($user),
            'programname' => $programname,
            'sitename' => format_string($SITE->fullname, true, ['context' => context_system::instance()->id, 'escape' => false]),
            'siteurl' => (new \moodle_url('/'))->out(false),
        ];
        $fullmessage = get_string('notificationcertificationuserdeallocated', 'tool_certification', $a);

        // Sink should catch messages.
        $sink = $this->redirectMessages();
        api::send_certification_user_deallocated_notification($user->id, $program->get('id'));
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($user->id, $messages[0]->useridto);
        $this->assertEquals($subject, $messages[0]->subject);
        $this->assertStringContainsString($fullmessage, $messages[0]->fullmessagehtml);
    }

    /**
     * Test send certification user allocated notification.
     */
    public function test_send_certification_user_allocated_notification(): void {
        global $SITE;

        $user = self::getDataGenerator()->create_user();
        $duedate = strtotime('+2 day');
        $certname = 'Certification1';
        $programname = 'Program1';
        $program = $this->programgenerator->generate_program((object)['fullname' => $programname]);
        $certification = $this->generator->generate_certification(['fullname' => $certname,
            'program' => $program->get('id'), 'duedatetype' => constants::DATE_ABSOLUTE, 'duedateabsolute' => $duedate]);
        $contextid = context_system::instance()->id;

        $data = [
            'certificationname' => $certname,
            'duedate' => userdate($duedate, get_string('strftimedatefullshort')),
        ];
        $certmsg = get_string('notificationcertificationdue', 'tool_certification', $data);

        $subject = get_string('notificationsubjectcertificationuserallocated', 'tool_certification', $programname);
        $a = [
            'userfullname' => fullname($user),
            'programname' => $programname,
            'certmsg' => $certmsg,
            'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
            'siteurl' => (new \moodle_url('/'))->out(false),
        ];

        $fullmessage = get_string('notificationcertificationuserallocated', 'tool_certification', $a);

        // Sink should catch messages.
        $sink = $this->redirectMessages();
        $this->generator->allocate_user($user->id, $certification->get('id'));
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($user->id, $messages[0]->useridto);
        $this->assertEquals($subject, $messages[0]->subject);
        $this->assertStringContainsString($fullmessage, $messages[0]->fullmessagehtml);
    }

    /**
     * Test send certification completed notification.
     */
    public function test_send_certification_completed_notification(): void {
        global $SITE;

        $user = self::getDataGenerator()->create_user();
        $certname = 'Certification1';
        $programname = 'Program1';
        $program = $this->programgenerator->generate_program((object)['fullname' => $programname]);
        $certification = $this->generator->generate_certification(['fullname' => $certname,
            'program' => $program->get('id')]);
        $this->generator->allocate_user($user->id, $certification->get('id'));
        $contextid = context_system::instance()->id;
        $expirydate = strtotime('+2 day');

        // We create the new completion record.
        $userdata = (object) [
            'userid' => $user->id,
            'certificationid' => $certification->get('id'),
            'expirydate' => $expirydate,
            'timerevoked' => 0,
            'revokedby' => null,
            'programid' => $program->get('id'),
            'islast' => 1,
            'timecertified' => time(),
            'certifiedby' => $user->id,
        ];
        $certcompletion = new certification_completion(0, $userdata);
        $certcompletion->create();

        $context = context_system::instance();
        $certname = format_string($certification->get('fullname'), true, ['context' => $context, 'escape' => false]);
        $subject = get_string('notificationsubjectcertificationcompletedmanual', 'tool_certification', $certname);
        $expirymsg = get_string('notificationcertificationwillexpireon', 'tool_certification',
            userdate($expirydate, get_string('strftimedatefullshort')));
        $a = [
            'userfullname' => fullname($user),
            'programname' => $programname,
            'certificationname' => $certname,
            'expirymessage' => $expirymsg,
            'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
            'siteurl' => (new \moodle_url('/'))->out(false),
        ];
        $fullmessage = get_string('notificationcertificationcompletedmanual', 'tool_certification', $a);

        // Sink should catch messages.
        $sink = $this->redirectMessages();
        api::send_certification_completed_notification($user->id, $certification->get('id'), $program->get('id'));
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($user->id, $messages[0]->useridto);
        $this->assertEquals($subject, $messages[0]->subject);
        $this->assertStringContainsString($fullmessage, $messages[0]->fullmessagehtml);
    }

    /**
     * Test for update certification name in calendar events.
     */
    public function test_update_certification_name_in_calendar_events(): void {
        global $DB;
        $user1 = self::getDataGenerator()->create_user();
        $certification = $this->generator->generate_certification(['fullname' => 'Certification ABC']);
        $this->generator->allocate_user($user1->id, $certification->get('id'));

        $params = [
            'component' => 'tool_certification',
            'instance' => $certification->get('id'),
        ];
        $events = $DB->get_records('event', $params, 'eventtype');

        // Only Due date event should exist. User is not certified and expiry date event should no be set in calendar.
        $this->assertCount(1, $events);
        $expected = get_string('calendarduedate', 'tool_certification', 'Certification ABC');
        $this->assertEquals($expected, reset($events)->name);
        $this->assertEquals($expected, reset($events)->description);

        api::update_certification_name_in_calendar_events($certification->get('id'), 'New certification name');

        $events = $DB->get_records('event', $params, 'eventtype');

        $expected = get_string('calendarduedate', 'tool_certification', 'New certification name');
        $this->assertEquals($expected, reset($events)->name);
        $this->assertEquals($expected, reset($events)->description);

        api::set_user_as_certified($user1->id, $certification->get('id'));

        $events = $DB->get_records('event', $params, 'eventtype');

        // Only Expiry date event should exist. User is certified and due date calendar event should not exist.
        $this->assertCount(1, $events);
        $expected = get_string('calendarexpirydate', 'tool_certification', 'Certification ABC');
        $this->assertEquals($expected, end($events)->name);
        $this->assertEquals($expected, end($events)->description);

        api::update_certification_name_in_calendar_events($certification->get('id'), 'New certification name');

        $events = $DB->get_records('event', $params, 'eventtype');

        $this->assertCount(1, $events);
        $expected = get_string('calendarexpirydate', 'tool_certification', 'New certification name');
        $this->assertEquals($expected, end($events)->name);
        $this->assertEquals($expected, end($events)->description);
    }

    /**
     * Test for hide certification calendar events.
     */
    public function test_hide_certification_user_calendar_events(): void {
        global $DB;
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $certification = $this->generator->generate_certification();
        $this->generator->allocate_user($user1->id, $certification->get('id'));
        $this->generator->allocate_user($user2->id, $certification->get('id'));
        api::set_user_as_certified($user1->id, $certification->get('id'));
        api::set_user_as_certified($user2->id, $certification->get('id'));

        $params = [
            'component' => 'tool_certification',
            'instance' => $certification->get('id'),
            'visible' => 1,
        ];
        // Only expiry date calendar events should exist because users are certified.
        $this->assertCount(2, $DB->get_records('event', $params));

        api::hide_certification_user_calendar_events($certification);

        $this->assertCount(0, $DB->get_records('event', $params));
        $params['visible'] = 0;
        $this->assertCount(2, $DB->get_records('event', $params));
    }

    /**
     * Test for show certification calendar events.
     */
    public function test_show_certification_user_calendar_events(): void {
        global $DB;
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $certification = $this->generator->generate_certification();
        $this->generator->allocate_user($user1->id, $certification->get('id'));
        $this->generator->allocate_user($user2->id, $certification->get('id'));
        api::set_user_as_certified($user1->id, $certification->get('id'));
        api::set_user_as_certified($user2->id, $certification->get('id'));
        api::archive_certification($certification->get('id'));

        $params = [
            'component' => 'tool_certification',
            'instance' => $certification->get('id'),
            'visible' => 0,
        ];
        $this->assertCount(2, $DB->get_records('event', $params));

        api::show_certification_user_calendar_events($certification);

        $this->assertCount(0, $DB->get_records('event', $params));
        $params['visible'] = 1;
        $this->assertCount(2, $DB->get_records('event', $params));
    }

    /**
     * Test to check adding and removing users from groups when archiving/restoring/deleting certification
     * and suspending/restoring user allocations.
     *
     * @covers \tool_certification\api::archive_certification
     * @covers \tool_certification\api::restore_certification
     * @covers \tool_certification\api::update_certification_user_dates_and_status
     * @covers \tool_certification\api::delete_certification
     */
    public function test_archive_certification_and_suspend_user_groups(): void {
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

        // Create one program and one certification using GROUPS_CERTIFICATION.
        $program = $this->programgenerator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups',
        ]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program->get_base_set();
        $programcourse = $this->programgenerator->add_course_to_set($course->id, $baseset->get('id'));
        $certification = $this->generator->generate_certification([
            'program' => $program->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);
        $certificationuser1a = $this->generator->allocate_user($user1->id, $certification->get('id'));
        $certificationuser1b = $this->generator->allocate_user($user2->id, $certification->get('id'));
        $programuser1 = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user1->id]);
        $programuser2 = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user2->id]);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser1);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser2);

        // Create a second program with a second certification using GROUPS_PROGRAM.
        $program2 = $this->programgenerator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 2',
            'autocreategroups' => (\tool_program\api::GROUPS_PROGRAM + \tool_program\api::GROUPS_TENANT),
        ]);
        $course2 = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->programgenerator->add_course_to_set($course2->id, $baseset2->get('id'));
        $certification2 = $this->generator->generate_certification([
            'program' => $program2->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_AS_IN_PROGRAMS),
        ]);
        $certificationuser2a = $this->generator->allocate_user($user1->id, $certification2->get('id'));
        $certificationuser2b = $this->generator->allocate_user($user2->id, $certification2->get('id'));
        $programuser3 = program_user::get_record(['certificationid' => $certification2->get('id'), 'userid' => $user1->id]);
        $programuser4 = program_user::get_record(['certificationid' => $certification2->get('id'), 'userid' => $user2->id]);
        $this->programgenerator->enrol_user_to_program_course($programcourse2, $programuser3);
        $this->programgenerator->enrol_user_to_program_course($programcourse2, $programuser4);

        // There should be 4 group members.
        $this->assertCount(4, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        // There should be another tenant group for certification1 associated to a group and with 2 group members.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers1 = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers1);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($groupmembers1, 'userid'));

        // There should be another tenant group for program2 associated to a group and with 2 group members.
        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $tenantgroup2[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers2);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($groupmembers2, 'userid'));

        // Suspend certification allocation of user2 in certification1.
        $data = $certificationuser1b->to_record();
        $data->status = constants::STATUS_SUSPENDED;
        api::update_certification_user_dates_and_status($certificationuser1b, $data);

        // Suspend certification allocation of user2 in certification2.
        $data = $certificationuser2a->to_record();
        $data->status = constants::STATUS_SUSPENDED;
        api::update_certification_user_dates_and_status($certificationuser2a, $data);

        // There should be just one group member for tool_certification group.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers1 = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers1);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($groupmembers1, 'userid'));

        // There should be just one group member for tool_program group.
        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $tenantgroup2[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers2);
        $this->assertEqualsCanonicalizing([$user2->id], array_column($groupmembers2, 'userid'));

        // There should be just 2 group member in total.
        $this->assertCount(2, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        // Archive both certifications.
        api::archive_certification($certification->get('id'));
        api::archive_certification($certification2->get('id'));

        // There should be 0 group members. All allocations have been removed from groups.
        $this->assertCount(0, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        // Restore the certification1.
        api::restore_certification($certification->get('id'));

        // There should be 1 group members.
        $this->assertCount(1, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        // There should be just one group member for tool_certification group.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers1 = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers1);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($groupmembers1, 'userid'));

        // Enable user allocation for user2 in certification1.
        $data->status = constants::STATUS_OVERRIDE_DEFAULT;
        api::update_certification_user_dates_and_status($certificationuser1b, $data);

        // There should be 2 group members.
        $this->assertCount(2, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        // Both tenant groups should be still created because allocations are suspended, not deleted.
        $this->assertCount(2, \tool_tenant\tenant_group::get_records());
        $this->assertCount(2, $DB->get_records('groups'));

        // Delete the first certification.
        api::archive_certification($certification->get('id'));
        $certification = new certification($certification->get('id'));
        api::delete_certification($certification);

        // Tenant group and group should be deleted and only one should remain from second certification.
        $this->assertCount(1, \tool_tenant\tenant_group::get_records());
        $this->assertCount(1, $DB->get_records('groups'));

        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers1 = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(0, $groupmembers1);
        $this->assertCount(0, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        // Restore the certification.
        api::restore_certification($certification2->get('id'));

        // There should be 1 group members.
        $this->assertCount(1, $DB->get_records('groups_members', ['component' => 'enrol_program']));

        $data->status = constants::STATUS_OVERRIDE_DEFAULT;
        api::update_certification_user_dates_and_status($certificationuser2a, $data);

        // There should be 2 group members and 1 tenant group.
        $this->assertCount(2, $DB->get_records('groups_members', ['component' => 'enrol_program']));
        $this->assertCount(1, \tool_tenant\tenant_group::get_records());
        $this->assertCount(1, $DB->get_records('groups'));

        // Delete the certification.
        api::archive_certification($certification2->get('id'));
        $certification2 = new certification($certification2->get('id'));
        api::delete_certification($certification2);

        // Tenant group and group should not be deleted yet.
        $this->assertCount(0, $DB->get_records('groups_members', ['component' => 'enrol_program']));
        $this->assertCount(1, \tool_tenant\tenant_group::get_records());
        $this->assertCount(1, $DB->get_records('groups'));

        \tool_program\api::archive_program($program2);
        \tool_program\api::delete_program($program2);

        // Tenant group and group should be deleted.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records());
        $this->assertCount(0, $DB->get_records('groups'));
    }

    /**
     * Test certification calendar event is created.
     */
    public function test_add_calendar_event(): void {
        global $DB;
        $certification = $this->generator->generate_certification(['fullname' => 'Certification ABC']);
        $user = self::getDataGenerator()->create_user();
        $duedate = time() + DAYSECS;

        $data = (object) [
            'userid' => $user->id,
            'name' => $certification->get_formatted_name(),
            'certificationid' => $certification->get('id'),
            'timestart' => $duedate,
            'certificationdatetype' => constants::CALENDAR_EVENT_DUE_DATE,
        ];

        $result = $DB->get_records('event');
        $this->assertEmpty($result);

        api::add_calendar_event($data);

        $result = $DB->get_records('event');
        $this->assertCount(1, $result);
        $result = reset($result);
        $str = get_string('calendarduedate', 'tool_certification', $certification->get_formatted_name());
        $this->assertEquals($str, $result->name);
        $this->assertEquals($str, $result->description);
        $this->assertEquals($user->id, $result->userid);
        $this->assertEquals('tool_certification', $result->component);
        $this->assertEquals($certification->get('id'), $result->instance);
        $this->assertEquals('tool_certification' . constants::CALENDAR_EVENT_DUE_DATE, $result->eventtype);
        $this->assertEquals($duedate, $result->timestart);
        $this->assertEquals(1, $result->visible);
    }

    /**
     * Test certification calendar event is updated.
     */
    public function test_update_calendar_event(): void {
        global $DB;
        $certification = $this->generator->generate_certification(['fullname' => 'Certification ABC']);
        $user = self::getDataGenerator()->create_user();
        $enddate = time() + DAYSECS;

        $data = (object)[
            'userid' => $user->id,
            'name' => $certification->get_formatted_name(),
            'certificationid' => $certification->get('id'),
            'timestart' => $enddate,
            'certificationdatetype' => constants::CALENDAR_EVENT_EXPIRY_DATE,
        ];

        api::add_calendar_event($data);

        $result = $DB->get_records('event');
        $this->assertCount(1, $result);
        $result = reset($result);
        $str = get_string('calendarexpirydate', 'tool_certification', $certification->get_formatted_name());
        $this->assertEquals($str, $result->name);
        $this->assertEquals($str, $result->description);
        $this->assertEquals($user->id, $result->userid);
        $this->assertEquals('tool_certification', $result->component);
        $this->assertEquals($certification->get('id'), $result->instance);
        $this->assertEquals('tool_certification' . constants::CALENDAR_EVENT_EXPIRY_DATE, $result->eventtype);
        $this->assertEquals($enddate, $result->timestart);

        $newenddate = $enddate + DAYSECS;
        $data = (object)[
            'userid' => $user->id,
            'certificationid' => $certification->get('id'),
            'name' => $certification->get_formatted_name(),
            'timestart' => $newenddate,
            'certificationdatetype' => constants::CALENDAR_EVENT_EXPIRY_DATE,
        ];
        api::update_calendar_event($data);

        $result = $DB->get_records('event');
        $this->assertCount(1, $result);
        $result = reset($result);
        $this->assertEquals($user->id, $result->userid);
        $this->assertEquals('tool_certification', $result->component);
        $this->assertEquals($certification->get('id'), $result->instance);
        $this->assertEquals('tool_certification' . constants::CALENDAR_EVENT_EXPIRY_DATE, $result->eventtype);
        $this->assertEquals($newenddate, $result->timestart);

        // Try to update a non existent event (CALENDAR_EVENT_DUE_DATE) and it will create it.
        $data->certificationdatetype = constants::CALENDAR_EVENT_DUE_DATE;
        $newdate = time() + 3 * DAYSECS;
        $data->timestart = $newdate;
        api::update_calendar_event($data);

        $result = $DB->get_records('event');
        $this->assertCount(2, $result);
    }

    /**
     * Test certification calendar events are deleted.
     */
    public function test_delete_calendar_events(): void {
        global $DB;
        $certification = $this->generator->generate_certification(['fullname' => 'Certification ABC']);
        $user = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $duedate = time() + DAYSECS;
        $enddate = time() + 2 * DAYSECS;

        $data = (object)[
            'userid' => $user->id,
            'name' => $certification->get_formatted_name(),
            'certificationid' => $certification->get('id'),
            'timestart' => $duedate,
            'certificationdatetype' => constants::CALENDAR_EVENT_DUE_DATE,
        ];
        api::add_calendar_event($data);

        $data = (object)[
            'userid' => $user->id,
            'name' => $certification->get_formatted_name(),
            'certificationid' => $certification->get('id'),
            'timestart' => $enddate,
            'certificationdatetype' => constants::CALENDAR_EVENT_EXPIRY_DATE,
        ];
        api::add_calendar_event($data);

        $this->assertCount(2, $DB->get_records('event'));

        // Try to delete non existent events.
        $data = (object) [
            'userid' => $user2->id,
            'certificationid' => $certification->get('id'),
        ];
        api::delete_calendar_events($data);

        $this->assertCount(2, $DB->get_records('event'));

        // Delete both user events.
        $data = (object) [
            'userid' => $user->id,
            'certificationid' => $certification->get('id'),
        ];
        api::delete_calendar_events($data);

        $this->assertEmpty($DB->get_records('event'));

        // Test to remove just one specific event type.
        $data = (object)[
            'userid' => $user->id,
            'name' => $certification->get_formatted_name(),
            'certificationid' => $certification->get('id'),
            'timestart' => $duedate,
            'certificationdatetype' => constants::CALENDAR_EVENT_DUE_DATE,
        ];
        api::add_calendar_event($data);

        $data = (object)[
            'userid' => $user->id,
            'name' => $certification->get_formatted_name(),
            'certificationid' => $certification->get('id'),
            'timestart' => $enddate,
            'certificationdatetype' => constants::CALENDAR_EVENT_EXPIRY_DATE,
        ];
        api::add_calendar_event($data);

        $this->assertCount(2, $DB->get_records('event'));

        // Delete just the CALENDAR_EVENT_DUE_DATE event.
        $data = (object) [
            'userid' => $user->id,
            'certificationid' => $certification->get('id'),
        ];
        api::delete_calendar_events($data, constants::CALENDAR_EVENT_DUE_DATE);

        $result = $DB->get_records('event');
        $this->assertCount(1, $result);
        $result = reset($result);
        $this->assertEquals($user->id, $result->userid);
        $this->assertEquals('tool_certification', $result->component);
        $this->assertEquals($certification->get('id'), $result->instance);
        $this->assertEquals('tool_certification' . constants::CALENDAR_EVENT_EXPIRY_DATE, $result->eventtype);
    }
}
