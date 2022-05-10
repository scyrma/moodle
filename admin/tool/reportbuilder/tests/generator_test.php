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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class generator_test
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class generator_test
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      tool_reportbuilder_generator
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_generator_testcase extends advanced_testcase {

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
            'source' => tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion::class,
        ]);
        $this->assertEquals(1, $DB->count_records('tool_reportbuilder'));

        $this->assertEquals(\tool_tenant\tenancy::get_default_tenant_id(),
            $DB->get_field('tool_reportbuilder', 'tenantid', ['id' => $report0->get_id()]));

        // Now create a new tenant and generate a report for this tenant.
        $tenant1 = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();
        $report1 = $this->get_generator()->create_report([
            'source' => tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion::class,
            'tenantid' => $tenant1->id
        ]);

        $this->assertEquals($tenant1->id,
            $DB->get_field('tool_reportbuilder', 'tenantid', ['id' => $report1->get_id()]));
    }
}
