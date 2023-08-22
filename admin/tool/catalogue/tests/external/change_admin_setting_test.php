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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_catalogue\external;

use tool_catalogue\configuration;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for \tool_catalogue\external\change_admin_setting
 *
 * @package    tool_catalogue
 * @category   test
 * @covers     \tool_catalogue\external\change_admin_setting
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class change_admin_setting_test extends \externallib_advanced_testcase {

    /**
     * Test execute actions toggle and move
     */
    public function test_execute_toggle_and_move(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $value = configuration::get_display_fields_list();
        $this->assertEquals([configuration::FIELD_SUMMARY], $value);

        change_admin_setting::execute('toggle',
            configuration::SETTING_DISPLAYFIELDS_LIST,
            configuration::FIELD_CONTACTS,
            1);

        change_admin_setting::execute('toggle',
            configuration::SETTING_DISPLAYFIELDS_LIST,
            configuration::FIELD_CATEGORY,
            1);

        change_admin_setting::execute('toggle',
            configuration::SETTING_DISPLAYFIELDS_LIST,
            configuration::FIELD_TAGS,
            1);

        $value = configuration::get_display_fields_list();
        $this->assertEquals(
            [
                configuration::FIELD_SUMMARY,
                configuration::FIELD_CONTACTS,
                configuration::FIELD_CATEGORY,
                configuration::FIELD_TAGS,
            ],
            $value);

        change_admin_setting::execute('toggle',
            configuration::SETTING_DISPLAYFIELDS_LIST,
            configuration::FIELD_CATEGORY,
            0);

        $value = configuration::get_display_fields_list();
        $this->assertEquals(
            [
                configuration::FIELD_SUMMARY,
                configuration::FIELD_CONTACTS,
                configuration::FIELD_TAGS,
            ],
            $value);

        change_admin_setting::execute('move',
            configuration::SETTING_DISPLAYFIELDS_LIST,
            configuration::FIELD_CONTACTS,
            -1);

        $value = configuration::get_display_fields_list();
        $this->assertEquals(
            [
                configuration::FIELD_CONTACTS,
                configuration::FIELD_SUMMARY,
                configuration::FIELD_TAGS,
            ],
            $value);

        change_admin_setting::execute('move',
            configuration::SETTING_DISPLAYFIELDS_LIST,
            configuration::FIELD_CONTACTS,
            1);

        $value = configuration::get_display_fields_list();
        $this->assertEquals(
            [
                configuration::FIELD_SUMMARY,
                configuration::FIELD_CONTACTS,
                configuration::FIELD_TAGS,
            ],
            $value);
    }

    /**
     * Test execute with errors
     */
    public function test_execute_errors(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        try {
            change_admin_setting::execute('unknownaction',
                configuration::SETTING_DISPLAYFIELDS_LIST,
                configuration::FIELD_CONTACTS,
                1);
            $this->fail('Expected exception not thrown');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Invalid action', $e->getMessage());
        }

        try {
            change_admin_setting::execute('toggle',
                'unknownsetting',
                configuration::FIELD_CONTACTS,
                1);
            $this->fail('Expected exception not thrown');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Invalid setting name', $e->getMessage());
        }

        // No error but no change in setting value if the field is not in the list.
        change_admin_setting::execute('toggle',
            configuration::SETTING_DISPLAYFIELDS_LIST,
            'unknownfield',
            1);
        $this->assertNotContains('unknownfield', configuration::get_display_fields_list());
    }

    /**
     * Tests for setting the allow html tags setting
     */
    public function test_execute_html(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $settingname = configuration::get_setting_name_allow_html_tags(
            configuration::SETTING_DISPLAYFIELDS_TILES, configuration::FIELD_SUMMARY);

        $this->assertEquals(false, get_config('tool_catalogue', $settingname));

        change_admin_setting::execute('set',
            $settingname,
            '',
            configuration::OPTION_HTML_TAGS_ALL);
        $this->assertEquals(configuration::OPTION_HTML_TAGS_ALL, get_config('tool_catalogue', $settingname));

        try {
            change_admin_setting::execute('set',
                'unknownsetting',
                '',
                configuration::OPTION_HTML_TAGS_ALL);
            $this->fail('Expected exception not thrown');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Unrecognised setting or field name', $e->getMessage());
        }
    }
}
