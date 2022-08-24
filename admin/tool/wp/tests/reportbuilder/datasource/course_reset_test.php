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

namespace tool_wp\reportbuilder\datasource;

use completion_info;
use core_reportbuilder_generator;
use core_reportbuilder_testcase;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_tenant_generator;
use tool_wp\course_reset_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Course reset datasource tests.
 *
 * @covers     \tool_wp\reportbuilder\datasource\course_reset
 * @package    tool_wp
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_reset_test extends core_reportbuilder_testcase {

    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var core_reportbuilder_generator */
    protected $rbgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->rbgenerator = self::getDataGenerator()->get_plugin_generator('core_reportbuilder');
    }

    /**
     * Test course reset datasource
     */
    public function test_course_reset_datasource(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create one user and allocate them to the default tenant.
        $userid = (int)self::getDataGenerator()->create_user(['firstname' => 'Carlos', 'lastname' => 'Perez'])->id;
        $defaulttenantid = tenancy::get_default_tenant_id();

        $this->tenantgenerator->allocate_user($userid, $defaulttenantid);

        // Add a course that supports completion.
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course to reset', 'enablecompletion' => 1]);
        $completion = new completion_info($course);

        // Enrol a user in the course.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($userid, (int)$course->id, $studentrole->id);

        // Create course module and some user records.
        $choice = $this->getDataGenerator()->create_module('choice', ['course' => (int)$course->id],
            ['completion' => 1]);
        $choicewithoptions = choice_get_choice($choice->id);
        $optionids = array_keys($choicewithoptions->option);
        $cm = get_coursemodule_from_instance('choice', $choice->id);
        choice_user_submit_response($optionids[2], $choice, $userid, $course, $cm);
        $completion->update_state($cm, COMPLETION_COMPLETE, $userid);

        // Reset the course for this user.
        $creset = new course_reset_api((int)$course->id, $userid);
        $resetparams = [
            'programid' => 123,
            'certificationid' => 2022,
            'reason' => 'Testing course reset RB core'
        ];
        $creset->reset_course($resetparams);

        $report = $this->rbgenerator->create_report([
            'name' => 'RB course reset',
            'source' => course_reset::class,
            'default' => false,
        ]);

        // Add user fullname column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);
        // Add course name column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname']);
        // Add course reset reason column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course_reset:reason']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        $contentrow = array_values(reset($content));
        $this->assertEquals([
            'Carlos Perez', // User fullname.
            'Course to reset', // Course fullname.
            'Testing course reset RB core', // Reason course reset.
        ], $contentrow);

        // Enable shared space.
        $sharedspaceid = sharedspace::enable_shared_space();
        tenancy::set_switched_tenant_id($sharedspaceid);
        $this->assertEquals($sharedspaceid, tenancy::get_tenant_id());

        $reportsharedspace = $this->rbgenerator->create_report([
            'name' => 'RB course reset in shared space',
            'source' => course_reset::class,
            'default' => true,
        ]);

        $contentsharedspace = $this->get_custom_report_content($reportsharedspace->get('id'));
        $this->assertCount(1, $contentsharedspace);

        // Check if tenant name is added to report when has subtenants.
        $contentrowshared = array_values(reset($contentsharedspace));
        $this->assertContains('Default tenant', $contentrowshared);
    }
}
