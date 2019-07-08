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
 * File containing tests for schedules helper class.
 *
 * @package   tool_reportbuilder
 * @category test
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_schedules_helper_testcase
 *
 * @package   tool_reportbuilder
 * @category test
 * @covers \tool_reportbuilder\local\helpers\schedules
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_schedules_helper_testcase extends advanced_testcase {

    /**
     * Basic test get_reports_select method.
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     */
    public function test_add_schedule() {
        $this->resetAfterTest();

        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->get_generator();

        $newreport = $generator->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $data = new stdClass();
        $data->reportid = $newreport->id;
        $data->name = 'Example schedule';
        $data->lastsenton = 0;
        $data->scheduled = time();
        $data->format = 'excel';
        $data->subject = 'Subject';
        $data->message = 'Message';
        $data->usercreated = 0;
        $data->audience = json_encode([]);
        $data->recurrence = 1;
        $newid = \tool_reportbuilder\local\helpers\schedules::add_schedule($data);

        $this->assertNotEmpty($newid);
    }

    /**
     * Basic test for delete_schedule method.
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_delete_schedule() {
        global $DB;
        $this->resetAfterTest();
        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->get_generator();
        $newschedule = $generator->create_schedule([]);
        $this->assertEquals(1, $DB->count_records('tool_reportbuilder_scheduled'));
        \tool_reportbuilder\local\helpers\schedules::delete_schedule($newschedule->id);
        $this->assertEquals(0, $DB->count_records('tool_reportbuilder_scheduled'));
    }

    /**
     * Test get schedule method.
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     */
    public function test_get_schedule() {
        $this->resetAfterTest();
        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->get_generator();
        $newschedule = $generator->create_schedule([]);
        $schedule = \tool_reportbuilder\local\helpers\schedules::get_schedule($newschedule->id);
        $this->assertEquals($newschedule, $schedule->to_record());
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
        $newschedule = $generator->create_schedule([]);
        $canedit = \tool_reportbuilder\local\helpers\schedules::can_edit_schedule($newschedule->id);
        $this->assertTrue($canedit);

        // Create a new tenant, user and schedule and check if can be deleted by user of other tenant.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $this->setUser($user1->id);
        $newschedule = $generator->create_schedule([]);
        $this->setUser($user2->id);
        // User2 can not deleted the schedule of the user1.
        $canedit = \tool_reportbuilder\local\helpers\schedules::can_edit_schedule($newschedule->id);
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