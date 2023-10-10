<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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

namespace mod_appointment;

use mod_appointment_generator;
use tool_brickfield\manager;

/**
 * Test for accessibility tool support
 *
 * @package    mod_appointment
 * @copyright  2021 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_brickfield_area_test extends \advanced_testcase {

    /**
     * Tests for the function manager::get_all_areas()
     */
    public function test_get_areas() {
        $this->resetAfterTest();
        $areas = manager::get_all_areas();
        $areaclassnames = array_map('get_class', $areas);

        // Make sure the list of areas contains some known areas.
        $this->assertContains(\mod_appointment\local\tool_brickfield\areas\intro::class, $areaclassnames);
        $this->assertContains(\mod_appointment\local\tool_brickfield\areas\name::class, $areaclassnames);
        $this->assertContains(\mod_appointment\local\tool_brickfield\areas\session::class, $areaclassnames);
    }

    /**
     * Get generator.
     *
     * @return mod_appointment_generator
     */
    protected function get_generator(): mod_appointment_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Create two modules with sessions
     *
     * @return array of two cm_info instances
     */
    protected function create_modules(): array {
        $course = $this->getDataGenerator()->create_course();

        // Create appointment module.
        $appointment1 = $this->getDataGenerator()->create_module('appointment',
            ['course' => $course->id]);

        // Create sessions.
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment1->id]);
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment1->id]);

        list($course1, $cm1) = get_course_and_cm_from_instance($appointment1->id, 'appointment');
        $appointment2 = $this->getDataGenerator()->create_module('appointment',
            ['course' => $course->id]);
        $session3 = $this->get_generator()->create_session(['appointment' => $appointment2->id, 'details' => 'Sess3']);
        list($course2, $cm2) = get_course_and_cm_from_instance($appointment2->id, 'appointment');

        return [$cm1, $cm2];
    }

    /**
     * Test for the intro area
     */
    public function test_intro() {
        $this->resetAfterTest();
        [$cm1, $cm2] = $this->create_modules();

        $intro = new \mod_appointment\local\tool_brickfield\areas\intro();
        $resultsrs = $intro->find_course_areas($cm1->course);
        // Set up a results array from the recordset for easier testing.
        $results = self::array_from_recordset($resultsrs);

        $this->assertCount(2, $results);
        $this->assertEquals($cm1->id, $results[0]->cmid);
        $this->assertEquals($cm2->instance, $results[1]->itemid);
        $this->assertEquals('intro', $results[1]->fieldorarea);

        // Emulate the course_module_created event.
        $event = \core\event\course_module_created::create_from_cm($cm1);
        $relevantresultsrs = $intro->find_relevant_areas($event);
        $relevantresults = self::array_from_recordset($relevantresultsrs);
        $this->assertEquals([$results[0]], $relevantresults);
    }

    /**
     * Test for name area
     */
    public function test_name() {
        $this->resetAfterTest();
        [$cm1, $cm2] = $this->create_modules();

        $name = new \mod_appointment\local\tool_brickfield\areas\name();
        $resultsrs = $name->find_course_areas($cm1->course);
        // Set up a results array from the recordset for easier testing.
        $resultsname = self::array_from_recordset($resultsrs);

        $this->assertCount(2, $resultsname);
        $this->assertEquals($cm1->id, $resultsname[0]->cmid);
        $this->assertEquals($cm2->instance, $resultsname[1]->itemid);
        $this->assertEquals('name', $resultsname[1]->fieldorarea);

        // Emulate the course_module_created event.
        $event = \core\event\course_module_created::create_from_cm($cm1);
        $relevantresultsrs = $name->find_relevant_areas($event);
        $relevantresults = self::array_from_recordset($relevantresultsrs);
        $this->assertEquals([$resultsname[0]], $relevantresults);
    }

    /**
     * Test for sessions area
     */
    public function test_sessions() {
        global $DB;
        $this->resetAfterTest();
        [$cm1, $cm2] = $this->create_modules();

        $c = new \mod_appointment\local\tool_brickfield\areas\session();
        $resultsrs = $c->find_course_areas($cm1->course);
        // Set up a results array from the recordset for easier testing.
        $resultssessions = self::array_from_recordset($resultsrs);

        $this->assertCount(3, $resultssessions);
        $this->assertEquals($cm2->id, $resultssessions[2]->cmid);
        $this->assertEquals('appointment_sessions', $resultssessions[2]->tablename);
        $this->assertEquals('appointment', $resultssessions[2]->reftable);
        $this->assertEquals($cm2->instance, $resultssessions[2]->refid);
        $options3 = $DB->get_records_menu('appointment_sessions', ['appointment' => $cm2->instance], 'id', 'details,id');
        $this->assertEquals($options3['Sess3'], $resultssessions[2]->itemid);
        $this->assertEquals('Sess3', $resultssessions[2]->content);
    }

    /**
     * Array from recordset.
     *
     * @param \moodle_recordset $rs
     * @return array
     */
    private static function array_from_recordset($rs): array {
        $records = [];
        foreach ($rs as $record) {
            $records[] = $record;
        }
        $rs->close();
        return $records;
    }
}
