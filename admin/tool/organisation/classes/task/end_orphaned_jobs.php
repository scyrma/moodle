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
 * Appointment ad-hoc task for ending orphaned jobs.
 *
 * @package   tool_organisation
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 Ruslan Kabalin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\task;

use tool_tenant\tenancy;
use tool_organisation\job;
use tool_organisation\job_manager;

/**
 * Ad-hoc task class
 *
 * @package   tool_organisation
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 Ruslan Kabalin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class end_orphaned_jobs extends \core\task\adhoc_task {

    /**
     * Execute the task
     *
     * @return void
     */
    public function execute() {
        global $DB;

        // Get jobs whose tenant does not match its user's tenant.
        // This suggests user moved the tenant.
        $defaulttenantid = tenancy::get_default_tenant_id();
        $sql = "SELECT sq.id
                  FROM (SELECT j.id, j.tenantid AS jobtenantid,
                      COALESCE(t.tenantid, {$defaulttenantid}) AS usertenantid
                          FROM {tool_organisation_job} j
                          LEFT JOIN {tool_tenant_user} t ON (j.userid = t.userid)) sq
                 WHERE sq.jobtenantid != sq.usertenantid";
        $jobrecords = $DB->get_records_sql($sql);

        $manager = new job_manager();
        $endtime = time();
        foreach ($jobrecords as $jobrecord) {
            $job = job::get_record(['id' => $jobrecord->id]);
            if ((int) $job->get('startdate') > $endtime) {
                // Job starts in future, delete.
                $manager->delete_job($jobrecord->id);
                continue;
            }

            // End all active and ended in future jobs.
            if ((int) $job->get('enddate') === 0 || (int) $job->get('enddate') > $endtime) {
                $manager->update_job($jobrecord->id, (object)['enddate' => $endtime]);
            }
        }
    }
}
