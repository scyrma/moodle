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
 * Plugin callbacks
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use core_user\output\myprofile\category;
use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;
use tool_organisation\department_manager;
use tool_organisation\organisation;
use tool_organisation\output\departments_tree;
use tool_organisation\output\positions_tree;
use tool_organisation\position_manager;

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
    global $PAGE;

    if (\tool_organisation\permission::can_view_user_jobs($user) &&
            ($userwithjobs = tool_organisation\organisation::get_user_with_jobs($user->id, null)) &&
            ($jobs = $userwithjobs->get_jobs())) {
        $tree->add_category(new category('jobs', get_string('jobs', 'tool_organisation')));

        $output = $PAGE->get_renderer('tool_organisation');

        $jobsexport = [];
        foreach ($jobs as $job) {
            $jobexport = $job->export($output);
            // Only list jobs in current tenant unless user can switch tenants.
            if ($jobexport->inusertenant || \tool_tenant\permission::can_switch_tenant()) {
                $jobsexport[] = $jobexport;
            }
        }

        // Push ended jobs to bottom of the list.
        core_collator::asort_objects_by_property($jobsexport, 'ended', core_collator::SORT_NUMERIC);

        $content = $output->render_from_template('tool_organisation/jobs_list', ['jobs' => array_values($jobsexport)]);
        $tree->add_node(new node('jobs', 'jobslist', null, null, null, $content));
    }
}

/**
 * Callback for inplace editable API.
 *
 * @param string $itemtype
 * @param string $itemid
 * @param string $newvalue
 * @return \core\output\inplace_editable
 */
function tool_organisation_inplace_editable($itemtype, $itemid, $newvalue) {
    external_api::validate_context(context_system::instance());

    // Clean new value according to position/department persistent name field.
    $newvalue = clean_param($newvalue, PARAM_TEXT);

    if ($itemtype === 'department_name') {
        $manager = new \tool_organisation\department_manager();
        \tool_organisation\permission::require_can_edit_department($manager->get_department($itemid));
        $department = $manager->update_department($itemid, (object)['name' => $newvalue]);
        return $department->get_editable_name();
    }

    if ($itemtype === 'position_name') {
        $manager = new \tool_organisation\position_manager();
        \tool_organisation\permission::require_can_edit_position($manager->get_position($itemid));
        $position = $manager->update_position($itemid, (object)['name' => $newvalue]);
        return $position->get_editable_name();
    }
}

/**
 * Callback for the tool_wp_potential_users_selector web service.
 *
 * @param string $area
 * @return array
 */
function tool_organisation_potential_users_selector(string $area) {
    if ($area !== 'jobassign') {
        return null;
    }
    \tool_organisation\permission::require_can_assign_job_to_anybody();
    list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u');
    return [$join, $where, $params];
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
    \tool_organisation\permission::require_can_view_position($position);
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
    \tool_organisation\permission::require_can_view_department($department);
    $tree = new departments_tree($department);
    $classes = 'card-body bg-white border-0 p-0';
    return \html_writer::div($OUTPUT->render_from_template('tool_wp/table_tree', $tree->export_for_template($OUTPUT)), $classes);
}
