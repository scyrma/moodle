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

namespace tool_organisation\task;

use advanced_testcase;
use tool_tenant_generator;

/**
 * Task test.
 *
 * @package     tool_organisation
 * @group       tool_organisation
 * @covers      \tool_organisation\task\end_orphaned_jobs
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class end_orphaned_jobs_test extends advanced_testcase {

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
    public function test_end_orphaned_jobs_task() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create users in tenant.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $tenant = $this->get_tenant_generator()->create_tenant();
        $tenantother = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant->id);
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant->id);

        // Create org structure.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $pf = $generator->create_position(['tenantid' => $tenant->id]);
        $pa = $generator->create_position(['parentid' => $pf->id]);

        $df = $generator->create_department(['tenantid' => $tenant->id]);
        $da = $generator->create_department(['parentid' => $df->id]);

        // Assign current jobs.
        $jobu1 = $generator->assign_job((object)['userid' => $user1->id,
            'positionid' => $pa->id, 'departmentid' => $da->id]);
        $jobu2 = $generator->assign_job((object)['userid' => $user2->id,
            'positionid' => $pa->id, 'departmentid' => $da->id]);

        // Assign future start jobs.
        $futurestart = strtotime('+1 year');
        $jobfsu1 = $generator->assign_job((object)['userid' => $user1->id,
            'positionid' => $pa->id, 'departmentid' => $da->id, 'startdate' => $futurestart]);
        $jobfsu2 = $generator->assign_job((object)['userid' => $user2->id,
            'positionid' => $pa->id, 'departmentid' => $da->id, 'startdate' => $futurestart]);

        // Assign future end jobs.
        $futureend = strtotime('+1 year');
        $jobfeu1 = $generator->assign_job((object)['userid' => $user1->id,
            'positionid' => $pa->id, 'departmentid' => $da->id, 'startdate' => strtotime('-1 year'), 'enddate' => $futureend]);
        $jobfeu2 = $generator->assign_job((object)['userid' => $user2->id,
            'positionid' => $pa->id, 'departmentid' => $da->id, 'startdate' => strtotime('-1 year'), 'enddate' => $futureend]);

        // Assign past end jobs.
        $pastend = strtotime('-1 month');
        $jobpeu1 = $generator->assign_job((object)['userid' => $user1->id,
            'positionid' => $pa->id, 'departmentid' => $da->id, 'startdate' => strtotime('-1 year'), 'enddate' => $pastend]);
        $jobpeu2 = $generator->assign_job((object)['userid' => $user2->id,
            'positionid' => $pa->id, 'departmentid' => $da->id, 'startdate' => strtotime('-1 year'), 'enddate' => $pastend]);

        // Make user2 jobs belong to the different tenant.
        $DB->set_field('tool_organisation_job', 'tenantid', $tenantother->id, ['userid' => $user2->id]);

        // Run the task.
        (new \tool_organisation\task\end_orphaned_jobs())->execute();
        $timestamp = time();

        // Validate current jobs.
        // User1 job is not changed.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $jobu1->id]);
        $this->assertEquals($jobu1->enddate, $rec->enddate);

        // User2 job is ended.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $jobu2->id]);
        $this->assertTrue($timestamp >= $rec->enddate);

        // Validate future start jobs.
        // User1 job is not changed.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $jobfsu1->id]);
        $this->assertEquals($jobfsu1->enddate, $rec->enddate);

        // User2 job is deleted.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $jobfsu2->id]);
        $this->assertFalse($rec);

        // Validate future end jobs.
        // User1 job is not changed.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $jobfeu1->id]);
        $this->assertEquals($jobfeu1->enddate, $rec->enddate);

        // User2 job is ended.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $jobfeu2->id]);
        $this->assertTrue($timestamp >= $rec->enddate);

        // Validate past end jobs.
        // User1 job is not changed.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $jobpeu1->id]);
        $this->assertEquals($jobpeu1->enddate, $rec->enddate);

        // User2 job is not changed.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $jobpeu2->id]);
        $this->assertEquals($jobpeu2->enddate, $rec->enddate);
    }
}
