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
 * File containing tests for send helper class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\local\helpers\send;
use tool_reportbuilder\tool_reportbuilder\datasources\report_users_list;
use tool_tenant\tenancy;

/**
 * Class tool_reportbuilder_helper_send_testcase
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @covers    \tool_reportbuilder\local\helpers\send
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_helper_send_testcase extends advanced_testcase {

    /** @var stdClass $user */
    protected $user;

    /**
     * Test setup
     *
     * @return void
     */
    protected function setUp() {
        $this->resetAfterTest();

        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        unset_config('noemailever');
    }

    /**
     * Test sending of schedule reports as a different user
     *
     * @return void
     */
    public function test_sendemail_from_user() {
        // When creating this user, we need to allow their email to be visible and in the list of allowed domains.
        $userfrom = $this->getDataGenerator()->create_user(['maildisplay' => core_user::MAILDISPLAY_EVERYONE]);
        set_config('allowedemaildomains', explode('@', $userfrom->email, 2)[1]);

        $report = $this->get_generator()->create_report([
            'source' => report_users_list::class,
            'tenantid' => tenancy::get_default_tenant_id(),
        ]);

        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'usercreated' => $userfrom->id,
            'audience' => json_encode(['users' => [$this->user->id]]),
        ]);

        // Send the schedule, catch emails in sink.
        $sink = $this->redirectEmails();
        (new send($schedule->id))->sendemail();
        $this->expectOutputRegex('|Sending schedule: ' . preg_quote($schedule->name) . '|');

        $messages = $sink->get_messages();
        $sink->close();

        $message = reset($messages);
        $this->assertEquals($userfrom->email, $message->from);
        $this->assertEquals($this->user->email, $message->to);
    }

    /**
     * Data provider for test_send_email_format
     *
     * @return array
     */
    public function send_email_format_provider() : array {
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
     * @return void
     *
     * @dataProvider send_email_format_provider
     */
    public function test_send_email_format(int $mailformat, string $messagehtml, string $expected) {
        $user = $this->getDataGenerator()->create_user(['mailformat' => $mailformat]);

        $report = $this->get_generator()->create_report([
            'source' => report_users_list::class,
            'tenantid' => tenancy::get_default_tenant_id(),
        ]);

        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'message' => $messagehtml,
            'audience' => json_encode(['users' => [$user->id]]),
        ]);

        // Send the schedule, catch emails in sink.
        $sink = $this->redirectEmails();
        (new send($schedule->id))->sendemail();
        $this->expectOutputRegex('|Sending schedule: ' . preg_quote($schedule->name) . '|');

        $messages = $sink->get_messages();
        $sink->close();

        $message = reset($messages);
        $this->assertRegExp('|^' . preg_quote($expected) . '$|m', $message->body);
    }

    /**
     * Test sendemail function
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_sendemail() {
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();

        $audience = [];
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
        }
        $audience['users'] = $users;
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->get_id();

        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $sendhelper = new \tool_reportbuilder\local\helpers\send($schedule->id);
        $sendhelper->sendemail();
        $this->expectOutputRegex('|Sending schedule: ' . preg_quote($schedule->name) . '|');

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(5, $messages);
    }

    /**
     * Test sendemail function with a department as audience
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_sendmail_with_a_deparment_as_audience() {
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();
        /** @var tool_organisation_generator $orggenerator */
        $orggenerator = advanced_testcase::getDataGenerator()->get_plugin_generator('tool_organisation');

        $pf = $orggenerator->create_position(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $pa = $orggenerator->create_position(['parentid' => $pf->id]);
        $df = $orggenerator->create_department(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $da = $orggenerator->create_department(['parentid' => $df->id]);

        for ($i = 0; $i < 3; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
            $orggenerator->assign_job((object)['userid' => $users[$i],
                'positionid' => $pa->id, 'departmentid' => $da->id]);

        }
        $audience['departmentid'] = $da->id;
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->get_id();

        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $sendhelper = new \tool_reportbuilder\local\helpers\send($schedule->id);
        $sendhelper->sendemail();
        $this->expectOutputRegex('|Sending schedule: ' . preg_quote($schedule->name) . '|');

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(3, $messages);
    }

    /**
     * Test sendemail function with a position as audience
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_sendmail_with_a_position_as_audience() {
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();
        /** @var tool_organisation_generator $orggenerator */
        $orggenerator = advanced_testcase::getDataGenerator()->get_plugin_generator('tool_organisation');

        $pf = $orggenerator->create_position(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $pa = $orggenerator->create_position(['parentid' => $pf->id]);
        $df = $orggenerator->create_department(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $da = $orggenerator->create_department(['parentid' => $df->id]);

        for ($i = 0; $i < 10; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
            $orggenerator->assign_job((object)['userid' => $users[$i],
                'positionid' => $pa->id, 'departmentid' => $da->id]);

        }
        $audience['positionid'] = $pa->id;
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->get_id();

        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $sendhelper = new \tool_reportbuilder\local\helpers\send($schedule->id);
        $sendhelper->sendemail();
        $this->expectOutputRegex('|Sending schedule: ' . preg_quote($schedule->name) . '|');

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(10, $messages);
    }

    /**
     * Test sendemail function with a department and position as audience
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_sendmail_with_a_department_and_position_as_audience() {
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();
        /** @var tool_organisation_generator $orggenerator */
        $orggenerator = advanced_testcase::getDataGenerator()->get_plugin_generator('tool_organisation');

        $pf = $orggenerator->create_position(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $pa = $orggenerator->create_position(['parentid' => $pf->id]);
        $pa2 = $orggenerator->create_position(['parentid' => $pf->id]);
        $df = $orggenerator->create_department(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $da = $orggenerator->create_department(['parentid' => $df->id]);

        for ($i = 0; $i < 10; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
            $orggenerator->assign_job((object)['userid' => $users[$i],
                'positionid' => $pa->id, 'departmentid' => $da->id]);

        }
        for ($i = 0; $i < 2; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
            $orggenerator->assign_job((object)['userid' => $users[$i],
                'positionid' => $pa2->id, 'departmentid' => $da->id]);

        }
        $audience['positionid'] = $pa->id;
        $audience['departmentid'] = $da->id;
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->get_id();

        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $sendhelper = new \tool_reportbuilder\local\helpers\send($schedule->id);
        $sendhelper->sendemail();
        $this->expectOutputRegex('|Sending schedule: ' . preg_quote($schedule->name) . '|');

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(12, $messages);
    }

    /**
     * Test sendemail function with custom emails as audience
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_sendmail_with_custom_emails_as_audience() {
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();

        $audience['emails'] = ['usermail1@fake.com', 'usermails2@fake.com'];
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->get_id();

        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $sendhelper = new \tool_reportbuilder\local\helpers\send($schedule->id);
        $sendhelper->sendemail();
        $this->expectOutputRegex('|Sending schedule: ' . preg_quote($schedule->name) . '|');

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(2, $messages);

        $this->assertEquals($audience['emails'][0], $messages[0]->to);
        $this->assertEquals($audience['emails'][1], $messages[1]->to);
    }

    /**
     * Test get_users function
     *
     * @throws ReflectionException
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_get_users() {
        $audience = [];
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
        }
        $audience['users'] = $users;
        $generator = $this->get_generator();
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->get_id();
        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $manager = new \tool_reportbuilder\local\helpers\send($schedule->id);

        $rc = new \ReflectionClass(get_class($manager));
        $rcm = $rc->getMethod('get_mails');
        $rcm->setAccessible(true);
        $result = $rcm->invokeArgs($manager, []);

        $this->assertCount(5, $result);
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
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
     * @throws coding_exception
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}