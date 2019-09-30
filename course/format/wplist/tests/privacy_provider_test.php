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
 * Privacy provider tests.
 *
 * @package    format_wplist
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \format_wplist\privacy\provider;
use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\approved_userlist;
use \core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests class.
 *
 * @package    format_wplist
 * @group      format_wplist
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_wplist_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Test set up.
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Get dynamic rule generator
     *
     * @return tool_organisation_generator
     */
    protected function get_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Test provider::get_metadata
     */
    public function test_get_metadata() {
        $collection = new collection('format_wplist');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(1, $itemcollection);

        $table = array_pop($itemcollection);
        $this->assertEquals('format_wplist_opensections', $table->get_name());
        $this->assertEquals('privacy:metadata:opensections', $table->get_summary());

        $this->assertNull($table->get_privacy_fields());
    }

    /**
     * Ensure that export_user_preferences returns no data if the user has not visited the myoverview block.
     */
    public function test_export_user_preferences_no_pref() {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['format' => 'wplist']);
        $coursecontext = \context_course::instance($course->id);
        provider::export_user_preferences($user->id);
        $writer = writer::with_context($coursecontext);
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * Test the export_user_preferences given different inputs
     */
    public function test_export_user_preferences() {
        global $DB;

        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course(['format' => 'wplist']);
        $coursecontext = \context_course::instance($course->id);

        $firstsectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 0]);

        // Expand a section in a course for user1.
        $prefname = 'format_wplist_opensections_' . $coursecontext->id;
        $prefvalue = "[{$firstsectionid}]";
        set_user_preference($prefname, $prefvalue, $user1->id);

        provider::export_user_preferences($user1->id);
        $writer = writer::with_context($coursecontext);
        $formatpreferences = $writer->get_user_preferences('format_wplist');
        $this->assertEquals($prefvalue, $formatpreferences->$prefname->value);
    }

    /**
     * Test for provider::get_contexts_for_userid().
     */
    public function test_get_contexts_for_userid() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Make sure contexts are not being returned for user1.
        $contextlist = provider::get_contexts_for_userid($user1->id);
        $this->assertCount(0, $contextlist->get_contextids());

        // Make sure contexts are not being returned for user2.
        $contextlist = provider::get_contexts_for_userid($user2->id);
        $this->assertCount(0, $contextlist->get_contextids());

        $course = $this->getDataGenerator()->create_course(['format' => 'wplist']);
        $coursecontext = \context_course::instance($course->id);

        $firstsectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 0]);

        // Expand a section in a course for user1.
        set_user_preference('format_wplist_opensections_' . $coursecontext->id, "[{$firstsectionid}]", $user1->id);

        // Make sure the course context is being returned for user1.
        $contextlist = provider::get_contexts_for_userid($user1->id);
        $expected = [$coursecontext->id];
        $actual = $contextlist->get_contextids();
        $this->assertCount(1, $actual);
        $this->assertEquals($expected, $actual);

        // Make sure contexts are still not being returned for user2.
        $contextlist = provider::get_contexts_for_userid($user2->id);
        $this->assertCount(0, $contextlist->get_contextids());

        // Expand a section in a course for user2.
        set_user_preference('format_wplist_opensections_' . $coursecontext->id, "[{$firstsectionid}]", $user2->id);

        // Make sure the course context is being returned for user2.
        $contextlist = provider::get_contexts_for_userid($user2->id);
        $expected = [$coursecontext->id];
        $actual = $contextlist->get_contextids();
        $this->assertCount(1, $actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test that only users within a context are fetched.
     */
    public function test_get_users_in_context() {
        global $DB;

        $component = 'format_wplist';

        $user1 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course(['format' => 'wplist']);
        $coursecontext = \context_course::instance($course->id);

        // The user list for coursecontext should not have any users.
        $userlist1 = new \core_privacy\local\request\userlist($coursecontext, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);

        $firstsectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 0]);

        // Expand a section in a course for user1.
        set_user_preference('format_wplist_opensections_' . $coursecontext->id, "[{$firstsectionid}]", $user1->id);

        // The user list for coursecontext now should have one user.
        $userlist2 = new \core_privacy\local\request\userlist($coursecontext, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);

        // The user list for systemcontext should not have any users.
        $userlist3 = new \core_privacy\local\request\userlist(\context_system::instance(), $component);
        provider::get_users_in_context($userlist3);
        $this->assertCount(0, $userlist3);
    }

    /**
     * Test for provider::export_user_data().
     */
    public function test_export_user_data() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course(['format' => 'wplist']);
        $coursecontext = \context_course::instance($course->id);
        $firstsectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 0]);

        // Expand a section in a course for user1.
        set_user_preference('format_wplist_opensections_' . $coursecontext->id, "[{$firstsectionid}]", $user1->id);

        // Export all of the data for the course context for user 1.
        $this->export_context_data_for_user($user1->id, $coursecontext, 'format_wplist');
        $writer = \core_privacy\local\request\writer::with_context($coursecontext);

        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course(['format' => 'wplist']);
        $coursecontext = \context_course::instance($course->id);

        $firstsectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 0]);

        set_user_preference('format_wplist_opensections_' . $coursecontext->id, "[{$firstsectionid}]", $user1->id);
        set_user_preference('format_wplist_opensections_' . $coursecontext->id, "[{$firstsectionid}]", $user2->id);

        // Delete data on system context will do nothing.
        $context = \context_system::instance();
        provider::delete_data_for_all_users_in_context($context);

        $params = ['name' => 'format_wplist_opensections_' . $coursecontext->id];

        $this->assertEquals(2, $DB->count_records('user_preferences', $params));

        // Delete data on course context will delete user's opensections.
        provider::delete_data_for_all_users_in_context($coursecontext);

        // After deletion, all jobs should have been deleted.
        $this->assertEquals(0, $DB->count_records('user_preferences', $params));
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course(['format' => 'wplist']);
        $coursecontext = \context_course::instance($course->id);

        $firstsectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 0]);

        set_user_preference('format_wplist_opensections_' . $coursecontext->id, "[{$firstsectionid}]", $user1->id);
        set_user_preference('format_wplist_opensections_' . $coursecontext->id, "[{$firstsectionid}]", $user2->id);

        $where = $DB->sql_like('name', ':name');
        $params['name'] = 'format_wplist_opensections_%';

        // Before deletion we should have 2 users with opensections.
        $this->assertEquals(2, $DB->count_records_select('user_preferences', $where, $params));

        // Delete data without context will do nothing.
        $contextlist = new \core_privacy\local\request\approved_contextlist($user1, 'format_wplist', []);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(2, $DB->count_records_select('user_preferences', $where, $params));

        // Delete data on system instance will do nothing.
        $context = \context_system::instance();
        $contextlist = new \core_privacy\local\request\approved_contextlist($user1, 'format_wplist', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(2, $DB->count_records_select('user_preferences', $where, $params));

        // Delete data on course context will delete user's open sections.
        $contextlist = new \core_privacy\local\request\approved_contextlist($user1, 'format_wplist', [$coursecontext->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(1, $DB->count_records_select('user_preferences', $where, $params));

        // Check the open sections for the other user are still there.
        $where .= ' AND userid = :userid';
        $params['userid'] = $user2->id;

        $this->assertEquals(1, $DB->count_records_select('user_preferences', $where, $params));
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users() {
        global $DB;

        $component = 'format_wplist';

        $user1 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course(['format' => 'wplist']);
        $coursecontext = \context_course::instance($course->id);

        $firstsectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 0]);

        set_user_preference('format_wplist_opensections_' . $coursecontext->id, "[{$firstsectionid}]", $user1->id);

        $userlist1 = new \core_privacy\local\request\userlist($coursecontext, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(1, $userlist1);

        // Convert $userlist1 into an approved_contextlist.
        $approvedlist1 = new approved_userlist($coursecontext, $component, $userlist1->get_userids());

        // Delete using delete_data_for_user.
        provider::delete_data_for_users($approvedlist1);
        // Re-fetch users in usercontext.
        $userlist1 = new \core_privacy\local\request\userlist($coursecontext, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);
    }
}
