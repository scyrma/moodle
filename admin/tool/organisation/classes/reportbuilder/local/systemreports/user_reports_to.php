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
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use tool_organisation\{helper, permission};
use tool_organisation\organisation;
use tool_organisation\output\user_with_jobs;
use tool_tenant\tenancy;

/**
 * System report class to shows managers the user reports to
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_reports_to extends system_report {
    /** @var int the type of relation who people has with a given user */
    protected $relationbasefield = null;

    /** @var user_with_jobs the reporting line that connects these people to the given user */
    protected $usermanagedjobs = null;

    /** @var int Default paging limit */
    public const DEFAULT_REPORT_PAGESIZE = 5;

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

        // Let's get the current user with jobs object.
        $usermanagedid = $this->get_parameter('userid', 0, PARAM_INT);
        $this->usermanagedjobs = organisation::get_user_with_jobs($usermanagedid, null);
        $managerparams = [];

        // We need to get all managers of the user.
        $relationalias = database::generate_alias();
        $allmanagerssql = helper::get_user_all_direct_managers_sql("" . $usermanagedid, $relationalias, true);
        $reportuseralias = database::generate_alias();
        $manageduserparam = database::generate_param_name();
        $managerparams[$manageduserparam] = $usermanagedid;
        $this->add_join("JOIN ({$allmanagerssql}) {$reportuseralias}
                                ON {$reportuseralias}.userid = {$useralias}.id
                                 AND {$reportuseralias}.userid <> :{$manageduserparam}", $managerparams);

        // Needed for actions.
        $this->add_base_fields("{$useralias}.id AS managerid, {$relationalias} AS relation, {$reportuseralias}.jobid AS jobid");

        // Check tenant id on users in case they have been moved to another tenant.
        [$join, $where, $params] = tenancy::get_users_sql($useralias, tenancy::get_tenant_id());
        $this->add_join($join);
        $this->add_base_condition_sql($where, $params);

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        if (!\tool_tenant\permission::can_view_inactive_users(tenancy::get_tenant_id())) {
            $this->add_base_condition_sql("{$useralias}.suspended = 0 AND {$useralias}.confirmed = 1");
        }

        $this->add_columns($userentity);
        $this->add_actions();

        $this->set_downloadable(false);
        $this->set_default_per_page(self::DEFAULT_REPORT_PAGESIZE);
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
     * Set the columns for the report.
     * @param user $userentity
     */
    protected function add_columns(user $userentity): void {
        $useralias = $this->get_main_table_alias();
        $this->add_column_from_entity('user:fullnamewithpicture');

        // We need to add a new column to show the manager permissions (include manually assigned).
        $this->add_column((new column(
            'managerpermissions',
            new lang_string('positionpermissions', 'tool_organisation'),
            $userentity->get_entity_name()
        ))
            ->add_fields("{$useralias}.id as managerid, jobid")
            ->set_type(column::TYPE_TEXT)
            ->add_joins($this->get_joins())
            ->add_callback([$this, 'format_manager_permissions'])
            ->set_is_sortable(false));

        $this->set_initial_sort_column('user:fullnamewithpicture', SORT_ASC);
    }

    /**
     * Method used to add format to new permission managers report.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string The formatted manager name with icons.
     */
    public function format_manager_permissions(?string $value, stdClass $row): string {
        global $OUTPUT;
        $rolespermissions = [];

        // This is cached, so it is better than fetching the jobs individually.
        $managerjob = organisation::get_user_with_jobs((int)$row->managerid, null);
        $relevantjobs = $managerjob->get_relevant_manager_jobs($this->usermanagedjobs);
        if (in_array($this->relationbasefield, [helper::GLOBAL_MANAGER, helper::DEPARTMENT_MANAGER])) {
            foreach ($relevantjobs as $relevantjob) {
                if ($relevantjob->get('id') != $row->jobid) {
                    continue;
                }
                $job = $relevantjob;
                break;
            }
            if (empty($job)) {
                return '';
            }
            $position = $job->get_position();
        }

        $permissions = [];
        $ismanual = false;
        switch ($this->relationbasefield) {
            case helper::GLOBAL_MANAGER:
                $permissionsparam = 'globalpermissions';
                $allpermissions = organisation::get_global_manager_permissions();
                $roletitle = get_string('globalmanager', 'tool_organisation');
                $extraclass = 'perm-global-manager';
                $permissiontype = 'globalmanager';
                break;
            case helper::DEPARTMENT_MANAGER:
                $permissionsparam = 'departmentpermissions';
                $allpermissions = organisation::get_department_manager_permissions();
                $roletitle = get_string('departmentmanager', 'tool_organisation');
                $extraclass = 'perm-department-manager';
                $permissiontype = 'departmentmanager';
                break;
            case helper::MANUALLY_ASSIGNED_MANAGER:
                $ismanual = true;
                $allpermissions = organisation::get_manually_assigned_manager_permissions();
                $roletitle = get_string('manuallyassigned', 'tool_organisation');
                $extraclass = 'perm-manually-assigned';
                $permissiontype = 'manuallyassigned';
                break;
            default:
                return '';
        }

        if (!$ismanual) {
            $permissionsindex = $position->get($permissionsparam);
        }
        foreach ($allpermissions as $index => $allpermission) {
            $set = $ismanual
                ? $managerjob->is_manually_assigned_manager($index, (int) $this->usermanagedjobs->get('id'))
                : (($permissionsindex & $index) ? true : false);
            $permissions[] = [
                'title' => $allpermission['title'],
                'icon' => $OUTPUT->render($allpermission['icon']),
                'set' => $set,
            ];
        }
        $rolespermissions[] = [
            'roletitle' => $roletitle,
            'permissions' => $permissions,
            'extraclass' => $extraclass,
            'permissiontype' => $permissiontype,
        ];

        // Let's rewrite the title to the permissions in order to check during automated test.
        array_walk($rolespermissions, function (&$roleperm) {
            array_walk($roleperm['permissions'], function (&$perm) {
                $perm['title'] = get_string(
                    $perm['set'] ? 'withpermission' : 'withoutpermission',
                    'tool_organisation',
                    $perm['title']
                );
            });
        });

        // We add the permissions to the output.
        $permissionsout = implode('', array_map(function ($permission) use ($OUTPUT) {
            return $OUTPUT->render_from_template('tool_organisation/rolespermissions', $permission);
        }, $rolespermissions));

        return $permissionsout;
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        $usermanagedid = $this->usermanagedjobs->get('id');

        // Edit manager, only shows for manually assigned managers.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/settings', '', 'core'),
            [
                'data-action' => 'editmanuallyassignedmanager',
                'data-id' => ':relation',
                'data-userid' => $usermanagedid,
                'data-managerid' => ':managerid',
                'data-title' => 'editmanuallyassignedmanager',
                'data-reportselector' => '.userjobs-section-reportsto',
            ],
            false,
            new lang_string('editmanuallyassignedmanager', 'tool_organisation')
        ))
            ->add_callback(static function (stdClass $row): bool {
                // Check if is manually assigned over current user and if user has the capability.
                return (int)$row->relation == helper::MANUALLY_ASSIGNED_MANAGER && permission::has_assignmanuallymgr_capability();
            }));

        // Un-assign manager, only shows for manually assigned managers.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('delete-o', '', 'tool_wp'),
            [
                'data-action' => 'deletemanuallyassignedmanager',
                'data-userid' => $usermanagedid,
                'data-managerid' => ':managerid',
            ],
            false,
            new lang_string('unassignmanager', 'tool_organisation')
        ))
            ->add_callback(static function (stdClass $row): bool {
                // Check if is manually assigned over current user and if user has the capability.
                return (int)$row->relation == helper::MANUALLY_ASSIGNED_MANAGER && permission::has_assignmanuallymgr_capability();
            }));

        $this->add_action_divider();

        // View jobs and reporting line.
        $this->add_action((new action(
            new moodle_url('/admin/tool/organisation/user.php', ['id' => ':managerid']),
            new pix_icon('briefcase', '', 'tool_wp'),
            [],
            false,
            new lang_string('viewjobsandreporting', 'tool_organisation')
        ))
            ->add_callback(static function (stdClass $row): bool {
                return permission::can_view_user_org_profile((int)$row->managerid);
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
}
