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

namespace tool_tenant;

use advanced_testcase;

/**
 * Tests for functions in classes/mod_url.php
 *
 * @package    tool_tenant
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Castro <david.castro@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * @covers \tool_tenant\mod_url
 */
class mod_url_test extends advanced_testcase {
    /**
     * Load required libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/url/locallib.php');
    }

    /**
     * Test url activity variable options when tenantdata config isn't defined.
     *
     * @covers \tool_tenant\mod_url::url_get_variable_options
     */
    public function test_workplace_data_variable_options_disabled() {
        $this->resetAfterTest();

        $config = (object) [
            'tenantdata' => false,
            'rolesinparams' => null,
        ];
        $options = url_get_variable_options($config);
        $this->assertArrayNotHasKey(get_string('modurl:tenantdata', 'tool_tenant'), $options);
    }

    /**
     * Test url activity variable options when tenantdata config is defined.
     *
     * @covers \tool_tenant\mod_url::url_get_variable_options
     */
    public function test_workplace_data_variable_options() {
        $this->resetAfterTest();

        $config = (object) [
            'tenantdata' => true,
            'rolesinparams' => null,
        ];
        $options = url_get_variable_options($config);
        $workplaceoptions = $options[get_string('tenant', 'tool_tenant')];
        $this->assertArrayHasKey('tenantid', $workplaceoptions);
        $this->assertArrayHasKey('tenantidnumber', $workplaceoptions);
    }

    /**
     * Test url activity variable values when tenantdata config is defined.
     *
     * @covers \tool_tenant\mod_url::url_get_variable_values
     */
    public function test_workplace_data_variable_values() {
        global $DB;

        $this->resetAfterTest();

        $config = (object) [
            'tenantdata' => true,
            'rolesinparams' => null,
        ];
        $idnumber = rand(1000, 1500);
        $generator = $this->getDataGenerator();
        $tenantgenerator = $generator->get_plugin_generator('tool_tenant');
        $tenant = $tenantgenerator->create_tenant(['idnumber' => $idnumber]);
        $user = $tenantgenerator->create_user(['tenantid' => $tenant->id]);

        $this->setUser($user);

        $course = $generator->create_course();
        $user = $generator->create_user();
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $generator->enrol_user($user->id, $course->id, $studentroleid);

        $url = $generator->create_module('url', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('url', $url->id);
        $values = url_get_variable_values($url, $cm, $course, $config);

        $this->assertEquals($tenant->id, $values['tenantid']);
        $this->assertEquals($idnumber, $values['tenantidnumber']);
    }
}
