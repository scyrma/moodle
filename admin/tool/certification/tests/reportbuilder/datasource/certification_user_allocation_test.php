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
 * Certification user allocation datasource tests.
 *
 * @covers     \tool_certification\reportbuilder\datasource\certification_user_allocation
 * @package    tool_certification
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_user_allocation_test extends core_reportbuilder_testcase {

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
     * Test certification user allocation datasource
     */
    public function test_certification_user_allocation_datasource(): void {
        $this->resetAfterTest();

        $program = $this->programgenerator->generate_program((object)['fullname' => 'My program']);
        $certification = $this->generator->generate_certification(['fullname' => 'My certification',
            'program' => $program->get('id')]);
        $report = $this->rbgenerator->create_report(['name' => 'Certification user allocations',
            'source' => certification_user_allocation::class, 'default' => false]);

        $user = self::getDataGenerator()->create_user();
        $this->generator->allocate_user((int) $user->id, $certification->get('id'));

        // Add certification user allocation type column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'),
            'uniqueidentifier' => 'certification_user:allocationtype']);
        // Add certification archived column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'certification:archived']);
        // Add program fullname column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'program:fullname']);
        // Add user firstname column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:firstname']);
        // Add user completion certified column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'),
            'uniqueidentifier' => 'certification_completion:certified']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        $contentrow = array_values(reset($content));
        $this->assertEquals([
            'Manual', // Certification_user allocationtype.
            'No', // Archived.
            'My program', // Program full name.
            $user->firstname, // User first name.
            'No', // Certified.
        ], $contentrow);
    }
}
