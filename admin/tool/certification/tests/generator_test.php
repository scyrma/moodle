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

/**
 * Tests for the tool_certification generator
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_certification generator
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_generator_testcase extends advanced_testcase {

    /** @var tool_certification_generator */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_certification');
        $this->resetAfterTest();
    }

    public function test_get_dummy_certificationdata() {
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
        $this->assertEquals($certification->get('id'), $record->certificationid);
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals($user->id, $record->userid);
    }
}
