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
 * Plugin callbacks
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use core_user\output\myprofile\category;
use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;

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
            ($jobs = tool_organisation\organisation::get_user_with_jobs($user->id, null)->get_jobs())) {
        $tree->add_category(new category('jobs', get_string('jobs', 'tool_organisation')));

        $output = $PAGE->get_renderer('tool_organisation');

        $jobsexport = [];
        foreach ($jobs as $job) {
            $jobsexport[] = $job->export($output);
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
 * @param string $itemtype - Only user_groups is supported.
 * @param string $itemid - Userid and groupid separated by a :
 * @param string $newvalue - json encoded list of groupids.
 * @return \core\output\inplace_editable
 */
function tool_organisation_inplace_editable($itemtype, $itemid, $newvalue) {
    global $CFG;
    require_once($CFG->libdir . '/externallib.php');

    if ($itemtype === 'department_name') {
        \external_api::validate_context(context_system::instance());
        $manager = new \tool_organisation\department_manager();
        \tool_organisation\permission::require_can_edit_department($manager->get_department($itemid));
        $department = $manager->update_department($itemid, (object)['name' => $newvalue]);
        return $department->get_editable_name();
    }

    if ($itemtype === 'position_name') {
        \external_api::validate_context(context_system::instance());
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
