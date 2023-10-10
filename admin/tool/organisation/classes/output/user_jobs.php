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

declare(strict_types=1);

namespace tool_organisation\output;

use context_system;
use core_reportbuilder\local\filters\select;
use renderer_base;
use stdClass;
use tool_tenant\system_report_factory;
use tool_organisation\reportbuilder\local\systemreports\{user_jobs_assigned, user_reporting_to, user_reports_to};

/**
 * User jobs and reporting lines output class
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_jobs implements \templatable, \renderable {

    /**
     * Class constructor
     *
     * @param stdClass $user
     */
    public function __construct(protected stdClass $user) {
    }

    /**
     * Export report data suitable for a template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {

        // Create the jobs assigned report, set the filter values to the current user.
        $jobsassignedreport = system_report_factory::create(user_jobs_assigned::class, ['userid' => $this->user->id]);
        // Create the reports to report, sending the user id as a parameter.
        $reportstoreport = system_report_factory::create(user_reports_to::class, ['userid' => $this->user->id]);
        // Create the reporting to report, sending the user id as a parameter.
        $reportingtoreport = system_report_factory::create(user_reporting_to::class, ['userid' => $this->user->id]);

        // Set the attributes for the assign job button.
        $reportselectors['jobsassigned'] = '.userjobs-section-jobsassigned';
        $reportselectors['reportsto'] = '.userjobs-section-reportsto';
        $reportselectors['peoplereportingto'] = '.userjobs-section-peoplereportingto';
        $assignjobbuttonattrib = [
            ['name' => 'data-action', 'value' => 'addjob'],
            ['name' => 'data-reportselector', 'value' => implode(',', $reportselectors)],
            ['name' => 'data-userid', 'value' => $this->user->id],
            ['name' => 'data-fullusername', 'value' => fullname($this->user)],
        ];

        // Set the attributes for the manually assigned manager button.
        $assignmanagerbuttonattrib = [
            ['name' => 'data-action', 'value' => 'newmanuallyassignedmanager'],
            ['name' => 'data-title', 'value' => 'assignmanager'],
            ['name' => 'data-htmltext', 'value' => 'addmanagerusers'],
            ['name' => 'data-reportselector', 'value' => '.userjobs-section-reportsto'],
            ['name' => 'data-userid', 'value' => $this->user->id],
            ['name' => 'data-fullusername', 'value' => fullname($this->user)],
        ];

        // Set the attributes for the manually assigned managed button.
        $assignmanagedbuttonattrib = [
            ['name' => 'data-action', 'value' => 'newmanuallyassignedmanager'],
            ['name' => 'data-title', 'value' => 'assignstaff'],
            ['name' => 'data-htmltext', 'value' => 'addmanagedusers'],
            ['name' => 'data-reportselector', 'value' => '.userjobs-section-peoplereportingto'],
            ['name' => 'data-userid', 'value' => $this->user->id],
            ['name' => 'data-fullusername', 'value' => fullname($this->user)],
        ];

        // Check if the user has any department or position to create jobs.
        $deptforjobs = (new \tool_organisation\department_manager())->has_any_department_for_jobcreate();
        $posforjobs = (new \tool_organisation\position_manager())->has_any_position_for_jobcreate();
        $assignjobbuttonattrib[] = ['name' => 'data-cancreatejobs', 'value' => ($deptforjobs && $posforjobs) ? 1 : 0];

        return (object) [
            'jobsassignedreport' => $jobsassignedreport->output(),
            'reportstoreport' => $reportstoreport->output(),
            'reportingtoreport' => $reportingtoreport->output(),
            'assignjobbuttonattrib' => $assignjobbuttonattrib,
            'assignmanagerbuttonattrib' => $assignmanagerbuttonattrib,
            'assignmanagedbuttonattrib' => $assignmanagedbuttonattrib,
            'user' => $this->user,
            'systemcontextid' => context_system::instance()->id,
        ];
    }
}
