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
 * Tests for the tool_program certification class.
 *
 * @package   tool_program
 * @copyright 2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_certification\api;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_program\external\program_certification_exporter;

defined('MOODLE_INTERNAL') || die();

/**
 * Certificate tests.
 *
 * @covers     \tool_program\external\program_certification_exporter
 * @package    tool_program
 * @copyright  2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_program_certification_exporter_testcase extends advanced_testcase {
    /** @var tool_certification_generator */
    private $certificationgenerator;

    /**
     * setUp.
     */
    public function setUp() {
        $this->certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->resetAfterTest();
    }

    /**
     * Test exports overriden dates from certification.
     */
    public function test_exports_overriden_dates_from_certification(): void {
        global $PAGE;
        $certification = $this->certificationgenerator->generate_certification();
        $user = self::getDataGenerator()->create_user();
        $this->certificationgenerator->allocate_user($user->id, $certification->get('id'));
        /** @var certification_user $certificationuser */
        $certificationuser = certification_user::get_record(['certificationid' => $certification->get('id')]);
        $context = context_system::instance();
        $output = $PAGE->get_renderer('core');

        // Set certification user dates (this should override program user dates).
        $time = 123456789;
        api::update_certification_user_dates_and_status($certificationuser, (object) [
            'startdatelocked' => 1,
            'startdate' => $time,
            'duedatelocked' => 1,
            'duedate' => $time,
            'status' => 1,
        ]);

        // Export user certification data with (hopefully) overriden user dates.
        $exporter = new program_certification_exporter(null, [
            'context' => $context,
            'certification' => $certification,
            'certificationuser' => $certificationuser,
        ]);
        $exporteddata = $exporter->export($output);

        // Check we are setting in the exporter data the overriden dates.
        $type = constants::DATE_ABSOLUTE;
        $this->assertEquals($type, $exporteddata->startdatetype);
        $this->assertEquals($time, $exporteddata->startdateabsolute);
        $this->assertEquals($type, $exporteddata->duedatetype);
        $this->assertEquals($time, $exporteddata->duedateabsolute);
        $this->assertEquals($type, $exporteddata->enddatetype);
        // End date should always have a "not set" value.
        $this->assertEquals(0, $exporteddata->enddateabsolute);
    }
}
