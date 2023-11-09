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

namespace tool_organisation\reportbuilder\local\systemreports;

use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\{action, column};
use core_reportbuilder\system_report;
use html_writer;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use tool_organisation\{helper, organisation, permission};
use tool_organisation\output\{job, user_with_jobs};
use tool_organisation\local\helpers\format;
use tool_tenant\tenancy;

/**
 * System report class to shows people reporting to a user
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_reporting_to extends system_report {

    /** @var int the type of relation who people has with a given user */
    protected $relationbasefield = null;

    /** @var user_with_jobs the reporting line that connects these people to the given user */
    protected $usermanagerjobs = null;

    /**
     * Initialise report
     */
    protected function initialise(): void {

        // Add user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->set_main_table('user', $useralias);

        // Join with tenant user table to get the tenant id.
        $this->add_base_condition_simple("{$useralias}.deleted", 0);
        $this->add_entity($userentity);

        // Get all the direct managed users of the current user and add them as a joined table.
        $managerid = $this->get_parameter('userid', 0, PARAM_INT);
        $this->usermanagerjobs = organisation::get_user_with_jobs($managerid, null);
        [$allmanagedsql, $managedparams] = helper::get_all_direct_managed_users_sql($this->usermanagerjobs);
        $manageduserssalias = database::generate_alias();
        $manageruserparam = database::generate_param_name();
        $managedparams[$manageruserparam] = $managerid;
        $this->add_join("JOIN ({$allmanagedsql}) {$manageduserssalias}
                            ON {$manageduserssalias}.userid = {$useralias}.id
                            AND {$manageduserssalias}.userid <> :{$manageruserparam}", $managedparams);

        // Add base fields.
        $this->add_base_fields("{$managerid} AS managerid, {$useralias}.id AS usermanagedid,
                                {$manageduserssalias}.manager AS relation, {$manageduserssalias}.jobid AS jobid");

        // Check tenant id on users in case they have been moved to another tenant.
        [$join, $where, $params] = tenancy::get_users_sql($useralias, tenancy::get_tenant_id());
        $this->add_join($join);
        $this->add_base_condition_sql($where, $params);

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        if (!\tool_tenant\permission::can_view_inactive_users(tenancy::get_tenant_id())) {
            $this->add_base_condition_sql("{$useralias}.suspended = 0 AND {$useralias}.confirmed = 1");
        }

        $this->add_columns($userentity);
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(false);
        $this->set_default_per_page(10);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        $managedid = $this->get_parameter('userid', 0, PARAM_INT);
        return permission::can_view_user_org_profile($managedid);
    }

    /**
     * Set the filters of the report
     */
    protected function add_filters(): void {
        // TODO: filter not defined yet, only fullname filter is available. Let's tracking WP-4067 for future changes.
        $this->add_filters_from_entities([
            'user:fullname',
        ]);
    }

    /**
     * Set the columns for the report.
     * @param user $userentity
     */
    protected function add_columns(user $userentity): void {
        $useralias = $this->get_main_table_alias();

        $this->add_column_from_entity('user:fullnamewithpicture');

        // Add the relevantjobs column to show the job for which listed people report to the current user.
        $this->add_column((new column(
            'relevantjobs',
            new lang_string('relevantjobs', 'tool_organisation'),
            $userentity->get_entity_name()
        ))
            ->set_title(new lang_string('relevantjobs', 'tool_organisation'))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$useralias}.id AS usermanagedid, jobid")
            ->add_callback([$this, 'set_formatted_job'], 'relevant')
            ->set_is_sortable(false)
        );

        // Add the reportingline column to show the job who connect the listed people with the current user.
        $this->add_column((new column(
            'reportingline',
            new lang_string('reportingline', 'tool_organisation'),
            $userentity->get_entity_name()
        ))
            ->set_title(new lang_string('reportingline', 'tool_organisation'))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$useralias}.id AS usermanagedid, jobid")
            ->add_callback([$this, 'set_formatted_job'], 'reporting')
            ->set_is_sortable(false)
        );

        // Start date.
        $this->add_column((new column(
            'peoplestartdate',
            new lang_string('startdate', 'tool_organisation'),
            $userentity->get_entity_name()
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$useralias}.id AS usermanagedid, jobid")
            ->add_callback([$this, 'set_formatted_job'], 'peoplestartdate')
            ->set_is_sortable(false)
        );
        $this->set_initial_sort_column('user:fullnamewithpicture', SORT_ASC);
    }

    /**
     * Return the correct row data formatted.
     *
     * @param null|string $value
     * @param stdClass $row
     * @param string|null $columnname
     * @return string
     */
    public function set_formatted_job(?string $value, stdClass $row, ?string $columnname): string {
        global $OUTPUT;
        $ismanual = $this->relationbasefield === helper::MANUALLY_ASSIGNED_MANAGER;

        if ($ismanual && $columnname !== 'reporting') {
            return "";
        }

        $manageduserjobs = organisation::get_user_with_jobs((int)$row->usermanagedid, null);

        // Get the relevant job for the current user.
        if (in_array($columnname, ['reporting', 'relevant', 'peoplestartdate']) && !$ismanual) {
            $relevantmanagedjobs = $manageduserjobs->get_relevant_jobs($this->usermanagerjobs);
            if (!$relevantmanagedjobs) {
                return '';
            }
            foreach ($relevantmanagedjobs as $relevantmanagedjob) {
                if ((int) $relevantmanagedjob->get('id') !== (int) $row->jobid) {
                    continue;
                }
                $relevantjob = $relevantmanagedjob;
                break;
            }
            if (!$relevantjob) {
                return '';
            }
        }

        switch ($columnname) {
            case 'relevant':
                $job = (object) [
                    'department' => $relevantjob->get_department()->get('name'),
                    'position' => $relevantjob->get_position()->get('name'),
                    'isactive' => true,
                ];
                $context = ['isuserview' => false, 'jobs' => [$job]];

                return $OUTPUT->render_from_template('tool_organisation/userjobs', $context);
            case 'reporting':
                $permissionsout = '';
                if ($this->relationbasefield !== helper::MANUALLY_ASSIGNED_MANAGER) {
                    $relevantmanagerjobs = self::get_connected_jobs($manageduserjobs);
                    if (!$relevantmanagerjobs) {
                        return '';
                    }

                    foreach ($relevantmanagerjobs as $relevantmanagerjob) {
                        if ($relevantjob->is_job_relevant($relevantmanagerjob)) {
                            $relevantvalidjob = $relevantmanagerjob;
                            break;
                        }
                    }

                    if (!$relevantvalidjob) {
                        return '';
                    }

                    $job = (object) [
                        'department' => $relevantvalidjob->get_department()->get('name'),
                        'position' => $relevantvalidjob->get_position()->get('name'),
                        'isactive' => true,
                    ];
                    $context = ['removeblockclass' => true, 'isuserview' => false, 'jobs' => [$job]];

                    $jobstring = $OUTPUT->render_from_template('tool_organisation/userjobs', $context);

                    // Get the relevant job.
                    $permissionsout = html_writer::div($jobstring, 'col-6');
                }

                // Get the permissions of the relevant job.
                switch ($this->relationbasefield) {
                    case helper::GLOBAL_MANAGER:
                        $permissionsparam = 'globalpermissions';
                        $allpermissions = organisation::get_global_manager_permissions();
                        $roletitle = get_string('globalmanager', 'tool_organisation');
                        $extraclass = 'perm-global-manager';
                        $permissiontype = 'globalmanager';
                        $showpermissionsonly = true;
                        break;
                    case helper::DEPARTMENT_MANAGER:
                        $permissionsparam = 'departmentpermissions';
                        $allpermissions = organisation::get_department_manager_permissions();
                        $roletitle = get_string('departmentmanager', 'tool_organisation');
                        $extraclass = 'perm-department-manager';
                        $permissiontype = 'departmentmanager';
                        $showpermissionsonly = true;
                        break;
                    case helper::MANUALLY_ASSIGNED_MANAGER:
                        $allpermissions = organisation::get_manually_assigned_manager_permissions();
                        $roletitle = get_string('manuallyassignedbadge', 'tool_organisation');
                        $extraclass = 'perm-manually-assigned';
                        $permissiontype = 'manuallyassigned';
                        $showpermissionsonly = false;
                        break;
                    default:
                        return '';
                }

                if (!$ismanual) {
                    $permissionsindex = $relevantvalidjob->get_position()->get($permissionsparam);
                }
                foreach ($allpermissions as $index => $allpermission) {
                    $set = $ismanual
                        ? $this->usermanagerjobs->is_manually_assigned_manager($index, (int) $manageduserjobs->get('id'))
                        : (($permissionsindex & $index) ? true : false);
                    $permissions[] = [
                        'title' => $allpermission['title'],
                        'icon' => $OUTPUT->render($allpermission['icon']),
                        'set' => $set,
                    ];
                }
                $rolespermissions = [
                    'roletitle' => $roletitle,
                    'permissions' => $permissions,
                    'extraclass' => $extraclass,
                    'permissiontype' => $permissiontype,
                    'showpermissionsonly' => $showpermissionsonly,
                ];

                // Let's rewrite the title to the permissions in order to check during automated test.
                array_walk($rolespermissions['permissions'], function (&$perm) {
                    $perm['title'] = get_string(
                        $perm['set'] ? 'withpermission' : 'withoutpermission',
                        'tool_organisation',
                        $perm['title']
                    );
                });

                // We add the permissions to the output.
                if ($ismanual) {
                    $permissionsout .= $OUTPUT->render_from_template('tool_organisation/rolespermissions', $rolespermissions);
                } else {
                    $permissionsout .= html_writer::div(
                        $OUTPUT->render_from_template('tool_organisation/rolespermissions', $rolespermissions),
                        "col-6"
                    );
                    $permissionsout = html_writer::div($permissionsout, 'row justify-content-between');
                }

                return $permissionsout;
            case 'peoplestartdate':
                // Start date of the managed user job.
                return format::jobdate($relevantjob->get('startdate'));
            default:
                return "";
        }
    }

    /**
     * Set the actions icons of the report.
     *
     */
    private function add_actions(): void {
        // Edit assignment.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/settings', '', 'core'),
            [
                'data-action' => 'editmanuallyassignedmanager',
                'data-managerid' => ':managerid',
                'data-id' => ':relation',
                'data-userid' => ':usermanagedid',
                'data-title' => 'editmanuallyassignedmanager',
                'data-reportselector' => '.userjobs-section-peoplereportingto',
            ],
            false,
            new lang_string('editmanuallyassignedmanager', 'tool_organisation')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return (int)$row->relation === helper::MANUALLY_ASSIGNED_MANAGER
                    && permission::has_assignmanuallymgr_capability();
            })
        );

        // Un-assign manager.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('delete-o', '', 'tool_wp'),
            [
                'data-action' => 'deletemanuallyassignedmanager',
                'data-userid' => ':usermanagedid',
                'data-managerid' => ':managerid',
            ],
            false,
            new lang_string('unassignperson', 'tool_organisation')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return (int)$row->relation === helper::MANUALLY_ASSIGNED_MANAGER
                    && permission::has_assignmanuallymgr_capability();
            })
        );

        $this->add_action_divider();

        // View jobs and reporting line.
        $this->add_action((new action(
            new moodle_url('/admin/tool/organisation/user.php', ['id' => ':usermanagedid']),
            new pix_icon('briefcase', '', 'tool_wp'), [],
            false,
            new lang_string('viewjobsandreporting', 'tool_organisation')
        ))->add_callback(static function (stdClass $row): bool {
            return permission::can_view_user_org_profile((int)$row->usermanagedid);
        }));
    }

    /**
     * Set the current row relation base field.
     *
     * @param stdClass $row
     */
    public function row_callback(stdClass $row): void {
        $this->relationbasefield = (int)$row->relation;
    }

    /**
     * Retrieve the first related job between the manager and the managed users.
     *
     * @param user_with_jobs $manageduserjobs
     * @return job|null
     */
    private function get_connected_job(user_with_jobs $manageduserjobs): ?job {
        $relevantmanagerjobs = self::get_connected_jobs($manageduserjobs);
        // TODO: WP-4387 - This might cause showing wrong information if there are multiple jobs (because of the reset).
        return $relevantmanagerjobs ? reset($relevantmanagerjobs) : null;
    }

    /**
     * Retrieve the related jobs between the manager and the managed users.
     *
     * @param user_with_jobs $manageduserjobs
     * @return array
     */
    private function get_connected_jobs(user_with_jobs $manageduserjobs): array {
        $relevantmanagerjobs = $this->usermanagerjobs->get_relevant_manager_jobs($manageduserjobs);
        $relevantmanagerjobs = array_filter($relevantmanagerjobs, function (job $job) {
            if ($this->relationbasefield === helper::DEPARTMENT_MANAGER) {
                return $job->get_position()->is_department_manager();
            } else if ($this->relationbasefield === helper::GLOBAL_MANAGER) {
                return $job->get_position()->is_global_manager();
            }
            return false;
        });

        return $relevantmanagerjobs;
    }
}
