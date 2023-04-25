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

namespace block_myinprogress\external;

use external_api;
use externallib_advanced_testcase;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Unit tests for get_inprogress_courses_test external class
 *
 * @package     block_myinprogress
 * @covers      \block_myinprogress\external\get_inprogress_courses
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_inprogress_courses_test extends externallib_advanced_testcase {
    /**
     * Test execute
     */
    public function test_execute(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = get_inprogress_courses::execute();
        $cleanresult = external_api::clean_returnvalue(get_inprogress_courses::execute_returns(), $result);
        $data = $cleanresult['data'];

        // Sanity check.
        $this->assertEmpty($data['courses']);

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $course2 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course1, strtotime('-1 days'));
        $this->getDataGenerator()->create_user_course_lastaccess($user, $course2, strtotime('-2 days'));

        $result = get_inprogress_courses::execute();
        $cleanresult = external_api::clean_returnvalue(get_inprogress_courses::execute_returns(), $result);
        $this->assertEmpty($cleanresult['warnings']);
        $data = $cleanresult['data'];

        // Check data contents.
        $this->assertCount(1, $data['courses']);
        $this->assertEquals($course1->fullname, $data['courses'][0]['fullnamedisplay']);
        $this->assertEquals($CFG->wwwroot.'/course/view.php?id=' . $course1->id, $data['courses'][0]['viewurl']);
        $this->assertNotEmpty($data['courses'][0]['courseimage']);
        $this->assertEquals(0, $data['courses'][0]['progress']);

        // Test now with 'onlywithcompletion' parameter to false.
        $result = get_inprogress_courses::execute(false);
        $cleanresult = external_api::clean_returnvalue(get_inprogress_courses::execute_returns(), $result);
        $data = $cleanresult['data'];

        // Check both courses are returned (course 1 was most recently accessed so should be first).
        $this->assertCount(2, $data['courses']);
        $this->assertEquals($course1->fullname, $data['courses'][0]['fullnamedisplay']);
        $this->assertEquals($course2->fullname, $data['courses'][1]['fullnamedisplay']);
    }
}
