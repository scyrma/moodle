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

namespace tool_wp;

use advanced_testcase;
use context_course;
use stdClass;

/**
 * Test for upgrade scripts in tool_program
 *
 * @package   tool_wp
 * @copyright 2023 Moodle Pty Ltd <support@moodle.com>
 * @author    2023 Mikel Martín <mikel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class upgradelib_test extends advanced_testcase {
    /**
     * Load our required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/wp/db/upgradelib.php');
    }

    /**
     * Tests for function test_upgrade_update_wplist_block_positions()
     *
     * @covers ::tool_wp_upgrade_update_wplist_block_positions()
     */
    public function test_upgrade_update_wplist_block_positions(): void {
        global $DB;

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $contextid1 = context_course::instance($course1->id)->id;
        $contextid2 = context_course::instance($course2->id)->id;

        $records = [
            $this->generate_block_positions_record(100, $contextid1, 'course-view-topics', 1),
            $this->generate_block_positions_record(100, $contextid1, 'course-view-wplist', 2),
            $this->generate_block_positions_record(101, $contextid1, 'course-view-topics', 2),
            $this->generate_block_positions_record(101, $contextid1, 'course-view-wplist', 1),
            $this->generate_block_positions_record(200, $contextid1, 'course-view-wplist', 1),
            $this->generate_block_positions_record(201, $contextid1, 'course-view-wplist', 1),
            $this->generate_block_positions_record(300, $contextid2, 'course-view-wplist', 1),
            $this->generate_block_positions_record(400, $contextid1, 'course-view-topics', 1),
        ];
        $DB->insert_records('block_positions', $records);
        // Block instances 100 and 101 have defined positions both in topics format and wplist format and course1 context.
        // Block instances 200 and 201 have defined positions only in wplist format and course1 context.
        // Block instance 300 has only position defined in wplist format but course2 context.
        // Block instance 400 has already position defined in topics format in course1 context.

        tool_wp_upgrade_update_wplist_block_positions();

        // Check that blocks in course format wplist that have also records in course format topics are not updated.
        $wplistrecords = $DB->get_fieldset_select('block_positions', 'blockinstanceid', 'pagetype = ?', ['course-view-wplist']);
        $this->assertEqualsCanonicalizing([100, 101], $wplistrecords);

        // Check that every other block in course format wplist has been updated to topics.
        // And block already in topics course format is not modified.
        $topicsrecords = $DB->get_fieldset_select('block_positions', 'blockinstanceid', 'pagetype = ?', ['course-view-topics']);
        $this->assertEqualsCanonicalizing([100, 101, 200, 201, 300, 400], $topicsrecords);
    }

    /**
     * Tests for function test_upgrade_update_wplist_block_positions() without detecting duplicates.
     *
     * @covers ::tool_wp_upgrade_update_wplist_block_positions()
     */
    public function test_upgrade_update_wplist_block_positions_without_duplicates(): void {
        global $DB;

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $contextid1 = context_course::instance($course1->id)->id;

        $records = [
            $this->generate_block_positions_record(200, $contextid1, 'course-view-wplist', 1),
            $this->generate_block_positions_record(300, $contextid1, 'course-view-wplist', 1),
            $this->generate_block_positions_record(400, $contextid1, 'course-view-topics', 1),
        ];
        $DB->insert_records('block_positions', $records);
        // Block instance 200 and 300 have defined positions only in wplist format and course1 context.
        // Block instance 400 has already position defined in topics format in course1 context.

        tool_wp_upgrade_update_wplist_block_positions();

        // Check that every block has been updated to topics and block already in topics course format is not modified.
        $topicsrecords = $DB->get_fieldset_select('block_positions', 'blockinstanceid', 'pagetype = ?', ['course-view-topics']);
        $this->assertEqualsCanonicalizing([200, 300, 400], $topicsrecords);
    }

    /**
     * Generate a block_positions table record.
     *
     * @param int $blockinstanceid
     * @param int $contextid
     * @param string $pagetype
     * @param int $weight
     * @return stdClass
     */
    private function generate_block_positions_record(
        int $blockinstanceid,
        int $contextid,
        string $pagetype,
        int $weight
    ): stdClass {
        return (object)[
            'blockinstanceid' => $blockinstanceid,
            'contextid' => $contextid,
            'pagetype' => $pagetype,
            'visible' => 1,
            'weight' => $weight
        ];
    }
}
