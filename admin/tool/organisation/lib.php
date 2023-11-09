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

/**
 * Plugin callbacks
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use block_myteams\userinfo_section;
use block_myteams\userinfo_section_item;
use core_reportbuilder\local\helpers\database;
use core_user\output\myprofile\category;
use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;
use tool_organisation\department_manager;
use tool_organisation\helper;
use tool_organisation\local\persistent\user_manager;
use tool_organisation\organisation;
use tool_organisation\output\departments_tree;
use tool_organisation\output\positions_tree;
use tool_organisation\output\user_with_jobs;
use tool_organisation\permission;
use tool_organisation\position_manager;
use tool_tenant\tenancy;

/**
 * Display jobs assigned to user in their profile
 *
 * @param tree $tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 * @return void
 */
function tool_organisation_myprofile_navigation(tree $tree, stdClass $user, bool $iscurrentuser, ?stdClass $course) {
    global $OUTPUT, $DB;

    $jobsexport = [];

    // Let's get all active jobs of the current user.
    $userwithjobs = tool_organisation\organisation::get_user_with_jobs($user->id);
    $jobs = $userwithjobs->get_jobs();

    // Verify if user has permission to view jobs.
    if (permission::can_view_user_org_profile($user->id)) {
        $userssalias = database::generate_alias();
        [$tenantjoin, $where, $params] = tenancy::get_users_sql($userssalias, tenancy::get_tenant_id($user->id));

        foreach ($jobs as $job) {
            $jobexport = $job->export($OUTPUT);

            $managerjobs = new user_with_jobs($user, [
                'time' => null,
                'jobs' => [$job],
                'fulluserrecord' => null,
                'ismanuallyassignedmgr' => null,
            ]);

            [$allmanagedsql, $managedparams] = helper::get_all_direct_managed_users_sql($managerjobs);

            $manageduserssalias = database::generate_alias();
            $manageruserparam = database::generate_param_name();
            $managedparams[$manageruserparam] = $user->id;
            $completejoin = "JOIN ({$allmanagedsql}) {$manageduserssalias}
                            ON {$manageduserssalias}.userid = {$userssalias}.id
                            AND {$manageduserssalias}.userid <> :{$manageruserparam}";

            $countreporters = $DB->count_records_sql("SELECT COUNT({$userssalias}.id) FROM {user}
                    {$userssalias} {$completejoin} {$tenantjoin} WHERE {$where}", $managedparams + $params);

            $jobexport->hasreporters = $countreporters > 0;
            $countstring = $countreporters === 1 ? "directreports" : "directreports_plural";
            $jobexport->totalreporters = get_string($countstring, 'tool_organisation', (object) [
                'count' => $countreporters,
                'url' => (new moodle_url("/admin/tool/organisation/user.php", ['id' => $user->id]))->out(false),
            ]);

            // Only list jobs in current tenant unless user can switch tenants.
            if ($jobexport->inusertenant || \tool_tenant\permission::can_switch_tenant()) {
                $jobsexport[] = $jobexport;
            }
        }

        // Let's check if current user is a manually assigned manager over other users.
        $mamalias = database::generate_alias();
        $mamparams = ['managerid' => $user->id];
        $countmam = $DB->count_records_sql("SELECT COUNT(1) FROM {tool_organisation_manual_mgr} {$mamalias}
                    WHERE {$mamalias}.managerid = :managerid", $mamparams);

        // If user is a manually assigned manager and has permission to view jobs, let's display them.
        if ($countmam > 0) {
            $mamdata = new stdClass();
            $mamdata->hasreporters = true;
            $mamdata->positionname = get_string('globalmanager', 'tool_organisation');
            $mamdata->departmentname = false;
            $mamdata->totalreporters = get_string('directreports', 'tool_organisation', (object) [
                'count' => $countmam,
                'url' => (new moodle_url("/admin/tool/organisation/user.php", ['id' => $user->id]))->out(false),
            ]);

            $jobsexport[] = $mamdata;
        }
    }

    if (!empty($jobsexport)) {
        $tree->add_category(new category('jobs', get_string('jobsnumber', 'tool_organisation')));

        // Add islast property to each job record in order to remove the final divider.
        array_walk($jobsexport, function($job) use ($jobsexport) {
            $job->islast = $job === end($jobsexport);
        });
        $content = $OUTPUT->render_from_template('tool_organisation/jobs_list', ['jobs' => array_values($jobsexport)]);
        $tree->add_node(new node('jobs', 'jobslist', null, null, null, $content));
    }

    // If user has managers, let's display them.
    if ($reportstomanagers = helper::get_user_direct_managers($user->id)) {
        array_walk($reportstomanagers, function($manager) use ($OUTPUT, $user, $userwithjobs) {
            $manager->departmentname = '';
            $manageruserjobs = organisation::get_user_with_jobs((int)$manager->id, null);
            $relevantjobs = $manageruserjobs->get_relevant_manager_jobs($userwithjobs);
            // Filter the relevant job for the current user by the relevant job for the manager user.
            if ($relevantjobs && in_array($manager->type, [helper::GLOBAL_MANAGER, helper::DEPARTMENT_MANAGER])) {
                // Set the filter method for each case to get the relevant job.
                $filtermethod = ($manager->type == helper::GLOBAL_MANAGER)
                    ? fn($job) => $job->get_position()->is_global_manager()
                    : fn($job) => $job->get_position()->is_department_manager();
                $relevantjob = array_filter($relevantjobs, $filtermethod);

                // Get the permissions of the relevant job.
                if (count($relevantjob) > 0) {
                    $manager->departmentname = reset($relevantjob)->get_department()->get_formatted_name();
                }
            }

            $manager->userpicture = $OUTPUT->user_picture($manager, ['class' => 'rounded-circle', 'link' => false]);
            $manager->fullname = fullname($manager);
            $manager->urlmessage = new moodle_url('/message/index.php', ['id' => $manager->id]);
        });

        $tree->add_category(new category('reportsto', get_string('reportsto', 'tool_organisation')));

        // Sort managers by fullname.
        core_collator::asort_objects_by_property($reportstomanagers, 'fullname');

        $content = $OUTPUT->render_from_template('tool_organisation/reportsto', ['reportsto' => array_values($reportstomanagers)]);
        $tree->add_node(new node('reportsto', 'reportstolist', null, null, null, $content));
    }
}

/**
 * Get icon mapping for font-awesome.
 */
function tool_organisation_get_fontawesome_icon_map() {
    return [
        'tool_organisation:department' => 'fa-building',
        'tool_organisation:position' => 'fa-user-circle-o',
    ];
}

/**
 * Callback for inplace editable API.
 *
 * @param string $itemtype
 * @param string $itemid
 * @param string $newvalue
 * @return \core\output\inplace_editable|null
 */
function tool_organisation_inplace_editable($itemtype, $itemid, $newvalue) {
    \core_external\external_api::validate_context(context_system::instance());

    // Clean new value according to position/department persistent name field.
    $newvalue = clean_param($newvalue, PARAM_TEXT);

    if ($itemtype === 'department_name') {
        $manager = new \tool_organisation\department_manager();
        permission::require_can_edit_department($manager->get_department($itemid));
        $department = $manager->update_department($itemid, (object)['name' => $newvalue]);
        return $department->get_editable_name();
    }

    if ($itemtype === 'position_name') {
        $manager = new \tool_organisation\position_manager();
        permission::require_can_edit_position($manager->get_position($itemid));
        $position = $manager->update_position($itemid, (object)['name' => $newvalue]);
        return $position->get_editable_name();
    }
}

/**
 * Callback for the tool_wp_potential_users_selector web service.
 *
 * @param string $area
 * @param int $itemid
 * @return null|array
 */
function tool_organisation_potential_users_selector(string $area, int $itemid): ?array {
    global $DB;
    if ($area === 'jobassign' || $area === 'manuallyassigned') {
        if ($area === 'jobassign') {
            permission::require_can_assign_job_to_anybody();
        } else {
            permission::require_can_assign_manually_assigned_manager();
        }

        list($join, $where, $params) = tenancy::get_users_sql('u');

        // If user autocomplete is called from people page, current user and users already assigned as manager/staff
        // to the current user doesn't need to be available for selection.
        if ($area === 'manuallyassigned') {
            // Get all manager/staff assigned manually to the current user.
            $managerparamname = database::generate_param_name();
            $managersids = $DB->get_fieldset_select(user_manager::TABLE, 'managerid', "userid = :{$managerparamname}",
                [$managerparamname => $itemid]);

            $staffparamname = database::generate_param_name();
            $staffids = $DB->get_fieldset_select(user_manager::TABLE, 'userid', "managerid = :{$staffparamname}",
                [$staffparamname => $itemid]);
            $excludedids = array_merge($managersids, $staffids);

            // Add current user to the excluded ids.
            $excludedids[] = $itemid;

            [$excludewhere, $excludeparams] = $DB->get_in_or_equal($excludedids, SQL_PARAMS_NAMED, 'useridsexcluded', false);
            $where .= " AND u.id {$excludewhere}";
            $params = $params + $excludeparams;
        }
        return [$join, $where, $params];
    } else {
        return null;
    }
}

/**
 * Implementation of callback control_view_profile
 *
 * Allows manager to always see profiles of their subordinates
 *
 * @param stdClass $user The other user's details.
 * @param stdClass $course if provided, only check permissions in this course.
 * @param context $usercontext The user context if available.
 * @return int
 */
function tool_organisation_control_view_profile($user, $course, $usercontext) {
    global $USER;
    if ($user->id != $USER->id &&
            ($userwithjobs = \tool_organisation\organisation::get_user_with_jobs()) &&
            $userwithjobs->is_manager_over_user($user->id)) {
        return core_user::VIEWPROFILE_FORCE_ALLOW;
    }
    return core_user::VIEWPROFILE_DO_NOT_PREVENT;
}

/**
 * This callback allows to modify the standard behaviour of web service functions
 *
 * @param stdClass $function
 * @param array $params
 * @return false|mixed
 */
function tool_organisation_override_webservice_execution(stdClass $function, array $params) {

    // Add setting to know if current user is a manager and if we should display the Teams tab in the mobile app.
    if ($function->name === 'core_webservice_get_site_info') {
        $user = organisation::get_user_with_jobs();
        $ismanager = $user && $user->is_manager();

        // Call the original function.
        $result = call_user_func_array([$function->classname, $function->methodname], $params);

        $result['advancedfeatures'][] = [
            'name' => 'wp_teammanagerview',
            'value' => (int)$ismanager,
        ];

        return $result;
    }
    return false;
}

/**
 * Fragment to get the position framework content.
 *
 * @param array $args Fragment arguments
 * @return string
 */
function tool_organisation_output_fragment_position_framework(array $args) : string {
    global $OUTPUT;

    $frameworkid = $args['frameworkid'];
    $manager = new position_manager();
    $position = $manager->get_position_structure($frameworkid);
    permission::require_can_view_position($position);
    $tree = new positions_tree($position);
    $classes = 'card-body bg-white border-0 p-0';
    return \html_writer::div($OUTPUT->render_from_template('tool_wp/table_tree', $tree->export_for_template($OUTPUT)), $classes);
}

/**
 * Fragment to get the department framework content.
 *
 * @param array $args Fragment arguments
 * @return string
 */
function tool_organisation_output_fragment_department_framework(array $args) : string {
    global $OUTPUT;

    $frameworkid = $args['frameworkid'];
    $manager = new department_manager();
    $department = $manager->get_department_structure($frameworkid);
    permission::require_can_view_department($department);
    $tree = new departments_tree($department);
    $classes = 'card-body bg-white border-0 p-0';
    return \html_writer::div($OUTPUT->render_from_template('tool_wp/table_tree', $tree->export_for_template($OUTPUT)), $classes);
}

/**
 * Callback for tool_wp, return list of role shortnames this component defines.
 */
function tool_organisation_workplace_roles() {
    return ['tool_organisation_manager'];
}

/**
 * Callback for theme_workplace, return list of workplace menu items to be added to the launcher.
 *
 * @return array[] The array containing the workplace menu items where each item is an array with keys:
 *                 url => moodle_url where item will redirect
 *                 name => string name shown in the launcher
 *                 imageurl => string url for the icon shown in the launcher
 *                 isglobal (optional) => bool to indicate if item is displayed in the global section.
 */
function tool_organisation_theme_workplace_menu_items(): array {
    global $OUTPUT;

    $menuitems = [];
    if (permission::can_view_index()) {
        $menuitems[] = [
            'url' => new moodle_url("/admin/tool/organisation/index.php"),
            'name' => get_string('orgstructure', 'tool_organisation'),
            'imageurl' => $OUTPUT->image_url('icon', 'tool_organisation')->out(false),
        ];
    }
    return $menuitems;
}

/**
 * Callback for block_myteams, return list of user sections to be added to the MyTeams block report.
 *
 * @param int $userid
 * @return userinfo_section[]
 */
function tool_organisation_block_myteams_user_section(int $userid): array {
    // Get user jobs data from organisation API.
    $user = organisation::get_user_with_jobs($userid);

    // If user doesn't have a job exit quickly.
    if (!$user) {
        return [];
    }

    // Generate the "Jobs" userinfo section.
    $sectionname = get_string('jobsnumber', 'tool_organisation');
    $jobssection = new userinfo_section($sectionname, null, 40);

    // We define same badge type for all.
    $badgetype = 'info';

    // Define string labels.
    $globalmanagerlabel = get_string('globalmanager', 'tool_organisation');
    $departmentmanagerlabel = get_string('departmentmanager', 'tool_organisation');

    // Feed the sections items.
    foreach ($user->get_jobs() as $job) {
        $position = $job->get_position();
        $department = $job->get_department();
        $item = new userinfo_section_item($position->get_formatted_name(), $department->get_formatted_name());

        if ($position->is_global_manager()) {
            $item->add_badge($globalmanagerlabel, $badgetype);
        }

        if ($position->is_department_manager()) {
            $item->add_badge($departmentmanagerlabel, $badgetype);
        }

        $jobssection->add_item($item);
    }

    return [$jobssection];
}
