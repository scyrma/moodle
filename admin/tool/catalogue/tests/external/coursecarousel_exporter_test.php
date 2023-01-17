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

namespace tool_catalogue\external;

use advanced_testcase;

/**
 * Unit tests for coursecarousel_exporter class
 *
 * @package     tool_catalogue
 * @covers      \tool_catalogue\external\coursecarousel_exporter
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class coursecarousel_exporter_test extends advanced_testcase {

    /**
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->libdir}/completionlib.php");
    }

    /**
     * Test export data
     */
    public function test_export(): void {
        global $CFG, $PAGE;

        $this->resetAfterTest();

        $CFG->enablecompletion = true;

        $course1 = self::getDataGenerator()->create_course();
        // Create a second course with completion criteria and one activity.
        $course2 = self::getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $this->getDataGenerator()->create_module('assign', ['course' => $course2->id],
            ['completion' => COMPLETION_TRACKING_MANUAL]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);

        $this->setUser($user);

        $exporter = new coursecarousel_exporter(null, ['courses' => [$course1, $course2]]);
        $data = $exporter->export($PAGE->get_renderer('tool_catalogue'));

        $this->assertCount(2, $data->courses);

        $this->assertEquals($course1->id, $data->courses[0]->id);
        $this->assertEquals(course_get_url($course1->id), $data->courses[0]->viewurl);
        $this->assertEquals($course1->fullname, $data->courses[0]->fullname);
        $this->assertNotEmpty($data->courses[0]->courseimage);
        $this->assertEquals('0.0', $data->courses[0]->progress);
        $this->assertEquals('0.0', $data->courses[1]->progress);
        $this->assertEquals(0, $data->courses[1]->progress);
    }
}
