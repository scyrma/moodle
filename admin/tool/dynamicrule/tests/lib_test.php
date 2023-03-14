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

namespace tool_dynamicrule;

use advanced_testcase;
use tool_certificate\customfield\issue_handler;
use tool_dynamicrule\tool_dynamicrule\condition\course_completed;
use tool_dynamicrule\tool_dynamicrule\outcome\dummy;

/**
 * Tests for the tool_dynamicrule lib class.
 *
 * @package    tool_dynamicrule
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class lib_test extends advanced_testcase {

    /**
     * Load required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/dynamicrule/lib.php');
    }

    /**
     * Data provider for test_tool_dynamicrule_tool_certificate_fields
     *
     * @return array
     */
    public function tool_certificate_fields_provider(): array {
        return [
            ['courseid'],
            ['courseshortname'],
            ['coursefullname'],
            ['courseurl'],
            ['coursecompletiondate'],
            ['coursegrade'],
            ['coursecustomfield_code'],
        ];
    }

    /**
     * Test for callback 'tool_dynamicrule_tool_certificate_fields'
     *
     * @covers ::tool_dynamicrule_tool_certificate_fields
     *
     * @param string $fieldshortname
     * @dataProvider tool_certificate_fields_provider
     */
    public function test_tool_dynamicrule_tool_certificate_fields(string $fieldshortname): void {
        $this->resetAfterTest();

        // Create a course customfield.
        $catid = $this->getDataGenerator()->create_custom_field_category([])->get('id');
        $this->getDataGenerator()->create_custom_field(['categoryid' => $catid, 'type' => 'text', 'shortname' => 'code']);
        $course = $this->getDataGenerator()->create_course(['customfield_code' => 'C0D3']);

        $configdata = json_encode(['courseid' => $course->id]);
        $condition = course_completed::instance(0, (object) ['configdata' => $configdata]);
        $availabledata = $condition->get_available_data_for_outcome(dummy::instance());

        // Function get_all_fields_shortnames calls tool_dynamicrule_tool_certificate_fields, we don't need to call it manually.

        $handler = issue_handler::create();

        // Check that certificate field exists in issue_handler.
        $this->assertContains($fieldshortname, $handler->get_all_fields_shortnames());
        // Check that certificate field added by dynamicrule exists in available_data_for_outcome.
        $this->assertArrayHasKey($fieldshortname, $availabledata);
    }
}
