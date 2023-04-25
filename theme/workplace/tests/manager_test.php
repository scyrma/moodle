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

namespace theme_workplace;

use advanced_testcase;
use tool_tenant_generator;

/**
 * Class workplace testcase
 *
 * @package   theme_workplace
 * @category  test
 * @covers    \theme_workplace\manager
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 Mikel Martín <mikel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager_test extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test get_feedback_reminder_actions
     */
    public function test_get_feedback_reminder_actions(): void {
        $reminderactions = manager::get_feedback_reminder_actions();

        // Check that get_feedback_reminder_actions is returning the 3 correct elements.
        $this->assertCount(3, $reminderactions);
        $this->assertEquals(get_string('calltofeedback_give'), $reminderactions[0]['title']);
        $this->assertEquals(get_string('shareyourexperience', 'theme_workplace'), $reminderactions[1]['title']);
        $this->assertEquals(get_string('calltofeedback_remind'), $reminderactions[2]['title']);
    }
}
