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
 * Methods for tool_uploaduser
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis <daniel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

defined('MOODLE_INTERNAL') || die();

use uu_progress_tracker;
use tool_tenant\tenancy;

/**
 * Class tool_uploaduser
 *
 * This implements methods to be use on uploading users CSV files.
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis <daniel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_uploaduser {

    /**
     * Callback for tool_uploaduser that process newly created of user.
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     */
    public static function process_new_user($user, $filecolumns, $upt) {
        $jobmanager = new job_manager();
        foreach ($filecolumns as $column) {
            if (preg_match('/^jobposition(\d+)$/', $column, $matches)) {
                $i = $matches[1];

                if (empty($user->{'jobposition'.$i}) || empty($user->{'jobdepartment'.$i})) {
                    // Skip if no position or department specified.
                    continue;
                }

                // Current user must be able to assign job to user.
                if (!permission::can_assign_job_to_user($user->id)) {
                    $error = get_string('nopermissions', 'error', get_capability_string('tool/organisation:assignjobs'));
                    $upt->track('tool_wp', $error, 'error');
                    continue;
                }

                // Validate position belongs to the same tenant as user or a parent tenant.
                $position = position::get_record(['idnumber' => $user->{'jobposition'.$i}]);
                if (!$position || !permission::can_access_entity($position, $user->id)) {
                    $upt->track('tool_wp', get_string('errorinvalidposition', 'tool_organisation'), 'error');
                    continue;
                }

                // Validate department belongs to the same tenant as user or a parent tenant.
                $department = department::get_record(['idnumber' => $user->{'jobdepartment'.$i}]);
                if (!$department || !permission::can_access_entity($department, $user->id)) {
                    $upt->track('tool_wp', get_string('errorinvaliddepartment', 'tool_organisation'), 'error');
                    continue;
                }

                // Validate startdate format.
                if (!empty($user->{'jobstartdate'.$i}) && !self::validate_date($user->{'jobstartdate'.$i})) {
                    $upt->track('tool_wp', get_string('errorinvalidjobstartdate', 'tool_organisation'), 'error');
                    continue;
                }

                // Validate enddate format.
                if (!empty($user->{'jobenddate'.$i}) && !self::validate_date($user->{'jobenddate'.$i})) {
                    $upt->track('tool_wp', get_string('errorinvalidjobenddate', 'tool_organisation'), 'error');
                    continue;
                }

                $params = [
                    'userid' => $user->id,
                    'positionid' => $position->get('id'),
                    'departmentid' => $department->get('id'),
                    'tenantid' => tenancy::get_tenant_id($user->id),
                ];

                if ($jobs = job::get_records($params, 'startdate', 'desc', 0, 1)) {
                    // Updating existing job. If there are more than one job allocations, pick the latest.
                    $job = reset($jobs);

                    $startdate = $job->get('startdate');
                    if (!empty($user->{'jobstartdate'.$i})) {
                        $startdate = helper::round_time(strtotime($user->{'jobstartdate'.$i}));
                    }

                    $enddate = $job->get('enddate');
                    if (isset($user->{'jobenddate'.$i}) && strlen($user->{'jobenddate'.$i})) {
                        if ($user->{'jobenddate'.$i} === '0') {
                            // Clear the end date.
                            $enddate = 0;
                        } else {
                            $enddate = helper::round_time(strtotime($user->{'jobenddate'.$i}));
                        }
                    }
                    // Validate if startdate is not after the enddate.
                    if ($enddate && $enddate < $startdate) {
                        $upt->track('tool_wp', get_string('errorinvalidenddate', 'tool_organisation'), 'error');
                        continue;
                    }
                    // Update the job.
                    $jobmanager->update_job($job->get('id'), (object) ['startdate' => $startdate, 'enddate' => $enddate]);
                } else {
                    // Creating new job.
                    $params['startdate'] = helper::round_time(time());
                    if (!empty($user->{'jobstartdate'.$i})) {
                        $params['startdate'] = helper::round_time(strtotime($user->{'jobstartdate'.$i}));
                    }
                    $params['enddate'] = 0;
                    if (!empty($user->{'jobenddate'.$i})) {
                        $params['enddate'] = helper::round_time(strtotime($user->{'jobenddate'.$i}));
                    }
                    // Validate if startdate is not after the enddate.
                    if ($params['enddate'] && $params['enddate'] < $params['startdate']) {
                        $upt->track('tool_wp', get_string('errorinvalidenddate', 'tool_organisation'), 'error');
                        continue;
                    }
                    // Create the job.
                    $jobmanager->create_job((object)$params, false);
                }
            }
        }
    }

    /**
     * Callback for tool_uploaduser that process updated user.
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     */
    public static function process_updated_user($user, $filecolumns, $upt) {
        self::process_new_user($user, $filecolumns, $upt);
    }

    /**
     * Check if date is in the expected 'Y-m-d' format.
     *
     * @param string $date
     * @return bool
     */
    private static function validate_date(string $date) : bool {
        $format = 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
