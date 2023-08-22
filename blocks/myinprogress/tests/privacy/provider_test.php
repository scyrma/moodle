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

namespace block_myinprogress;

use context_user;
use block_myinprogress\privacy\provider;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the privacy provider class methods.
 *
 * @covers      \block_myinprogress\privacy\provider
 * @package     block_myinprogress
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider_test extends provider_testcase {

    /**
     * Test for provider::test_export_user_preferences().
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest();

        // Test setup.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        $userpreferencevalue = json_encode([$course1->id, $course2->id]);

        // Set the preference to hide both courses.
        set_user_preference('block_myinprogress_hidden_courses', $userpreferencevalue, $user);

        // Set the preference to show hidden cards.
        set_user_preference('block_myinprogress_show_hidden_cards', true, $user);

        // Test the user preferences export contains the user preference record.
        provider::export_user_preferences($user->id);
        $contextuser = context_user::instance($user->id);
        $writer = writer::with_context($contextuser);
        $this->assertTrue($writer->has_any_data());

        $exportedpreferences = $writer->get_user_preferences('block_myinprogress');
        $this->assertCount(2, (array) $exportedpreferences);
        $this->assertEquals($userpreferencevalue, $exportedpreferences->block_myinprogress_hidden_courses->value);
        $this->assertEquals(get_string('privacy:metadata:hiddencourses', 'block_myinprogress'),
            $exportedpreferences->block_myinprogress_hidden_courses->description);
        $this->assertEquals('Yes', $exportedpreferences->block_myinprogress_show_hidden_cards->value);
        $this->assertEquals(get_string('privacy:metadata:showhiddencards', 'block_myinprogress'),
            $exportedpreferences->block_myinprogress_show_hidden_cards->description);
    }
}
