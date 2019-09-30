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
 * Events test.
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Toni Barberá <toni@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\test\mock_report;
use tool_reportbuilder\event\report_created;
use tool_reportbuilder\event\report_updated;
use tool_reportbuilder\manager;
use tool_reportbuilder\local\helpers\schedules;
use tool_reportbuilder\event\schedule_created;
use tool_reportbuilder\event\schedule_deleted;

/**
 * Class tool_reportbuilder_events_testcase
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @category  test
 * @covers    \tool_reportbuilder\event\report_created
 * @covers    \tool_reportbuilder\event\report_updated
 * @covers    \tool_reportbuilder\event\schedule_created
 * @covers    \tool_reportbuilder\event\schedule_deleted
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Toni Barberá <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_events_testcase extends advanced_testcase {

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
     * Tests for create report event triggering.
     *
     * @covers \tool_reportbuilder\event\report_created
     */
    public function test_report_created_event(): void {
        $this->resetAfterTest();

        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->get_generator();

        // Catch the events.
        $sink = $this->redirectEvents();
        $mockreport = $generator->create_report(
            ['source' => mock_report::class]
        );
        $mockreportobj = $mockreport->get_persistent()->to_record();
        $events = $sink->get_events();

        // Validate the event.
        $this->assertCount(2, $events);
        $event = $events[0];
        $this->assertInstanceOf(report_created::class, $event);
        $this->assertEquals('tool_reportbuilder', $event->objecttable);
        $this->assertEquals(0, $event->courseid);
        $this->assertEquals($mockreportobj->name, $event->other['name']);
        $this->assertEquals($mockreportobj->source, $event->other['source']);
        $this->assertDebuggingNotCalled();
        $sink->close();
    }

    /**
     * Tests for update report event triggering.
     *
     * @covers \tool_reportbuilder\event\report_updated
     */
    public function test_report_updated_event(): void {
        $this->resetAfterTest();

        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->get_generator();

        $mockreport = $generator->create_report(
            ['source' => mock_report::class]
        );
        $mockreportobj = $mockreport->get_persistent()->to_record();

        $data = (object) [
            'id' => $mockreport->get_id(),
            'name' => 'New report update'
        ];

        // Catch the events.
        $sink = $this->redirectEvents();
        manager::update_report($data);
        $events = $sink->get_events();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(report_updated::class, $event);
        $this->assertEquals('tool_reportbuilder', $event->objecttable);
        $this->assertEquals(0, $event->courseid);
        $this->assertEquals('New report update', $event->other['name']);
        $this->assertEquals($mockreportobj->source, $event->other['source']);
        $this->assertDebuggingNotCalled();
        $sink->close();
    }

    /**
     * Tests for create schedule event triggering.
     *
     * @covers \tool_reportbuilder\event\schedule_created
     */
    public function test_schedule_created_event(): void {
        $this->resetAfterTest();

        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->get_generator();

        $mockreport = $generator->create_report(
            ['source' => mock_report::class]
        );

        $data = (object) [
            'reportid' => $mockreport->get_id(),
            'name' => 'Schedule test',
            'scheduled' => 1,
            'lastsenton' => '',
            'format' => 'pdf',
            'subject' => '',
            'message' => '',
            'usercreated' => 0,
            'audience' => json_encode([]),
            'recurrence' => 1
        ];

        // Catch the events.
        $sink = $this->redirectEvents();
        schedules::add_schedule($data);
        $events = $sink->get_events();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(schedule_created::class, $event);
        $this->assertEquals('tool_reportbuilder_scheduled', $event->objecttable);
        $this->assertEquals(0, $event->courseid);
        $this->assertEquals($mockreport->get_id(), $event->other['reportid']);
        $this->assertDebuggingNotCalled();
        $sink->close();
    }

    /**
     * Tests for delete schedule event triggering.
     *
     * @covers \tool_reportbuilder\event\schedule_deleted
     */
    public function test_schedule_deleted_event(): void {
        $this->resetAfterTest();

        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->get_generator();

        $mockreport = $generator->create_report(
            ['source' => mock_report::class]
        );

        $data = (object) [
            'reportid' => $mockreport->get_id(),
            'name' => 'Schedule test 2',
            'scheduled' => 1,
            'lastsenton' => '',
            'format' => 'csv',
            'subject' => '',
            'message' => '',
            'usercreated' => 0,
            'audience' => json_encode([]),
            'recurrence' => 1
        ];

        $newschedule = schedules::add_schedule($data);

        // Catch the events.
        $sink = $this->redirectEvents();
        schedules::delete_schedule($newschedule->get('id'));
        $events = $sink->get_events();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(schedule_deleted::class, $event);
        $this->assertEquals('tool_reportbuilder_scheduled', $event->objecttable);
        $this->assertEquals(0, $event->courseid);
        $this->assertEquals($mockreport->get_id(), $event->other['reportid']);
        $this->assertDebuggingNotCalled();
        $sink->close();
    }
}