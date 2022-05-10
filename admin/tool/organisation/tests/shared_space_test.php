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
 * File containing tests for shared_space class.
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The shared_space test class.
 *
 * @package    tool_organisation
 * @group      tool_organisation
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_shared_space_testcase extends advanced_testcase {

    /** @var tool_organisation_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /** setUp */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_organisation');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');

        $this->resetAfterTest();
    }

    /**
     * Test create departments
     *
     * @throws coding_exception
     */
    public function test_create_departments(): void {
        self::setAdminUser();
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();

        $params = ['name' => 'Frmk Shared Space', 'shared' => 1, 'tenantid' => $sharedtenantid];
        $sharedframework = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $this->assertEquals('1', $sharedframework->get('shared'));
        $this->assertEquals($sharedtenantid, $sharedframework->get('tenantid'));
        $this->assertNull($sharedframework->get('parentid'));

        $params = ['name' => 'Share dep1', 'parentid' => $sharedframework->get('id')];
        $shareddep1 = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $this->assertEquals('1', $sharedframework->get('shared'));
        $this->assertEquals($sharedtenantid, $sharedframework->get('tenantid'));
        $this->assertEquals($sharedframework->get('id'), $shareddep1->get('parentid'));

        $params = ['name' => 'Frmk 1'];
        $sharedframework = (new \tool_organisation\department_manager())->create_department((object)$params, false);
        $this->assertEquals('0', $sharedframework->get('shared'));
        $this->assertEquals($defaulttenantid, $sharedframework->get('tenantid'));
    }

    /**
     * Test create positions
     *
     * @throws coding_exception
     */
    public function test_create_positions(): void {
        self::setAdminUser();
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();

        $params = ['name' => 'Frmk Shared Space', 'shared' => 1, 'tenantid' => $sharedtenantid];
        $sharedframework = (new \tool_organisation\position_manager())->create_position((object)$params, false);
        $this->assertEquals('1', $sharedframework->get('shared'));
        $this->assertEquals($sharedtenantid, $sharedframework->get('tenantid'));
        $this->assertNull($sharedframework->get('parentid'));

        $params = ['name' => 'Share pos1', 'parentid' => $sharedframework->get('id')];
        $sharedpos1 = (new \tool_organisation\position_manager())->create_position((object)$params, false);
        $this->assertEquals('1', $sharedframework->get('shared'));
        $this->assertEquals($sharedtenantid, $sharedframework->get('tenantid'));
        $this->assertEquals($sharedframework->get('id'), $sharedpos1->get('parentid'));

        $params = ['name' => 'Frmk 1'];
        $sharedframework = (new \tool_organisation\position_manager())->create_position((object)$params, false);
        $this->assertEquals('0', $sharedframework->get('shared'));
        $this->assertEquals($defaulttenantid, $sharedframework->get('tenantid'));
    }

    /**
     * Test create jobs
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_create_jobs(): void {
        global $DB;
        self::setAdminUser();
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();

        $pf = $this->generator->create_position(['tenantid' => $sharedtenantid, 'shared' => 1]);
        $pfother = $this->generator->create_position(['tenantid' => $defaulttenantid]);

        $pa = $this->generator->create_position(['parentid' => $pf->id]);
        $pb = $this->generator->create_position(['parentid' => $pf->id]);
        $pa1 = $this->generator->create_position(['parentid' => $pa->id]);

        $df = $this->generator->create_department(['tenantid' => $sharedtenantid, 'shared' => 1]);
        $dfother = $this->generator->create_department(['tenantid' => $defaulttenantid]);

        $da = $this->generator->create_department(['parentid' => $df->id]);
        $db = $this->generator->create_department(['parentid' => $df->id]);
        $da1 = $this->generator->create_department(['parentid' => $da->id]);

        $record = $DB->get_record('tool_organisation_position', ['id' => $pa->id]);
        $this->assertEquals($sharedtenantid, $record->tenantid);
        $this->assertEquals('1', $record->shared);

        $record = $DB->get_record('tool_organisation_department', ['id' => $da->id]);
        $this->assertEquals($sharedtenantid, $record->tenantid);
        $this->assertEquals('1', $record->shared);

        $user = $this->getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user->id, $defaulttenantid);

        $this->assertEquals(0, $DB->count_records('tool_organisation_job'));
        $manager = new \tool_organisation\job_manager();

        // Create a job using a shared department and a shared position.
        $job = $manager->create_job((object)['userid' => $user->id,
            'positionid' => $pa1->id, 'departmentid' => $da1->id, 'startdate' => 289263600]);

        $record = $DB->get_record('tool_organisation_job', ['id' => $job->get('id')]);
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals($da1->id, $record->departmentid);
        $this->assertEquals($pa1->id, $record->positionid);
        $this->assertEquals($defaulttenantid, $record->tenantid);
        $this->assertEquals(289263600, $record->startdate);

        $manager->update_job($job->get('id'), (object)['startdate' => 1263513600]);
        $record = $DB->get_record('tool_organisation_job', ['id' => $job->get('id')]);
        $this->assertEquals(1263513600, $record->startdate);
    }

    /**
     * Test move jobs to new tenant
     *
     * @covers \tool_organisation\job_manager::move_jobs_to_new_tenant()
     */
    public function test_move_jobs_to_new_tenant(): void {
        global $DB;
        self::setAdminUser();
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $tenant2 = $this->tenantgenerator->create_tenant();

        $pf = $this->generator->create_position(['tenantid' => $sharedtenantid, 'shared' => 1]);
        $pfother = $this->generator->create_position(['tenantid' => $defaulttenantid]);
        $pa = $this->generator->create_position(['parentid' => $pf->id]);
        $pother1 = $this->generator->create_position(['parentid' => $pfother->id]);

        $df = $this->generator->create_department(['tenantid' => $sharedtenantid, 'shared' => 1]);
        $dfother = $this->generator->create_department(['tenantid' => $defaulttenantid]);
        $da = $this->generator->create_department(['parentid' => $df->id]);
        $dother1 = $this->generator->create_department(['parentid' => $dfother->id]);

        // Create a new user on default tenant.
        $user = $this->getDataGenerator()->create_user();

        // Sanity check.
        $this->assertEquals(0, $DB->count_records('tool_organisation_job'));

        $manager = new \tool_organisation\job_manager();

        // Create a job using a shared department and a shared position.
        $startdate = strtotime('-1 year');
        $jobshared = $manager->create_job((object)['userid' => $user->id,
            'positionid' => $pa->id, 'departmentid' => $da->id, 'startdate' => $startdate]);

        // Create job with shared department and non shared position.
        $job1 = $manager->create_job((object)['userid' => $user->id,
            'positionid' => $pother1->id, 'departmentid' => $da->id, 'startdate' => $startdate]);

        // Create job with a non shared department and a shared position.
        $job2 = $manager->create_job((object)['userid' => $user->id,
            'positionid' => $pa->id, 'departmentid' => $dother1->id, 'startdate' => $startdate]);

        // Create job with a non shared department and a non shared position.
        $job3 = $manager->create_job((object)['userid' => $user->id,
            'positionid' => $pother1->id, 'departmentid' => $dother1->id, 'startdate' => $startdate]);

        $jobs = $DB->get_records('tool_organisation_job', ['userid' => $user->id, 'tenantid' => $defaulttenantid]);
        $this->assertCount(4, $jobs);
        $jobs = $DB->get_records('tool_organisation_job', ['userid' => $user->id, 'tenantid' => $tenant2->id]);
        $this->assertEmpty($jobs);

        // Move user to tenant 2.
        $this->tenantgenerator->allocate_user($user->id, $tenant2->id);

        // Check that only one job (the one with shared position and shared department) has been moved to the new tenant.
        $jobs = $DB->get_records('tool_organisation_job', ['userid' => $user->id, 'tenantid' => $tenant2->id]);
        $this->assertCount(1, $jobs);
        $job = reset($jobs);
        $this->assertEquals($jobshared->get('id'), $job->id);
        $this->assertEmpty($job->enddate);

        // Check that the other 3 jobs are still in old tenant and having enddate set.
        $jobs = $DB->get_records('tool_organisation_job', ['userid' => $user->id, 'tenantid' => $defaulttenantid]);
        $this->assertCount(3, $jobs);
        $this->assertEqualsCanonicalizing([$job1->get('id'), $job2->get('id'), $job3->get('id')], array_column($jobs, 'id'));
        foreach ($jobs as $job) {
            $this->assertNotEmpty($job->enddate);
        }

        // Move back to default tenant.
        $this->tenantgenerator->allocate_user($user->id, $defaulttenantid);

        // Check that shared job moved to default tenant and was not ended.
        $jobs = $DB->get_records('tool_organisation_job', ['userid' => $user->id, 'tenantid' => $tenant2->id]);
        $this->assertCount(0, $jobs);
        $jobs = $DB->get_records('tool_organisation_job', ['userid' => $user->id, 'tenantid' => $defaulttenantid]);
        $this->assertCount(4, $jobs);
        $job = $DB->get_record('tool_organisation_job', ['id' => $jobshared->get('id')]);
        $this->assertEmpty($job->enddate);
    }

    /**
     * Test move jobs to new tenant result in ending jobs that can't be moved.
     *
     * @covers \tool_organisation\job_manager::move_jobs_to_new_tenant()
     */
    public function test_move_jobs_to_new_tenant_ended(): void {
        global $DB;
        self::setAdminUser();
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $tenant2 = $this->tenantgenerator->create_tenant();

        $pf = $this->generator->create_position(['tenantid' => $sharedtenantid, 'shared' => 1]);
        $pfother = $this->generator->create_position(['tenantid' => $defaulttenantid]);
        $pa = $this->generator->create_position(['parentid' => $pf->id]);
        $pother1 = $this->generator->create_position(['parentid' => $pfother->id]);

        $df = $this->generator->create_department(['tenantid' => $sharedtenantid, 'shared' => 1]);
        $dfother = $this->generator->create_department(['tenantid' => $defaulttenantid]);
        $da = $this->generator->create_department(['parentid' => $df->id]);
        $dother1 = $this->generator->create_department(['parentid' => $dfother->id]);

        // Create a new user on default tenant.
        $user = $this->getDataGenerator()->create_user();

        // Sanity check.
        $this->assertEquals(0, $DB->count_records('tool_organisation_job'));

        // Create a job using a shared department and a shared position.
        $startdate = strtotime('-1 year');
        $jobshared = $this->generator->assign_job((object)['userid' => $user->id, 'positionid' => $pa->id,
            'departmentid' => $da->id, 'startdate' => $startdate]);

        // Create current job.
        $job1 = $this->generator->assign_job((object)['userid' => $user->id, 'positionid' => $pother1->id,
            'departmentid' => $dother1->id, 'startdate' => $startdate]);

        // Create job, future start.
        $job2 = $this->generator->assign_job((object)['userid' => $user->id, 'positionid' => $pother1->id,
            'departmentid' => $dother1->id, 'startdate' => strtotime('+1 year')]);

        // Create job, future end.
        $job3 = $this->generator->assign_job((object)['userid' => $user->id, 'positionid' => $pother1->id,
            'departmentid' => $dother1->id, 'startdate' => $startdate, 'enddate' => strtotime('+1 month')]);

        // Create job, enddate in the past.
        $job4 = $this->generator->assign_job((object)['userid' => $user->id, 'positionid' => $pother1->id,
            'departmentid' => $dother1->id, 'startdate' => $startdate, 'enddate' => strtotime('-1 month')]);

        $jobs = $DB->get_records('tool_organisation_job', ['userid' => $user->id, 'tenantid' => $defaulttenantid]);
        $this->assertCount(5, $jobs);
        $jobs = $DB->get_records('tool_organisation_job', ['userid' => $user->id, 'tenantid' => $tenant2->id]);
        $this->assertEmpty($jobs);

        // Move user to tenant 2.
        $this->tenantgenerator->allocate_user($user->id, $tenant2->id);

        // Check that only one job (the one with shared position and shared department) has been moved to the new tenant.
        $jobs = $DB->get_records('tool_organisation_job', ['userid' => $user->id, 'tenantid' => $tenant2->id]);
        $this->assertCount(1, $jobs);
        $job = reset($jobs);
        $this->assertEquals($jobshared->id, $job->id);
        $this->assertEquals($jobshared->enddate, $job->enddate);
        $this->assertEquals($jobshared->startdate, $job->startdate);

        // Validate current job is ended.
        $timestamp = time();
        $rec = $DB->get_record('tool_organisation_job', ['id' => $job1->id, 'tenantid' => $defaulttenantid]);
        $this->assertTrue($timestamp >= $rec->enddate);

        // Validate future start job is deleted.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $job2->id, 'tenantid' => $defaulttenantid]);
        $this->assertFalse($rec);

        // Validate future end job is ended now.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $job3->id, 'tenantid' => $defaulttenantid]);
        $this->assertTrue($timestamp >= $rec->enddate);

        // Validate past end job remained the same.
        $rec = $DB->get_record('tool_organisation_job', ['id' => $job4->id, 'tenantid' => $defaulttenantid]);
        $this->assertEquals($job4->enddate, $rec->enddate);
    }
}
