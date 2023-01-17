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

namespace tool_organisation;

use advanced_testcase;
use tool_organisation_generator;
use tool_tenant_generator;

/**
 * Tests for events observers
 *
 * @package    tool_organisation
 * @covers     \tool_organisation_observer
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class events_test extends advanced_testcase {

    /**
     * Tenant generator
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test observer for tenant_deleted event, make sure the jobs, departments and positions are deleted.
     */
    public function test_delete_tenant() {
        global $DB;
        $this->resetAfterTest();
        $userdef = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user->id, $tenant->id);

        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        // Create 1 position and 3 departments and 1 job in the new tenant.
        $pf = $generator->create_position(['tenantid' => $tenant->id]);
        $pos = $generator->create_position(['parentid' => $pf->id]);
        $df = $generator->create_department(['tenantid' => $tenant->id]);
        $dep1 = $generator->create_department(['parentid' => $df->id]);
        $dep2 = $generator->create_department(['parentid' => $df->id]);
        $dep3 = $generator->create_department(['parentid' => $df->id]);
        $job = $generator->assign_job(['userid' => $user->id, 'departmentid' => $dep1->id, 'positionid' => $pos->id]);
        // Make one department "orphaned".
        $DB->update_record('tool_organisation_department',
            ['id' => $dep3->id, 'parentid' => -1, 'path' => '/-1/'.$dep3->id.'/']);

        // Create 1 position and 1 department and 1 job in the default tenant.
        $pdef = $generator->create_position(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $pdefpos = $generator->create_position(['parentid' => $pdef->id]);
        $ddef = $generator->create_department(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $ddefdep = $generator->create_department(['parentid' => $ddef->id]);
        $job = $generator->assign_job(['userid' => $userdef->id, 'departmentid' => $ddefdep->id, 'positionid' => $pdefpos->id]);

        // There are now 2 records in job table, 4 in positions and 6 in departments.
        $this->assertCount(2, $DB->get_records('tool_organisation_job'));
        $this->assertCount(4, $DB->get_records('tool_organisation_position'));
        $this->assertCount(6, $DB->get_records('tool_organisation_department'));

        // Delete a tenant.
        (new \tool_tenant\manager())->archive_tenant($tenant->id);
        (new \tool_tenant\manager())->delete_tenant($tenant->id);

        // There are now 1 records in job table, 2 in positions and 2 in departments.
        $this->assertCount(1, $DB->get_records('tool_organisation_job'));
        $this->assertCount(2, $DB->get_records('tool_organisation_position'));
        $this->assertCount(2, $DB->get_records('tool_organisation_department'));
    }

    /**
     * Test observer for user_deleted event, make sure the user jobs are deleted.
     */
    public function test_delete_user() {
        global $DB;
        $this->resetAfterTest();
        $userdef = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user->id, $tenant->id);
        $this->get_tenant_generator()->allocate_user($userdef->id, $tenant->id);

        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        // Create 1 position and 3 departments and 1 job in the new tenant.
        $pf = $generator->create_position(['tenantid' => $tenant->id]);
        $pos = $generator->create_position(['parentid' => $pf->id]);
        $df = $generator->create_department(['tenantid' => $tenant->id]);
        $dep1 = $generator->create_department(['parentid' => $df->id]);
        $generator->assign_job(['userid' => $user->id, 'departmentid' => $dep1->id, 'positionid' => $pos->id]);
        $generator->assign_job(['userid' => $userdef->id, 'departmentid' => $dep1->id, 'positionid' => $pos->id]);

        // There are now 2 records in job table.
        $this->assertCount(2, $DB->get_records('tool_organisation_job'));

        // Delete a user.
        delete_user($user);

        // User job is now deleted.
        $this->assertCount(1, $DB->get_records('tool_organisation_job'));
    }
}
