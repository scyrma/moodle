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

/**
 * Class mod_appointment_search_testcase
 *
 * @package     mod_appointment
 * @category    test
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment;

use advanced_testcase;
use mod_appointment_generator;
use testable_core_search;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');

/**
 * Class mod_appointment_search_testcase
 *
 * @package     mod_appointment
 * @group       mod_appointment
 * @covers      \mod_appointment\search\activity
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_test extends advanced_testcase {

    /**
     * @var string Area id
     */
    protected $appointmentareaid = null;

    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest(true);
        set_config('enableglobalsearch', true);

        $this->appointmentareaid = \core_search\manager::generate_areaid('mod_appointment', 'activity');

        // Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
        $search = testable_core_search::instance();
    }

    /**
     * Run after each test
     *
     * @return void
     */
    protected function tearDown(): void {
        // Stop it from faking time in the search manager (if set by test).
        \testable_core_search::fake_current_time();
        parent::tearDown();
    }

    /**
     * Get generator.
     *
     * @return \mod_appointment_generator
     */
    protected function get_generator(): mod_appointment_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Search availability.
     *
     * @return void
     */
    public function test_search_enabled() {

        $searcharea = \core_search\manager::get_search_area($this->appointmentareaid);
        list($componentname, $varname) = $searcharea->get_config_var_name();

        // Enabled by default once global search is enabled.
        $this->assertTrue($searcharea->is_enabled());

        set_config($varname . '_enabled', 0, $componentname);
        $this->assertFalse($searcharea->is_enabled());

        set_config($varname . '_enabled', 1, $componentname);
        $this->assertTrue($searcharea->is_enabled());
    }

    /**
     * Indexing activity.
     *
     * @return void
     */
    public function test_activity_indexing() {
        global $DB;

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->appointmentareaid);
        $this->assertInstanceOf('\mod_appointment\search\activity', $searcharea);

        $course1 = self::getDataGenerator()->create_course();
        $appointment1 = $this->getDataGenerator()->create_module('appointment', ['course' => $course1->id]);
        $appointment2 = $this->getDataGenerator()->create_module('appointment', ['course' => $course1->id]);

        // All records.
        $recordset = $searcharea->get_recordset_by_timestamp(0);
        $this->assertTrue($recordset->valid());
        $nrecords = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $doc = $searcharea->get_document($record);
            $this->assertInstanceOf('\core_search\document', $doc);

            // Static caches are working.
            $dbreads = $DB->perf_get_reads();
            $doc = $searcharea->get_document($record);
            $this->assertEquals($dbreads, $DB->perf_get_reads());
            $this->assertInstanceOf('\core_search\document', $doc);
            $nrecords++;
        }
        // If there would be an error/failure in the foreach above the recordset would be closed on shutdown.
        $recordset->close();
        $this->assertEquals(2, $nrecords);

        // The +2 is to prevent race conditions.
        $recordset = $searcharea->get_recordset_by_timestamp(time() + 2);

        // No new records.
        $this->assertFalse($recordset->valid());
        $recordset->close();

        // Query by context.
        $recordset = $searcharea->get_document_recordset(0, \context_module::instance($appointment1->cmid));
        $this->assertEquals(1, iterator_count($recordset));
        $recordset->close();

        $recordset = $searcharea->get_document_recordset(0, \context_module::instance($appointment2->cmid));
        $this->assertEquals(1, iterator_count($recordset));
        $recordset->close();

        // Course.
        $recordset = $searcharea->get_document_recordset(0, \context_course::instance($course1->id));
        $this->assertEquals(2, iterator_count($recordset));
        $recordset->close();
    }

    /**
     * Tests that partial indexing works correctly.
     */
    public function test_partial_indexing() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and an appointment module.
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('appointment', ['course' => $course->id, 'name' => 'First']);

        // Index everything up to current. Ensure the course is older than current second so it
        // definitely doesn't get indexed again next time.
        $this->waitForSecond();
        $search = \testable_core_search::instance();
        $search->index(false, 0);

        $searcharea = $search->get_search_area($this->appointmentareaid);
        list($componentname, $varname) = $searcharea->get_config_var_name();
        $this->assertFalse(get_config($componentname, $varname . '_partial'));

        // Add another appointment module.
        $now = time();
        $this->getDataGenerator()->create_module('appointment', ['course' => $course->id, 'name' => 'Second']);
        time_sleep_until($now + 3);

        // Clear the count of added documents.
        $search->get_engine()->get_and_clear_added_documents();

        // Make the search engine delay while indexing each document.
        $search->get_engine()->set_add_delay(1.2);

        // Use fake time, starting from now.
        \testable_core_search::fake_current_time(time());

        // Index with a limit of 2 seconds - it should index new appointment.
        $search->index(false, 2);
        $added = $search->get_engine()->get_and_clear_added_documents();
        $this->assertCount(1, $added);
        $this->assertEquals('Second', $added[0]->get('title'));
    }
}
