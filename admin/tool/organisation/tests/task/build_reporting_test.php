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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_organisation\task;

use advanced_testcase;
use core\task\manager;
use tool_tenant\tenancy;
use tool_tenant_generator;

/**
 * Ad-hoc building reporting task test.
 *
 * @package    tool_organisation
 * @group      tool_organisation
 * @covers     \tool_organisation\task\build_reporting
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class build_reporting_test extends advanced_testcase {

    /**
     * Tenant generator
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test the task
     */
    public function test_build_reporting_task() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create two users in default tenant.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Create new tenant and move previous two users to it.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant1->id);

        // Assert only one task is create for default tenant.
        $defaulttenantcd = json_encode(['tenantid' => tenancy::get_default_tenant_id()]);
        $params = [
            'classname' => '\tool_organisation\task\build_reporting',
            'component' => 'tool_organisation',
            'customdata' => $defaulttenantcd
        ];
        $sql = 'classname = ? AND component = ? AND ' .
            $DB->sql_compare_text('customdata', \core_text::strlen($defaulttenantcd) + 1) . ' = ?';
        $tasksdefaulttenant = $DB->get_records_select('task_adhoc', $sql, $params);
        $this->assertCount(1, $tasksdefaulttenant);

        // Assert only one task is create for tenant1.
        $tenant1cd = json_encode(['tenantid' => $tenant1->id]);
        // Change the customdata to tenant1.
        $params['customdata'] = $tenant1cd;
        $sql = 'classname = ? AND component = ? AND ' .
            $DB->sql_compare_text('customdata', \core_text::strlen($tenant1cd) + 1) . ' = ?';
        $taskstenant1 = $DB->get_records_select('task_adhoc', $sql, $params);
        $this->assertCount(1, $taskstenant1);

        // Retrieve all ad-hoc tasks.
        $adhoctasks = manager::get_adhoc_tasks('\\tool_organisation\\task\\build_reporting');

        // There should exist two ad-hoc task.
        $this->assertCount(2, $adhoctasks);

        // Execute them.
        foreach ($adhoctasks as $adhoctask) {
            $task = manager::get_adhoc_task($adhoctask->get_id());
            $this->assertInstanceOf('\\tool_organisation\\task\\build_reporting', $task);
            $task->execute();
            manager::adhoc_task_complete($task);
        }

        // Assert there not exist any record in reporting table for default tenant / tenant1,
        // since any org structure hasn't been created.
        $rldefaultenant = $DB->get_records('tool_organisation_reporting', ['tenantid' => tenancy::get_default_tenant_id()]);
        $rltenant1 = $DB->get_records('tool_organisation_reporting', ['tenantid' => $tenant1->id]);

        $this->assertEmpty($rldefaultenant);
        $this->assertEmpty($rltenant1);
    }
}
