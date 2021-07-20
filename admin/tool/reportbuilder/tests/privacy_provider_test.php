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
 * File containing tests for privacy provider class
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use tool_reportbuilder\constants;
use tool_reportbuilder\local\helpers\filters;
use tool_reportbuilder\privacy\provider;
use tool_reportbuilder\test\mock_report;

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\privacy\provider
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_privacy_provider_testcase extends provider_testcase {

    /** @var stdClass $reportuser The user who will create the report */
    protected $reportuser;

    /** @var \tool_reportbuilder\report_base $report */
    protected $report;

    /** @var stdClass $extrauser The user who will create the report audience/schedule */
    protected $extrauser;

    /** @var \tool_reportbuilder\audience_base $audience */
    protected $audience;

    /** @var \tool_reportbuilder\local\models\schedule $schedule */
    protected $schedule;

    /*
     * Test setup
     *
     * @return void
     */
    public function setUp(): void {
        $this->resetAfterTest();

        // Create user and report.
        $this->reportuser = $this->getDataGenerator()->create_user();
        $this->setUser($this->reportuser);

        $this->report = $this->get_plugin_generator()->create_report([
            'name' => 'My super report',
            'source' => mock_report::class,
        ]);

        // Create user and report audience/schedule.
        $this->extrauser = $this->getDataGenerator()->create_user();
        $this->setUser($this->extrauser);

        $this->audience = $this->get_plugin_generator()->create_audience([
            'reportid' => $this->report->get_id(),
            'classname' => \tool_reportbuilder\tool_reportbuilder\audiences\manual::class,
            'configdata' => ['users' => [$this->extrauser->id]],
        ]);

        $this->schedule = $this->get_plugin_generator()->create_schedule([
            'name' => 'My important schedule',
            'reportid' => $this->report->get_id(),
            'scheduled' => strtotime('1 January 2018 08:00'),
            'recurrence' => constants::RECURRENCE_WEEKLY,
            'lastsenton' => strtotime('15 January 2018 08:00'),
            'format' => 'pdf',
            'subject' => 'Hello',
            'message' => 'Here\'s your weekly report',
        ]);

        // Set current user back to null.
        $this->setUser(null);
    }

    /**
     * Test class get_contexts_for_userid method
     *
     * @return void
     */
    public function test_get_contexts_for_userid() {
        // User who created the report.
        $contextlist = $this->get_contexts_for_userid($this->reportuser->id, 'tool_reportbuilder');
        $this->assertCount(1, $contextlist);
        $this->assertInstanceOf(context_system::class, $contextlist->current());

        // User who created the audience/schedule.
        $contextlist = $this->get_contexts_for_userid($this->extrauser->id, 'tool_reportbuilder');
        $this->assertCount(1, $contextlist);
        $this->assertInstanceOf(context_system::class, $contextlist->current());
    }

    /**
     * Test class get_users_in_context method
     *
     * @return void
     */
    public function test_get_users_in_context() {
        $userlist = new userlist(context_system::instance(), 'tool_reportbuilder');
        provider::get_users_in_context($userlist);

        $this->assertCount(2, $userlist);
        $this->assertEqualsCanonicalizing([$this->reportuser->id, $this->extrauser->id],
            $userlist->get_userids());
    }

    /**
     * Test class export_user_data method
     *
     * return void
     */
    public function test_export_user_data() {
        $context = context_system::instance();
        $this->export_context_data_for_user($this->extrauser->id, $context, 'tool_reportbuilder');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $persistent = $this->report->get_persistent();
        $contextpath = provider::get_export_path($persistent);

        $reportdata = $writer->get_data($contextpath);
        $this->assertEquals($persistent->get('name'), $reportdata->name);
        $this->assertEquals($persistent->get('source'), $reportdata->source);
        $this->assertEquals($persistent->get('tenantid'), $reportdata->tenantid);
        $this->assertEquals($this->reportuser->id, $reportdata->usercreated);
        $this->assertEquals($this->reportuser->id, $reportdata->usermodified);
        $this->assertNotEmpty($reportdata->timecreated);
        $this->assertNotEmpty($reportdata->timemodified);

        // Audiences.
        $audiencesdata = $writer->get_related_data($contextpath, 'audiences')->data;
        $this->assertCount(1, $audiencesdata);
        $this->assertEquals($this->audience->get_description(), $audiencesdata[0]->description);
        $this->assertEquals($this->extrauser->id, $audiencesdata[0]->usercreated);
        $this->assertEquals($this->extrauser->id, $audiencesdata[0]->usermodified);
        $this->assertNotEmpty($audiencesdata[0]->timecreated);
        $this->assertNotEmpty($audiencesdata[0]->timemodified);

        // Schedules.
        $schedulesdata = $writer->get_related_data($contextpath, 'schedules')->data;
        $this->assertCount(1, $schedulesdata);
        $this->assertEquals($this->schedule->get('name'), $schedulesdata[0]->name);
        $this->assertNotEmpty($schedulesdata[0]->scheduled);
        $this->assertEquals($this->schedule->get('recurrence'), $schedulesdata[0]->recurrence);
        $this->assertNotEmpty($schedulesdata[0]->lastsenton);
        $this->assertEquals($this->schedule->get('format'), $schedulesdata[0]->format);
        $this->assertEquals($this->schedule->get('subject'), $schedulesdata[0]->subject);
        $this->assertEquals($this->schedule->get('message'), $schedulesdata[0]->message);
        $this->assertEquals($this->extrauser->id, $schedulesdata[0]->usercreated);
        $this->assertEquals($this->extrauser->id, $schedulesdata[0]->usermodified);
        $this->assertNotEmpty($schedulesdata[0]->timecreated);
        $this->assertNotEmpty($schedulesdata[0]->timemodified);
    }

    /**
     * Test class export_user_preferences method
     *
     * @return void
     */
    public function test_export_user_preferences() {
        $this->setUser($this->reportuser);

        $data = (object) ['foo' => 'bar'];
        filters::set_filter($this->report->get_id(), $data);

        provider::export_user_preferences($this->reportuser->id);

        $context = context_system::instance();
        $preferences = writer::with_context($context)->get_user_preferences('tool_reportbuilder');

        // TODO: stop hardcoding this everywhere.
        $key = 'filters_report_' . $this->report->get_id();
        $this->assertEquals(json_encode($data), $preferences->{$key}->value);
    }

    /**
     * Get Report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_plugin_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }
}
