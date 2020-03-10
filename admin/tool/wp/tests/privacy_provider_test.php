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
    public function setUp() {
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
        $coursecontext = context_course::instance($this->course->id);
        $this->export_context_data_for_user($this->user->id, $coursecontext, 'tool_wp');

        $writer = writer::with_context($coursecontext);
        $this->assertFalse($writer->has_any_data());
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