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
 * Class generator_test
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use advanced_testcase;
use coding_exception;
use tool_reportbuilder_generator;

/**
 * Class generator_test
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      tool_reportbuilder_generator
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class generator_test extends advanced_testcase {

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Create report
     */
    public function test_create_report() {
        global $DB;
        $this->resetAfterTest();
        // There are no reports in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_reportbuilder'));

        // Generate a report for a default tenant.
        $report0 = $this->get_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion::class,
        ]);
        $this->assertEquals(1, $DB->count_records('tool_reportbuilder'));

        $this->assertEquals(\tool_tenant\tenancy::get_default_tenant_id(),
            $DB->get_field('tool_reportbuilder', 'tenantid', ['id' => $report0->get_id()]));

        // Now create a new tenant and generate a report for this tenant.
        $tenant1 = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();
        $report1 = $this->get_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion::class,
            'tenantid' => $tenant1->id
        ]);

        $this->assertEquals($tenant1->id,
            $DB->get_field('tool_reportbuilder', 'tenantid', ['id' => $report1->get_id()]));
    }
}
