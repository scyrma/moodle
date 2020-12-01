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
 * Create new job in organisation Unit testing
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Sumit Negi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/lib/externallib.php');

use tool_organisation\external;
use tool_organisation\helper;
use tool_organisation\organisation;
use tool_organisation\output\job;

/**
 * Create new job in organisation unit test class
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Sumit Negi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_external_job_testcase extends advanced_testcase {
    /**
     * @var stdClass tenant
     */
    protected $tenant;
    /**
     * @var \stdClass user detail
     */
    protected $user;
    /**
     * @var \stdClass currentuser detail
     */
    protected $currentuser;
    /**
     * @var \stdClass department
     */
    protected $df;
    /**
     * @var \stdClass position
     */
    protected $pf;

    /**
     * @var tool_organisation_generator instance
     */
    protected $orggenerator;

    /**
     * @var tool_tenant_generator instance
     */
    protected $tenantgenerator;

    /**
     * Test create job with same tenant
     */
    public function test_create_job_with_same_tenant() {
        $this->resetAfterTest(true);
        $this->create_tenant_organisation_structure();
        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);
        $this->tenantgenerator->allocate_user($this->currentuser->id, $this->tenant->id);
        self::setUser($this->currentuser->id);
        $this->orggenerator->assign_capability('tool/organisation:assignjobs', $this->currentuser->id, context_system::instance());
        $userid = $this->user->id;
        $jobdepartment = $this->df->idnumber;
        $jobposition = $this->pf->idnumber;
        $startdate = 1598624537;
        $enddate = 1758758400;
        $job = external\create_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\create_job::execute_returns(), $job);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was created.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->df->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pf->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(helper::round_time($enddate), $userjob->get('enddate'));
    }

    /**
     * Test create job with same tenant with default dates
     */
    public function test_create_job_with_same_tenant_defaultdates() {
        $this->resetAfterTest(true);
        $this->create_tenant_organisation_structure();
        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);
        $this->tenantgenerator->allocate_user($this->currentuser->id, $this->tenant->id);
        self::setUser($this->currentuser->id);
        $this->orggenerator->assign_capability('tool/organisation:assignjobs', $this->currentuser->id, context_system::instance());
        $userid = $this->user->id;
        $jobdepartment = $this->df->idnumber;
        $jobposition = $this->pf->idnumber;
        $job = external\create_job::execute($userid, $jobdepartment, $jobposition);
        $jobreturn = external_api::clean_returnvalue(external\create_job::execute_returns(), $job);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was created.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->df->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pf->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time(time()), $userjob->get('startdate'));
        $this->assertEquals(0, $userjob->get('enddate'));
    }


    /**
     * Test create job with user who is not in the assignee tenant
     */
    public function test_create_job_in_other_tenant() {
        $this->resetAfterTest(true);
        $this->create_tenant_organisation_structure();
        $this->orggenerator->assign_capability('tool/organisation:assignjobs', $this->currentuser->id, context_system::instance());
        $this->assign_manage_tenant_capability('tool/tenant:manage', $this->currentuser->id, context_system::instance());
        self::setUser($this->currentuser->id);
        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);
        $userid = $this->user->id;
        $jobdepartment = $this->df->idnumber;
        $jobposition = $this->pf->idnumber;
        $startdate = 1598624537;
        $enddate = 1758758400;
        $job = external\create_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\create_job::execute_returns(), $job);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was created.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->df->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pf->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(helper::round_time($enddate), $userjob->get('enddate'));
    }

    /**
     * Test update job with same tenant
     *
     */
    public function test_update_job_with_same_tenant() {
        $this->resetAfterTest(true);
        $this->create_tenant_organisation_structure();
        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);
        $this->tenantgenerator->allocate_user($this->currentuser->id, $this->tenant->id);
        $this->orggenerator->assign_capability('tool/organisation:assignjobs', $this->currentuser->id, context_system::instance());
        self::setUser($this->currentuser->id);
        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pf->id,
            'departmentid' => $this->df->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->df->idnumber;
        $jobposition = $this->pf->idnumber;
        $startdate = 1598624537;
        $enddate = 1758758400;
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->df->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pf->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(helper::round_time($enddate), $userjob->get('enddate'));
    }

    /**
     * Update job with default dates
     */
    public function test_update_job_with_same_tenant_defaultdates() {
        $this->resetAfterTest(true);

        $this->create_tenant_organisation_structure();
        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);
        $this->tenantgenerator->allocate_user($this->currentuser->id, $this->tenant->id);
        $this->orggenerator->assign_capability('tool/organisation:assignjobs', $this->currentuser->id, context_system::instance());
        self::setUser($this->currentuser->id);
        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pf->id,
            'departmentid' => $this->df->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->df->idnumber;
        $jobposition = $this->pf->idnumber;
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->df->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pf->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time(time()), $userjob->get('startdate'));
        $this->assertEquals(0, $userjob->get('enddate'));
    }

    /**
     * Update job with disable end date for the job
     */
    public function test_update_job_with_same_tenant_noenddate() {
        $this->resetAfterTest(true);
        $this->create_tenant_organisation_structure();
        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);
        $this->tenantgenerator->allocate_user($this->currentuser->id, $this->tenant->id);
        $this->orggenerator->assign_capability('tool/organisation:assignjobs', $this->currentuser->id, context_system::instance());
        self::setUser($this->currentuser->id);
        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pf->id,
            'departmentid' => $this->df->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->df->idnumber;
        $jobposition = $this->pf->idnumber;
        $startdate = 1598624537;
        $enddate = 0;
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->df->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pf->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(0, $userjob->get('enddate'));
    }

    /**
     * Update job for user
     */
    public function test_update_job_with_other_tenant() {
        $this->resetAfterTest(true);
        $this->create_tenant_organisation_structure();
        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);
        $this->orggenerator->assign_capability('tool/organisation:assignjobs', $this->currentuser->id, context_system::instance());
        $this->assign_manage_tenant_capability('tool/tenant:manage', $this->currentuser->id, context_system::instance());
        self::setUser($this->currentuser->id);
        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pf->id,
            'departmentid' => $this->df->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->df->idnumber;
        $jobposition = $this->pf->idnumber;
        $startdate = 1598624537;
        $enddate = 1758758400;
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->df->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pf->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(helper::round_time($enddate), $userjob->get('enddate'));
    }

    /**
     * Update job with disable end date for the job
     */
    public function test_update_job_with_other_tenant_noenddate() {
        $this->resetAfterTest(true);
        $this->create_tenant_organisation_structure();
        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);
        $this->assign_manage_tenant_capability('tool/tenant:manage', $this->currentuser->id, context_system::instance());
        $this->orggenerator->assign_capability('tool/organisation:assignjobs', $this->currentuser->id, context_system::instance());
        self::setUser($this->currentuser->id);
        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pf->id,
            'departmentid' => $this->df->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->df->idnumber;
        $jobposition = $this->pf->idnumber;
        $startdate = 1598624537;
        $enddate = 0;
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->df->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pf->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(0, $userjob->get('enddate'));
    }

    /**
     * Update job when start and end date not given
     */
    public function test_update_job_with_other_tenant_defaultdates() {
        $this->resetAfterTest(true);
        $this->create_tenant_organisation_structure();
        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);
        $this->assign_manage_tenant_capability('tool/tenant:manage', $this->currentuser->id, context_system::instance());
        $this->orggenerator->assign_capability('tool/organisation:assignjobs', $this->currentuser->id, context_system::instance());
        self::setUser($this->currentuser->id);
        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pf->id,
            'departmentid' => $this->df->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->df->idnumber;
        $jobposition = $this->pf->idnumber;
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->df->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pf->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time(time()), $userjob->get('startdate'));
        $this->assertEquals(0, $userjob->get('enddate'));
    }

    /**
     * Test updating a user job when user is assigned the same job multiple times
     */
    public function test_update_job_user_duplicate_assignments(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->create_tenant_organisation_structure();

        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);

        // Assign the same job to our test user multiple times.
        $jobdata = [
            'tenantid' => $this->tenant->id,
            'userid' => $this->user->id,
            'positionid' => $this->pf->id,
            'departmentid' => $this->df->id,
        ];
        $jobone = $this->orggenerator->assign_job($jobdata + [
            'startdate' => strtotime('2020-01-15'),
            'enddate' => strtotime('2020-03-15'),
        ]);
        $jobtwo = $this->orggenerator->assign_job($jobdata + [
            'startdate' => strtotime('2020-06-15'),
            'enddate' => strtotime('2020-08-15'),
        ]);

        // When we update, we should be updating the one with the most recent start date.
        $newstartdate = strtotime('2020-09-15');
        $newenddate = strtotime('2020-10-15');

        $result = external\update_job::clean_returnvalue(
            external\update_job::execute_returns(),
            external\update_job::execute($this->user->id, $this->df->idnumber, $this->pf->idnumber,
                $newstartdate, $newenddate)
        );
        $this->assertTrue($result['status']);

        // User job ordering is undefined, retrieve each jobs start/end date and sort by the start date.
        $userjobdates = array_map(static function(job $job): stdClass {
            return (object) [
                'id' => $job->get('id'),
                'startdate' => $job->get('startdate'),
                'enddate' => $job->get('enddate'),
            ];
        }, $this->get_user_jobs());

        core_collator::asort_objects_by_property($userjobdates, 'startdate');
        [$userjobone, $userjobtwo] = array_values($userjobdates);

        // First job shouldn't have changed.
        $this->assertEquals($jobone->id, $userjobone->id);
        $this->assertEquals($jobone->startdate, $userjobone->startdate);
        $this->assertEquals($jobone->enddate, $userjobone->enddate);

        // Second job's start/end dates should have been updated.
        $this->assertEquals($jobtwo->id, $userjobtwo->id);
        $this->assertEquals($newstartdate, $userjobtwo->startdate);
        $this->assertEquals($newenddate, $userjobtwo->enddate);
    }

    /**
     * Test updating a job that a user was never assigned
     */
    public function test_update_non_assigned_job(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->create_tenant_organisation_structure();

        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);

        $newstartdate = strtotime('2020-09-15');
        $newenddate = strtotime('2020-10-15');

        $result = external\update_job::clean_returnvalue(
            external\update_job::execute_returns(),
            external\update_job::execute($this->user->id, $this->df->idnumber, $this->pf->idnumber,
                $newstartdate, $newenddate)
        );
        $this->assertFalse($result['status']);

        // Sanity check, user should still have no job assignments.
        $userjobs = $this->get_user_jobs();
        $this->assertEmpty($userjobs);
    }

    /**
     * Assigns capability to user
     *
     * @param string  $capability
     * @param int     $userid
     * @param context $context
     *
     * @throws coding_exception
     */
    public function assign_manage_tenant_capability(string $capability, int $userid, context $context): void {
        $roleid = create_role('Dummy role', 'dummymanagetenantrole', 'Manage tenants dummy role description');
        assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $userid, $context->id);
    }

    /**
     * Return all current job assignments for our test user
     *
     * @return job[]
     */
    protected function get_user_jobs(): array {
        return organisation::get_user_with_jobs($this->user->id, null)->get_jobs();
    }

    /**
     * Tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_organisation_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Create tenant and organisation structure data
     */
    protected function create_tenant_organisation_structure() {
        $this->user = $this->getDataGenerator()->create_user();
        $this->currentuser = $this->getDataGenerator()->create_user();
        $this->tenantgenerator = $this->get_tenant_generator();
        $this->tenant = $this->tenantgenerator->create_tenant();
        $this->orggenerator = $this->get_organisation_generator();
        $department = ['name' => 'HR Department', 'idnumber' => 'hrd', 'tenantid' => $this->tenant->id];
        $position = ['name' => 'Manager', 'idnumber' => 'manager', 'tenantid' => $this->tenant->id];
        $this->pf = $this->orggenerator->create_position($department);
        $this->df = $this->orggenerator->create_department($position);
    }

}