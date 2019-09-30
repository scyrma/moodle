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
 * @package    tool_organisation
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_organisation\privacy\provider;
use core_privacy\local\metadata\collection;
use \core_privacy\local\request\approved_userlist;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests class.
 *
 * @package    tool_organisation
 * @group      tool_organisation
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_organisation_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Test set up.
     */
    public function setUp() {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $pf = $generator->create_position();
        $pa = $generator->create_position(['parentid' => $pf->id]);

        $df = $generator->create_department();
        $da = $generator->create_department(['parentid' => $df->id]);

        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();

        $this->manager = new \tool_organisation\job_manager();

        $this->manager->create_job((object)['userid' => $this->user1->id,
            'positionid' => $pa->id, 'departmentid' => $da->id, 'startdate' => 1262304000]);

        $this->manager->create_job((object)['userid' => $this->user2->id,
            'positionid' => $pa->id, 'departmentid' => $da->id, 'startdate' => 1262304000]);
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
        $collection = new collection('tool_organisation');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(1, $itemcollection);

        $table = array_pop($itemcollection);
        $this->assertEquals('tool_organisation_job', $table->get_name());
        $this->assertEquals('privacy:metadata:jobssummary', $table->get_summary());

        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('department', $privacyfields);
        $this->assertArrayHasKey('position', $privacyfields);
        $this->assertArrayHasKey('startdate', $privacyfields);
        $this->assertArrayHasKey('enddate', $privacyfields);
        $this->assertArrayHasKey('timecreated', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);
    }

    /**
     * Test for provider::get_contexts_for_userid().
     */
    public function test_get_contexts_for_userid() {

        // Check the context supplied is correct.
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertCount(1, $contextlist);

        $contextids = $contextlist->get_contextids();
        $this->assertContains(\context_user::instance($this->user1->id)->id, $contextids);
    }

    /**
     * Test that only users within a context are fetched.
     */
    public function test_get_users_in_context() {
        global $DB;

        $component = 'tool_organisation';

        // The user list for usercontext1 should have one user.
        $userlist1 = new \core_privacy\local\request\userlist(\context_user::instance($this->user1->id), $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(1, $userlist1);

        // The user list for systemcontext should not have any users.
        $userlist2 = new \core_privacy\local\request\userlist(\context_system::instance(), $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(0, $userlist2);

        // The user list for usercontext2 should have one user.
        $userlist3 = new \core_privacy\local\request\userlist(\context_user::instance($this->user2->id), $component);
        provider::get_users_in_context($userlist3);
        $this->assertCount(1, $userlist3);
    }

    /**
     * Test for provider::export_user_data().
     */
    public function test_export_user_data() {

        // Export all of the data for the context for user 1.
        $context = \context_user::instance($this->user1->id);
        $this->export_context_data_for_user($this->user1->id, $context, 'tool_organisation');
        $writer = \core_privacy\local\request\writer::with_context($context);

        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data();
        // This has only 1 record because we are exporting user 1.
        $this->assertCount(1, $data->jobs);

        foreach ($data->jobs as $job) {
            $this->assertArrayHasKey('department', $job);
            $this->assertArrayHasKey('position', $job);
            $this->assertArrayHasKey('startdate', $job);
            $this->assertArrayHasKey('enddate', $job);
            $this->assertArrayHasKey('timecreated', $job);
            $this->assertArrayHasKey('timemodified', $job);
        }
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        // Delete data on system context will do nothing.
        $context = \context_system::instance();
        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(2, $DB->count_records('tool_organisation_job'));

        // Delete data on user context will delete user's jobs.
        $usercontext1 = \context_user::instance($this->user1->id);
        provider::delete_data_for_all_users_in_context($usercontext1);

        // After deletion, all jobs should have been deleted.
        $this->assertEquals(1, $DB->count_records('tool_organisation_job'));
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user() {
        global $DB;

        // Before deletion we should have 2 jobs.
        $this->assertEquals(2, $DB->count_records('tool_organisation_job'));

        // Delete data without context will do nothing.
        $contextlist = new \core_privacy\local\request\approved_contextlist($this->user1, 'tool_organisation', []);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(2, $DB->count_records('tool_organisation_job'));

        // Delete data on system instance will do nothing.
        $context = \context_system::instance();
        $contextlist = new \core_privacy\local\request\approved_contextlist($this->user1, 'tool_organisation', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(2, $DB->count_records('tool_organisation_job'));

        // Delete data on user context will delete user's jobs.
        $usercontext1 = \context_user::instance($this->user1->id);
        $contextlist = new \core_privacy\local\request\approved_contextlist($this->user1, 'tool_organisation', [$usercontext1->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(1, $DB->count_records('tool_organisation_job'));

        // Check the jobs for the other user are still there.
        $this->assertEquals(1, $DB->count_records('tool_organisation_job', ['userid' => $this->user2->id]));
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users() {

        $component = 'tool_organisation';

        $usercontext = \context_user::instance($this->user1->id);
        $userlist1 = new \core_privacy\local\request\userlist($usercontext, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(1, $userlist1);

        // Convert $userlist1 into an approved_contextlist.
        $approvedlist1 = new approved_userlist($usercontext, $component, $userlist1->get_userids());

        // Delete using delete_data_for_user.
        provider::delete_data_for_users($approvedlist1);
        // Re-fetch users in usercontext.
        $userlist1 = new \core_privacy\local\request\userlist($usercontext, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);
    }
}
