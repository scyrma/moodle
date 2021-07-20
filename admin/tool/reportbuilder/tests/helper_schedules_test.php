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
 * File containing tests for schedules helper class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\constants;
use tool_reportbuilder\local\helpers\schedules;
use tool_reportbuilder\local\models\schedule;
use tool_reportbuilder\test\mock_report;

/**
 * Class tool_reportbuilder_helper_schedules_testcase
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @covers    \tool_reportbuilder\local\helpers\schedules
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_helper_schedules_testcase extends advanced_testcase {

    /**
     * Basic test adding a schedule.
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     */
    public function test_add_schedule() {
        $this->resetAfterTest();

        $newreport = $this->get_generator()->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $data = new stdClass();
        $data->reportid = $newreport->get_id();
        $data->name = 'Example schedule';
        $data->scheduled = time();
        $data->format = 'excel';
        $data->subject = 'Subject';
        $data->message = 'Message';
        $data->usercreated = 0;
        $data->recurrence = 1;
        $newid = \tool_reportbuilder\local\helpers\schedules::add_schedule($data);

        $this->assertNotEmpty($newid);
    }

    /**
     * Basic test for delete_schedule method.
     */
    public function test_delete_schedule() {
        $this->resetAfterTest();

        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $newschedule = $this->get_generator()->create_schedule(['reportid' => $report->get_id()]);

        $this->assertEquals(1, schedule::count_records());
        \tool_reportbuilder\local\helpers\schedules::delete_schedule($newschedule->get('id'));
        $this->assertEquals(0, schedule::count_records());
    }

    /**
     * Test that deleting a report clears associated schedule data
     *
     * @return void
     */
    public function test_delete_report() {
        $this->resetAfterTest();

        $report = $this->get_generator()->create_report(['source' => \tool_reportbuilder\test\mock_report::class]);
        $this->get_generator()->create_schedule(['reportid' => $report->get_id()]);

        $this->assertEquals(1, schedule::count_records(['reportid' => $report->get_id()]));

        // Make sure that deleting the report also clears associated schedule data.
        $report->get_persistent()->delete();
        $this->assertEquals(0, schedule::count_records(['reportid' => $report->get_id()]));
    }

    /**
     * Test get schedule method.
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     */
    public function test_get_schedule() {
        $this->resetAfterTest();

        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $newschedule = $this->get_generator()->create_schedule(['reportid' => $report->get_id()]);

        $schedule = \tool_reportbuilder\local\helpers\schedules::get_schedule($newschedule->get('id'));
        $this->assertEquals($newschedule, $schedule);
    }

    /**
     * Test can edit schedule method.
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     */
    public function test_can_edit_schedule() {
        $this->resetAfterTest();
        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->get_generator();

        // Create a new tenant, user and schedule and check if can be deleted by user of other tenant.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $this->setUser($user1->id);
        $newreport = $this->get_generator()->create_report([
            'source' => mock_report::class,
            'tenantid' => $tenant1->id,
        ]);
        $newschedule = $generator->create_schedule(['reportid' => $newreport->get_id()]);
        $this->setUser($user2->id);
        // User2 can not deleted the schedule of the user1.
        $canedit = \tool_reportbuilder\permission::can_edit_schedule(
            \tool_reportbuilder\local\helpers\schedules::get_schedule($newschedule->get('id')));
        $this->assertFalse($canedit);
    }

    /**
     * Test get formats method.
     *
     * @throws coding_exception
     */
    public function test_get_formats() {
        $this->resetAfterTest();
        $formats = \tool_reportbuilder\local\helpers\schedules::get_formats();
        $this->assertNotEmpty($formats);
    }

    /**
     * Test get format method.
     *
     * @throws coding_exception
     */
    public function test_get_format() {
        $this->resetAfterTest();

        // Format excel.
        $format = \tool_reportbuilder\local\helpers\schedules::get_format('excel');
        $this->assertEquals(
            'Microsoft Excel (.xlsx)'
        , $format);

        // Format pdf.
        $format = \tool_reportbuilder\local\helpers\schedules::get_format('csv');
        $this->assertEquals(
            'Comma separated values (.csv)'
        , $format);

        // Format CSV.
        $format = \tool_reportbuilder\local\helpers\schedules::get_format('pdf');
        $this->assertEquals(
            'Portable Document Format (.pdf)'
        , $format);

        // Format JSON.
        $format = \tool_reportbuilder\local\helpers\schedules::get_format('json');
        $this->assertEquals(
            'Javascript Object Notation (.json)'
            , $format);

        // Format HTML.
        $format = \tool_reportbuilder\local\helpers\schedules::get_format('html');
        $this->assertEquals(
            'HTML table'
            , $format);

        // Format ODS.
        $format = \tool_reportbuilder\local\helpers\schedules::get_format('ods');
        $this->assertEquals(
            'OpenDocument (.ods)'
            , $format);
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Test that we can force a schedule to be sent, regardless of recurrence
     *
     * @return void
     */
    public function test_send_force() : void {
        $this->resetAfterTest(true);

        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'recurrence' => constants::RECURRENCE_MONTHLY,
            'scheduled' => time() + DAYSECS,
        ]);

        // Shouldn't be sent yet, as not scheduled until tomorrow.
        $this->assertFalse(schedules::send($schedule->get('id')));
        $this->assertEquals(-1, schedules::get_schedule($schedule->get('id'))->get('lastsenton'));

        // This time, force the message to be sent.
        $this->assertTrue(schedules::send($schedule->get('id'), true));
        $this->assertGreaterThan(0, schedules::get_schedule($schedule->get('id'))->get('lastsenton'));
    }

    /**
     * Test that we can force a schedule to be sent, regardless of enabled state
     *
     * @return void
     */
    public function test_send_force_disabled_schedule(): void {
        $this->resetAfterTest(true);

        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'recurrence' => constants::RECURRENCE_NONE,
            'scheduled' => time() - MINSECS,
            'enabled' => 0,
        ]);

        // Shouldn't be sent, as disabled.
        $this->assertFalse(schedules::send($schedule->get('id')));
        $this->assertEquals(-1, schedules::get_schedule($schedule->get('id'))->get('lastsenton'));

        // This time, force the message to be sent.
        $this->assertTrue(schedules::send($schedule->get('id'), true));
        $this->assertGreaterThan(0, schedules::get_schedule($schedule->get('id'))->get('lastsenton'));
    }

    /**
     * Test that schedule reports shouldn't be sent when disabled
     *
     * @return void
     */
    public function test_needs_to_be_sent_disabled_schedule(): void {
        $this->resetAfterTest(true);

        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'recurrence' => constants::RECURRENCE_NONE,
            'scheduled' => time() - MINSECS,
            'enabled' => 0,
        ]);

        $this->assertFalse(schedules::needs_to_be_sent($schedule));

        // Re-enabled the schedule.
        $schedule->set('enabled', 1)->update();
        $this->assertTrue(schedules::needs_to_be_sent($schedule));
    }

    /**
     * Test that schedule reports shouldn't be sent early
     *
     * @return void
     */
    public function test_needs_to_be_sent_early() : void {
        $this->resetAfterTest(true);

        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'recurrence' => constants::RECURRENCE_DAILY,
            'scheduled' => time() + MINSECS,
        ]);

        $this->assertFalse(schedules::needs_to_be_sent($schedule));
    }

    /**
     * Test that schedule reports without recurrence should be sent the first time scheduled date has passed
     *
     * @return void
     */
    public function test_needs_to_be_sent_first_time_no_recurrence() : void {
        $this->resetAfterTest(true);

        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'recurrence' => constants::RECURRENCE_NONE,
            'scheduled' => time() - MINSECS,
        ]);

        $this->assertTrue(schedules::needs_to_be_sent($schedule));
    }

    /**
     * Test that schedule reports with recurrence shouldn't automatically be sent the first time scheduled date has passed
     *
     * @return void
     */
    public function test_needs_to_be_sent_first_time_recurrence() : void {
        $this->resetAfterTest(true);

        // Creating a daily schedule with the initial date set in the past, it should be executed the following day (not now).
        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'recurrence' => constants::RECURRENCE_DAILY,
            'scheduled' => time() - MINSECS,
        ]);

        $this->assertFalse(schedules::needs_to_be_sent($schedule));
    }

    /**
     * Test that schedule reports with no recurrent shouldn't be re-sent
     *
     * @return void
     */
    public function test_needs_to_be_sent_no_recurrence() : void {
        $this->resetAfterTest(true);

        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'recurrence' => constants::RECURRENCE_NONE,
            'scheduled' => time() - MINSECS,
            'lastsenton' => time() - MINSECS,
        ]);

        $this->assertFalse(schedules::needs_to_be_sent($schedule));
    }

    /**
     * Data provider for test_needs_to_be_sent
     *
     * TODO WP-1640: these tests are all tied to the current date. Replace with uopz and remove the phpcs exclusion
     *
     * @return array
     */
    public function needs_to_be_sent_provider() : array {
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        return [/*
            // Set 'datelastsent' to -1 (never sent).
            [constants::RECURRENCE_DAILY, -1, true],
            [constants::RECURRENCE_WEEKLY, -1, true],
            [constants::RECURRENCE_MONTHLY, -1, true],
            [constants::RECURRENCE_ANNUALLY, -1, true],
            // Following 'datelastsent' values are relative to current date.
            [constants::RECURRENCE_DAILY, strtotime('-2 hours'), false],
            [constants::RECURRENCE_DAILY, strtotime('-1 day'), true],
            [constants::RECURRENCE_WEEKLY, strtotime('-6 days'), false],
            [constants::RECURRENCE_WEEKLY, strtotime('-7 days'), true],
            [constants::RECURRENCE_MONTHLY, strtotime('-3 weeks'), false],
            [constants::RECURRENCE_MONTHLY, strtotime('-31 days'), true],
            [constants::RECURRENCE_ANNUALLY, strtotime('-51 weeks'), false],
            [constants::RECURRENCE_ANNUALLY, strtotime('-1 year'), true],
        */];
    }

    /**
     * Test whether report should be sent with different recurrence values
     *
     * @param int $recurrence
     * @param int $datelastsent
     * @param bool $expected
     * @return void
     *
     * @dataProvider needs_to_be_sent_provider
     */
    public function test_needs_to_be_sent(int $recurrence, int $datelastsent, bool $expected) : void {
        $this->resetAfterTest(true);

        $report = $this->get_generator()->create_report(['source' => mock_report::class]);
        $schedule = $this->get_generator()->create_schedule([
            'reportid' => $report->get_id(),
            'recurrence' => $recurrence,
            'scheduled' => strtotime('1 January 2018 08:00'),
            'lastsenton' => $datelastsent,
        ]);

        $this->assertEquals($expected, schedules::needs_to_be_sent($schedule));
    }

    /**
     * Data provider for test_calculate_next_send_time
     *
     * @return array
     */
    public function calculate_next_send_time_provider() : array {
        $datenow = strtotime('30 August 2019 15:00'); // Friday.
        $dateschedule = $datenow - HOURSECS;

        return [
            'None' =>
                [constants::RECURRENCE_NONE, $dateschedule, $datenow, $dateschedule],
            'Daily (Last sent one hour ago)' =>
                [constants::RECURRENCE_DAILY, $dateschedule, $datenow, strtotime('31 August 2019 14:00')],
            'Daily (Last sent four days ago)' =>
                [constants::RECURRENCE_DAILY, $dateschedule - (4 * DAYSECS), $datenow, strtotime('31 August 2019 14:00')],
            'Weekday (Last sent Thursday)' =>
                [constants::RECURRENCE_DAILY_WEEKDAY, $dateschedule - DAYSECS, $dateschedule, $dateschedule],
            'Weekday (Last sent one hour ago)' =>
                [constants::RECURRENCE_DAILY_WEEKDAY, $dateschedule, $datenow, strtotime('2 September 2019 14:00')],
            'Weekday (Last sent Monday' =>
                [constants::RECURRENCE_DAILY_WEEKDAY, $dateschedule - (4 * DAYSECS), $datenow, strtotime('2 September 2019 14:00')],
            'Weekly (Last sent one hour ago)' =>
                [constants::RECURRENCE_WEEKLY, $dateschedule, $datenow, strtotime('6 September 2019 14:00')],
            'Weekly (last sent three weeks ago)' =>
                [constants::RECURRENCE_WEEKLY, $dateschedule - (3 * WEEKSECS), $datenow, strtotime('6 September 2019 14:00')],
            'Monthly (Last sent one hour ago)' =>
                [constants::RECURRENCE_MONTHLY, $dateschedule, $datenow, strtotime('30 September 2019 14:00')],
            'Monthly (Last sent 3 months ago)' =>
                [constants::RECURRENCE_MONTHLY, strtotime('30 May 2019 14:00'), $datenow, strtotime('30 September 2019 14:00')],
            'Annually (Last sent one hour ago)' =>
                [constants::RECURRENCE_ANNUALLY, $dateschedule, $datenow, strtotime('30 August 2020 14:00')],
            'Annually (Last sent 3 years ago)' =>
                [constants::RECURRENCE_ANNUALLY, strtotime('30 August 2016 14:00'), $datenow, strtotime('30 August 2020 14:00')]
        ];
    }

    /**
     * Test class calculate_next_send_time method
     *
     * @param int $recurrence
     * @param int $scheduled
     * @param int $datenow TODO WP-1640: Replace with uopz.
     * @param int $expected
     * @return void
     *
     * @dataProvider calculate_next_send_time_provider
     */
    public function test_calculate_next_send_time(int $recurrence, int $scheduled, int $datenow, int $expected): void {
        $this->assertEquals($expected, schedules::calculate_next_send_time($recurrence, $scheduled, $datenow));
    }

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator
     * @throws coding_exception
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        return $generator;
    }
}
