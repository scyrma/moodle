<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Class generator_test
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class generator_test
 *
 * @package     tool_reportbuilder
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

    /**
     * Create a schedule
     */
    public function test_create_schedule() {
        global $DB;
        $this->resetAfterTest();
        // There are no schedules in the beginning.
        $this->assertEquals(0, $DB->count_records('tool_reportbuilder_scheduled'));

        // Generate a schedule.
        $this->get_generator()->create_schedule([]);
        $this->assertEquals(1, $DB->count_records('tool_reportbuilder_scheduled'));
    }

    /**
     * Add a column to the report
     */
    public function test_add_column() {
        global $DB;
        $this->resetAfterTest();

        // Create a report without default columns.
        $mockreportid = $this->get_generator()->create_report(
            ['source' => \tool_reportbuilder\test\mock_report::class, 'adddefault' => 0]
        )->get_id();
        $this->assertEquals(0, $DB->count_records('tool_reportbuilder_column', ['reportid' => $mockreportid]));

        // Add a column.
        $this->get_generator()->add_column($mockreportid, 'user:firstname');
        $this->assertEquals(1, $DB->count_records('tool_reportbuilder_column', ['reportid' => $mockreportid]));

        // Add the same column again.
        $this->get_generator()->add_column($mockreportid, 'user:firstname');
        $this->assertEquals(2, $DB->count_records('tool_reportbuilder_column', ['reportid' => $mockreportid]));
    }
}
