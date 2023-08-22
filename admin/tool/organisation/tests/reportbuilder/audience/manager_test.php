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

namespace tool_organisation\reportbuilder\audience;

use advanced_testcase;
use core_reportbuilder_generator;
use core_user\reportbuilder\datasource\users;
use tool_organisation_generator;

/**
 * Unit tests for report audience type based on a users in position with global/department manager permission
 *
 * @package     tool_organisation
 * @covers      \tool_organisation\reportbuilder\audience\manager
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager_test extends advanced_testcase {

    /**
     * Test description
     */
    public function test_get_description(): void {
        $this->resetAfterTest();

        // Description for Managers.
        $globalmanager = 'globalmanager';
        $audienceglobal = $this->create_audience_instance($globalmanager);
        $expectedglobal = get_string('audiencemanagerdescription', 'tool_organisation', [
            'permissions' => get_string($globalmanager, 'tool_organisation')
        ]);

        $this->assertEquals($expectedglobal, $audienceglobal->get_description());

        // Description for Department lead.
        $departmentmanager = 'departmentmanager';
        $audiencedepartment = $this->create_audience_instance($departmentmanager);
        $expecteddepartment = get_string('audiencemanagerdescription', 'tool_organisation', [
            'permissions' => get_string($departmentmanager, 'tool_organisation')
        ]);

        $this->assertEquals($expecteddepartment, $audiencedepartment->get_description());

        // Description for Manager or department lead.
        $anymanager = 'anymanager';
        $audienceany = $this->create_audience_instance($anymanager);
        $expectedany = get_string('audiencemanagerdescription', 'tool_organisation', [
            'permissions' => get_string($anymanager, 'tool_organisation')
        ]);

        $this->assertEquals($expectedany, $audienceany->get_description());
    }

    /**
     * Test getting SQL for users with any manager permission active in some position.
     */
    public function test_get_sql_any_manager(): void {
        global $DB;

        $this->resetAfterTest();

        $anymanagerposition = ['globalmanager' => 1, 'departmentmanager' => 1];

        [$position, $department] = $this->get_generator()->create_position_and_department($anymanagerposition);

        $userglobal = $this->getDataGenerator()->create_user();
        $this->get_generator()->assign_job([
            'userid' => $userglobal->id,
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        $userdepartment = $this->getDataGenerator()->create_user();
        $this->get_generator()->assign_job([
            'userid' => $userdepartment->id,
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        $instance = $this->create_audience_instance('anymanager');
        [$joins, $where, $params] = $instance->get_sql('u');

        $userids = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$joins} WHERE {$where}", $params);
        $this->assertEqualsCanonicalizing([$userglobal->id, $userdepartment->id], $userids);
    }

    /**
     * Test getting SQL for users with department manager permission active in some position.
     */
    public function test_get_sql_department_manager(): void {
        global $DB;

        $this->resetAfterTest();

        $departmentmanagerposition = ['departmentmanager' => 1];

        [$position, $department] = $this->get_generator()->create_position_and_department($departmentmanagerposition);

        $userdepartment = $this->getDataGenerator()->create_user();
        $this->get_generator()->assign_job([
            'userid' => $userdepartment->id,
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        $instance = $this->create_audience_instance('departmentmanager');
        [$joins, $where, $params] = $instance->get_sql('u');

        $userids = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$joins} WHERE {$where}", $params);
        $this->assertEquals([$userdepartment->id], $userids);
    }

    /**
     * Test getting SQL for users with global manager permission active in some position.
     */
    public function test_get_sql_global_manager(): void {
        global $DB;

        $this->resetAfterTest();

        $globalmanagerposition = ['globalmanager' => 1];

        [$position, $department] = $this->get_generator()->create_position_and_department($globalmanagerposition);

        $userglobal = $this->getDataGenerator()->create_user();
        $this->get_generator()->assign_job([
            'userid' => $userglobal->id,
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        $instance = $this->create_audience_instance('globalmanager');
        [$joins, $where, $params] = $instance->get_sql('u');

        $userids = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$joins} WHERE {$where}", $params);
        $this->assertEquals([$userglobal->id], $userids);
    }

    /**
     * Helper method to create an audience instance for users with some manager permission active in position.
     *
     * @param string $permissions Options:
     *   - globalmanager: set to get users with global manager permission active in position.
     *   - departmentmanager: set to get users with department manager permission active in position.
     * @return manager
     */
    protected function create_audience_instance(string $permissions): manager {
        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'My report', 'source' => users::class]);

        return manager::create($report->get('id'), [
            'permissions' => $permissions
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
}
