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

namespace tool_program\reportbuilder\datasource;

use core_reportbuilder_generator;
use core_reportbuilder_testcase;
use tool_program_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Programs allocation completion datasource tests.
 *
 * @covers     \tool_program\reportbuilder\datasource\programs_allocation_completion
 * @package    tool_program
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programs_allocation_completion_test extends core_reportbuilder_testcase {

    /** @var tool_program_generator */
    protected $generator;
    /** @var core_reportbuilder_generator */
    protected $rbgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->rbgenerator = self::getDataGenerator()->get_plugin_generator('core_reportbuilder');
    }

    /**
     * Test programs allocation completion datasource
     */
    public function test_programs_allocation_completion_datasource(): void {
        $this->resetAfterTest();

        $program = $this->generator->generate_program((object)['fullname' => 'My program']);
        $basesetid = $program->get_base_set()->get('id');
        $course = self::getDataGenerator()->create_course(['fullname' => 'My Lionel course']);
        $this->generator->add_course_to_set((int)$course->id, $basesetid);
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Lionel', 'lastname' => 'Kenobi']);
        $this->generator->allocate_user_to_program($program->get('id'), (int)$user1->id);

        $report = $this->rbgenerator->create_report([
            'name' => 'Programs allocation completion',
            'source' => programs_allocation_completion::class,
            'default' => false,
        ]);

        // Add program fullname column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'program:fullname']);
        // Add program user allocation source column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'program_user:allocationtype']);
        // Add user fullname column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        $contentrow = array_values(reset($content));
        $this->assertEquals([
            'My program', // Program fullname.
            'Manual', // Allocation type.
            'Lionel Kenobi', // User full name.
        ], $contentrow);
    }

    /**
     * Stress test datasource
     */
    public function test_stress_datasource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $program = $this->generator->generate_program((object) ['fullname' => 'My program']);

        // Add a program course.
        $course = $this->getDataGenerator()->create_course();
        $this->generator->add_course_to_set((int) $course->id, $program->get_base_set()->get('id'));

        // Add a program user.
        $user = $this->getDataGenerator()->create_user();
        $this->generator->allocate_user_to_program($program->get('id'), (int) $user->id);

        $this->datasource_stress_test_columns(programs_allocation_completion::class);
        $this->datasource_stress_test_columns_aggregation(programs_allocation_completion::class);
        $this->datasource_stress_test_conditions(programs_allocation_completion::class, 'program_user:suspended');
    }
}
