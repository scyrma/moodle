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

namespace tool_tenant\local\config;

use advanced_testcase;
use tool_tenant\config;
use tool_tenant_generator;

/**
 * Tests for multi-tenancy in message airnotifier
 *
 * @package     tool_tenant
 * @category    test
 * @covers      \tool_tenant\local\config\message_airnotifier
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class message_airnotifier_test extends advanced_testcase {

    /**
     * @var tool_tenant_generator
     */
    protected $tenantgenator;

    /**
     * Setup test
     */
    protected function setUp(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $this->tenantgenator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_forum\subscriptions::reset_forum_cache();
        \mod_forum\subscriptions::reset_discussion_cache();

        // Messaging is not compatible with transactions...
        $this->preventResetByRollback();

        // Forcibly reduce the maxeditingtime to a second in the past to
        // ensure that messages are sent out.
        $CFG->maxeditingtime = -1;
    }

    /**
     * Create a new discussion and post within the specified forum, as the
     * specified author.
     *
     * @param \stdClass $forum The forum to post in
     * @param \stdClass $author The author to post as
     * @param array $fields any other fields in discussion (name, message, messageformat, ...)
     * @return array An array containing the discussion object, and the post object
     */
    protected function helper_post_to_forum($forum, $author, $fields = array()) {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        // Create a discussion in the forum, and then add a post to that discussion.
        $record = (object)$fields;
        $record->course = $forum->course;
        $record->userid = $author->id;
        $record->forum = $forum->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the post which was created by create_discussion.
        $post = $DB->get_record('forum_posts', ['discussion' => $discussion->id]);

        return [$discussion, $post];
    }

    /**
     * Testing sending messages via airnotifier as forum notifications
     *
     * @return void
     */
    public function test_send_message() {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        $options = ['course' => $course->id, 'forcesubscribe' => FORUM_FORCESUBSCRIBE];
        $forum = $this->getDataGenerator()->create_module('forum', $options);

        $author = $this->getDataGenerator()->create_and_enrol($course);
        $commenter = $this->getDataGenerator()->create_and_enrol($course);

        list($discussion, $post) = $this->helper_post_to_forum($forum, $author);

        // Mock server responses.
        $CFG->airnotifierurl = 'localhost';
        \curl::mock_response(json_encode(['status' => 'ok']));
        \curl::mock_response(json_encode(['status' => 'ok']));
        $CFG->airnotifieraccesskey = 'test';    // For enabling Airnotifier.
        $CFG->airnotifierappname .= ' ';
        $this->add_fake_device($commenter);

        ob_start();
        cron_setup_user();
        $cron = new \mod_forum\task\cron_task();
        $cron->execute();
        ob_clean();
        $this->runAdhocTasks(\mod_forum\task\send_user_notifications::class, $author->id);
        $output = ob_get_contents();
        ob_clean();
        $this->assertStringContainsString('Sent 1 messages with 0 failures', $output);
        $this->runAdhocTasks(\mod_forum\task\send_user_notifications::class, $commenter->id);
        $output = ob_get_contents();
        $this->assertStringContainsString('Sent 1 messages with 0 failures', $output);
        ob_end_clean();
    }

    /**
     * Register a mobile device for the user
     *
     * @param \stdClass $user
     */
    protected function add_fake_device(\stdClass $user) {
        global $DB;

        // Add fake core device.
        $device = array(
            'appid' => 'com.moodle.moodlemobile',
            'name' => 'occam',
            'model' => 'Nexus 4',
            'platform' => 'Android',
            'version' => '4.2.2',
            'pushid' => 'apushdkasdfj4835',
            'uuid' => 'asdnfl348qlksfaasef859',
            'userid' => $user->id,
            'timecreated' => time(),
            'timemodified' => time(),
        );
        $coredeviceid = $DB->insert_record('user_devices', (object) $device);

        $airnotifierdev = array(
            'userdeviceid' => $coredeviceid,
            'enable' => 1
        );
        $DB->insert_record('message_airnotifier_devices', (object) $airnotifierdev);
    }

    /**
     * Test that curl is executed when we send messages through airnotifier
     *
     * We can not test how exactly the curl request to airnotifier was performed, however we can test the
     * fact that curl() was called during sending messages by checking what is left in the stack.
     */
    public function test_send_message_curl() {
        global $CFG;
        $tenant1 = $this->tenantgenator->create_tenant();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->tenantgenator->create_user(['tenantid' => $tenant1->id]);

        // Configure airnotifier for tenant1 but leave it unconfigured for the default tenant.
        $CFG->airnotifierurl = 'localhost';
        $CFG->airnotifierappname  = 'x';
        config::set_config_tenant_override($tenant1->id, 'airnotifieraccesskey', 'test');

        // Send message to user2 who has registered devices. Message will not be sent because the default tenant
        // does not have airnotifier configured.
        $this->add_fake_device($user2);
        set_user_preference('message_provider_moodle_instantmessage_enabled', 'airnotifier', $user2);
        \curl::mock_response(1);
        \curl::mock_response(2);
        $res = $this->send_test_message($user1, $user2);
        $this->assertNotEmpty($res);
        $resp = (new \curl())->post('localhost');
        $this->assertEquals(2, $resp); // We never took anything from the mockresponse stack.

        // Send message to user3 who is in a tenant with configured airnotifier. Message will be sent.
        $this->add_fake_device($user3);
        set_user_preference('message_provider_moodle_instantmessage_enabled', 'airnotifier', $user3);
        \curl::mock_response(3);
        \curl::mock_response(4);
        $res = $this->send_test_message($user1, $user3);
        $this->assertNotEmpty($res);
        $resp = (new \curl())->post('localhost');
        $this->assertEquals(3, $resp); // Last value "disappeared" from mockresponse stack.
    }

    /**
     * Send a test message between two users
     *
     * @param \stdClass $userfrom
     * @param \stdClass $userto
     * @return false|int|mixed
     */
    protected function send_test_message(\stdClass $userfrom, \stdClass $userto) {
        $message = new \core\message\message();
        $message->courseid          = 1;
        $message->component         = 'moodle';
        $message->name              = 'instantmessage';
        $message->userfrom          = $userfrom;
        $message->userto            = $userto;
        $message->subject           = 'message subject 1';
        $message->fullmessage       = 'message body';
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml   = '<p>message body</p>';
        $message->smallmessage      = 'small message';
        $message->notification      = 1;
        $content = ['*' => ['header' => ' test ', 'footer' => ' test ']];
        $message->set_additional_content('email', $content);

        return message_send($message);
    }
}
