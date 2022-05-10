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
 * @package     tool_datastore
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use tool_datastore\helper;
use tool_datastore\privacy\provider;

/**
 * Test class
 *
 * @package     tool_datastore
 * @group       tool_datastore
 * @category    test
 * @covers      \tool_datastore\privacy\provider
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_datastore_privacy_provider_testcase extends provider_testcase {

    /** @var stdClass $course */
    protected $course;

    /** @var stdClass $teacher */
    protected $teacher;

    /** @var stdClass $student */
    protected $student;

    /*
     * Test setup
     *
     * @return void
     */
    public function setUp(): void {
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();

        // Create teacher, set them as the current user.
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->teacher);

        // Create a course completion action (by the teacher).
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        helper::add_course_completion($this->student->id, $this->course->id, time());
    }

    /**
     * Test class get_contexts_for_userid method
     *
     * @return void
     */
    public function test_get_contexts_for_userid() {
        // User who created the action (usermodified).
        $contextlist = $this->get_contexts_for_userid($this->teacher->id, 'tool_datastore');
        $this->assertCount(1, $contextlist);
        $this->assertInstanceOf(context_system::class, $contextlist->current());

        // User affected by the action (relateduserid).
        $contextlist = $this->get_contexts_for_userid($this->student->id, 'tool_datastore');
        $this->assertCount(1, $contextlist);
        $this->assertInstanceOf(context_system::class, $contextlist->current());
    }

    /**
     * Test class get_users_in_context method
     *
     * @return void
     */
    public function test_get_users_in_context() {
        $userlist = new userlist(context_system::instance(), 'tool_datastore');
        provider::get_users_in_context($userlist);

        $this->assertCount(2, $userlist);
        $this->assertEqualsCanonicalizing([$this->teacher->id, $this->student->id],
            $userlist->get_userids());
    }

    /**
     * Test class export_user_data method
     *
     * return void
     */
    public function test_export_user_data() {
        $context = context_system::instance();
        $this->export_context_data_for_user($this->teacher->id, $context, 'tool_datastore');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_related_data([get_string('pluginname', 'tool_datastore')], 'actions');

        $this->assertCount(1, $data);
        $this->assertEquals('course_completed', $data[0]->action);
        $this->assertEquals($this->student->id, $data[0]->relateduserid);
        $this->assertEquals($this->course->id, $data[0]->originalcourseid);
        $this->assertEmpty($data[0]->originalprogramid);
        $this->assertEquals(1, $data[0]->tenantid);
        $this->assertEquals($this->teacher->id, $data[0]->usermodified);
        $this->assertNotEmpty($data[0]->timecreated);
    }
}
