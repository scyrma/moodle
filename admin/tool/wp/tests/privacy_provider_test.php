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
 * File containing tests for privacy provider class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use tool_wp\privacy\provider;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\import_persistent;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\privacy\provider
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_privacy_provider_testcase extends provider_testcase {

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
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        // Initialise a course reset for our test user/course.
        (new \tool_wp\course_reset_api($this->course->id, $this->user->id))->reset_course([
            'reason' => 'Privacy API test',
        ]);
    }

    /**
     * Test class get_contexts_for_userid method
     *
     * @return void
     */
    public function test_get_contexts_for_userid() {
        $contextlist = $this->get_contexts_for_userid($this->user->id, 'tool_wp');
        $this->assertCount(1, $contextlist);

        $coursecontext = context_course::instance($this->course->id);
        $this->assertSame($coursecontext, $contextlist->current());
    }

    /**
     * Test class get_contexts_for_userid method for user who has performed an export
     *
     * @return void
     */
    public function test_get_contexts_for_userid_export() {
        (new export_persistent(0, (object) [
            'createdby' => $this->user->id,
            'exporter' => 'hello',
        ]))->save();

        $contextlist = $this->get_contexts_for_userid($this->user->id, 'tool_wp');
        $this->assertCount(2, $contextlist);

        $context = context_system::instance();
        $this->assertSame($context, $contextlist->get_contexts()[1]);
    }

    /**
     * Test class get_contexts_for_userid method for user who has performed an export
     *
     * @return void
     */
    public function test_get_contexts_for_userid_import() {
        (new import_persistent(0, (object) [
            'createdby' => $this->user->id,
        ]))->save();

        $contextlist = $this->get_contexts_for_userid($this->user->id, 'tool_wp');
        $this->assertCount(2, $contextlist);

        $context = context_system::instance();
        $this->assertSame($context, $contextlist->get_contexts()[1]);
    }

    /**
     * Test class get_users_in_context method
     *
     * @return void
     */
    public function test_get_users_in_context() {
        $coursecontext = context_course::instance($this->course->id);

        $userlist = new userlist($coursecontext, 'tool_wp');
        provider::get_users_in_context($userlist);

        $this->assertEquals([$this->user->id], $userlist->get_userids());
    }

    /**
     * Test class export_user_data method
     *
     * return void
     */
    public function test_export_user_data() {
        (new export_persistent(0, (object) [
            'createdby' => $this->user->id,
            'exporter' => 'hello',
        ]))->save();

        (new import_persistent(0, (object) [
            'createdby' => $this->user->id,
        ]))->save();

        $context = context_system::instance();
        $this->export_context_data_for_user($this->user->id, $context, 'tool_wp');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        /** @var stdClass[] $exportdata */
        $exportdata = $writer->get_related_data([get_string('pluginname', 'tool_wp')], 'exports');
        $this->assertCount(1, $exportdata);
        $this->assertEquals($this->user->id, $exportdata[0]->createdby);
        $this->assertNotEmpty($exportdata[0]->timecreated);
        $this->assertNull($exportdata[0]->tenantid);
        $this->assertEquals(0, $exportdata[0]->status);

        /** @var stdClass[] $importdata */
        $importdata = $writer->get_related_data([get_string('pluginname', 'tool_wp')], 'imports');
        $this->assertCount(1, $importdata);
        $this->assertEquals($this->user->id, $importdata[0]->createdby);
        $this->assertNotEmpty($importdata[0]->timecreated);
        $this->assertNull($importdata[0]->tenantid);
        $this->assertEquals(0, $importdata[0]->status);
    }

    /**
     * Test class delete_data_for_all_users_in_context method
     *
     * return void
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $coursecontext = context_course::instance($this->course->id);
        provider::delete_data_for_all_users_in_context($coursecontext);

        $this->assertEmpty($DB->get_records('tool_wp_course_reset', ['courseid' => $this->course->id]));
    }

    /**
     * Test class delete_data_for_user method
     *
     * return void
     */
    public function test_delete_data_for_user() {
        global $DB;

        $contextlist = $this->get_contexts_for_userid($this->user->id, 'tool_wp');

        $approvedcontextlist = new approved_contextlist($this->user, 'tool_wp', $contextlist->get_contextids());
        provider::delete_data_for_user($approvedcontextlist);

        $this->assertEmpty($DB->get_records('tool_wp_course_reset', ['userid' => $this->user->id]));
    }

    /**
     * Test class delete_data_for_users method
     *
     * return void
     */
    public function test_delete_data_for_users() {
        global $DB;

        $coursecontext = context_course::instance($this->course->id);

        $userlist = new approved_userlist($coursecontext, 'tool_wp', [$this->user->id]);
        provider::delete_data_for_users($userlist);

        $this->assertEmpty($DB->get_records('tool_wp_course_reset', ['courseid' => $this->course->id]));
    }
}
