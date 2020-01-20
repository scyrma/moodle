<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Class manager
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use cache;
use tool_organisation\event\job_created;
use tool_organisation\event\job_deleted;
use tool_organisation\event\job_updated;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class manager
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        global $DB;
        $conditions['tenantid'] = tenancy::get_tenant_id();
        if (!$record = $DB->get_record(job::TABLE, $conditions)) {
            throw new \moodle_exception('jobnotfound', 'tool_organisation');
        }
        return new job(0, $record);
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
     * @return job
     * @throws \moodle_exception
     */
    public function create_job(\stdClass $data) : job {
        global $DB;
        if (empty($data->tenantid)) {
            $data->tenantid = tenancy::get_tenant_id();
        } else if ($data->tenantid != tenancy::get_tenant_id()) {
            throw new \moodle_exception('errorcreatingjob', 'tool_organisation');
        }

        // Validate user belongs to the same tenant.
        if (tenancy::get_tenant_id($data->userid) != $data->tenantid) {
            throw new \moodle_exception('errorcreatingjob', 'tool_organisation');
        }

        // Validate department belongs to the same tenant.
        if (!$DB->record_exists(department::TABLE, ['id' => $data->departmentid, 'tenantid' => $data->tenantid])) {
            throw new \moodle_exception('errorcreatingjob', 'tool_organisation');
        }

        // Validate position belongs to the same tenant.
        if (!$DB->record_exists(position::TABLE, ['id' => $data->positionid, 'tenantid' => $data->tenantid])) {
            throw new \moodle_exception('errorcreatingjob', 'tool_organisation');
        }

        // Create a job.
        $entity = new job(0, $data);
        $entity->save();
        job_created::create_from_object($entity)->trigger();
        cache::make('tool_reportbuilder', 'userreports')->purge();
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
     * Check if Jobs tab can be accessed by current user.
     *
     * @return bool
     */
    public static function is_tab_available() {
        $deptforjobs = (new \tool_organisation\department_manager())->has_any_department_for_jobcreate();
        $posforjobs = (new \tool_organisation\position_manager())->has_any_position_for_jobcreate();
        return $deptforjobs && $posforjobs;
    }
}
