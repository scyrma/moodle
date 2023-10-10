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

        // Base fields required for action callbacks and checkbox toggle.
        $this->set_checkbox_toggleall(static function(stdClass $row): array {
            return [$row->usermanagedid, get_string('select')];
        });

        // Get all the direct managed users of the current user.
        $managerid = $this->get_parameter('userid', 0, PARAM_INT);
        $this->usermanagerjobs = organisation::get_user_with_jobs($managerid, null);
        [$managedsjoin, $managedparams] = helper::get_all_direct_managed_users_join($this->usermanagerjobs);
        $manageduserssalias = database::generate_alias();

        // Let's join all the managed users relations using union all and add them as a joined table.
        $allmanagedsql = implode(' UNION ALL ', $managedsjoin);
        $manageruserparam = database::generate_param_name();
        $managedparams[$manageruserparam] = $managerid;
        $this->add_join("JOIN ({$allmanagedsql}) {$manageduserssalias}
                            ON {$manageduserssalias}.userid = {$useralias}.id
                            AND {$manageduserssalias}.userid <> :{$manageruserparam}", $managedparams);

        // Add base fields.
        $this->add_base_fields("{$managerid} AS managerid, {$useralias}.id AS usermanagedid,
                                {$manageduserssalias}.manager AS relation");

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
        return permission::can_view_jobs();
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
            ->add_fields("{$useralias}.id AS usermanagedid")
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
            ->add_fields("{$useralias}.id AS usermanagedid")
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
            ->add_fields("{$useralias}.id AS usermanagedid")
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
        $manageduserjobs = organisation::get_user_with_jobs((int)$row->usermanagedid, null);

        switch ($columnname) {
            case 'relevant':
                if ($this->relationbasefield === helper::MANUALLY_ASSIGNED_MANAGER) {
                    return "";
                } else {
                    // Get the relevant job for the current user.
                    $relevantmanagedjobs = $manageduserjobs->get_relevant_jobs($this->usermanagerjobs);
                    if (!$relevantmanagedjobs) {
                        return '';
                    }

                    // Get the relevant job for the manager user.
                    $relevantmanagerjobs = self::get_connected_jobs($manageduserjobs);

                    // Filter the relevant job for the current user by the relevant job for the manager user.
                    $relevantmanagedjobs = array_filter($relevantmanagedjobs, function(job $job) use ($relevantmanagerjobs) {
                        if ($this->relationbasefield === helper::DEPARTMENT_MANAGER) {
                            // If the current user is not a department manager and are in same department, then return the job.
                            $isinsamedepartment = false;
                            foreach ($relevantmanagerjobs as $relevantmanagerjob) {
                                if ($job->get_department()->get('id') === $relevantmanagerjob->get_department()->get('id')) {
                                    $isinsamedepartment = true;
                                    break;
                                }
                            }
                            return !$job->get_position()->is_department_manager() && $isinsamedepartment;
                        } else if ($this->relationbasefield === helper::GLOBAL_MANAGER) {
                            // If the current user is not a global manager, and its position is directly underneath
                            // of the manager position, then return the job.
                            $mgrpaths = [];
                            foreach ($relevantmanagerjobs as $relevantmanagerjob) {
                                $mgrpaths[] = $relevantmanagerjob->get_position()->get('path') . '/'
                                    . $job->get_position()->get('id');
                            }
                            $userpath = $job->get_position()->get('path');
                            return in_array($userpath, $mgrpaths);
                        }
                        return false;
                    });

                    // TODO: WP-4387 - If there are multiple jobs, it will always show the same because of the "reset".
                    $relevantmanagedjobs = reset($relevantmanagedjobs);
                    return $relevantmanagedjobs->get_position()->get('name') . '&nbsp;·&nbsp;' .
                        $relevantmanagedjobs->get_department()->get('name');
                }
            case 'reporting':
                if ($this->relationbasefield === helper::MANUALLY_ASSIGNED_MANAGER) {
                    $detailedpermissions = [];
                    $globalpermissions = organisation::get_manually_assigned_manager_permissions();
                    foreach ($globalpermissions as $index => $globalpermission) {
                        $detailedpermissions[] = [
                            'title' => $globalpermission['title'],
                            'icon' => $OUTPUT->render($globalpermission['icon']),
                            'set' => $this->usermanagerjobs->is_manually_assigned_manager($index,
                                (int)$manageduserjobs->get('id')),
                        ];
                    }
                    $permission = [
                        'roletitle' => get_string('manuallyassignedbadge', 'tool_organisation'),
                        'permissions' => $detailedpermissions,
                        'extraclass' => 'perm-manually-assigned',
                        'permissiontype' => 'manuallyassigned',
                    ];

                    // Let's rewrite the title to the permissions in order to check during automated test.
                    array_walk($permission['permissions'], function(&$perm) {
                        $perm['title'] = get_string($perm['set'] ? 'withpermission' : 'withoutpermission',
                            'tool_organisation', $perm['title']);
                    });

                    return $OUTPUT->render_from_template('tool_organisation/rolespermissions', $permission);
                } else {
                    $relevantmanagerjobs = self::get_connected_job($manageduserjobs);
                    if (!$relevantmanagerjobs) {
                        return '';
                    }

                    // Get the permissions of the relevant job.
                    $permissionsout = html_writer::div($relevantmanagerjobs->get_position()->get('name')
                        . '&nbsp;·&nbsp;' . $relevantmanagerjobs->get_department()->get('name'), 'col-6');
                    $permissions = $relevantmanagerjobs->get_position()->get_node_roles_permissions();
                    // We add the permissions to the output.
                    $count = 0;
                    foreach ($permissions as $permission) {
                        $count++;
                        $extraclass = $count > 1 ? 'offset-md-6' : '';
                        $permission['showpermissionsonly'] = true;
                        $permissionsout .= html_writer::div($OUTPUT->render_from_template('tool_organisation/rolespermissions',
                            $permission), "col-6 {$extraclass}");
                    }

                    return html_writer::div($permissionsout, 'row justify-content-between');
                }
            case 'peoplestartdate':
                if ($this->relationbasefield === helper::MANUALLY_ASSIGNED_MANAGER) {
                    return "";
                } else {
                    $relevantmanagerjobs = self::get_connected_job($manageduserjobs);
                    return $relevantmanagerjobs ? format::jobdate($relevantmanagerjobs->get('startdate')) : '';
                }
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
        )));
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
        $relevantmanagerjobs = array_filter($relevantmanagerjobs, function(job $job) {
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
