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
 * Plugin callbacks.
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Callback for inplace editable API.
 *
 * @param string $itemtype - Only user_groups is supported.
 * @param string $itemid - Userid and groupid separated by a :
 * @param string $newvalue - json encoded list of groupids.
 * @return \core\output\inplace_editable
 */
function tool_tenant_inplace_editable($itemtype, $itemid, $newvalue) {
    global $CFG;
    require_once($CFG->libdir . '/externallib.php');

    if ($itemtype === 'tenant_name') {
        \external_api::validate_context(context_system::instance());
        \tool_tenant\permission::require_can_edit_tenant($itemid);
        $manager = new \tool_tenant\manager();
        $tenant = $manager->update_tenant($itemid, (object)['name' => $newvalue]);
        return $tenant->get_editable_name();
    }
}

/**
 * Callback for the tool_wp_potential_users_selector web service
 *
 * @param string $area
 * @param int $itemid
 * @return array
 */
function tool_tenant_potential_users_selector($area, $itemid) {
    if ($area !== 'tenantadmin') {
        return null;
    }
    \tool_tenant\permission::require_can_edit_tenant($itemid);

    list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u', $itemid);
    return [$join, $where, $params];
}

/**
 * Serves files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool|null false if file not found, does not return anything if found - just send the file
 */
function tool_tenant_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG;

    require_once($CFG->libdir . '/filelib.php');

    // We are positioning the elements.
    if ($filearea === 'headerlogo' || $filearea === 'loginlogo' || $filearea === 'loginbackground' || $filearea === 'favicon') {

        $relativepath = implode('/', $args);
        $fullpath = '/' . $context->id . '/tool_tenant/' . $filearea . '/' . $relativepath;

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        send_stored_file($file, 0, 0, $forcedownload);
    }
}

/**
 * Implementation of callback control_view_profile
 *
 * Prevents users from different tenants to view each others profiles
 *
 * @param stdClass $user The other user's details.
 * @param stdClass $course if provided, only check permissions in this course.
 * @param context $usercontext The user context if available.
 * @return int
 */
function tool_tenant_control_view_profile($user, $course, $usercontext) {
    global $USER;
    if (\tool_tenant\permission::can_view_tenants_list()) {
        // Always allow to view user profiles.
        return core_user::VIEWPROFILE_FORCE_ALLOW;
    }
    $tenantid = \tool_tenant\tenancy::get_tenant_id($user->id);
    if ($user->id != $USER->id &&
            \tool_tenant\tenancy::get_tenant_id() != $tenantid) {
        return core_user::VIEWPROFILE_PREVENT;
    }
    if (\tool_tenant\permission::can_browse_users($tenantid)) {
        // This is a tenant admin. Always allow to view user profiles.
        return core_user::VIEWPROFILE_FORCE_ALLOW;
    }
    return core_user::VIEWPROFILE_DO_NOT_PREVENT;
}

/**
 * Callback executed from setup.php, available from Moodle 3.8 in core
 */
function tool_tenant_after_config() {
    global $SITE, $COURSE, $CFG;
    if (during_initial_install() || isset($CFG->upgraderunning)) {
        return;
    }

    // Prepare the current tenant.
    try {
        $tenantid = \tool_tenant\tenancy::get_tenant_id();
    } catch (\Exception $e) {
        // We are probably inside the plugin installation.
        return;
    }
    if ($tenantid != \tool_tenant\manager::get_tenant_cookie()) {
        \tool_tenant\manager::set_tenant_cookie($tenantid);
    }
    if (isset($SITE)) {
        $tenants = \tool_tenant\tenancy::get_tenants();
        $tenant = $tenants[$tenantid];
        $SITE->fullname = $tenant->sitename ?: $SITE->fullname;
        $SITE->shortname = $tenant->siteshortname ?: $SITE->shortname;

        if (isset($COURSE->id) && $COURSE->id == $SITE->id) {
            $COURSE->fullname = $tenant->sitename ?: $SITE->fullname;
            $COURSE->shortname = $tenant->siteshortname ?: $SITE->shortname;
        }
    }
}
