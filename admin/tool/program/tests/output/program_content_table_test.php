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

declare(strict_types=1);

namespace tool_program\output;

use advanced_testcase;
use tool_program\output\program_content_table;
use tool_program_generator;

/**
 * Program content table tests.
 *
 * @covers    \tool_program\output\program_content_table
 * @package    tool_program
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_content_table_test extends advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
    }

    /**
     * Test export for template method.
     */
    public function test_export_for_template(): void {
        global $PAGE;

        $this->resetAfterTest();

        // Create program.
        $program = $this->generator->generate_program();

        // Create three courses, one with completion disabled, other with completion enabled but not criteria and last one
        // with completion and criteria enabled.
        $course1 = self::getDataGenerator()->create_course(['fullname' => 'Course1']);
        $course2 = self::getDataGenerator()->create_course(['fullname' => 'Course2', 'enablecompletion' => 1]);
        $course3 = self::getDataGenerator()->create_course(['fullname' => 'Course3', 'enablecompletion' => 1]);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course3->id], ['completion' => 1]);

        // Set completion criteria for course3.
        $criteriadata = (object) [
            'id' => $course3->id,
            'criteria_activity' => [$assign->cmid => 1],
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);

        // Add previous courses to program set.
        $this->generator->add_course_to_set((int)$course1->id, $program->get_base_set()->get('id'), 1);
        $this->generator->add_course_to_set((int)$course2->id, $program->get_base_set()->get('id'), 2);
        $this->generator->add_course_to_set((int)$course3->id, $program->get_base_set()->get('id'), 3);

        // Get the program content table render.
        $renderer = $PAGE->get_renderer('tool_program');
        $programcontent = new program_content_table($program, $program->get_context());
        $programcontentdata = $programcontent->export_for_template($renderer);

        // Assert that warning related to course completion disabled is shown.
        $completiondisablestring = get_string('coursecompletiondisabled', 'tool_program');
        $coursecompletiondisabled = reset($programcontentdata["children"])["columns"][0]["content"];
        $this->assertStringContainsString($completiondisablestring, $coursecompletiondisabled);
        $this->assertStringContainsString('Course1', $coursecompletiondisabled);

        // Assert that warning related to course completion criteria disabled is shown.
        $criteriadisablestring = get_string('coursecriteriadisabled', 'tool_program');
        $coursecriteriadisabled = $programcontentdata["children"][1]["columns"][0]["content"];
        $this->assertStringContainsString($criteriadisablestring, $coursecriteriadisabled);
        $this->assertStringContainsString('Course2', $coursecriteriadisabled);

        // Assert that no warnings are shown.
        $coursecriteriaenabled = $programcontentdata["children"][2]["columns"][0]["content"];
        $this->assertStringNotContainsString($coursecriteriaenabled, $coursecompletiondisabled);
        $this->assertStringNotContainsString($coursecriteriaenabled, $coursecriteriadisabled);
        $this->assertStringContainsString('Course3', $coursecriteriaenabled);
    }
}
