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
 * API tests.
 *
 * @package    tool_certification
 * @author     2018 Mitxel Moriana
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use core\event\base;
use core\event\calendar_event_created;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_completion;
use tool_certification\certification_user;
use tool_certification\event\certification_created;
use tool_certification\event\certification_deleted;
use tool_certification\event\certification_updated;
use tool_certification\event\user_allocation_updated;
use tool_certification\event\user_allocation_created;
use tool_certification\event\user_allocation_deleted;
use tool_certification\event\certification_completion_created;
use tool_certification\constants;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * API tests.
 *
 * @package    tool_certification
 * @author     2018 Mitxel Moriana
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_api_testcase extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_program_generator */
    protected $programgenerator;

    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    public function test_create_certification(): void {
        global $DB;
        self::setAdminUser();
        $certificationdata = $this->generator->get_dummy_certificationdata();

        $program = $this->programgenerator->generate_program();
        $certificationdata->program = $program->get('id');

        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');
        $this->assertNotEmpty($certification->get('id'));

        $record = $DB->get_record('tool_certification', ['id' => $certificationid]);
        $this->assertNotFalse($record);
        $this->assertSame('A certification fullname', $record->fullname);
        $this->assertSame('1', $record->idnumber);
        $this->assertEquals('0', $record->archived);

        // Create from duplicate certification.
        $certificationdata->duplicatecertification = $certificationid;
        $certificationdata->allocationstartdatetype = null;
        $certificationdata->allocationstartdateabsolute = null;
        $certificationdata->allocationenddatetype = null;
        $certificationdata->allocationenddateabsolute = null;
        $this->assertNull($certificationdata->allocationstartdatetype);
        $certification2 = api::create_certification($certificationdata);
        $certificationid2 = $certification2->get('id');
        $this->assertNotEquals($certificationid, $certificationid2);

        $record2 = $DB->get_record('tool_certification', ['id' => $certificationid2]);
        $this->assertNotFalse($record2);
        $this->assertSame('A certification fullname', $record2->fullname);
        $this->assertSame('1', $record2->idnumber);
        $this->assertEquals('0', $record2->archived);
        $this->assertSame($record->allocationstartdatetype, $record2->allocationstartdatetype);
        $this->assertSame($record->allocationstartdateabsolute, $record2->allocationstartdateabsolute);
        $this->assertEquals($record->allocationenddatetype, $record2->allocationenddatetype);
        $this->assertEquals($record->allocationenddateabsolute, $record2->allocationenddateabsolute);
    }

    public function test_create_certification_triggers_event(): void {
        global $USER;
        $data = $this->generator->create_tenant_and_user();
        self::setUser($data->user);
        $programdata = $this->generator->get_dummy_program(['tenantid' => $data->defaulttenantid]);
        $program = \tool_program\api::create_program($programdata);

        $certificationdata = $this->generator->get_dummy_certificationdata();
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
        $eventdescription = "The user with id '" . $data->user->id . "' created the certification with id '"
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
            'allocationtype' => constants::ALLOCATION_CERTIFICATION
        ];

        $user2 = self::getDataGenerator()->create_user();
        $userdata2 = (object) [
            'certificationid' => $certificationid,
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION
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
            $this->assertContains($str, $e->getMessage());
        }

        // We marked as archived.
        $certification->set('archived', 1);
        $certification->update();

        $params = [
            'component' => 'tool_certification',
            'componentarea' => 'certification',
            'itemid' => $certificationid
        ];
        $this->assertTrue($DB->record_exists('tool_dynamicrule', $params));

        // We try to delete again.
        api::delete_certification($certification);

        $this->assertFalse($DB->get_record('tool_certification', ['id' => $certificationid]));
        $this->assertFalse($DB->record_exists('tool_dynamicrule', $params));
    }

    public function test_delete_certification_triggers_event(): void {
        global $USER;
        $data = $this->generator->create_tenant_and_user();
        self::setUser($data->user);
        $programdata = $this->generator->get_dummy_program(['tenantid' => $data->defaulttenantid]);
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
            $this->assertContains($str, $e->getMessage());
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
        $eventdescription = "The user with id '" . $data->user->id . "' deleted the certification with id '"
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
        $data = $this->generator->create_tenant_and_user();
        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid]);
        $certificationid = $certification->get('id');

        $originalstartdate = strtotime('-7 day');
        $userid = $data->user->id;
        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $userid,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
            'startdate' => $originalstartdate,
            'duedate' => strtotime('+7 day')
        ];

        $certificationuser = api::allocate_user($certification, $userdata);

        $this->assertNotEmpty($certificationuser);
        $record = $DB->get_record('tool_certification_users', ['id' => $certificationuser->get('id')]);
        $this->assertNotFalse($record);
        $this->assertEquals($certificationid, $record->certificationid);
        $this->assertEquals($userid, $record->userid);

        // Check allocation to related program has been also created.
        $params = [
            'programid' => $certification->get('program'),
            'certificationid' => $certificationid,
            'userid' => $userid
        ];
        $record = $DB->get_records('tool_program_users', $params);
        $this->assertNotEmpty($record);
        $this->assertCount(1, $record);

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
        $record = $DB->get_record('tool_program_users', ['userid' => $userid, 'certificationid' => $certificationid]);
        // We cannot change startdate after it is in the past.
        $this->assertEquals($originalstartdate, $record->startdate);

        $programuser = program_user::get_record($params);
        $programuser->delete();

        $originalstartdate = strtotime('+1 day');
        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $userid,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
            'startdate' => $originalstartdate,
            'duedate' => strtotime('+7 day')
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
        $record = $DB->get_record('tool_program_users', ['userid' => $userid, 'certificationid' => $certificationid]);
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
        api::set_user_as_certified($userid, $certificationid);
        api::deallocate_user($certificationid, $userid);

        $certificationuser = api::allocate_user($certification, $userdata);

        // User is certified. Current programid should be null.
        $this->assertNull($certificationuser->get('currentprogramid'));
        $lastcompletion = api::get_last_completion_record($userid, $certificationid);
        $nextstartdate = strtotime('-' . $certification->get('recertstartdaterelative'), $lastcompletion->get('expirydate'));
        $this->assertEquals($nextstartdate, $certificationuser->get('nextstartdate'));
    }

    public function test_allocate_user_triggers_event(): void {
        global $USER;
        self::setAdminUser();
        $certification = $this->generator->generate_certification();
        $certificationid = $certification->get('id');
        $user = self::getDataGenerator()->create_user();
        $userid = $user->id;
        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $userid,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
        ];

        $sink = $this->redirectEvents();
        $certuser = api::allocate_user($certification, $userdata);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(user_allocation_created::class, $event);

        $certificationid = $certification->get('id');

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($certuser->get('id'), $event->objectid);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals($certificationid, $event->other['certificationid']);

        // Test event get_name().
        $eventname = get_string('eventuserallocated', 'tool_certification');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '" . $USER->id . "' allocated the user with id '" . $certuser->get('id') . "'.";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $eventurl = new moodle_url('/admin/tool/certification/edit.php', ['id' => $certificationid]);
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
            'status' => constants::STATUS_OVERRIDE_DEFAULT
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
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        api::allocate_user($certification, $userdata);

        $sink = $this->redirectEvents();
        api::deallocate_user($certification->get('id'), $user->id);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(user_allocation_deleted::class, $event);
    }

    public function test_archive_certification(): void {
        self::setAdminUser();
        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');

        $archive = $certification->get('archived');
        $this->assertFalse($archive);

        $return = api::archive_certification($certificationid);
        $this->assertTrue($return);

        $certification = new certification($certificationid);

        $archive = $certification->get('archived');
        $this->assertEquals('1', $archive);
    }

    public function test_archive_certification_triggers_event(): void {
        self::setAdminUser();
        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');

        $archive = $certification->get('archived');
        $this->assertFalse($archive);

        $sink = $this->redirectEvents();
        api::archive_certification($certificationid);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(certification_updated::class, $event);
    }

    public function test_restore_certification(): void {
        self::setAdminUser();
        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certificationdata->archived = 1;
        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');

        $archive = $certification->get('archived');
        $this->assertEquals('1', $archive);

        $return = api::restore_certification($certificationid);
        $this->assertTrue($return);

        $certification = new certification($certificationid);

        $archive = $certification->get('archived');
        $this->assertEquals('0', $archive);
    }

    public function test_restore_certification_triggers_event(): void {
        self::setAdminUser();
        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certificationdata->archived = 1;
        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');

        $archive = $certification->get('archived');
        $this->assertEquals('1', $archive);

        $sink = $this->redirectEvents();
        api::restore_certification($certificationid);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(certification_updated::class, $event);
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
            'duedatetype' => constants::DATE_NONE,
            'duedaterelative' => '1 week',
            'duedateabsolute' => $now,
            'expirydatetype' => constants::DATE_NONE,
            'expirydaterelative' => '1 year',
            'expirydateabsolute' => $now,
            'program' => $certificationdata->program,
            'autocreategroups' => 0
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
            'autocreategroups' => 0
        ];

        $sink = $this->redirectEvents();
        api::update_certification_calendar($newdates);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(certification_updated::class, $event);
    }

    public function test_user_can_be_allocated(): void {
        self::setAdminUser();

        $certificationdata = $this->generator->get_dummy_certificationdata();
        // Allocation dates are not set.

        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');

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
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];

        $certuser = api::allocate_user($certification, $userdata);
        $params = ['certificationid' => $certificationid, 'userid' => $user->id, 'timerevoked' => 0];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertFalse($record);

        $sink = $this->redirectEvents();
        api::set_user_as_certified($user->id, $certificationid);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(calendar_event_created::class, $event);

        $event = array_pop($events);
        $this->assertInstanceOf(certification_completion_created::class, $event);
    }

    public function test_set_user_as_certified(): void {
        global $DB;
        $data = $this->generator->create_tenant_and_user();
        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid], true);
        $certificationid = $certification->get('id');

        $userid = $data->user->id;
        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $userid,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];

        $certuser = api::allocate_user($certification, $userdata);

        // We try with currentprogramid null.
        $certuser->set('currentprogramid', null);
        $certuser->update();
        try {
            api::set_user_as_certified($userid, $certificationid);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
            $this->assertContains('current program id should not be null', $e->getMessage());
        }

        $certuser->set('currentprogramid', $certification->get('program'));
        $certuser->update();

        api::set_user_as_certified($userid, $certificationid);

        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0, 'islast' => 1];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertNotEmpty($record);

        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'programid' => $certification->get('program')];
        $proguser = program_user::get_record($params);
        $this->assertNotEmpty($proguser);

        // Test passing an expirydate.
        $certuser->set('currentprogramid', $certification->get('program'));
        $certuser->update();
        $expirydate = strtotime('+2 day');
        $certified = strtotime('-2 day');
        $certifiedby = 33;
        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0, 'islast' => 1];
        $DB->delete_records('tool_certification_compltion', $params);

        api::set_user_as_certified($userid, $certificationid, $expirydate, $certified, $certifiedby);

        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertEquals($expirydate, $record->expirydate);
        $this->assertEquals($certified, $record->timecertified);
        $this->assertEquals($certifiedby, $record->certifiedby);
        $this->assertEquals($certification->get('program'), $record->programid);

        $userdata = ['userid' => $userid, 'certificationid' => $certificationid];
        $certificationuser = $DB->get_record('tool_certification_users', $userdata);
        $this->assertNull($certificationuser->currentprogramid);
        $nextstartdate = strtotime('-' . $certification->get('recertstartdaterelative'), $expirydate);
        $this->assertEquals($nextstartdate, $certificationuser->nextstartdate);

        // Recertification.
        api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $userid,
            constants::STATUS_OVERRIDE_DEFAULT);
        api::set_user_as_certified($userid, $certificationid, $expirydate, $certified, $certifiedby);

        $params2 = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0, 'islast' => 0];
        $firstcompletion = $DB->get_record('tool_certification_compltion', $params2);
        $this->assertEquals($record->id, $firstcompletion->id);
        $secondcompletion = $DB->get_record('tool_certification_compltion', $params);
        $this->assertEquals($certification->get('recertificationprogram'), $secondcompletion->programid);
    }

    public function test_set_certification_completed_by_user_and_program(): void {
        global $DB;
        $data = $this->generator->create_tenant_and_user();

        $programdata = $this->generator->get_dummy_program();
        unset($programdata->program_tags);
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

        $params = ['tenantid' => $data->defaulttenantid, 'program' => $programid];
        $certification1 = $this->generator->generate_certification($params);
        $certification2 = $this->generator->generate_certification($params);

        $userdata = (object) [
            'certificationid' => $certification1->get('id'),
            'userid' => $data->user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        api::allocate_user($certification1, $userdata);
        $userdata2 = (object) [
            'certificationid' => $certification2->get('id'),
            'userid' => $data->user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        api::allocate_user($certification2, $userdata2);

        api::set_certification_completed_by_user_and_program($data->user->id, $program->get('id'));
        $params = ['userid' => $data->user->id, 'certificationid' => $certification1->get('id'), 'timerevoked' => 0];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertNotEmpty($record);
        $params = ['userid' => $data->user->id, 'certificationid' => $certification2->get('id'), 'timerevoked' => 0];
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
            'startdatelocked' => constants::DATE_LOCKED
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

        // Mark the program related to the certification as completed by the user.
        $this->programgenerator->complete_program($program, $user->id);

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
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        api::allocate_user($certification, $userdata);

        $userstatus = api::get_user_allocation_status($certification->get('id'), $user->id);
        // Status should be Open.
        $this->assertNotEmpty($userstatus);
        $this->assertArrayHasKey('status', $userstatus[0]);
        $this->assertArrayHasKey('statusstr', $userstatus[0]);
        $this->assertArrayHasKey('statusint', $userstatus[0]);
        $this->assertEquals('Open', $userstatus[0]['statusstr']);
        $this->assertEquals(constants::STATUS_OPEN, $userstatus[0]['statusint']);

        // Future allocation status.
        $certification->set('startdateabsolute', $onedaymore);
        $certification->update();

        $user2 = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        api::allocate_user($certification, $userdata);
        $userstatus = api::get_user_allocation_status($certification->get('id'), $user2->id);
        $this->assertNotEmpty($userstatus);
        $this->assertArrayHasKey('status', $userstatus[0]);
        $this->assertArrayHasKey('statusstr', $userstatus[0]);
        $this->assertArrayHasKey('statusint', $userstatus[0]);
        $this->assertEquals('Future allocation', $userstatus[0]['statusstr']);
        $this->assertEquals(constants::STATUS_FUTUREALLOCATION, $userstatus[0]['statusint']);

        // Overdue status.
        $certification->set('startdateabsolute', $twodaysless);
        $certification->update();

        $user3 = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user3->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        $certuser = api::allocate_user($certification, $userdata);
        $userstatus = api::get_user_allocation_status($certification->get('id'), $user3->id);
        $this->assertNotEmpty($userstatus);
        $this->assertArrayHasKey('status', $userstatus[0]);
        $this->assertArrayHasKey('statusstr', $userstatus[0]);
        $this->assertArrayHasKey('statusint', $userstatus[0]);
        $this->assertEquals('Overdue', $userstatus[0]['statusstr']);
        $this->assertEquals(constants::STATUS_OVERDUE, $userstatus[0]['statusint']);

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
        $this->assertNotEmpty($userstatus);
        $this->assertArrayHasKey('status', $userstatus[0]);
        $this->assertArrayHasKey('statusstr', $userstatus[0]);
        $this->assertArrayHasKey('statusint', $userstatus[0]);
        $this->assertEquals('Suspended', $userstatus[0]['statusstr']);
        $this->assertEquals('cert_user_status_suspended', $userstatus[0]['status']);
        $this->assertEquals(constants::STATUS_SUSPENDED, $userstatus[0]['statusint']);

        // Certified status.
        $user4 = self::getDataGenerator()->create_user();
        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user4->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'expirydate' => 0,
            'timerevoked' => 0
        ];
        api::allocate_user($certification, $userdata);
        api::set_user_as_certified($user4->id, $certification->get('id'));
        $userstatus = api::get_user_allocation_status($certification->get('id'), $user4->id);
        $statusstr = get_string('certified', 'tool_certification');
        $this->assertNotEmpty($userstatus);
        $this->assertEquals($statusstr, $userstatus[0]['statusstr']);
        $this->assertEquals(constants::STATUS_CERTIFIED, $userstatus[0]['statusint']);

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
        $statusstr = get_string('suspended', 'tool_certification');
        $this->assertEquals($statusstr, $userstatus[0]['statusstr']);
        $this->assertEquals(constants::STATUS_SUSPENDED, $userstatus[0]['statusint']);
        $statusstr = get_string('certified', 'tool_certification');
        $this->assertNotEmpty($userstatus);
        $this->assertEquals($statusstr, $userstatus[1]['statusstr']);
        $this->assertEquals(constants::STATUS_CERTIFIED, $userstatus[1]['statusint']);

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
        $statusstr = get_string('expired', 'tool_certification');
        $this->assertNotEmpty($userstatus);
        $this->assertEquals($statusstr, $userstatus[0]['statusstr']);
        $this->assertEquals(constants::STATUS_EXPIRED, $userstatus[0]['statusint']);
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
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];

        api::allocate_user($certification, $userdata);
        api::set_user_as_certified($user->id, $certificationid);

        $iscertified = api::is_user_certified($user->id, $certificationid);
        $this->assertTrue($iscertified);
    }

    public function test_get_potential_programs(): void {
        $data = $this->generator->create_tenant_and_user();
        $programdata = $this->generator->get_dummy_program(['tenantid' => $data->defaulttenantid]);
        $program = \tool_program\api::create_program($programdata);

        $programdata->fullname = 'program number 2';
        $programdata->idnumber = 'idnumber_2';
        $program2 = \tool_program\api::create_program($programdata);

        $programdata->fullname = 'program number 3';
        $programdata->idnumber = 'idnumber_3';
        $programdata->tenantid = $data->othertenantid;
        $program3 = \tool_program\api::create_program($programdata);

        $search = 'program';
        $progslist = api::get_potential_programs($search);

        // Regular user can't search programs.
        $this->assertEmpty($progslist);

        // Assign user capability to edit certifications, then he will be able to search programs.
        $this->generator->assign_edit_capability($data->user->id, context_system::instance());
        $this->setUser($data->user);

        $progslist = api::get_potential_programs($search);
        $this->assertNotEmpty($progslist);
        $this->assertCount(2, $progslist);
        $this->assertArrayHasKey($program->get('id'), $progslist);
        $this->assertArrayHasKey($program2->get('id'), $progslist);
        $this->assertArrayNotHasKey($program3->get('id'), $progslist);
        $this->assertEquals('A program fullname', $progslist[$program->get('id')]->fullname);
        $this->assertEquals('program number 2', $progslist[$program2->get('id')]->fullname);

        $search = 'fullname';
        $progslist = api::get_potential_programs($search);
        $this->assertNotEmpty($progslist);
        $this->assertCount(1, $progslist);

        $search = 'team';
        $progslist = api::get_potential_programs($search);
        $this->assertEmpty($progslist);

        $search = 'idnumber_2';
        $progslist = api::get_potential_programs($search);
        $this->assertNotEmpty($progslist);
        $this->assertCount(1, $progslist);
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
            'timerevoked' => 0
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
        $recordprogramuser = $DB->get_record('tool_program_users', $params);
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
     * @covers \tool_program\api::add_default_dynamicrule_conditions_to_certification
     * @uses \tool_program\api::create_certification
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

    public function test_get_default_certification_dates() {
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
        $certification->set('expirydatetype', constants::DATE_NEVER);
        $certification->set('recertexpirydatetype', constants::RECERT_EXPIRY_DATE_NEVER_DATE);
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('Allocation date', $dates->startdate);
        $this->assertEquals('Never', $dates->expirydate);
        $this->assertEquals('Never', $dates->expirydaterecertification);

        $certification->set('startdatetype', constants::DATE_RELATIVE_TO_ALLOCATION_DATE);
        $certification->set('startdaterelative', '1 month');
        $certification->set('expirydatetype', constants::DATE_AFTER_DUE_DATE);
        $certification->set('expirydaterelative', '1 week');
        $certification->set('recertexpirydatetype', constants::RECERT_EXPIRY_DATE_AFTR_PREV_EXP);
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('1 month relative to allocation date', $dates->startdate);
        $this->assertEquals('1 week after due date', $dates->expirydate);
        $this->assertEquals('1 week after previous certification expiry date', $dates->expirydaterecertification);

        $certification->set('expirydatetype', constants::DATE_AFTER_ALLOCATION_DATE);
        $certification->set('expirydaterelative', '2 week');
        $certification->set('recertexpirydatetype', constants::RECERT_EXPIRY_DATE_AFTR_LATEST);
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('2 week after allocation date', $dates->expirydate);
        $str = '1 week after the latter of the current completion or expiration';
        $this->assertEquals($str, $dates->expirydaterecertification);

        $certification->set('expirydatetype', constants::DATE_AFTER_COMPLETION);
        $certification->set('expirydaterelative', '2 week');
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('2 week after completion', $dates->expirydate);
    }

    public function test_remove_deleted_user_from_certifications() {
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

    public function test_is_idnumber_unique() {
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
    public function recalculate_nextstartdate_provider() : array {
        return [
            [0, 0, 0],
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
     * @throws coding_exception
     *
     * @dataProvider recalculate_nextstartdate_provider
     */
    public function test_recalculate_nextstartdate(string $expirydate, string $expected, string $expected2) {
        $certification = $this->generator->generate_certification(['recertstartdaterelative' => '1 day']);
        $nextstartdate = api::recalculate_nextstartdate($certification, strtotime($expirydate));
        $this->assertEquals(strtotime($expected), $nextstartdate);

        $certification->set('recertstartdaterelative', '1 week');
        $certification->update();
        $nextstartdate = api::recalculate_nextstartdate($certification, strtotime($expirydate));
        $this->assertEquals(strtotime($expected2), $nextstartdate);
    }

    public function test_get_last_completion_record() {
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

    public function test_recalculate_recertification_user_due_date() {
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
            $this->assertContains('Completion records should exist if is recertification', $e->getMessage());
        }

        $completiondata = $this->generator->get_dummy_completion_data($certificationid, $user1->id, $programid, 1);
        $completion1 = new certification_completion(0, $completiondata);
        $completion1->create();
        $duedate = api::recalculate_recertification_user_due_date($certificationuser);
        $this->assertEquals($duedate, $completion1->get('expirydate'));
    }

    public function test_get_previous_valid_completion_record() {
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

    public function test_is_recertification_program_different() {
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
    public function recalculate_graceperiodends_provider() : array {
        return [
            [0, 0, 0],
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
     * @throws coding_exception
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
        $recordprogram = $DB->get_record('tool_program_users', $params);
        $this->assertEquals($programid, $recordprogram->programid);

        api::set_user_as_certified($user1->id, $certificationid);
        api::allocate_recertification_users();

        // User is allocated into recertification program. Recertification program is different from the initial one.
        $recordprogram = $DB->get_record('tool_program_users', $params);
        $this->assertNotEquals($programid, $recordprogram->programid);
        $this->assertEquals($recordprogram->programid, $certification->get('recertificationprogram'));

        api::reallocate_user_into_initial_program($certification, $certificationuser);

        // User is allocated into initial program.
        $recordprogram = $DB->get_record('tool_program_users', $params);
        $this->assertEquals($programid, $recordprogram->programid);
    }

    public function test_allocate_recertification_users() {
        global $DB;
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day'
        ];
        $certification = $this->generator->generate_certification($params, true);
        $user1 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user1->id, $certification->get('id'));

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

        api::allocate_recertification_users();

        $recordprogramuser = $DB->get_record('tool_program_users', $params);
        $this->assertEquals($certification->get('recertificationprogram'), $recordprogramuser->programid);

        $recordcertificationuser = $DB->get_record('tool_certification_users', $params);
        $this->assertNotNull($recordcertificationuser->currentprogramid);
        $this->assertEquals(1, $recordcertificationuser->isrecertification);
        $this->assertEquals($graceperiod, $recordcertificationuser->graceperiodends);
        $this->assertEquals($nextstartdate, $recordcertificationuser->nextstartdate);
    }

    public function test_allocate_user_recertification() {
        global $DB;
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day'
        ];
        $certification = $this->generator->generate_certification($params, true);
        $user1 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user1->id, $certification->get('id'));

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'programid' => $certification->get('recertificationprogram'),
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];

        try {
            api::allocate_user_recertification($certification, $certification->get('recertificationprogram'), $user1->id,
                constants::STATUS_OVERRIDE_DEFAULT);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
            $this->assertContains('Previous completion should exist', $e->getMessage());
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
        $this->assertEquals(1, $certificationuser->get('isrecertification'));
        $this->assertEquals($graceperiod, $certificationuser->get('graceperiodends'));
        $this->assertEquals($nextstartdate, $certificationuser->get('nextstartdate'));
        $recordprogramuser = $DB->get_record('tool_program_users', $params);
        $this->assertEquals($certification->get('recertificationprogram'), $recordprogramuser->programid);
        $this->assertEquals($nextstartdate, $recordprogramuser->startdate);
        $this->assertEquals(0, $recordprogramuser->startdatelocked);
        $this->assertEquals($lastcompletion->get('expirydate'), $recordprogramuser->duedate);
        $this->assertEquals(0, $recordprogramuser->duedatelocked);
        $this->assertEquals(0, $recordprogramuser->enddate);
        $this->assertEquals(1, $recordprogramuser->status);
    }

    public function test_deallocate_users_after_grace_period_end() {
        global $DB;
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day'
        ];
        $certification = $this->generator->generate_certification($params, true);
        $user1 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user1->id, $certification->get('id'));

        api::set_user_as_certified($user1->id, $certification->get('id'));
        $certificationuser = api::allocate_user_recertification($certification, $certification->get('recertificationprogram'),
            $user1->id, constants::STATUS_OVERRIDE_DEFAULT);

        // User is allocated to recertification program.
        $params = ['certificationid' => $certification->get('id'), 'userid' => $user1->id];
        $recordprogramuser = $DB->get_record('tool_program_users', $params);
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

    public function test_update_certification_recertification() {
        global $DB;
        $certification = $this->generator->generate_certification([], true);

        $data = (object)[
            'id' => $certification->get('id'),
            'requirerecertification' => 0
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
            'startdaterelative' => '0 day'
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

    public function test_delete_certification_dynamic_rules() {
        global $DB;
        $certification = $this->generator->generate_certification();

        $params = [
            'component' => 'tool_certification',
            'componentarea' => 'certification',
            'itemid' => $certification->get('id')
        ];
        $this->assertTrue($DB->record_exists('tool_dynamicrule', $params));
        api::delete_certification_dynamic_rules($certification->get('id'));
        $this->assertFalse($DB->record_exists('tool_dynamicrule', $params));
    }
}
