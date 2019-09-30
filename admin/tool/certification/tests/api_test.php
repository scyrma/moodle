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
 */

use core\event\base;
use tool_certification\api;
use tool_certification\certification;
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
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];

        $user2 = self::getDataGenerator()->create_user();
        $userdata2 = (object) [
            'certificationid' => $certificationid,
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
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

        // We try to delete again.
        api::delete_certification($certification);

        $softdeletedcertrecord = $DB->get_record('tool_certification', ['id' => $certificationid]);
        $this->assertFalse($softdeletedcertrecord);
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

        $userid = $data->user->id;
        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $userid,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];

        $certificationuser = api::allocate_user($certification, $userdata);

        $this->assertNotEmpty($certificationuser);
        $record = $DB->get_record('tool_certification_users', ['id' => $certificationuser->get('id')]);
        $this->assertNotFalse($record);
        $this->assertEquals($certificationid, $record->certificationid);
        $this->assertEquals($userid, $record->userid);
        $this->assertEquals($certification->get('startdateabsolute'), $record->startdate);
        $this->assertEquals($certification->get('expirydateabsolute'), $record->expirydate);

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
        api::update_certification_details($certdata);
        $record = $DB->get_record('tool_certification_users', ['userid' => $userid, 'certificationid' => $certificationid]);
        $this->assertEquals($record->timecreated, $record->startdate);

        $certdata->startdatetype = constants::DATE_RELATIVE_TO_ALLOCATION_DATE;
        $certdata->startdaterelative = '1 week';
        api::update_certification_details($certdata);
        $record = $DB->get_record('tool_certification_users', ['userid' => $userid, 'certificationid' => $certificationid]);
        $userstartdate = strtotime('+1 week', $record->timecreated);
        $this->assertEquals($userstartdate, $record->startdate);

        $certdata->startdatetype = 99;
        try {
            api::update_certification_details($certdata);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }

        // Test recalculation of user expiry dates.
        $certdata->startdatetype = constants::DATE_ABSOLUTE;
        $certdata->startdateabsolute = strtotime("-7 day");
        $certdata->duedatetype = constants::DATE_AFTER_START_DATE;
        $certdata->duedaterelative = '1 week';
        $certdata->expirydatetype = constants::DATE_NEVER;
        api::update_certification_details($certdata);
        $record = $DB->get_record('tool_certification_users', ['userid' => $userid, 'certificationid' => $certificationid]);
        $this->assertEquals(0, $record->expirydate);

        $certdata->expirydatetype = constants::DATE_AFTER_DUE_DATE;
        $certdata->expirydaterelative = '1 week';
        api::update_certification_details($certdata);
        $record = $DB->get_record('tool_certification_users', ['userid' => $userid, 'certificationid' => $certificationid]);
        $userexpirydate = strtotime('+1 week', $record->duedate);
        $this->assertEquals($userexpirydate, $record->expirydate);

        $certdata->duedatetype = constants::DATE_NONE;
        $certdata->expirydaterelative = '1 week';
        api::update_certification_details($certdata);
        $record = $DB->get_record('tool_certification_users', ['userid' => $userid, 'certificationid' => $certificationid]);
        $userexpirydate = strtotime('+1 week', $record->duedate);
        $this->assertEquals($userexpirydate, $record->expirydate);

        $certdata->expirydatetype = 99;
        try {
            api::update_certification_details($certdata);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }
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
            'status' => constants::STATUS_OVERRIDE_DEFAULT
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
        ];
        api::update_certification_calendar($newdates);

        $certification = new certification($certificationid);

        $startdatetype = $certification->get('allocationstartdatetype');
        $this->assertEquals(constants::DATE_ABSOLUTE, $startdatetype);
        $startdateabsolute = $certification->get('allocationstartdateabsolute');
        $this->assertEquals($now, $startdateabsolute);
        $enddatetype = $certification->get('allocationenddatetype');
        $this->assertEquals(constants::ALLOCATION_SET, $enddatetype);
        $enddateabsolute = $certification->get('allocationenddateabsolute');
        $this->assertEquals($now, $enddateabsolute);
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
        $this->generator->allocate_user($user->id, $certificationid);
        $params = ['certificationid' => $certificationid, 'userid' => $user->id, 'timerevoked' => 0];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertFalse($record);

        $sink = $this->redirectEvents();
        api::set_user_as_certified($user->id, $certificationid);
        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(certification_completion_created::class, $event);
    }

    public function test_set_user_as_certified(): void {
        global $DB;
        $data = $this->generator->create_tenant_and_user();
        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid]);
        $certificationid = $certification->get('id');

        $userid = $data->user->id;
        $userdata = (object) [
            'certificationid' => $certificationid,
            'userid' => $userid,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];

        api::allocate_user($certification, $userdata);
        api::set_user_as_certified($userid, $certificationid, true);

        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertNotEmpty($record);

        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'programid' => $certification->get('program')];
        $proguser = program_user::get_record($params);
        $this->assertNotEmpty($proguser);
        $this->assertEquals(0, $proguser->get('status'));

        // Test passing an expirydate.
        $twodays = strtotime('+2 day');
        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0];
        $DB->delete_records('tool_certification_compltion', $params);
        api::set_user_as_certified($userid, $certificationid, true, $twodays);

        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertNotEmpty($record);
        $this->assertEquals($twodays, $record->expirydate);
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

        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $certificationuser->get('status'));
        $this->assertEquals($sevendays, $certificationuser->get('startdate'));
        $this->assertEquals(constants::DATE_LOCKED, (int)$certificationuser->get('startdatelocked'));
        $this->assertEquals('0', $certificationuser->get('enddate'));
        $this->assertEquals(constants::DATE_LOCKED, (int)$certificationuser->get('enddatelocked'));

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
        ];
        api::update_certification_user_dates_and_status($certificationuser, $data);

        $this->assertEquals(constants::STATUS_OVERRIDE_SUSPENDED, $certificationuser->get('status'));
        $this->assertEquals($onedayless, $certificationuser->get('startdate'));
        $this->assertEquals(constants::DATE_LOCKED, $certificationuser->get('startdatelocked'));
        $this->assertEquals($onedaymore, $certificationuser->get('duedate'));
        $this->assertEquals(constants::DATE_LOCKED, $certificationuser->get('duedatelocked'));
        $this->assertEquals('0', $certificationuser->get('enddate'));

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
        ];
        api::update_certification_user_dates_and_status($certificationuser, $data);

        $this->assertEquals(constants::STATUS_OVERRIDE_DEFAULT, $certificationuser->get('status'));
        $this->assertEquals($now, $certificationuser->get('startdate'));
        $this->assertEquals(constants::DATE_UNLOCKED, $certificationuser->get('startdatelocked'));
        $this->assertEquals($onedaymore, $certificationuser->get('duedate'));
        $this->assertEquals(constants::DATE_UNLOCKED, $certificationuser->get('duedatelocked'));
        $this->assertEquals('0', $certificationuser->get('enddate'));
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
        $this->generator->complete_certification($certification, $user4->id);
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
        $this->generator->complete_certification($certification, $user4->id);
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
        $this->generator->complete_certification($certification, $user5->id, $expirydate);
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
        $certificationdata = $this->generator->get_dummy_certificationdata();
        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');
        $user = self::getDataGenerator()->create_user();

        $iscertified = api::is_user_certified($user->id, $certificationid);
        $this->assertFalse($iscertified);

        $this->generator->complete_certification($certification, $user->id);

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
        $this->assertEquals(constants::DATE_ABSOLUTE, $data->startdatetype);
        $this->assertEquals(constants::DATE_AFTER_START_DATE, $data->duedatetype);

        $now = time();
        $data->fullname = 'Name changed!';
        $data->idnumber = '33';
        $data->startdatetype = constants::DATE_NONE;
        $data->duedatetype = constants::DATE_NONE;
        $data->expirydatetype = constants::DATE_NONE;
        $data->startdateabsolute = $now;
        $data->expirydateabsolute = $now;
        $data->startdaterelative = '1 day';
        $data->duedaterelative = '1 week';
        $data->expirydaterelative = '1 year';

        api::update_certification_details($data);

        $certification = new certification($data->id);
        $this->assertEquals('Name changed!', $certification->get('fullname'));
        $this->assertEquals('33', $certification->get('idnumber'));
        $this->assertEquals(constants::DATE_NONE, $certification->get('startdatetype'));
        $this->assertEquals(constants::DATE_AFTER_START_DATE, $certification->get('duedatetype'));
        $this->assertEquals(constants::DATE_NONE, $certification->get('expirydatetype'));
        $this->assertEquals($now, $certification->get('startdateabsolute'));
        $this->assertEquals($now, $certification->get('expirydateabsolute'));
        $this->assertEquals('1 day', $certification->get('startdaterelative'));
        $this->assertEquals('1 week', $certification->get('duedaterelative'));
        $this->assertEquals('1 year', $certification->get('expirydaterelative'));
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
        global $DB;

        $certification = $this->generator->generate_certification();
        $certificationid = $certification->get('id');

        $user = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user->id, $certificationid);

        $params = ['certificationid' => $certificationid, 'userid' => $user->id, 'timerevoked' => 0];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertFalse($record);

        api::set_user_as_certified($user->id, $certificationid);

        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertEquals($certificationid, $record->certificationid);
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals('0', $record->expirydate);

        $sink = $this->redirectEvents();
        api::revoke_certification_from_user($user->id, $certificationid);

        $events = $sink->get_events();
        $sink->close();
        $event = array_pop($events);
        $this->assertInstanceOf(user_allocation_updated::class, $event);

        $params = ['certificationid' => $certificationid, 'userid' => $user->id, 'timerevoked' => 0];
        $record = $DB->get_record('tool_certification_compltion', $params);
        $this->assertFalse($record);
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
        $this->assertCount(6, $rulerecords);
        foreach ($rulerecords as $rulerecord) {
            $this->assertEquals($certification->get('tenantid'), $rulerecord->tenantid);
            $conditions = $DB->get_records('tool_dynamicrule_condition', ['ruleid' => $rulerecord->id]);
            $this->assertCount(1, $conditions);
        }
    }

    public function test_get_default_certification_dates() {
        self::setAdminUser();
        $certification = $this->generator->generate_certification();

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

        $certification->set('startdatetype', constants::DATE_USER_ALLOCATION_DATE);
        $certification->set('expirydatetype', constants::DATE_NEVER);
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('Allocation date', $dates->startdate);
        $this->assertEquals('Never', $dates->expirydate);

        $certification->set('startdatetype', constants::DATE_RELATIVE_TO_ALLOCATION_DATE);
        $certification->set('startdaterelative', '1 month');
        $certification->set('expirydatetype', constants::DATE_AFTER_DUE_DATE);
        $certification->set('expirydaterelative', '1 week');
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('1 month relative to allocation date', $dates->startdate);
        $this->assertEquals('1 week after due date', $dates->expirydate);

        $certification->set('expirydatetype', constants::DATE_AFTER_ALLOCATION_DATE);
        $certification->set('expirydaterelative', '2 week');
        $certification->update();
        $dates = api::get_default_certification_dates($certification);
        $this->assertEquals('2 week after allocation date', $dates->expirydate);

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
}
