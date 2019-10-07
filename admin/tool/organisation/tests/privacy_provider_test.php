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
 * @covers     \tool_organisation\privacy\provider
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_organisation_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {

    /** @var stdClass $user1 */
    private $user1;

    /** @var stdClass $user2 */
    private $user2;

    /** @var \tool_organisation\job_manager $manager */
    private $manager;

    /** @var stdClass $department */
    private $department;

    /** @var stdClass $position */
    private $position;

    /**
     * Test set up.
     */
    public function setUp() {
        $this->resetAfterTest();

        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $this->department = $generator->create_department();
        $this->position = $generator->create_position();

        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();

        $this->manager = new \tool_organisation\job_manager();

        $this->manager->create_job((object)['userid' => $this->user1->id,
            'positionid' => $this->position->id, 'departmentid' => $this->department->id, 'startdate' => 1262304000]);

        $this->manager->create_job((object)['userid' => $this->user2->id,
            'positionid' => $this->position->id, 'departmentid' => $this->department->id, 'startdate' => 1262304000]);
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
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        // Add another job for our test user, ensure it is exported.
        $newposition = $generator->create_position();
        $this->manager->create_job((object) [
            'userid' => $this->user1->id,
            'positionid' => $newposition->id,
            'departmentid' => $this->department->id,
            'startdate' => 1546300800,
            'enddate' => 1551398400,
        ]);

        // Export all of the data for the context for user 1.
        $context = \context_user::instance($this->user1->id);
        $this->export_context_data_for_user($this->user1->id, $context, 'tool_organisation');
        $writer = \core_privacy\local\request\writer::with_context($context);

        $this->assertTrue($writer->has_any_data());

        $contextpath = [get_string('pluginname', 'tool_organisation')];
        $data = $writer->get_data($contextpath);

        $this->assertCount(2, $data->jobs);
        list($job1, $job2) = $data->jobs;

        $this->assertEquals($this->department->name, $job1['department']);
        $this->assertEquals($this->position->name, $job1['position']);
        $this->assertNotNull($job1['startdate']);
        $this->assertNull($job1['enddate']);
        $this->assertArrayHasKey('timecreated', $job1);
        $this->assertArrayHasKey('timemodified', $job1);

        $this->assertEquals($this->department->name, $job1['department']);
        $this->assertEquals($newposition->name, $job2['position']);
        $this->assertNotNull($job2['startdate']);
        $this->assertNotNull($job2['enddate']);
        $this->assertArrayHasKey('timecreated', $job2);
        $this->assertArrayHasKey('timemodified', $job2);
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
