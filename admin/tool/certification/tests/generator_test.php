<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Tests for the tool_certification generator
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_certification generator
 *
 * @package    tool_certification
 * @copyright  2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certification_generator_testcase extends advanced_testcase
{
    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_certification');
        $this->resetAfterTest();
    }

    public function test_get_dummy_certificationdata() {
        $this->resetAfterTest();

        $certdata = $this->generator->get_dummy_certificationdata();
        $this->assertEquals('A certification fullname', $certdata->fullname);
        $this->assertEquals('1', $certdata->idnumber);
        $this->assertCount(2, $certdata->certification_tags);
        $this->assertEquals('0', $certdata->allocationstartdateabsolute);
    }

    public function test_generate_certification() {
        $certification = $this->generator->generate_certification();

        $this->assertInstanceOf('tool_certification\certification', $certification);
        $this->assertEquals('A certification fullname', $certification->get('fullname'));
        $this->assertEquals('1', $certification->get('idnumber'));
        $this->assertNotEmpty($certification->get('program'));
    }

    public function test_allocate_user() {
        global $DB;
        $certification = $this->generator->generate_certification();
        $user = $this->getDataGenerator()->create_user();

        $this->generator->allocate_user($user->id, $certification->get('id'));

        $params = ['certificationid' => $certification->get('id'), 'userid' => $user->id];
        $record = $DB->get_record('tool_certification_users', $params, '*', IGNORE_MISSING);
        $this->assertNotFalse($record);
        $this->assertEquals($certification->get('id'), $record->certificationid);
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals($user->id, $record->userid);
    }
}
