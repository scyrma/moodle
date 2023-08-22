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

/**
 * File containing tests for send helper class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use advanced_testcase;
use core_user;
use stdClass;
use tool_reportbuilder_generator;
use tool_reportbuilder\tool_reportbuilder\audiences\manual;
use tool_reportbuilder\tool_reportbuilder\datasources\report_users_list;
use tool_tenant\manager;
use tool_tenant\tenancy;
use tool_tenant_generator;

/**
 * Class tool_reportbuilder_helper_send_testcase
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @covers    \tool_reportbuilder\local\helpers\send
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class send_test extends advanced_testcase {

    /** @var stdClass $user */
    protected $user;

    /**
     * Test setup
     *
     * @return void
     */
    protected function setUp(): void {
        $this->resetAfterTest();

        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        // Make sure e-mail/attachments are enabled for this test.
        set_config('allowattachments', 1);
        unset_config('noemailever');
    }

    /**
     * Test sending of schedule reports as a different user
     */
    public function test_sendemail_from_user(): void {
        // When creating this user, we need to allow their email to be visible and in the list of allowed domains.
        $userfrom = $this->getDataGenerator()->create_user(['maildisplay' => core_user::MAILDISPLAY_EVERYONE]);
        set_config('allowedemaildomains', explode('@', $userfrom->email, 2)[1]);

        $report = $this->get_generator()->create_report([
            'source' => report_users_list::class,
            'tenantid' => tenancy::get_default_tenant_id(),
        ]);

        $audience = $this->get_generator()->create_audience([
            'reportid' => $report->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$this->user->id]],
        ]);

        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'name' => 'Test',
            'format' => 'csv',
            'usercreated' => $userfrom->id,
            'audiences' => json_encode([$audience->get_id()]),
        ]);

        // Send the schedule, catch emails in sink.
        $sink = $this->redirectEmails();

        $result = (new send($schedule))->sendemail();
        $this->assertTrue($result);

        $messages = $sink->get_messages();
        $sink->close();

        $message = reset($messages);
        $this->assertEquals($userfrom->email, $message->from);
        $this->assertEquals($this->user->email, $message->to);

        // Verify attached report (attachment is in MIME format, but we can detect some Content fields).
        $this->assertMatchesRegularExpression('|Content-Type: text/csv; name="?Test.csv"?|', $message->body);
        $this->assertStringContainsString('Content-Disposition: attachment; filename=Test.csv', $message->body);
    }

    /**
     * Data provider for test_send_email_format
     *
     * @return array
     */
    public function send_email_format_provider(): array {
        $messagehtml = '<p>Hello there<br></p>';

        return [
            'text' => [0, $messagehtml, 'Hello there'],
            'html' => [1, $messagehtml, $messagehtml],
        ];
    }

    /**
     * Test schedule report messages are sent in the correct format
     *
     * @param int $mailformat
     * @param string $messagehtml
     * @param string $expected
     *
     * @dataProvider send_email_format_provider
     */
    public function test_send_email_format(int $mailformat, string $messagehtml, string $expected): void {
        $user = $this->getDataGenerator()->create_user(['mailformat' => $mailformat]);

        $report = $this->get_generator()->create_report([
            'source' => report_users_list::class,
            'tenantid' => tenancy::get_default_tenant_id(),
        ]);

        $audience = $this->get_generator()->create_audience([
            'reportid' => $report->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$user->id]],
        ]);

        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'message' => $messagehtml,
            'audiences' => json_encode([$audience->get_id()]),
        ]);

        // Send the schedule, catch emails in sink.
        $sink = $this->redirectEmails();

        $result = (new send($schedule))->sendemail();
        $this->assertTrue($result);

        $messages = $sink->get_messages();
        $sink->close();

        $message = reset($messages);
        $this->assertMatchesRegularExpression('|^' . preg_quote($expected) . '\s*$|m', $message->body);
    }

    /**
     * Test scheduled report with lots of rows
     */
    public function test_report_ignore_paging(): void {
        // Lots of users to simulate a long report.
        $users = [];
        for ($i = 0; $i < 20; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[$user->id] = $user;
        }

        $report = $this->get_generator()->create_report([
            'source' => report_users_list::class,
            'tenantid' => tenancy::get_default_tenant_id(),
        ]);

        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'format' => 'csv',
        ]);

        // Count should be those users we created, plus three (header row, admin user, user created in setup).
        $filepath = (new send($schedule))->create_attachment();
        $rows = file($filepath);

        $this->assertCount(count($users) + 3, $rows);
    }

    /**
     * Test sendemail function
     */
    public function test_sendemail(): void {
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();

        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
        }

        $report = $generator->create_report([
            'source' => report_users_list::class,
            'tenantid' => tenancy::get_default_tenant_id(),
        ]);

        $audience = $this->get_generator()->create_audience([
            'reportid' => $report->get_id(),
            'classname' => \tool_reportbuilder\tool_reportbuilder\audiences\manual::class,
            'configdata' => ['users' => $users],
        ]);

        $schedule = $generator->create_schedule([
            'reportid' => $report->get_id(),
            'audiences' => json_encode([$audience->get_id()]),
        ]);

        $result = (new send($schedule))->sendemail();
        $this->assertTrue($result);

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(5, $messages);
    }

    /**
     * Send to multiple audiences
     */
    public function test_sendemail_multiple_audience(): void {
        $report = $this->get_generator()->create_report([
            'source' => report_users_list::class,
            'tenantid' => tenancy::get_default_tenant_id(),
        ]);

        // First audience containing one user.
        $userone = $this->get_tenant_generator()->create_user();
        $audienceone = $this->get_generator()->create_audience([
            'reportid' => $report->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$userone->id]],
        ]);

        // Second audience containined two users.
        $usertwo = $this->get_tenant_generator()->create_user();
        $userthree = $this->get_tenant_generator()->create_user();
        $audiencetwo = $this->get_generator()->create_audience([
            'reportid' => $report->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$usertwo->id, $userthree->id]],
        ]);

        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'audiences' => json_encode([$audienceone->get_id(), $audiencetwo->get_id()]),
        ]);

        // Create fregadero to catch all our email.
        $sink = $this->redirectEmails();

        $result = (new send($schedule))->sendemail();
        $this->assertTrue($result);

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(3, $messages);
    }

    /**
     * Test sendemail doesn't proceed for a schedule belonging to an archived tenant
     */
    public function test_sendemail_archived_tenant(): void {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($this->user->id, $tenant->id);

        $report = $this->get_generator()->create_report(
            ['source' => report_users_list::class, 'tenantid' => $tenant->id]);

        $audience = $this->get_generator()->create_audience([
            'reportid' => $report->get_id(),
            'classname' => \tool_reportbuilder\tool_reportbuilder\audiences\manual::class,
            'configdata' => ['users' => [$this->user->id]],
        ]);

        // Create a report schedule, with our test user as the recipient.
        $schedule = $this->get_generator()->create_schedule(
            ['reportid' => $report->get_id(), 'audiences' => json_encode([$audience->get_id()])]);

        // Archive the tenant.
        (new manager())->archive_tenant($tenant->id);

        $sink = $this->redirectEmails();

        // No messages should be sent.
        $result = (new send($schedule))->sendemail();
        $this->assertFalse($result);

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertEmpty($messages);
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_generator(): tool_reportbuilder_generator {
        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
        return $generator;
    }

    /**
     * Returns the tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}
