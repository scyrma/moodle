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

namespace tool_datastore\reportbuilder\datasource;

use completion_completion;
use core_reportbuilder_generator;
use core_reportbuilder_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Tests of the course completion from datastore report source
 *
 * @package     tool_datastore
 * @covers      \tool_datastore\reportbuilder\datasource\course_completion
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_completion_test extends core_reportbuilder_testcase {

    /**
     * Test default report created from source
     */
    public function test_default_report(): void {
        $this->resetAfterTest();

        // Trigger a course completion.
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course1']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Leo', 'lastname' => 'Lion']);

        $timecomplete = time();
        (new completion_completion(['course' => $course->id, 'userid' => $user->id]))
            ->mark_complete($timecomplete);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Course completion', 'source' => course_completion::class]);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        $this->assertEquals([
            'Course1',
            'Leo',
            'Lion',
            userdate($timecomplete),
        ], array_values(reset($content)));
    }

    /**
     * Stress test datasource
     */
    public function test_stress_datasource(): void {
        $this->resetAfterTest();

        // Trigger a course completion.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        (new completion_completion(['course' => $course->id, 'userid' => $user->id]))->mark_complete();

        $this->datasource_stress_test_columns(course_completion::class);
        $this->datasource_stress_test_columns_aggregation(course_completion::class);
        $this->datasource_stress_test_conditions(course_completion::class, 'completion:timecompleted');
    }
}
