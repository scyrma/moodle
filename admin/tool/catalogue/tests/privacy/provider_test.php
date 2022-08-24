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

namespace tool_catalogue;

use tool_catalogue\privacy\provider;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the privacy provider class methods.
 *
 * @covers      \tool_catalogue\privacy\provider
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Marina Glancy
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

        // Set the option to hide the program cover help.
        set_user_preference('tool_catalogue_hide_program_cover_help', true, $user);

        // Test the user preferences export contains 1 user preference record for the User.
        provider::export_user_preferences($user->id);
        $contextuser = \context_user::instance($user->id);
        $writer = writer::with_context($contextuser);
        $this->assertTrue($writer->has_any_data());

        $exportedpreferences = $writer->get_user_preferences('tool_catalogue');
        $this->assertCount(1, (array) $exportedpreferences);
        $this->assertEquals(get_string('privacy:programcoverhelphidden', 'tool_catalogue'),
            $exportedpreferences->tool_catalogue_hide_program_cover_help->value);
        $this->assertEquals(get_string('privacy:metadata:showprogramcoverhelp', 'tool_catalogue'),
                $exportedpreferences->tool_catalogue_hide_program_cover_help->description);
    }

    /**
     * Test the privacy exporting of user preferences.
     */
    public function test_export_user_preferences_view_program_content(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $name = "tool_catalogue_show_program_content_1";

        set_user_preference($name, 1, $user);
        provider::export_user_preferences($user->id);
        $writer = writer::with_context(\context_system::instance());
        $toolpreferences = $writer->get_user_preferences('tool_catalogue');

        $this->assertEquals(
            get_string("privacy:request:preference:set", 'tool_catalogue', (object) [
                'name' => $name,
                'value' => 1,
            ]),
            $toolpreferences->{$name}->description
        );
    }
}
