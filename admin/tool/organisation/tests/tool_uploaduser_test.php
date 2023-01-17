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

/**
 * File containing test for uploaduser tool integration.
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use advanced_testcase;
use stdClass;
use tool_organisation_generator;
use tool_tenant_generator;
use uu_progress_tracker;

/**
 * Test class
 *
 * @package     tool_organisation
 * @category    test
 * @covers      \tool_organisation\tool_uploaduser
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_uploaduser_test extends advanced_testcase {
    /** @var stdClass tenant */
    protected $tenant;
    /** @var \stdClass tenantadmin detail */
    protected $tenantadmin;
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
    /** @var string */
    protected $trackederror = '';

    /**
     * Load required libraries (upload user progress tracker)
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/uploaduser/locallib.php");
    }

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->create_tenant_organisation_structure();
    }

    /**
     * Test create job in the other tenant no user permission
     */
    public function test_create_job_other_tenant_no_user_permission(): void {
        // Create user in different tenant.
        $tenant = $this->tenantgenerator->create_tenant();
        $user = $this->tenantgenerator->create_user(['tenantid' => $tenant->id]);
        $user->jobposition1 = $this->pos->idnumber;
        $user->jobdepartment1 = $this->dep->idnumber;
        $user->jobstartdate1 = '2020-03-01';
        $user->jobenddate1 = '2030-03-01';

        // Allocate job.
        self::setUser($this->tenantadmin->id);
        tool_uploaduser::process_new_user($user, ['jobposition1'], $this->get_progress_tracker());
        $this->assertEquals(get_string('nopermissions', 'error', get_capability_string('tool/organisation:assignjobs')),
            $this->get_progress_tracker_error());
    }

    /**
     * Test create job in the same tenant no entity permission
     */
    public function test_create_job_same_tenant_no_entity_permission(): void {
        // Create user in different tenant, use shared department and position in different tenant.
        $tenant = $this->tenantgenerator->create_tenant();
        $user = $this->tenantgenerator->create_user(['tenantid' => $tenant->id]);
        $user->jobposition1 = $this->pos->idnumber;
        $user->jobdepartment1 = $this->sdep->idnumber;
        $user->jobstartdate1 = '2020-03-01';
        $user->jobenddate1 = '2030-03-01';

        // Allocate job using position in other tenant.
        self::setAdminUser();
        tool_uploaduser::process_new_user($user, ['jobposition1'], $this->get_progress_tracker());
        $this->assertEquals(get_string('errorinvalidposition', 'tool_organisation'),
            $this->get_progress_tracker_error());

        // Allocate job. Use shared position and department in different tenant.
        $user->jobposition1 = $this->spos->idnumber;
        $user->jobdepartment1 = $this->dep->idnumber;
        tool_uploaduser::process_new_user($user, ['jobposition1'], $this->get_progress_tracker());
        $this->assertEquals(get_string('errorinvaliddepartment', 'tool_organisation'),
            $this->get_progress_tracker_error());
    }

    /**
     * Test create job in the same tenant using invalid date format
     */
    public function test_create_job_same_tenant_invalid_date_format(): void {
        // Allocate job as tenantadmin.
        self::setUser($this->tenantadmin->id);
        $user = $this->tenantgenerator->create_user(['tenantid' => $this->tenant->id]);
        $user->jobposition1 = $this->pos->idnumber;
        $user->jobdepartment1 = $this->dep->idnumber;
        $user->jobstartdate1 = '01/03/2020';
        $user->jobenddate1 = '2030-03-01';

        // Expect invalid startdate.
        tool_uploaduser::process_new_user($user, ['jobposition1'], $this->get_progress_tracker());
        $this->assertEquals(get_string('errorinvalidjobstartdate', 'tool_organisation'),
            $this->get_progress_tracker_error());

        // Expect invalid enddate.
        $user->jobstartdate1 = '2020-03-01';
        $user->jobenddate1 = '1616064554';
        tool_uploaduser::process_new_user($user, ['jobposition1'], $this->get_progress_tracker());
        $this->assertEquals(get_string('errorinvalidjobenddate', 'tool_organisation'),
            $this->get_progress_tracker_error());
    }

    /**
     * Data provider for {{@see test_create_job_same_tenant_dates}}
     *
     * @return array
     */
    public function create_job_dates_provider(): array {
        return [
            'Not set' => [null, null, '', null, 0],
            'Start date set' => ['2020-03-01', null, '', strtotime('2020-03-01'), 0],
            'End date set' => [null, '2030-03-01', '', null, strtotime('2030-03-01')],
            'End date set to past' => [null, '2010-03-01', get_string('errorinvalidenddate', 'tool_organisation')],
            'Enddate before startdate' => ['2030-03-01', '2020-03-01', get_string('errorinvalidenddate', 'tool_organisation')],
        ];
    }

    /**
     * Test create job in the same tenant using various dates
     *
     * @param null|string $jobstartdate
     * @param null|string $jobenddate
     * @param string $error
     * @param int|null $expectedjobstartdate expected startdate (or null if it should be "now")
     * @param int $expectedjobenddate
     *
     * @dataProvider create_job_dates_provider
     */
    public function test_create_job_same_tenant_dates($jobstartdate = null, $jobenddate = null,
            $error = '', ?int $expectedjobstartdate = null, $expectedjobenddate = 0): void {

        $user = $this->tenantgenerator->create_user(['tenantid' => $this->tenant->id]);
        $user->jobposition1 = $this->pos->idnumber;
        $user->jobdepartment1 = $this->dep->idnumber;
        $user->jobstartdate1 = $jobstartdate;
        $user->jobenddate1 = $jobenddate;

        // Allocate job as tenantadmin.
        self::setUser($this->tenantadmin->id);
        tool_uploaduser::process_new_user($user, ['jobposition1'], $this->get_progress_tracker());

        // Validate error.
        $this->assertEquals($error, $this->get_progress_tracker_error());

        // If no error, check what dates have been set.
        if (!$error) {
            $userjobs = organisation::get_user_with_jobs($user->id, null)->get_jobs();
            $this->assertCount(1, $userjobs);
            $userjob = reset($userjobs);
            $this->assertEquals($expectedjobstartdate ?? helper::round_time(time()), $userjob->get('startdate'));
            $this->assertEquals($expectedjobenddate, $userjob->get('enddate'));
        }
    }

    /**
     * Data provider for {{@see test_update_job_same_tenant_dates}}
     *
     * @return array
     */
    public function update_job_dates_provider(): array {
        return [
            'Not set' => [null, null, '', strtotime('2020-03-01'), strtotime('2030-03-01')],
            'Start date changed' => ['2020-05-01', null, '', strtotime('2020-05-01'), strtotime('2030-03-01')],
            'End date changed' => [null, '2030-05-01', '', strtotime('2020-03-01'), strtotime('2030-05-01')],
            'End date set to past' => [null, '2010-03-01', get_string('errorinvalidenddate', 'tool_organisation')],
            'Enddate before startdate' => ['2030-03-01', '2020-03-01', get_string('errorinvalidenddate', 'tool_organisation')],
            'End date unset' => [null, '0', '', strtotime('2020-03-01'), 0],
        ];
    }

    /**
     * Test update job in the same tenant using various dates
     *
     * @param null|string $jobstartdate
     * @param null|string $jobenddate
     * @param string $error
     * @param int $expectedjobstartdate
     * @param int $expectedjobenddate
     *
     * @dataProvider update_job_dates_provider
     */
    public function test_update_job_same_tenant_dates($jobstartdate = null, $jobenddate = null,
            $error = '', $expectedjobstartdate = 0, $expectedjobenddate = 0): void {

        // Assign job to user.
        $user = $this->tenantgenerator->create_user(['tenantid' => $this->tenant->id]);
        $data = [
            'tenantid' => $this->tenant->id,
            'positionid' => $this->pos->id,
            'departmentid' => $this->dep->id,
            'userid' => $user->id,
            'startdate' => strtotime('2020-03-01'),
            'enddate' => strtotime('2030-03-01')
        ];
        $this->orggenerator->assign_job((object) $data);

        // Update job as tenantadmin.
        self::setUser($this->tenantadmin->id);
        $user->jobposition1 = $this->pos->idnumber;
        $user->jobdepartment1 = $this->dep->idnumber;
        $user->jobstartdate1 = $jobstartdate;
        $user->jobenddate1 = $jobenddate;
        tool_uploaduser::process_new_user($user, ['jobposition1'], $this->get_progress_tracker());

        // Validate error.
        $this->assertEquals($error, $this->get_progress_tracker_error());

        // If no error, check what dates have been set.
        if (!$error) {
            $userjobs = organisation::get_user_with_jobs($user->id, null)->get_jobs();
            $this->assertCount(1, $userjobs);
            $userjob = reset($userjobs);
            $this->assertEquals($expectedjobstartdate, $userjob->get('startdate'));
            $this->assertEquals($expectedjobenddate, $userjob->get('enddate'));
        }
    }

    /**
     * Test create job in same tenant
     */
    public function test_create_job_same_tenant(): void {
        // Allocate job as tenantadmin.
        self::setUser($this->tenantadmin->id);
        $user = $this->tenantgenerator->create_user(['tenantid' => $this->tenant->id]);
        $user->jobposition1 = $this->pos->idnumber;
        $user->jobdepartment1 = $this->dep->idnumber;
        $user->jobstartdate1 = '2020-03-01';
        $user->jobenddate1 = '2030-03-01';
        tool_uploaduser::process_new_user($user, ['jobposition1'], $this->get_progress_tracker());

        // Assert the job assignment was created.
        $this->assertEmpty($this->get_progress_tracker_error());
        $userjobs = organisation::get_user_with_jobs($user->id, null)->get_jobs();
        $this->assertCount(1, $userjobs);
        $userjob = reset($userjobs);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(strtotime($user->jobstartdate1), $userjob->get('startdate'));
        $this->assertEquals(strtotime($user->jobenddate1), $userjob->get('enddate'));
    }

    /**
     * Test create job in the tenant as site admin
     */
    public function test_create_job_other_tenant(): void {
        // Allocate job in the tenant as admin.
        self::setAdminUser();
        $user = $this->tenantgenerator->create_user(['tenantid' => $this->tenant->id]);
        $user->jobposition1 = $this->pos->idnumber;
        $user->jobdepartment1 = $this->dep->idnumber;
        $user->jobstartdate1 = '2020-03-01';
        $user->jobenddate1 = '2030-03-01';
        tool_uploaduser::process_new_user($user, ['jobposition1'], $this->get_progress_tracker());

        // Assert the job assignment was created.
        $this->assertEmpty($this->get_progress_tracker_error());
        $userjobs = organisation::get_user_with_jobs($user->id, null)->get_jobs();
        $this->assertCount(1, $userjobs);
        $userjob = reset($userjobs);
        $this->assertEquals($this->dep->id, $userjob->get_department()->get('id'));
        $this->assertEquals($this->pos->id, $userjob->get_position()->get('id'));
        $this->assertEquals(strtotime($user->jobstartdate1), $userjob->get('startdate'));
        $this->assertEquals(strtotime($user->jobenddate1), $userjob->get('enddate'));
    }

    /**
     * Create tenant and organisation structure data
     */
    protected function create_tenant_organisation_structure(): void {
        $this->tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->tenant = $this->tenantgenerator->create_tenant();
        $this->tenantadmin = $this->tenantgenerator->create_user(['tenantid' => $this->tenant->id, 'tenantadmin' => true]);
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

    /**
     * Retrieve progress tracker mocked instance.
     *
     * @return uu_progress_tracker
     */
    protected function get_progress_tracker(): uu_progress_tracker {
        $this->trackederror = '';
        $mock = $this->getMockBuilder(uu_progress_tracker::class)
            ->setMethods(['track'])
            ->getMock();
        $mock->method('track')->will($this->returnCallback(function($col, $error) {
            $this->trackederror = $error;
        }));
        return $mock;
    }

    /**
     * Retrieve progress tracker error.
     *
     * @return string
     */
    protected function get_progress_tracker_error(): string {
        return $this->trackederror;
    }
}
