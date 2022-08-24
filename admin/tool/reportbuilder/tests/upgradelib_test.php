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

namespace tool_reportbuilder;

use advanced_testcase;
use coding_exception;
use stdClass;
use tool_organisation_generator;
use tool_reportbuilder_generator;
use tool_reportbuilder\test\mock_report;
use tool_reportbuilder\tool_reportbuilder\audiences\manual;

/**
 * Upgradelib tests.
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @category  test
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class upgradelib_test extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Load our required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/reportbuilder/db/upgradelib.php");
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Get organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_organisation_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Test for create an audience type
     *
     * @covers ::tool_reportbuilder_upgrade_create_audience_type
     */
    public function test_upgrade_create_audience_type(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);

        $this->assertEmpty($DB->get_records('tool_reportbuilder_audiences'));

        $id = tool_reportbuilder_upgrade_create_audience_type($report->get_id(), manual::class, ['users' => [$user1->id]], 2);

        $audiences = $DB->get_records('tool_reportbuilder_audiences');
        $audience = reset($audiences);

        $this->assertEquals($id, $audience->id);
        $this->assertEquals($report->get_id(), $audience->reportid);
        $this->assertEquals(manual::class, $audience->classname);
        $this->assertEquals(json_encode(['users' => [$user1->id]]), $audience->configdata);
    }

    /**
     * Test for create schedule with audiences
     *
     * @covers ::tool_reportbuilder_upgrade_create_schedule_with_audiences
     */
    public function test_upgrade_create_schedule_with_audiences(): void {
        global $DB;

        $schedulecreator = $this->getDataGenerator()->create_user();
        $schedulerecipient = $this->getDataGenerator()->create_user();

        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);

        // Create old schedule, defining department/position and manually selected user and email.
        [$position, $department] = $this->get_organisation_generator()->create_position_and_department();
        $oldschedule = $this->create_old_schedule([
            'reportid' => $report->get_id(),
            'positionid' => $position->id,
            'departmentid' => $department->id,
            'recipients' => json_encode([
                'users' => [$schedulerecipient->id],
                'emails' => ['someone@example.com'],
            ]),
            'usercreated' => $schedulecreator->id,
            'usermodified' => $schedulecreator->id,
        ]);

        // Sanity check.
        $this->assertEmpty($DB->get_records('tool_reportbuilder_audiences'));
        $this->assertEmpty($DB->get_records('tool_reportbuilder_schedule'));

        $id = tool_reportbuilder_upgrade_create_schedule_with_audiences($oldschedule);

        // Check that 3 audiences have been created.
        $audiences = $DB->get_records('tool_reportbuilder_audiences', [], '', 'id,reportid,classname,configdata');

        $audiencesids = array_column($audiences, 'id');
        foreach ($audiences as $audience) {
            unset($audience->id);
        }

        $this->assertEqualsCanonicalizing([
            (object)[
                'reportid' => (string) $report->get_id(),
                'classname' => 'tool_organisation\tool_reportbuilder\audiences\job',
                'configdata' => json_encode(['department' => ['id' => $department->id], 'position' => ['id' => 0]]),
            ],
            (object)[
                'reportid' => (string) $report->get_id(),
                'classname' => 'tool_organisation\tool_reportbuilder\audiences\job',
                'configdata' => json_encode(['position' => ['id' => $position->id], 'department' => ['id' => 0]]),
            ],
            (object)[
                'reportid' => (string) $report->get_id(),
                'classname' => 'tool_reportbuilder\tool_reportbuilder\audiences\manual',
                'configdata' => json_encode(['users' => [$schedulerecipient->id]]),
            ],
        ], $audiences);

        // Check that 1 schedule has been created.
        $schedules = $DB->get_records('tool_reportbuilder_schedule');
        $this->assertCount(1, $schedules);

        $schedule = reset($schedules);
        $this->assertEquals($id, $schedule->id);
        $this->assertEquals($report->get_id(), $schedule->reportid);
        $this->assertEquals($oldschedule->name, $schedule->name);
        $this->assertEqualsCanonicalizing($audiencesids, json_decode($schedule->audiences, true));

        // Assert task was created to inform schedule creator of changes re: emails.
        $tasks = \core\task\manager::get_adhoc_tasks(\tool_reportbuilder\task\notify_schedule_upgrade::class);
        $this->assertCount(1, $tasks);

        // Create sink to catch messages sent from task.
        $sink = $this->redirectEmails();

        reset($tasks)->execute();
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);

        $this->assertEquals($schedulecreator->email, $messages[0]->to);
        $this->assertEquals('Upgraded report schedules', $messages[0]->subject);
        $this->assertStringContainsString('someone@example.com', $messages[0]->body);

        $sink->close();
    }

    /**
     * Test for creating schedule with audiences, where the original schedule was created by a deleted user
     *
     * @covers ::tool_reportbuilder_upgrade_create_schedule_with_audiences
     */
    public function test_upgrade_create_schedule_with_audiences_deleted_user(): void {
        $schedulecreator = $this->getDataGenerator()->create_user();

        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);

        $oldschedule = $this->create_old_schedule([
            'reportid' => $report->get_id(),
            'recipients' => json_encode([
                'emails' => ['someone@example.com'],
            ]),
            'usercreated' => $schedulecreator->id,
            'usermodified' => $schedulecreator->id,
        ]);

        delete_user($schedulecreator);
        tool_reportbuilder_upgrade_create_schedule_with_audiences($oldschedule);

        // No exception should be thrown, nor task created.
        $tasks = \core\task\manager::get_adhoc_tasks(\tool_reportbuilder\task\notify_schedule_upgrade::class);
        $this->assertEmpty($tasks);
    }

    /**
     * Creates old schedule type instance
     *
     * @param array $record
     * @return stdClass
     */
    private function create_old_schedule(array $record): stdClass {
        global $USER;

        // Required properties.
        if (!array_key_exists('reportid', $record)) {
            throw new coding_exception('Report id must be specified when creating a schedule');
        }

        // Populate defaults.
        if (!array_key_exists('name', $record)) {
            $record['name'] = 'New schedule ' . random_string(5);
        }
        if (!array_key_exists('format', $record)) {
            $record['format'] = 'excel';
        }
        if (!array_key_exists('subject', $record)) {
            $record['subject'] = 'Subject';
        }
        if (!array_key_exists('message', $record)) {
            $record['message'] = 'Message';
        }
        if (!array_key_exists('departmentid', $record)) {
            $record['departmentid'] = 0;
        }
        if (!array_key_exists('positionid', $record)) {
            $record['positionid'] = 0;
        }
        if (!array_key_exists('usercreated', $record)) {
            $record['usercreated'] = $USER->id;
        }
        if (!array_key_exists('recurrence', $record)) {
            $record['recurrence'] = \tool_reportbuilder\constants::RECURRENCE_NONE;
        }

        return (object) $record;
    }
}
