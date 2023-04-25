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

namespace tool_organisation\tool_reportbuilder\audiences;

use advanced_testcase;
use tool_organisation_generator;
use tool_reportbuilder_generator;
use tool_reportbuilder\test\mock_report;

/**
 * Unit tests for report audience type based on a users jobs within the organisation structure
 *
 * @package     tool_organisation
 * @group       tool_organisation
 * @covers      \tool_organisation\tool_reportbuilder\audiences\job
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class job_test extends advanced_testcase {

    /**
     * Test description
     */
    public function test_get_description(): void {
        $this->resetAfterTest();

        [$position, $department] = $this->get_generator()->create_position_and_department();
        $instance = $this->create_audience_instance($department->id, $position->id, true, false);

        $this->assertEquals("Users in department: {$department->name} (Include subdepartments)<br />\n" .
            "With position: {$position->name}", $instance->get_description());
    }

    /**
     * Test getting SQL for users with jobs in any department/position
     */
    public function test_get_sql_any(): void {
        global $DB;

        $this->resetAfterTest();

        [$position, $department] = $this->get_generator()->create_position_and_department();

        $userone = $this->getDataGenerator()->create_user();
        $this->get_generator()->assign_job([
            'userid' => $userone->id,
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        // Dummy, shouldn't be returned.
        $usertwo = $this->getDataGenerator()->create_user();

        $instance = $this->create_audience_instance();
        [$joins, $where, $params] = $instance->get_sql('u');

        $userids = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$joins} WHERE {$where}", $params);
        $this->assertEquals([$userone->id], $userids);
    }

    /**
     * Test getting SQL for users with jobs in specific department
     */
    public function test_get_sql_department(): void {
        global $DB;

        $this->resetAfterTest();

        [$position, $department] = $this->get_generator()->create_position_and_department();

        $userone = $this->getDataGenerator()->create_user();
        $this->get_generator()->assign_job([
            'userid' => $userone->id,
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        // Dummy, shouldn't be returned.
        $usertwo = $this->getDataGenerator()->create_user();
        $departmenttwo = $this->get_generator()->create_department();
        $this->get_generator()->assign_job([
            'userid' => $usertwo->id,
            'departmentid' => $departmenttwo->id,
            'positionid' => $position->id,
        ]);

        $instance = $this->create_audience_instance($department->id);
        [$joins, $where, $params] = $instance->get_sql('u');

        $userids = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$joins} WHERE {$where}", $params);
        $this->assertEquals([$userone->id], $userids);
    }

    /**
     * Test getting SQL for users with jobs in specific position
     */
    public function test_get_sql_position(): void {
        global $DB;

        $this->resetAfterTest();

        [$position, $department] = $this->get_generator()->create_position_and_department();

        $userone = $this->getDataGenerator()->create_user();
        $this->get_generator()->assign_job([
            'userid' => $userone->id,
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        // Dummy, shouldn't be returned.
        $usertwo = $this->getDataGenerator()->create_user();
        $positiontwo = $this->get_generator()->create_position();
        $this->get_generator()->assign_job([
            'userid' => $usertwo->id,
            'departmentid' => $department->id,
            'positionid' => $positiontwo->id,
        ]);

        $instance = $this->create_audience_instance(0, $position->id);
        [$joins, $where, $params] = $instance->get_sql('u');

        $userids = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$joins} WHERE {$where}", $params);
        $this->assertEquals([$userone->id], $userids);
    }

    /**
     * Test getting SQL for users with jobs in specific department and position
     */
    public function test_get_sql_department_position(): void {
        global $DB;

        $this->resetAfterTest();

        [$position, $department] = $this->get_generator()->create_position_and_department();

        $userone = $this->getDataGenerator()->create_user();
        $this->get_generator()->assign_job([
            'userid' => $userone->id,
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        // Dummy, shouldn't be returned.
        $usertwo = $this->getDataGenerator()->create_user();
        [$positiontwo, $departmenttwo] = $this->get_generator()->create_position_and_department();
        $this->get_generator()->assign_job([
            'userid' => $usertwo->id,
            'departmentid' => $departmenttwo->id,
            'positionid' => $positiontwo->id,
        ]);

        $instance = $this->create_audience_instance($department->id, $position->id);
        [$joins, $where, $params] = $instance->get_sql('u');

        $userids = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$joins} WHERE {$where}", $params);
        $this->assertEquals([$userone->id], $userids);
    }

    /**
     * Test getting SQL for users with jobs in specific department and position including sub-department/positions
     */
    public function test_get_sql_department_position_with_sub(): void {
        global $DB;

        $this->resetAfterTest();

        [$position, $department] = $this->get_generator()->create_position_and_department();
        [$positionsub, $departmentsub] = $this->get_generator()->create_position_and_department([
            'parentid' => $position->id,
        ], [
            'parentid' => $department->id
        ]);

        $userone = $this->getDataGenerator()->create_user();
        $this->get_generator()->assign_job([
            'userid' => $userone->id,
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        $usertwo = $this->getDataGenerator()->create_user();
        $this->get_generator()->assign_job([
            'userid' => $usertwo->id,
            'departmentid' => $departmentsub->id,
            'positionid' => $positionsub->id,
        ]);

        // Dummy, shouldn't be returned.
        $userthree = $this->getDataGenerator()->create_user();
        [$positionthree, $departmentthree] = $this->get_generator()->create_position_and_department();
        $this->get_generator()->assign_job([
            'userid' => $usertwo->id,
            'departmentid' => $departmentthree->id,
            'positionid' => $positionthree->id,
        ]);

        $instance = $this->create_audience_instance($department->id, $position->id, true, true);
        [$joins, $where, $params] = $instance->get_sql('u');

        $userids = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$joins} WHERE {$where}", $params);
        $this->assertEqualsCanonicalizing([$userone->id, $usertwo->id], $userids);
    }

    /**
     * Helper method to create an audience instance for specified department/position
     *
     * @param int $departmentid (0 = 'Any')
     * @param int $positionid (0 = 'Any')
     * @param bool $withsubdepartments
     * @param bool $withsubpositions
     * @return job
     */
    protected function create_audience_instance(int $departmentid = 0, int $positionid = 0,
            bool $withsubdepartments = false, bool $withsubpositions = false): job {

        $report = $this->get_reportbuilder_generator()->create_report(['source' => mock_report::class]);

        return job::create($report->get_id(), [
            'department' => [
                'id' => $departmentid,
                'withsubdepartments' => (int) $withsubdepartments,
            ],
            'position' => [
                'id' => $positionid,
                'withsubpositions' => (int) $withsubpositions,
            ],
        ]);
    }

    /**
     * Get plugin generator
     *
     * @return tool_organisation_generator
     */
    protected function get_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_reportbuilder_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }
}
