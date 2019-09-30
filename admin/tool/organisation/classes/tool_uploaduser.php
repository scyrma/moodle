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
 * Methods for tool_uploaduser
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use tool_tenant\tenancy;
use uu_progress_tracker;

/**
 * Class tool_uploaduser
 *
 * This implements methods to be use on uploading users CSV files.
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        foreach ($filecolumns as $column) {
            if (preg_match('/^jobposition(\d+)$/', $column, $matches)) {
                $i = $matches[1];

                if (empty($user->{'jobposition'.$i}) || empty($user->{'jobdepartment'.$i})) {
                    continue;
                }

                $tenantid = \tool_tenant\tenancy::get_tenant_id($user->id);

                // Current user must be in the same tenant as the user as must have capability to assign jobs.
                $context = \context_system::instance();
                if (!permission::can_assign_job_to_user($user->id)) {
                    $error = get_string('nopermissions', 'error', get_capability_string('tool/organisation:assignjobs'));
                    $upt->track('tool_wp', $error, 'error');
                    continue;
                }

                $conditions = [
                    'idnumber' => $user->{'jobposition'.$i},
                    'tenantid' => $tenantid
                ];
                if (!$position = position::get_record($conditions)) {
                    $upt->track('tool_wp', get_string('errorinvalidposition', 'tool_organisation'), 'error');
                    continue;
                }

                $conditions = [
                    'idnumber' => $user->{'jobdepartment'.$i},
                    'tenantid' => $tenantid
                ];
                if (!$department = department::get_record($conditions)) {
                    $upt->track('tool_wp', get_string('errorinvaliddepartment', 'tool_organisation'), 'error');
                    continue;
                }

                $params = [
                    'userid' => $user->id,
                    'positionid' => $position->get('id'),
                    'departmentid' => $department->get('id'),
                    'tenantid' => $tenantid
                ];
                if ($job = job::get_record($params)) {
                    if (!empty($user->{'jobstartdate'.$i})) {
                        if (!self::validate_date($user->{'jobstartdate'.$i})) {
                            $upt->track('tool_wp', get_string('errorinvalidjobstartdate', 'tool_organisation'), 'error');
                            continue;
                        }
                        $job->set('startdate', strtotime($user->{'jobstartdate'.$i}));
                    }
                    if (!empty($user->{'jobenddate'.$i})) {
                        if (!self::validate_date($user->{'jobenddate'.$i})) {
                            $upt->track('tool_wp', get_string('errorinvalidjobenddate', 'tool_organisation'), 'error');
                            continue;
                        }
                        $job->set('enddate', strtotime($user->{'jobenddate'.$i}));
                    }
                    $job->save();
                } else {
                    if (empty($user->{'jobstartdate'.$i})) {
                        $params['startdate'] = strtotime('today');
                    } else {
                        if (!self::validate_date($user->{'jobstartdate'.$i})) {
                            $upt->track('tool_wp', get_string('errorinvalidjobstartdate', 'tool_organisation'), 'error');
                            continue;
                        }
                        $params['startdate'] = strtotime($user->{'jobstartdate'.$i});
                    }
                    if (empty($user->{'jobenddate'.$i})) {
                        $params['enddate'] = 0;
                    } else {
                        if (!self::validate_date($user->{'jobenddate'.$i})) {
                            $upt->track('tool_wp', get_string('errorinvalidjobenddate', 'tool_organisation'), 'error');
                            continue;
                        }
                        $params['enddate'] = strtotime($user->{'jobenddate'.$i});
                    }
                    $manager = new job_manager();
                    $manager->create_job((object)$params);
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
