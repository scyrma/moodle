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
 * File containing tests for report_tool_certificate_templates datasource
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <danielneis@gmail.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use advanced_testcase;
use coding_exception;
use tool_certificate_generator;
use tool_reportbuilder_generator;
use tool_tenant\tenancy;

/**
 * Tests for the datasource report_tool_certificate_templates
 *
 * @package     tool_reportbuilder
 * @covers      \tool_reportbuilder\tool_reportbuilder\datasources\report_tool_certificate_templates
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <danielneis@gmail.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_tool_certificate_templates_test extends advanced_testcase {

    /** @var tool_certificate_generator */
    protected $certgenerator;

    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->certgenerator = self::getDataGenerator()->get_plugin_generator('tool_certificate');

        // Create 2 certificates.
        $cert1name = 'Certificate 1';
        $certificate1 = $this->certgenerator->create_template((object)['name' => $cert1name]);
        $cert2name = 'Certificate 2';
        $certificate2 = $this->certgenerator->create_template((object)['name' => $cert2name]);
    }

    /**
     * Create a report
     *
     * @param int $tenantid
     * @param bool $adddefault
     * @return int
     */
    protected function create_report(int $tenantid, bool $adddefault): int {
        return $this->get_reportbuilder_generator()->create_report([
            'source' => report_tool_certificate_templates::class,
            'tenantid' => $tenantid,
            'adddefault' => (int) $adddefault
        ])->get_id();
    }

    /**
     * Stress testing - add all available columns, try all possible aggregation methods.
     *
     * @coversNothing
     */
    public function test_stress_aggregation(): void {
        $generator = $this->get_reportbuilder_generator();
        self::setAdminUser();

        // Create a report from the certificate templates datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id(), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_aggregation($reportid, $this);
    }

    /**
     * Stress testing - add all available conditions.
     *
     * @coversNothing
     */
    public function test_stress_conditions(): void {
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the certificate templates datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id(), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing - add all available filters.
     *
     * @coversNothing
     */
    public function test_stress_filters(): void {
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the certificate templates datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id(), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_filters($reportid, $this);
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator|\component_generator_base
     * @throws coding_exception
     */
    protected function get_reportbuilder_generator(): tool_reportbuilder_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Check that each column / filter / condition can be converted to the core reportbuilder
     */
    public function test_convert_to_core_reportbuilder(): void {
        $this->setAdminUser();
        $this->get_reportbuilder_generator()->datasource_test_convert_to_core_reportbuilder(
            report_tool_certificate_templates::class, $this);
    }
}
