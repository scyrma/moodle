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
 * Plugin callbacks.
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;

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
        // Clean new value according to persistent field type.
        $newvalue = clean_param($newvalue, PARAM_TEXT);
        $manager = new \tool_tenant\manager();
        $tenant = $manager->update_tenant($itemid, (object)['name' => $newvalue]);
        return $tenant->get_editable_name();
    }

    if (preg_match('/^auth_(.+)$/', $itemtype, $matches)) {
        \external_api::validate_context(context_system::instance());
        // We are editing settings in site administration. Make sure we do not apply any modifications to any tenants.
        $auth = $matches[1];
        $newvalue = clean_param($newvalue, PARAM_INT);
        $tenantid = $itemid;
        if ($tenantid) {
            \tool_tenant\permission::require_can_edit_tenant_auth_settings($tenantid);
            return \tool_tenant\auth_manager::change_tenant_auth_status($auth, $tenantid, $newvalue);
        } else {
            require_capability('moodle/site:config', \context_system::instance());
            return \tool_tenant\auth_manager::change_default_auth_status($auth, $newvalue);
        }
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
    if ($filearea === 'headerlogo' || $filearea === 'loginlogo' || $filearea === 'tenantselectorlogo' ||
            $filearea === 'loginbackground' || $filearea === 'favicon') {

        $relativepath = implode('/', $args);
        $fullpath = '/' . $context->id . '/tool_tenant/' . $filearea . '/' . $relativepath;

        $fs = get_file_storage();
        if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
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
    global $SITE, $COURSE, $CFG, $FULLME;
    if (during_initial_install() || isset($CFG->upgraderunning) || defined('BEHAT_TEST')) {
        return;
    }

    // Prepare the current tenant.
    try {
        $tenantid = \tool_tenant\tenancy::get_tenant_id();
    } catch (\Exception $e) {
        // We are probably inside the plugin installation.
        return;
    }

    // Some authentication pages can detect the user's tenant before login.

    if ($CFG->wwwroot.'/'.$CFG->admin.'/settings.php' === strip_querystring($FULLME) ||
            $CFG->wwwroot.'/'.$CFG->admin.'/category.php' === strip_querystring($FULLME) ||
            $CFG->wwwroot.'/'.$CFG->admin.'/search.php' === strip_querystring($FULLME) ||
            $CFG->wwwroot.'/'.$CFG->admin.'/auth.php' === strip_querystring($FULLME) ||
            $CFG->wwwroot.'/'.$CFG->admin.'/auth_config.php' === strip_querystring($FULLME)) {
        // For admin settings pages always ignore the tenant in the config values.
        $tenantid = 0;
    } else if ($authtenantid = \tool_tenant\auth_manager::detect_tenant_on_auth_page()) {
        $tenantid = $authtenantid;
        \tool_tenant\manager::set_tenant_cookie($tenantid);
    } else if ($tenantid != \tool_tenant\manager::get_tenant_cookie()) {
        \tool_tenant\manager::set_tenant_cookie($tenantid);
    }
    \tool_tenant\config::push_for_tenant($tenantid);

    // Add a warning on the core page "Add a new user" if the user limit is reached.

    if ($CFG->wwwroot.'/user/editadvanced.php' === strip_querystring($FULLME)) {
        $id = optional_param('id', 0, PARAM_INT);
        if ($id == -1 && !\tool_tenant\permission::check_quotas_to_add_users($tenantid, 1)) {
            \core\notification::error(get_string('userslimitreached', 'tool_tenant'));
        }
    }
}
/**
 * Hook called to prevent deleting of a category associated with a tenant.
 *
 * @param core_course_category $category The category record.
 * @return bool
 */
function tool_tenant_can_course_category_delete(core_course_category $category) {
    // Is the category associated with a tenant. If so, cannot delete.
    return empty(\tool_tenant\tenancy::find_tenant_by_category_id($category->id));

}

/**
 * Hook called to prevent moving of a category associated with a tenant.
 *
 * @param core_course_category $category
 * @param core_course_category $newcategory
 * @return bool
 */
function tool_tenant_can_course_category_delete_move(core_course_category $category, core_course_category $newcategory) {
    // Is the category associated with a tenant. If so, cannot move contents.
    return empty(\tool_tenant\tenancy::find_tenant_by_category_id($category->id));

}

/**
 * Hook called to display the tenant name in the course category contents list.
 *
 * @param core_course_category $category
 * @return string
 */
function tool_tenant_get_course_category_contents(core_course_category $category) {
    return ($tenant = \tool_tenant\tenancy::find_tenant_by_category_id($category->id)) ? $tenant->get_formatted_name() : '';
}

/**
 * My Profile API hook for tenant name.
 *
 * @param tree $tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 */
function tool_tenant_myprofile_navigation(tree $tree, stdClass $user, bool $iscurrentuser, ?stdClass $course) {
    // Only people with ability to switch tenant can see this.
    if (\tool_tenant\permission::can_switch_tenant()) {
        $tentantid = \tool_tenant\tenancy::get_actual_tenant_id($user->id);
        $content = \tool_tenant\tenancy::get_tenant_name_from_id($tentantid);
        $tree->add_node(new node('contact', 'tenantname', get_string('tenant', 'tool_tenant'), null, null, $content));
    }
}

/**
 * Implementation of the hook post_signup_request
 *
 * If user signs up (auth_email, for example), set the future user tenant as the current tenant, where
 * current tenant was obtained from query parameter or a cookie
 *
 * @param stdClass $user
 */
function tool_tenant_post_signup_requests(stdClass $user) {
    \tool_tenant\manager::preallocate_new_user($user, \tool_tenant\tenancy::get_tenant_id(), 'tool_tenant', 'Sign up');
}

/**
 * Get icon mapping for font-awesome.
 */
function tool_tenant_get_fontawesome_icon_map() {
    return [
        'tool_tenant:sitemap' => 'fa-sitemap',
    ];
}

/**
 * Display tenant selector on the login page
 *
 * @return string
 */
function tool_tenant_standard_footer_html() {
    global $OUTPUT, $PAGE;
    if ((!isloggedin() || isguestuser()) && in_array($PAGE->pagetype, ['login-index', 'login-signup'])) {
        $tenantmanager = new \tool_tenant\manager();
        if (!empty($tenantmanager->get_login_selector_tenants())) {
            return $OUTPUT->render_from_template('tool_tenant/login_tenant_selector', []);
        }
    }
    return '';
}

/**
 * Callback for tool_wp, return list of role shortnames this component defines.
 */
function tool_tenant_workplace_roles() {
    return ['tool_tenant_manager', 'tool_tenant_admin', 'tool_tenant_user'];
}
