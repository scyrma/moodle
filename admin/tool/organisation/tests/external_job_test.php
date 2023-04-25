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

use context_system;
use core_collator;
use external_api;
use externallib_advanced_testcase;
use moodle_exception;
use stdClass;
use tool_organisation_external;
use tool_organisation_generator;
use tool_tenant_generator;
use tool_organisation\external;
use tool_organisation\output\job;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Create new job in organisation unit test class
 *
 * TODO: WP-3102 split this test between classes
 *
 * @package    tool_organisation
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Sumit Negi
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external_job_test extends externallib_advanced_testcase {
    /** @var stdClass tenant */
    protected $tenant;
    /** @var \stdClass user detail */
    protected $user;
    /** @var \stdClass currentuser detail */
    protected $currentuser;
    /** @var \stdClass shared department */
    protected $sdep;
    /** @var \stdClass shared position */
    protected $spos;
    /** @var \stdClass department */
    protected $dep;
    /** @var \stdClass position */
    protected $pos;
    /** @var tool_organisation_generator instance */
    protected $orggenerator;
    /** @var tool_tenant_generator instance */
    protected $tenantgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->create_tenant_organisation_structure();
        $this->tenantgenerator->allocate_user($this->user->id, $this->tenant->id);
        $this->tenantgenerator->allocate_user($this->currentuser->id, $this->tenant->id);
        $this->orggenerator->assign_capability('tool/organisation:assignjobs', $this->currentuser->id, context_system::instance());
    }

    /**
     * Test create job with same tenant
     */
    public function test_create_job_with_same_tenant() {
        self::setUser($this->currentuser->id);

        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = strtotime('+1 year');
        $job = external\create_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\create_job::execute_returns(), $job);
        $this->assertEquals(true, $jobreturn['status']);
        $this->assertNotEmpty($jobreturn['jobid']);

        // Assert the job assignment was created.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(helper::round_time($enddate), $userjob->get('enddate'));
    }

    /**
     * Test create job with same tenant in shared department and position
     */
    public function test_create_job_with_same_tenant_shared() {
        self::setUser($this->currentuser->id);

        $userid = $this->user->id;
        $jobdepartment = $this->sdep->idnumber;
        $jobposition = $this->spos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = strtotime('+1 year');
        $job = external\create_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\create_job::execute_returns(), $job);
        $this->assertEquals(true, $jobreturn['status']);
        $this->assertNotEmpty($jobreturn['jobid']);

        // Assert the job assignment was created.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->sdep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->spos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(helper::round_time($enddate), $userjob->get('enddate'));
    }

    /**
     * Test create job with same tenant with default dates
     */
    public function test_create_job_with_same_tenant_defaultdates() {
        self::setUser($this->currentuser->id);

        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $job = external\create_job::execute($userid, $jobdepartment, $jobposition);
        $jobreturn = external_api::clean_returnvalue(external\create_job::execute_returns(), $job);
        $this->assertEquals(true, $jobreturn['status']);
        $this->assertNotEmpty($jobreturn['jobid']);

        // Assert the job assignment was created.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time(time()), $userjob->get('startdate'));
        $this->assertEquals(0, $userjob->get('enddate'));
    }


    /**
     * Test create job with user who is not in the assignee tenant
     */
    public function test_create_job_in_other_tenant() {
        self::setUser($this->currentuser->id);

        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = strtotime('+1 year');
        $job = external\create_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\create_job::execute_returns(), $job);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was created.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($userjob->get('id'), $jobreturn['jobid']);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(helper::round_time($enddate), $userjob->get('enddate'));
    }

    /**
     * Test update job with same tenant
     */
    public function test_update_job_with_same_tenant() {
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = strtotime('+1 year');
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(helper::round_time($enddate), $userjob->get('enddate'));
    }

    /**
     * Test update job with same tenant using shared department and position
     */
    public function test_update_job_with_same_tenant_shared() {
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->spos->id,
            'departmentid' => $this->sdep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->sdep->idnumber;
        $jobposition = $this->spos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = strtotime('+1 year');
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->sdep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->spos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(helper::round_time($enddate), $userjob->get('enddate'));
    }

    /**
     * Update job with default dates
     */
    public function test_update_job_with_same_tenant_defaultdates() {
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time(time()), $userjob->get('startdate'));
        $this->assertEquals(0, $userjob->get('enddate'));
    }

    /**
     * Update job with disable end date for the job
     */
    public function test_update_job_with_same_tenant_noenddate() {
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = 0;
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(0, $userjob->get('enddate'));
    }

    /**
     * Update job for user
     */
    public function test_update_job_with_other_tenant() {
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = strtotime('+1 year');
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(helper::round_time($enddate), $userjob->get('enddate'));
    }

    /**
     * Update job with disable end date for the job
     */
    public function test_update_job_with_other_tenant_noenddate() {
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = 0;
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time($startdate), $userjob->get('startdate'));
        $this->assertEquals(0, $userjob->get('enddate'));
    }

    /**
     * Update job when start and end date not given
     */
    public function test_update_job_with_other_tenant_defaultdates() {
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $jobupdate = external\update_job::execute($userid, $jobdepartment, $jobposition);
        $jobreturn = external_api::clean_returnvalue(external\update_job::execute_returns(), $jobupdate);
        $this->assertEquals(true, $jobreturn['status']);

        // Assert the job assignment was updated.
        $userjobs = $this->get_user_jobs();
        $this->assertCount(1, $userjobs);

        $userjob = reset($userjobs);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(helper::round_time(time()), $userjob->get('startdate'));
        $this->assertEquals(0, $userjob->get('enddate'));
    }

    /**
     * Test updating a user job when user is assigned the same job multiple times
     */
    public function test_update_job_user_duplicate_assignments(): void {
        $this->setAdminUser();

        // Assign the same job to our test user multiple times.
        $jobdata = [
            'tenantid' => $this->tenant->id,
            'userid' => $this->user->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
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
            external\update_job::execute($this->user->id, $this->dep->idnumber, $this->pos->idnumber,
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
        $this->setAdminUser();

        $newstartdate = strtotime('2020-09-15');
        $newenddate = strtotime('2020-10-15');

        $result = external\update_job::clean_returnvalue(
            external\update_job::execute_returns(),
            external\update_job::execute($this->user->id, $this->dep->idnumber, $this->pos->idnumber,
                $newstartdate, $newenddate)
        );
        $this->assertFalse($result['status']);

        // Sanity check, user should still have no job assignments.
        $userjobs = $this->get_user_jobs();
        $this->assertEmpty($userjobs);
    }

    /**
     * Job failed in other tenant if user has no permission to access that tenant.
     */
    public function test_failed_create_job_by_user_in_other_tenant() : void {
        // Login as new tenant user.
        $tenant2 = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($this->currentuser->id, $tenant2->id);
        self::setUser($this->currentuser->id);

        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = strtotime('+1 year');
        $this->expectException(\moodle_exception::class);
        external\create_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
    }

    /**
     * Update job failed in other tenant if user has no permission to access that tenant.
     */
    public function test_failed_update_job_by_user_in_other_tenant() : void {
        // Login as new tenant user.
        $tenant2 = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($this->currentuser->id, $tenant2->id);
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $this->orggenerator->assign_job((object) $data);

        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = strtotime('+1 year');
        $this->expectException(\moodle_exception::class);
        external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
    }

    /**
     * Create job failed if enddate is before startdate.
     */
    public function test_failed_create_job_enddate_before_startdate() : void {
        self::setUser($this->currentuser->id);

        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = strtotime('-1 day');
        try {
            external\create_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString(get_string('errorinvalidenddate', 'tool_organisation'), $e->getMessage());
        }
    }

    /**
     * Update job failed if enddate is before startdate.
     */
    public function test_failed_update_job_enddate_before_startdate() : void {
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('-1 day'),
            'enddate' => strtotime('+1 day')
        ];
        $this->orggenerator->assign_job((object) $data);

        $userid = $this->user->id;
        $jobdepartment = $this->dep->idnumber;
        $jobposition = $this->pos->idnumber;
        $startdate = strtotime('+1 day');
        $enddate = strtotime('-1 day');
        try {
            external\update_job::execute($userid, $jobdepartment, $jobposition, $startdate, $enddate);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString(get_string('errorinvalidenddate', 'tool_organisation'), $e->getMessage());
        }
    }

    /**
     * Test delete job in same tenant
     */
    public function test_delete_job_in_same_tenant() {
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $return = tool_organisation_external::job_delete($job->id);

        $this->assertEmpty($return);
        $this->assertFalse(\tool_organisation\job::get_record(['id' => $job->id]));
    }

    /**
     *  Test failure of deleting job in other tenant
     */
    public function test_failed_delete_job_in_other_tenant() {
        // Login as new tenant user.
        $tenant2 = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($this->currentuser->id, $tenant2->id);
        self::setUser($this->currentuser->id);

        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $this->user->id,
            'startdate' => strtotime('2020-09-20'),
            'enddate' => strtotime('2021-09-20')
        ];
        $job = $this->orggenerator->assign_job((object) $data);
        $this->expectException(\moodle_exception::class);
        tool_organisation_external::job_delete($job->id);
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
     * Create tenant and organisation structure data
     */
    protected function create_tenant_organisation_structure() {
        $this->user = $this->getDataGenerator()->create_user();
        $this->currentuser = $this->getDataGenerator()->create_user();
        $this->tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->tenant = $this->tenantgenerator->create_tenant();
        $this->orggenerator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        // Departments.
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        $sdf = $this->orggenerator->create_department(['tenantid' => $sharedtenantid, 'shared' => 1]);
        $df = $this->orggenerator->create_department(['tenantid' => $this->tenant->id]);

        $this->sdep = $this->orggenerator->create_department(
            ['name' => 'Shared Department', 'idnumber' => 'sd', 'parentid' => $sdf->id]);
        $this->dep = $this->orggenerator->create_department(
            ['name' => 'HR Department', 'idnumber' => 'hrd', 'parentid' => $df->id]);

        // Positions.
        $spf = $this->orggenerator->create_position(['tenantid' => $sharedtenantid, 'shared' => 1]);
        $pf = $this->orggenerator->create_position(['tenantid' => $this->tenant->id]);

        $this->spos = $this->orggenerator->create_position(
            ['name' => 'Shared Position', 'idnumber' => 'sp', 'parentid' => $spf->id]);
        $this->pos = $this->orggenerator->create_position(
            ['name' => 'Manager', 'idnumber' => 'manager', 'parentid' => $pf->id]);
    }
}
