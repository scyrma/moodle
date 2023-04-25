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
 * Class manager
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use cache;
use tool_organisation\event\job_created;
use tool_organisation\event\job_deleted;
use tool_organisation\event\job_updated;
use tool_tenant\tenancy;

/**
 * Class manager
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class job_manager {

    /**
     * Returns an instance of a job
     *
     * @param array $conditions
     * @return job
     * @throws \moodle_exception
     */
    public function get_job(array $conditions) : job {
        $record = job::get_record($conditions);
        if (!$record || !\tool_tenant\permission::can_access_tenant($record->get('tenantid'))) {
            throw new \moodle_exception('jobnotfound', 'tool_organisation');
        }
        return $record;
    }


    /**
     * Updates a job (only start/end dates)
     *
     * @param int $jobid
     * @param \stdClass $newdata
     * @return job
     */
    public function update_job(int $jobid, \stdClass $newdata) : job {
        $entity = $this->get_job(['id' => $jobid]);
        $oldrecord = $entity->to_record();
        $haschanges = false;
        foreach ($newdata as $key => $value) {
            if (($key === 'startdate' || $key === 'enddate') && (int)$value != (int)$entity->get($key)) {
                $entity->set($key, $value);
                $haschanges = true;
            }
        }
        if ($haschanges) {
            // Validate if startdate is not after the enddate.
            if ($entity->get('enddate') && $entity->get('enddate') < $entity->get('startdate')) {
                throw new \moodle_exception('errorinvalidenddate', 'tool_organisation');
            }
            $entity->save();
            job_updated::create_from_object($entity, $oldrecord)->trigger();
            cache::make('tool_reportbuilder', 'userreports')->purge();
        }

        return $entity;
    }

    /**
     * Creates a new job
     *
     * @param \stdClass $data
     * @param bool $currenttenantonly only allow to create in the current tenant (default: true). Can be set to false
     *     from unittests and imports
     * @return job
     * @throws \moodle_exception
     */
    public function create_job(\stdClass $data, bool $currenttenantonly = true) : job {
        if (empty($data->tenantid)) {
            $data->tenantid = tenancy::get_tenant_id();
        } else if ($data->tenantid != tenancy::get_tenant_id() && $currenttenantonly) {
            throw new \moodle_exception('errorcreatingjob', 'tool_organisation');
        }

        // Validate user belongs to the same tenant.
        if (tenancy::get_tenant_id($data->userid) != $data->tenantid) {
            throw new \moodle_exception('errorcreatingjob', 'tool_organisation');
        }

        // Validate department belongs to the same tenant or a parent tenant and is shared.
        $department = department::get_record(['id' => $data->departmentid]);
        if (!permission::can_access_entity($department, $data->userid)) {
            throw new \moodle_exception('errorcreatingjob', 'tool_organisation');
        }

        // Validate position belongs to the same tenant or a parent tenant and is shared.
        $position = position::get_record(['id' => $data->positionid]);
        if (!permission::can_access_entity($position, $data->userid)) {
            throw new \moodle_exception('errorcreatingjob', 'tool_organisation');
        }

        // Validate if startdate is not after the enddate.
        if (!empty($data->enddate) && $data->enddate < $data->startdate) {
            throw new \moodle_exception('errorinvalidenddate', 'tool_organisation');
        }

        // Create a job.
        $entity = new job(0, $data);
        $entity->save();
        job_created::create_from_object($entity)->trigger();
        cache::make('tool_reportbuilder', 'userreports')->purge();
        cache::make('tool_organisation', 'myjob')->purge();
        return $entity;
    }

    /**
     * URL to view job
     *
     * @return \moodle_url
     */
    public static function get_job_url() : \moodle_url {
        return new \moodle_url('/admin/tool/organisation/index.php', [], 'jobs');
    }

    /**
     * Delete a job
     *
     * @param int $jobid
     */
    public function delete_job(int $jobid) {
        $entity = $this->get_job(['id' => $jobid]);
        $event = job_deleted::create_from_object($entity);
        if ($entity->delete()) {
            $event->trigger();
            cache::make('tool_reportbuilder', 'userreports')->purge();
        }
    }

    /**
     * Delete all jobs for given user.
     *
     * @param int $userid
     */
    public static function delete_user_jobs(int $userid) {
        global $DB;
        return $DB->delete_records(job::TABLE, ['userid' => $userid]);
    }

    /**
     * Delete all jobs in the given tenant
     *
     * @param int $tenantid
     * @return bool
     */
    public static function delete_all_jobs_for_tenant(int $tenantid) {
        global $DB;
        return $DB->delete_records(job::TABLE, ['tenantid' => $tenantid]);
    }

    /**
     * Check if Jobs tab can be accessed by current user.
     *
     * @return bool
     */
    public static function is_tab_available() {
        $deptforjobs = (new \tool_organisation\department_manager())->has_any_department_for_jobcreate();
        $posforjobs = (new \tool_organisation\position_manager())->has_any_position_for_jobcreate();
        return $deptforjobs && $posforjobs;
    }

    /**
     * Move jobs for the given user to new tenant
     *
     * If both, department AND position, belong to new tenant or parents of new tenant,
     * move job also, otherwise end the job.
     *
     * @param int $userid
     * @param int $oldtenantid
     * @param int $newtenantid
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     */
    public static function move_jobs_to_new_tenant(int $userid, int $oldtenantid, int $newtenantid): void {
        // Get user jobs.
        // We can't use get_user_with_jobs here, as user is in new tenant already, but jobs are at old.
        if (!$jobs = job::get_records(['userid' => $userid, 'tenantid' => $oldtenantid])) {
            return;
        }

        // Move jobs.
        $jobstoend = [];
        foreach ($jobs as $job) {
            // Validate if we can access department as new tenant.
            $department = department::get_record(['id' => $job->get('departmentid')]);
            if (!\tool_tenant\hierarchy::is_own_or_parent_shared_entity(
                    $department->get('tenantid'), $department->get('shared'), $newtenantid)) {
                // Department is not accessible from new tenant, end this job.
                $jobstoend[] = $job;
                continue;
            }

            // Validate if we can access position as new tenant.
            $position = position::get_record(['id' => $job->get('positionid')]);
            if (!\tool_tenant\hierarchy::is_own_or_parent_shared_entity(
                    $position->get('tenantid'), $position->get('shared'), $newtenantid)) {
                // Position is not accessible from new tenant, end this job.
                $jobstoend[] = $job;
                continue;
            }

            // Update job tenant to new tenant.
            $oldrecord = $job->to_record();
            $job->set('tenantid', $newtenantid);
            $job->update();
            job_updated::create_from_object($job, $oldrecord)->trigger();
        }

        // End jobs that can't be moved safely.
        $enddate = time();
        $manager = new self();
        foreach ($jobstoend as $job) {
            // If job starts in future, delete it.
            if ((int) $job->get('startdate') > $enddate) {
                $manager->delete_job($job->get('id'));
                continue;
            }
            // End all active and ended in future jobs.
            if ((int) $job->get('enddate') === 0 || (int) $job->get('enddate') > $enddate) {
                $manager->update_job($job->get('id'), (object)['enddate' => $enddate]);
            }
        }
    }
}
