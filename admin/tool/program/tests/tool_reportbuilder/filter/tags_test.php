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

namespace tool_program\tool_reportbuilder\filter;

use core_tag_tag;
use testable_report_exporter;
use tool_reportbuilder_generator;
use tool_program_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Unit tests for tags report filter
 *
 * @package     tool_program
 * @covers      \tool_wp\reportbuilder\local\filters\tags
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Roberto Bravo <roberto.bravo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tags_test extends \core_reportbuilder_testcase {

    /**
     * Load required classes
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/reportbuilder/tests/fixtures/testable_report_exporter.php");
    }

    /**
     * Convert tags column HTML content into array of plaintext values
     *
     * @param string $tags
     * @return string
     */
    private static function extract_tags(string $tags): string {
        preg_match_all('/<span[^>]*>([^<]*)<\/span>/', $tags, $matches);
        return implode(', ', $matches[1]);
    }

    /**
     * Test for conversion of tags condition
     * @return void
     */
    public function test_tags_filter_convert(): void {

        $this->resetAfterTest();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        /** @var tool_reportbuilder_generator $toolreportbuildergenerator */
        $toolreportbuildergenerator = self::getDataGenerator()->get_plugin_generator('tool_reportbuilder');

        // Generate programs.
        $programgenerator->generate_program((object)['fullname' => 'p1', 'program_tags' => ['cat', 'dog']]);
        $programgenerator->generate_program((object)['fullname' => 'p2', 'program_tags' => ['cat', 'mouse']]);
        $programgenerator->generate_program((object)['fullname' => 'p3', 'program_tags' => ['dog', 'mouse']]);

        // Create report.
        $report = $toolreportbuildergenerator->create_report(
            [
                'source' => \tool_program\tool_reportbuilder\datasources\report_programs::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
                'adddefault' => 0,
            ]);
        $reportid = $report->get_id();
        $report = \tool_reportbuilder\manager::get_report($reportid);

        // Add program name column to the report.
        $toolreportbuildergenerator->add_column($report, 'tool_program:fullname');
        // Add tags column to the report.
        $toolreportbuildergenerator->add_column($report, 'tool_program:tags');
        // Add tags condition.
        $tag = core_tag_tag::get_by_name(0, 'dog');
        $toolreportbuildergenerator->add_condition($report, 'tool_program:tags');
        $toolreportbuildergenerator->set_report_conditions_values($reportid, ['tool_program:tags' => $tag->id]);

        // Test report results.
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertCount(2, $rows);
        $rows = array_map(static function(array $row): array {
            return [$row[0], self::extract_tags($row[1])];
        }, $rows);
        $this->assertEqualsCanonicalizing([["p1", "cat, dog"], ["p3", "dog, mouse"]], $rows);

        // Test conversion.
        /** @var \tool_reportbuilder\datasource $report */
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $newreportid = $report->convert();
        $newrows = $this->get_custom_report_content($newreportid);
        $this->assertCount(2, $newrows);
        $newrows = array_map(static function(array $newrow): array {
            return [$newrow['c0_fullname'], self::extract_tags($newrow['c1_tags'])];
        }, $newrows);
        $this->assertEqualsCanonicalizing([["p1", "cat, dog"], ["p3", "dog, mouse"]], $newrows);
    }
}
