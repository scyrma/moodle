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

namespace tool_certification\reportbuilder\datasource;

use core_reportbuilder_generator;
use core_reportbuilder_testcase;
use tool_certification_generator;
use tool_program_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Certifications datasource tests.
 *
 * @covers     \tool_certification\reportbuilder\datasource\certifications
 * @package    tool_certification
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certifications_test extends core_reportbuilder_testcase {

    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var core_reportbuilder_generator */
    protected $rbgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->rbgenerator = self::getDataGenerator()->get_plugin_generator('core_reportbuilder');
    }

    /**
     * Test certifications datasource
     */
    public function test_certifications_datasource(): void {
        $this->resetAfterTest();

        $program = $this->programgenerator->generate_program((object)['fullname' => 'My program']);
        $this->generator->generate_certification(['fullname' => 'My certification', 'program' => $program->get('id')]);
        $report = $this->rbgenerator->create_report(['name' => 'Certifications',
            'source' => certifications::class, 'default' => false]);

        // Add certification fullname column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'certification:fullname']);
        // Add certification archived column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'certification:archived']);
        // Add program fullname column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'program:fullname']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        $contentrow = array_values(reset($content));
        $this->assertEquals([
            'My certification', // Certification fullname.
            'No', // Archived.
            'My program', // Program full name.
        ], $contentrow);
    }

    /**
     * Stress test datasource
     */
    public function test_stress_datasource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $program = $this->programgenerator->generate_program((object) ['fullname' => 'My program']);

        // Add a program course.
        $course = $this->getDataGenerator()->create_course();
        $this->programgenerator->add_course_to_set((int) $course->id, $program->get_base_set()->get('id'));

        // Create certification.
        $certification = $this->generator->generate_certification(['fullname' => 'My certification',
            'program' => $program->get('id')]);

        // Add certification user.
        $user = $this->getDataGenerator()->create_user();
        $this->generator->allocate_user((int) $user->id, $certification->get('id'));

        $this->datasource_stress_test_columns(certifications::class);
        $this->datasource_stress_test_columns_aggregation(certifications::class);
        $this->datasource_stress_test_conditions(certifications::class, 'certification:fullname');
    }
}
