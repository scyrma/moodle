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
 * Class manager.
 *
 * @package     tool_tenant
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant;

use tool_tenant\event\tenant_created;
use tool_tenant\event\tenant_deleted;
use tool_tenant\event\tenant_updated;
use tool_tenant\event\tenant_user_created;
use tool_tenant\event\tenant_user_updated;

defined('MOODLE_INTERNAL') || die();

/**
 * Methods for managing the list of tenants
 *
 * Not external API.
 *
 * Use {@link \tool_tenant\tenancy} to get information about current tenant and its users
 *
 * @package     tool_tenant
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /**
     * Returns list of tenants in the system
     *
     * @return tenant[]
     */
    public function get_tenants() : array {
        global $DB;
        $cache = \cache::make('tool_tenant', 'tenants');
        if (!($tenants = $cache->get('active'))) {
            $tenantrecords = $DB->get_records(tenant::TABLE, ['archived' => 0],
                'isdefault DESC, sortorder, id');
            $tenants = [];
            foreach ($tenantrecords as $tenantrecord) {
                $tenants[$tenantrecord->id] = new tenant(0, $tenantrecord);
            }
            if (empty($tenants)) {
                // Create default tenant.
                $tenant = $this->create_tenant((object)[
                    'name' => get_string('defaultname', 'tool_tenant'),
                    'isdefault' => 1]);
                $tenants[$tenant->get('id')] = $tenant;
            }
            $cache->set('active', $tenants);
        }
        return $tenants;
    }

    /**
     * Returns list of archived tenants in the system
     *
     * @return tenant[]
     */
    public function get_archived_tenants() {
        global $DB;
        $cache = \cache::make('tool_tenant', 'tenants');
        if (($archivedtenants = $cache->get('archived')) === false) {
            $tenantrecords = $DB->get_records(tenant::TABLE, ['archived' => 1], 'timearchived DESC');
            $archivedtenants = [];
            foreach ($tenantrecords as $tenantrecord) {
                $archivedtenants[$tenantrecord->id] = new tenant(0, $tenantrecord);
            }
            $cache->set('archived', $archivedtenants);
        }
        return $archivedtenants;
    }

    /**
     * Resets tenants list cache
     */
    protected function reset_tenants_cache() {
        \cache_helper::purge_by_event('tenantsmodified');
        \cache::make('tool_tenant', 'mytenant')->purge();
        \cache::make('tool_tenant', 'tenants')->purge();
    }

    /**
     * Retrieves an active tenant by id
     *
     * @param int $id
     * @param \moodle_url $exceptionlink (optional) link to use in exception message
     * @return tenant
     * @throws \moodle_exception
     */
    public function get_tenant(int $id, \moodle_url $exceptionlink = null) : tenant {
        $tenants = $this->get_tenants();
        if (array_key_exists($id, $tenants)) {
            return $tenants[$id];
        }
        throw new \moodle_exception('tenantnotfound', 'tool_tenant',
            $exceptionlink ?: self::get_base_url());
    }

    /**
     * Creates a new tenant
     *
     * @param \stdClass $data
     */
    public function create_tenant(\stdClass $data) : tenant {
        $tenant = new tenant(0, $data);
        $tenant->create();
        if (!$tenant->get('isdefault')) {
            // Do not trigger event when default tenant is created, it is done automatically on the first request
            // and may affect core unittests.
            tenant_created::create_from_object($tenant)->trigger();
        }
        $this->reset_tenants_cache();
        $this->change_sortorder($tenant->get('id'));
        return $tenant;
    }

    /**
     * Create a tenant with default properties and generated unique name
     *
     * @return tenant
     */
    public function create_tenant_quick() : tenant {
        $tenants = $this->get_tenants();
        for ($i = 1; $i < 101; $i++) {
            $name = get_string('newname', 'tool_tenant', $i);
            foreach ($tenants as $tenant) {
                if ($tenant->get('name') === $name) {
                    continue 2;
                }
            }
            break;
        }
        $last = end($tenants);
        return $this->create_tenant((object)['name' => $name, 'sortorder' => $last ? ($last->get('sortorder') + 1) : 0]);
    }

    /**
     * Updates a tenant
     *
     * @param int $tenantid
     * @param \stdClass $newdata
     * @return tenant
     */
    public function update_tenant(int $tenantid, \stdClass $newdata) : tenant {
        return $this->update_tenant_object($this->get_tenant($tenantid), $newdata);
    }

    /**
     * Updates a tenant
     *
     * @param tenant $tenant
     * @param \stdClass $newdata
     * @return tenant same object that was passed to this method
     */
    protected function update_tenant_object(tenant $tenant, \stdClass $newdata) : tenant {
        $oldrecord = $tenant->to_record();
        foreach ($newdata as $key => $value) {
            if (tenant::has_property($key) && $key !== 'id') {
                $tenant->set($key, $value);
            }
        }
        $tenant->save();
        tenant_updated::create_from_object($tenant, $oldrecord)->trigger();
        $this->reset_tenants_cache();
        return $tenant;
    }

    /**
     * Archives a tenant
     *
     * @param int $id
     * @return tenant
     */
    public function archive_tenant(int $id) : tenant {
        $tenant = $this->get_tenant($id);
        if ($tenant->get('isdefault')) {
            throw new \moodle_exception('cannotarchivetenant', 'tool_tenant', self::get_base_url());
        }
        return $this->update_tenant_object($tenant,
            (object)['archived' => 1, 'timearchived' => time()]);
    }

    /**
     * Restores archived tenant
     *
     * @param int $id
     * @return tenant
     * @throws \moodle_exception
     */
    public function restore_tenant(int $id) : tenant {
        global $USER;
        $tenant = new tenant($id);
        if (!$tenant->get('id') || !$tenant->get('archived')) {
            throw new \moodle_exception('tenantnotfound', 'tool_tenant', self::get_base_url(true));
        }
        $this->update_tenant_object($tenant, (object)['archived' => 0, 'timearchived' => null]);
        unset($USER->tenantid);
        return $tenant;
    }

    /**
     * Deletes archived tenant
     *
     * @param int $id
     * @return tenant
     * @throws \moodle_exception
     */
    public function delete_tenant(int $id) : tenant {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/group/lib.php');

        $tenant = new tenant($id);
        if (!$tenant->get('id') || !$tenant->get('archived')) {
            throw new \moodle_exception('tenantnotfound', 'tool_tenant', self::get_base_url(true));
        }

        // Delete tenant users associations.
        $DB->delete_records('tool_tenant_user', ['tenantid' => $id]);

        // Delete tenant groups in courses.
        tenant_group::delete_for_tenant($id);

        // Delete tenant record.
        $tenant->delete();

        tenant_deleted::create_from_object($tenant)->trigger();
        $this->reset_tenants_cache();
        return $tenant;
    }

    /**
     * Executes one of the management actions
     *
     * @param string $action
     * @param int $id
     */
    public function manage_action(string $action, $id = null) {
        if ($action === 'archive' && $id) {
            permission::require_can_archive_tenant($id);
            $this->archive_tenant($id);
        } else if ($action === 'delete' && $id) {
            permission::require_can_delete_tenant($id);
            $this->delete_tenant($id);
        } else if ($action === 'restore' && $id) {
            permission::require_can_restore_tenant($id);
            $this->restore_tenant($id);
        }
    }

    /**
     * Moves a tenant with id $id before tenant with id $beforeid
     *
     * @param int $id
     * @param int $beforeid
     * @throws \moodle_exception
     */
    public function change_sortorder(int $id, int $beforeid = 0) {
        $tenants = $this->get_tenants();
        if (!array_key_exists($id, $tenants) || ($beforeid && !array_key_exists($beforeid, $tenants))) {
            throw new \moodle_exception('tenantnotfound', 'tool_tenant');
        }
        if ($tenants[$id]->get('isdefault')) {
            // Do not move the default tenant.
            return;
        }
        if ($beforeid && $tenants[$beforeid]->get('isdefault')) {
            // Can not move before the default tenant, move right after it.
            $nondefaulttenants = array_filter($tenants, function(tenant $t) {
                return !$t->get('isdefault');
            });
            $beforeid = key($nondefaulttenants);
        }
        $tenantids = array_values(array_diff(array_keys($tenants), [$id]));
        if (!$beforeid) {
            $tenantids = array_merge($tenantids, [$id]);
        } else {
            $idx = array_search($beforeid, $tenantids);
            $tenantids = array_merge(array_slice($tenantids, 0, $idx), [$id], array_slice($tenantids, $idx));
        }

        foreach (array_values($tenantids) as $idx => $tenantid) {
            if ($tenants[$tenantid]->get('sortorder') != $idx) {
                $tenants[$tenantid]->set('sortorder', $idx);
                $tenants[$tenantid]->save();
                $this->reset_tenants_cache();
            }
        }
    }

    /**
     * Allocate the user to the tenant
     *
     * @param int $userid
     * @param int $tenantid
     * @param string $component component that called this method
     * @param string $reason
     */
    public function allocate_user(int $userid, int $tenantid, string $component, string $reason) {
        if (isguestuser($userid)) {
            return;
        }
        $usertenant = tenant_user::create_for_user($userid);
        $oldrecord = $usertenant->to_record();
        $usertenant->set('tenantid', $tenantid);
        $usertenant->set('component', $component);
        $usertenant->set('reason', $reason);
        $usertenant->save();
        if ($oldrecord->id) {
            tenant_user_updated::create_from_object($usertenant, $oldrecord)->trigger();
        } else {
            tenant_user_created::create_from_object($usertenant)->trigger();
        }
        // Check to see if this user has been assigned a tenant role.
        $this->assign_tenant_user_role($userid, $tenantid);

        $cache = \cache::make('tool_tenant', 'mytenant');
        $cacheidx = 'tenantid-' . $userid;
        $cache->delete($cacheidx);
    }

    /**
     * Assigns a user the tenant user role (while deleting them from all other roles).
     * TODO SP-361 Create a task to sync the roles.
     *
     * @param  int $userid The user ID.
     * @param  int $tenantid The tenant ID.
     * @param  int $categoryid The category ID.
     */
    public function assign_tenant_user_role(int $userid, int $tenantid, int $categoryid = null) {
        // Remove from all tool_tenant roles.
        role_unassign_all(['userid' => $userid, 'component' => 'tool_tenant']);

        if (!isset($categoryid)) {
            $tenant = tenancy::get_tenants()[$tenantid];
            $categoryid = $tenant->categoryid;
        }

        // This checks to see if the user is in a tenant with no category id and if so just marks the user context
        // as dirty.
        if (!empty($categoryid)) {
            $context = \context_coursecat::instance($categoryid);
            // Assign the tenant user role.
            \role_assign(self::get_tenant_user_role(), $userid, $context->id, 'tool_tenant', $tenantid);
            \cache_helper::purge_by_event('changesincoursecat');
        }
    }

    /**
     * Assigns the 'Tenant administrator' role in given tenantid to given array of userids
     *
     * @param array $userids
     * @param int $tenantid
     */
    public function assign_tenant_admin_roles(array $userids, int $tenantid) {
        $adminrole = self::get_tenant_admin_role();
        $managerrole = self::get_tenant_manager_role();
        $systemcontext = \context_system::instance();
        $catcontext = self::get_tenant_category_context($tenantid);
        foreach ($userids as $userid) {
            role_assign($adminrole, $userid, $systemcontext->id, 'tool_tenant', $tenantid);
        }
        if ($catcontext) {
            foreach ($userids as $userid) {
                role_assign($managerrole, $userid, $catcontext->id, 'tool_tenant', $tenantid);
            }
        }
    }

    /**
     * Unassigns the 'Tenant administrator' role in given tenantid to given array of userids
     *
     * @param array $userids
     * @param int $tenantid
     */
    public function unassign_tenant_admin_roles(array $userids, int $tenantid) {
        $adminrole = self::get_tenant_admin_role();
        $managerrole = self::get_tenant_manager_role();
        $systemcontext = \context_system::instance();
        foreach ($userids as $userid) {
            role_unassign($adminrole, $userid, $systemcontext->id, 'tool_tenant', $tenantid);
        }
        $catcontext = self::get_tenant_category_context($tenantid);
        if ($catcontext) {
            foreach ($userids as $userid) {
                role_unassign($managerrole, $userid, $catcontext->id, 'tool_tenant', $tenantid);
            }
        }
    }

    /**
     * Return the context of category of given tenant. Return null if tenant has no category associated.
     *
     * @param int $tenantid
     * @return null|\context_coursecat
     */
    protected function get_tenant_category_context(int $tenantid) {
        $tenant = $this->get_tenant($tenantid);
        $categoryid = $tenant->get('categoryid');
        if ($categoryid) {
            return \context_coursecat::instance($tenant->get('categoryid'));
        } else {
            return null;
        }
    }

    /**
     * Change tenant category
     *
     * @param int $tenantid
     * @param int $categoryid
     */
    public function change_tenant_category(int $tenantid, int $categoryid = 0) {
        global $DB;

        role_unassign_all(['itemid' => $tenantid, 'component' => 'tool_tenant',
            'roleid' => self::get_tenant_admin_role()]);

        role_unassign_all(['itemid' => $tenantid, 'component' => 'tool_tenant',
            'roleid' => self::get_tenant_manager_role()]);

        role_unassign_all(['itemid' => $tenantid, 'component' => 'tool_tenant',
            'roleid' => self::get_tenant_user_role()]);

        if (!$categoryid) {
            \cache_helper::purge_by_event('changesincoursecat');
            return;
        }

        $context = \context_coursecat::instance($categoryid);
        $userroleid = self::get_tenant_user_role();

        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u', $tenantid);
        $sql = "SELECT u.id FROM {user} u " . $join . ' WHERE ' . $where;
        $users = $DB->get_fieldset_sql($sql, $params);
        foreach ($users as $userid) {
            role_assign($userroleid, $userid, $context->id, 'tool_tenant', $tenantid);
        }
        \cache_helper::purge_by_event('changesincoursecat');
    }

    /**
     * Assign the roles 'tool_tenant_admin' and 'tool_tenant_manager' to admin users.
     * TODO SP-361 Create a task to sync the roles.
     * TODO Rename to make obvious it removes other admins.
     *
     * @param  int    $tenantid The tenant ID.
     * @param  array  $userids  IDs of the tenant admins
     */
    public function assign_tenant_admin_role(int $tenantid, array $userids) {
        // Remove old admins.
        $currentadmins = \tool_tenant\tenancy::get_tenant_admins($tenantid);
        $adminstoremove = array_diff($currentadmins, $userids);
        self::unassign_tenant_admin_roles($adminstoremove, $tenantid);

        // Make sure all admins have roles assigned.
        self::assign_tenant_admin_roles($userids, $tenantid);
        \cache_helper::purge_by_event('changesincoursecat');
    }

    /**
     * Get the css configuration for this tenant.
     *
     * @param  int    $tenantid           The tenant ID.
     * @param  array  $filemanageroptions File manager options for the related files.
     * @return \stdClass The css configuration for this tenant.
     */
    public function get_css_config(int $tenantid, array $filemanageroptions) : ?\stdClass {
        if (empty($tenantid)) {
            return null;
        }
        $tenant = $this->get_tenant($tenantid);
        $cssconfig = $tenant->get('cssconfig');
        $info = $cssconfig ? json_decode($cssconfig) : (object)[];
        $info->tenantid = $tenantid;
        // Get all the files as well.
        $info->headerlogo = $this->get_file_draftid('headerlogo', $tenantid, $filemanageroptions);
        $info->loginlogo = $this->get_file_draftid('loginlogo', $tenantid, $filemanageroptions);
        $info->loginbackground = $this->get_file_draftid('loginbackground', $tenantid, $filemanageroptions);
        $info->favicon = $this->get_file_draftid('favicon', $tenantid, $filemanageroptions);
        return $info;
    }

    /**
     * Returns the logo css for the tenant.
     *
     * @param  int    $tenantid The ID of the tenant to get the logo css for.
     * @return string The CSS for the logos.
     */
    public function get_logo_css(int $tenantid) : string {
        $scss = '';
        foreach (['headerlogo', 'loginlogo', 'loginbackground'] as $logoname) {
            $logofile = $this->get_css_file($tenantid, $logoname);
            if (isset($logofile)) {
                $url = \moodle_url::make_pluginfile_url(\context_system::instance()->id, 'tool_tenant', $logoname,
                         $tenantid, $logofile->get_filepath(), $logofile->get_filename());
                $scss .= '$' . $logoname . ': "' . $url->out() . '";';
            }
        }
        return $scss;
    }

    /**
     * Returns the favicon image for the tenant.
     *
     * @param  int    $tenantid The ID of the tenant to get the favicon for.
     * @return string The favicon url.
     */
    public function get_favicon(int $tenantid) : string {
        $url = '';
        $fs = \get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'tool_tenant', 'favicon', $tenantid);
        foreach ($files as $file) {
            if ($file->get_filesize() > 0) {
                $url = \moodle_url::make_pluginfile_url(\context_system::instance()->id, 'tool_tenant', 'favicon',
                         $tenantid, $file->get_filepath(), $file->get_filename());
            }
        }
        return $url;
    }

    /**
     * Get the css file associated with the file area and tenant id.
     *
     * @param  int    $tenantid The tenant ID.
     * @param  string $filearea The file area.
     * @return \stored_file The file.
     */
    protected function get_css_file(int $tenantid, string $filearea) : ?\stored_file {
        $fs = \get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'tool_tenant', $filearea, $tenantid);
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Get the file draft ID for the css config files.
     *
     * @param  string $filearea           The file area for the file.
     * @param  int    $tenantid           The tenant ID.
     * @param  array  $filemanageroptions File manager options.
     * @return int The filedraftid.
     */
    protected function get_file_draftid(string $filearea, int $tenantid, array $filemanageroptions) : int {
        $context = \context_system::instance();
        $draftitemid = \file_get_submitted_draft_itemid($filearea);
        \file_prepare_draft_area($draftitemid, $context->id, 'tool_tenant', $filearea, $tenantid, $filemanageroptions);

        return $draftitemid;
    }

    /**
     * Saves the CSS config data.
     *
     * @param \stdClass $data css config data. See edit_css_form.php for expected elements.
     */
    public function save_css_config(\stdClass $data) {
        $tenant = $this->get_tenant($data->tenantid);
        $tenantid = $tenant->get('id');
        unset($data->tenantid);
        $this->save_css_files($data->headerlogo, 'headerlogo', $tenantid);
        unset($data->headerlogo);
        $this->save_css_files($data->loginlogo, 'loginlogo', $tenantid);
        unset($data->loginlogo);
        $this->save_css_files($data->loginbackground, 'loginbackground', $tenantid);
        unset($data->loginbackground);
        $this->save_css_files($data->favicon, 'favicon', $tenantid);
        unset($data->favicon);
        $cssconfig = json_encode($data);
        $tenant = $this->update_tenant($tenant->get('id'), (object) ['cssconfig' => $cssconfig]);
        theme_reset_all_caches();
    }

    /**
     * Saves css files.
     *
     * @param  int    $filedraftareaid The file draftarea id supplied by the filemanager.
     * @param  string $filearea        The file area the file belongs to
     * @param  int    $tenantid        The tenant id for this file.
     */
    protected function save_css_files(int $filedraftareaid, string $filearea, int $tenantid) {
        $context = \context_system::instance();
        \file_save_draft_area_files($filedraftareaid, $context->id, 'tool_tenant', $filearea, $tenantid);
    }

    /**
     * Can this tenant be moved to the specified course category.
     *
     * @param  int    $tenantid   The tenant ID.
     * @param  int    $categoryid Category ID to move to.
     * @return bool Returns true if the move is possible, otherwise false.
     */
    public static function can_change_category(int $tenantid, int $categoryid) : bool {
        global $DB;
        if (empty($categoryid)) {
            return true;
        }
        $params = ['tenantid' => $tenantid, 'categoryid' => $categoryid];
        $count = $DB->count_records_select('tool_tenant', 'id <> :tenantid AND categoryid = :categoryid', $params);
        if ($count > 0) {
            return false;
        }
        return true;
    }

    /**
     * Can this user create users in the supplied tenant.
     *
     * @deprecated use permission::can_create_users()
     *
     * @param  int $tenantid The current tenant ID.
     * @return bool True is this user can create users in the tenant.
     */
    public static function can_create_users($tenantid) : bool {
        return permission::can_create_users($tenantid);
    }

    /**
     * Can this user update users in the supplied tenant.
     *
     * @deprecated use permission::can_update_users()
     *
     * @param  int $tenantid The current tenant ID.
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_update_users($tenantid) : bool {
        return permission::can_update_users($tenantid);
    }

    /**
     * Can this user browse users in tenants. Will first check to see if the user can browse users in all tenants
     * and if not then check if they have the capability to browser users in the specified tenant (will use the default
     * tenant by default).
     *
     * @deprecated use permission::can_browse_users
     *
     * @param  int $currenttenantid The current tenant ID.
     * @return bool True is this user can browse users.
     */
    public static function can_browse_users(int $currenttenantid = 0) : bool {
        return permission::can_browse_users($currenttenantid);
    }

    /**
     * Can this user delete users in the supplied tenant.
     *
     * @deprecated use permission::can_delete_users
     *
     * @param  int $tenantid The current tenant ID.
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_delete_users($tenantid) : bool {
        return permission::can_delete_users($tenantid);
    }

    /**
     * Can this user suspend users in the supplied tenant.
     *
     * @deprecated use permission::can_suspend_users()
     *
     * @param  int $tenantid The current tenant ID.
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_suspend_users($tenantid) : bool {
        return permission::can_suspend_users($tenantid);
    }

    /**
     * Can user view "Roles" tab for the given tenant id
     *
     * @deprecated use permission::can_see_roles_tab
     *
     * @param int $currenttenantid
     * @return bool
     */
    public static function can_see_roles_tab(int $currenttenantid = 0) : bool {
        return permission::can_see_roles_tab($currenttenantid);
    }

    /**
     * Can the user move other users between different tenants.
     *
     * @deprecated use permission::can_move_users_between_tenants
     *
     * @return bool True if the user has permission to move users between tenants.
     */
    public static function can_move_users_between_tenants() : bool {
        return permission::can_move_users_between_tenants();
    }

    /**
     * Can the user edit the theme for tenants.
     *
     * @deprecated use permission::can_edit_tenant_themes
     *
     * @param int $tenantid
     * @return bool True if the user has permission to edit the themes of tenants, else false.
     */
    public static function can_edit_tenant_themes(int $tenantid = 0) : bool {
        return permission::can_edit_tenant_theme($tenantid);
    }

    /**
     * Base URL to view tenants list
     * @param bool $archived
     * @return \moodle_url
     */
    public static function get_base_url(bool $archived = false) : \moodle_url {
        $url = new \moodle_url('/admin/tool/tenant/index.php');
        if ($archived) {
            $url->set_anchor('archivedtenants');
        }
        return $url;
    }

    /**
     * URL to view a tenant
     * @param int $tenantid
     * @return \moodle_url
     */
    public static function get_view_url(int $tenantid) : \moodle_url {
        return new \moodle_url(self::get_base_url(), ['id' => $tenantid]);
    }

    /**
     * URL to view users in a tenant
     * @param int $tenantid
     * @return \moodle_url
     */
    public static function get_edit_tenant_url(int $tenantid) : \moodle_url {
        return new \moodle_url('/admin/tool/tenant/edit.php', ['id' => $tenantid]);
    }

    /**
     * Returns role id for tenant admins, creates if it does not exist
     *
     * @return int
     */
    public static function get_tenant_admin_role() : int {
        global $CFG;
        if (empty($CFG->tool_tenant_adminrole)) {
            self::create_tenant_roles();
        }
        return (int)$CFG->tool_tenant_adminrole;
    }

    /**
     * Returns tenantmanager role, creates if it does not exist
     *
     * @return int
     */
    public static function get_tenant_manager_role() : int {
        global $CFG;
        if (empty($CFG->tool_tenant_managerrole)) {
            self::create_tenant_roles();
        }
        return (int)$CFG->tool_tenant_managerrole;
    }

    /**
     * Returns the tenantuser role, creates if it does not exist
     *
     * @return int
     */
    public static function get_tenant_user_role() : int {
        global $CFG;
        if (empty($CFG->tool_tenant_userrole)) {
            self::create_tenant_roles();
        }
        return (int)$CFG->tool_tenant_userrole;
    }

    /**
     * List of default capabilities for the tenant admin.
     *
     * @return array A list of default capabilities.
     */
    protected static function get_tenant_admin_capabilities() : array {
        return [
            'moodle/site:configview',
            'tool/certificate:issue',
            'tool/certificate:manage',
            'tool/certificate:verify',
            'tool/certificate:viewallcertificates',
            'tool/certification:allocateuser',
            'tool/certification:edit',
            'tool/dynamicrule:manage',
            'tool/organisation:assignjobs',
            'tool/organisation:managedepartments',
            'tool/organisation:managepositions',
            'tool/program:allocateuser',
            'tool/program:edit',
            'tool/program:viewhidden',
            'tool/reportbuilder:edit',
            'tool/reportbuilder:read',
            'tool/tenant:browseusers',
            'tool/tenant:managetheme',
            'tool/tenant:manageusers',
            // TODO: what to do with moodle/user:viewdetails ?
            'moodle/role:assign',
            'moodle/site:uploadusers',
            'moodle/site:viewuseridentity',
            'moodle/site:doclinks',
            'moodle/badges:awardbadge',
            'moodle/badges:viewawarded'
        ];
    }

    /**
     * Lost of roles that tenantmanager can assign in course category context and below
     *
     * @return array
     */
    protected static function get_tenant_manager_roles_associations() : array {
        $roles = get_all_roles();
        $rv = ['view' => [], 'switch' => [], 'override' => [], 'assign' => []];
        foreach ($roles as $role) {
            if (in_array($role->shortname, ['student', 'teacher', 'editingteacher', 'coursecreator'])) {
                $rv['assign'][] = $role->id;
                $rv['view'][] = $role->id;
                $rv['override'][] = $role->id;
            }
            if (in_array($role->shortname, ['tool_tenant_manager'])) {
                $rv['assign'][] = $role->id;
                $rv['view'][] = $role->id;
            }
            if (in_array($role->shortname, ['student', 'teacher', 'editingteacher', 'guest'])) {
                $rv['switch'][] = $role->id;
            }
            if (in_array($role->shortname, ['user', 'guest', 'frontpage'])) {
                $rv['view'][] = $role->id;
                $rv['override'][] = $role->id;
            }
            if (in_array($role->shortname, ['tool_tenant_user'])) {
                $rv['override'][] = $role->id;
            }
            if (in_array($role->shortname, ['manager'])) {
                $rv['view'][] = $role->id;
            }
        }
        return $rv;
    }

    /**
     * Lost of roles that tenantadmin can assign in system context
     *
     * @return array
     */
    protected static function get_tenant_admin_roles_associations() : array {
        // Admin should be able to assign roles (in system context).
        $roles = get_all_roles();
        $rv = ['view' => [], 'switch' => [], 'override' => [], 'assign' => []];
        foreach ($roles as $role) {
            // TODO WP-677 remove certificate from this list.
            if (preg_match('/^tool_(program|certification|certificate|organisation|reportbuilder|dynamicrule)_/',
                    $role->shortname)) {
                $rv['assign'][] = $role->id;
                $rv['view'][] = $role->id;
                $rv['override'][] = $role->id;
                $rv['switch'][] = $role->id;
            }
            if (in_array($role->shortname, ['tool_tenant_admin'])) {
                $rv['view'][] = $role->id;
            }
        }
        return $rv;
    }

    /**
     * List of default capabilities for the tenant manager.
     *
     * We take the list of capabilities that are normally given to the 'manager' archetype excluding
     * capabilities that are assignable in system/user context.
     *
     * @return array A list of default capabilities.
     */
    public static function get_tenant_manager_capabilities() : array {

        // Most of the code copied from get_default_capabilities() except that we look at the context as well.
        $archetype = 'manager';

        $alldefs = array();
        $defaults = array();
        $components = array();
        $allcaps = get_all_capabilities();

        foreach ($allcaps as $cap) {
            if (!in_array($cap['component'], $components)) {
                $components[] = $cap['component'];
                $alldefs = array_merge($alldefs, load_capability_def($cap['component']));
            }
        }
        foreach ($alldefs as $name => $def) {
            if (isset($def['contextlevel']) && in_array($def['contextlevel'], [CONTEXT_SYSTEM, CONTEXT_USER])) {
                continue;
            }
            // Use array 'archetypes if available. Only if not specified, use 'legacy'.
            if (isset($def['archetypes'])) {
                if (isset($def['archetypes'][$archetype]) && $def['archetypes'][$archetype] == CAP_ALLOW) {
                    $defaults[$name] = $name;
                }
                // Def 'legacy' is for backward compatibility with 1.9 access.php.
            } else {
                if (isset($def['legacy'][$archetype]) && $def['legacy'][$archetype] == CAP_ALLOW) {
                    $defaults[$name] = $def['legacy'][$archetype];
                }
            }
        }

        $caps = array_keys($defaults);

        // Following capabilities have 'manager' archetype but should be excluded from tenantmanager role.
        $caps = array_diff($caps, [
            'enrol/database:config', // Not tested/supported yet.
            'enrol/database:unenrol', // Not tested/supported yet.
            'enrol/imsenterprise:config', // Not tested/supported yet.
            'enrol/ldap:manage', // Not tested/supported yet.
            'enrol/lti:config', // Not tested/supported yet.
            'enrol/lti:unenrol', // Not tested/supported yet.
        ]);

        // Following capabilities do not have 'manager' archetype but we should include them in tenantmanager role.
        $caps = array_merge($caps, [
            'moodle/category:viewcourselist',
        ]);

        return $caps;
    }

    /**
     * List of default capabilities for the tenant user.
     *
     * @return array A list of default capabilities.
     */
    protected static function get_tenant_user_capabilities() : array {
        return [
            'moodle/category:viewcourselist',
        ];
    }

    /**
     * Changes core roles to work better in multitenant environment
     */
    public static function change_core_roles() {
        global $CFG;
        require_once($CFG->libdir . '/accesslib.php');

        $capability = 'moodle/category:viewcourselist';
        if (get_capability_info($capability)) {
            $roles = get_all_roles();
            foreach ($roles as $role) {
                $permission = in_array($role->shortname,
                    ['tool_tenant_user', 'tool_tenant_manager', 'manager', 'coursecreator']) ?
                    CAP_ALLOW : CAP_INHERIT;
                assign_capability($capability, $permission, $role->id, \context_system::instance());
            }
        }
    }

    /**
     * Creates a role (if it does not exist) and adds capabilities to it
     *
     * If a role with the same shortname already exists, it is UPDATED (name, description and capabitilies)
     * Note, this function does not check if capabilities exist so it can be called from install.php and update.php
     *
     * @param string $shortname
     * @param string $name
     * @param string $description
     * @param array $capabilities list of capabilities that need to be added to this role. If the role already
     *     exists, these capabilities will be added to it. No capabilities will be removed.
     * @param string $archetype role archetype, by default none
     * @param array $contextlevels assignable context levels, by default [CONTEXT_SYSTEM]
     * @return int id of the role
     */
    protected static function create_role(string $shortname, string $name,
              string $description, array $capabilities, string $archetype = '', ?array $contextlevels = null) : int {
        global $DB;

        $systemroles = $DB->get_records_menu('role', array(), 'sortorder ASC', 'shortname,id');
        $context = \context_system::instance();

        if (array_key_exists($shortname, $systemroles)) {
            $roleid = $systemroles[$shortname];
            $DB->update_record('role', ['id' => $roleid, 'name' => $name,
                'description' => $description
            ]);
        } else {
            // Create role.
            $roleid = create_role($name, $shortname, $description, $archetype);
            set_role_contextlevels($roleid, $contextlevels ? $contextlevels : [CONTEXT_SYSTEM]);
        }

        // Add capabilities to the role.
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }

        return $roleid;
    }

    /**
     * Add role assignments, overrides, etc
     *
     * @param int $roleid
     * @param array $roles
     */
    protected static function add_role_assignments(int $roleid, array $roles) {
        global $DB;
        $roles += ['assign' => [], 'override' => [], 'switch' => [], 'view' => []];
        foreach ($roles['assign'] as $otherroleid) {
            if (!$DB->record_exists('role_allow_assign', ['roleid' => $roleid, 'allowassign' => $otherroleid])) {
                core_role_set_assign_allowed($roleid, $otherroleid);
            }
        }
        foreach ($roles['override'] as $otherroleid) {
            if (!$DB->record_exists('role_allow_override', ['roleid' => $roleid, 'allowoverride' => $otherroleid])) {
                core_role_set_override_allowed($roleid, $otherroleid);
            }
        }
        foreach ($roles['switch'] as $otherroleid) {
            if (!$DB->record_exists('role_allow_switch', ['roleid' => $roleid, 'allowswitch' => $otherroleid])) {
                core_role_set_switch_allowed($roleid, $otherroleid);
            }
        }
        foreach ($roles['view'] as $otherroleid) {
            if (!$DB->record_exists('role_allow_view', ['roleid' => $roleid, 'allowview' => $otherroleid])) {
                core_role_set_view_allowed($roleid, $otherroleid);
            }
        }

    }

    /**
     * Creates tenant admin/manager/user role.
     *
     * Can be called from install.php and/or update.php or tool_tenant
     */
    public static function create_tenant_roles() {
        $allcaps = array_keys(get_all_capabilities());

        // Tenant admin.
        $capabilities = array_intersect(self::get_tenant_admin_capabilities(), $allcaps);
        $roleid = self::create_role('tool_tenant_admin', get_string('tenantadmin', 'tool_tenant'),
            get_string('tenantadmindescription', 'tool_tenant'), $capabilities);
        set_config('tool_tenant_adminrole', $roleid);
        self::add_role_assignments($roleid, self::get_tenant_admin_roles_associations());

        // Tenant manager.
        $capabilities = array_intersect(self::get_tenant_manager_capabilities(), $allcaps);
        $roleid = self::create_role('tool_tenant_manager', get_string('tenantmanager', 'tool_tenant'),
            get_string('tenantmanagerdescription', 'tool_tenant'), $capabilities, 'manager',
            [CONTEXT_COURSECAT, CONTEXT_COURSE]);
        set_config('tool_tenant_managerrole', $roleid);
        self::add_role_assignments($roleid, self::get_tenant_manager_roles_associations());

        // Tenant user.
        $capabilities = array_intersect(self::get_tenant_user_capabilities(), $allcaps);
        $roleid = self::create_role('tool_tenant_user', get_string('tenantuser', 'tool_tenant'),
            get_string('tenantuserdescription', 'tool_tenant'), $capabilities);
        set_config('tool_tenant_userrole', $roleid);

        // Reset caches.
        accesslib_reset_role_cache();
    }

    /**
     * Creates a role for a workplace plugin that depends on tool_tenant
     *
     * Can be called from install.php and/or update.php of that plugin, will create/update necessary
     * associations with the main tenant roles.
     *
     * If used in install.php and/or update.php make sure to call update_capabilities('pluginname') first.
     * TODO MDL-65668 remove this comment.
     *
     * @param string $shortname
     * @param string $name
     * @param string $description
     * @param array $capabilities
     */
    public static function create_workplace_role(string $shortname, string $name,
            string $description, array $capabilities) {
        global $DB, $CFG;

        if (!$roleid = $DB->get_field('role', 'id', ['shortname' => $shortname])) {
            $roleid = create_role($name, $shortname, $description, '');
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        }

        $context = \context_system::instance();

        $capabilities[] = 'moodle/site:doclinks';
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        }

        // If tool_tenant is already installed, add this capability to the list of "assignable" capabilities of the tenantadmin.
        if (!empty($CFG->tool_tenant_adminrole)) {
            // Add associations with other roles.
            $roleassoc = self::get_tenant_admin_roles_associations();
            foreach ($roleassoc as $key => $roles) {
                $roleassoc[$key] = array_intersect($roles, [$roleid]);
            }
            self::add_role_assignments($CFG->tool_tenant_adminrole, $roleassoc);
        }

        if (!empty($CFG->tool_tenant_managerrole)) {
            // Add associations with other roles.
            $roleassoc = self::get_tenant_manager_roles_associations();
            foreach ($roleassoc as $key => $roles) {
                $roleassoc[$key] = array_intersect($roles, [$roleid]);
            }
            self::add_role_assignments($CFG->tool_tenant_managerrole, $roleassoc);
        }

        // Reset caches.
        accesslib_reset_role_cache();
    }

    /**
     * Name for the cookie to store the last used tenant id
     *
     * @return null|string
     */
    protected static function get_tenant_cookie_name() {
        global $CFG;

        if (NO_MOODLE_COOKIES) {
            return null;
        }
        if (defined('BEHAT_SITE_RUNNING') && (defined('BEHAT_TEST') || defined('BEHAT_UTIL'))) {
            // We are inside behat set up that is not a test.
            return null;
        }
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return null;
        }

        $cookiename = 'MOODLETENANT1_' . (isset($CFG->sessioncookie) ? $CFG->sessioncookie : '');
        if (defined('BEHAT_SITE_RUNNING') && !empty($CFG->behatrunprocess)) {
            $cookiename .= clean_param($CFG->behatrunprocess, PARAM_ALPHANUMEXT);
        }

        return $cookiename;
    }

    /**
     * Sets a moodle cookie with a current tenant id
     *
     * @param string $tenantid to encrypt and place in a cookie, '' means delete current cookie
     * @return void
     */
    public static function set_tenant_cookie($tenantid) {
        global $CFG;

        if (($cookiename = self::get_tenant_cookie_name()) === null) {
            return;
        }

        if (defined('BEHAT_SITE_RUNNING')) {
            // When used it behat, set cookie for this session only.
            setcookie($cookiename, $tenantid);
            return;
        }

        $cookiesecure = is_moodle_cookie_secure();

        // Delete old cookie.
        setcookie($cookiename, '', time() - HOURSECS, $CFG->sessioncookiepath, $CFG->sessioncookiedomain,
            $cookiesecure, $CFG->cookiehttponly);

        if ($tenantid) {
            // Set tenantid cookie for 60 days.
            setcookie($cookiename, $tenantid, time() + (DAYSECS * 60), $CFG->sessioncookiepath, $CFG->sessioncookiedomain,
                $cookiesecure, $CFG->cookiehttponly);
        }
    }

    /**
     * Gets a moodle cookie with a last used tenant id
     *
     * @return int tenantid
     */
    public static function get_tenant_cookie() {
        if (($cookiename = self::get_tenant_cookie_name()) === null) {
            return null;
        }

        if (!empty($_COOKIE[$cookiename])) {
            $tenantid = (int)$_COOKIE[$cookiename];
            if ($tenantid && array_key_exists($tenantid, tenancy::get_tenants())) {
                return $tenantid;
            }
        }
        return null;
    }

    /**
     * Check if user is tenant admin.
     *
     * @param int $tenantid
     * @param int $userid
     * @return bool
     */
    public static function is_tenant_admin($tenantid, $userid) : bool {
        global $CFG, $DB;
        return $DB->record_exists('role_assignments',
            ['component' => 'tool_tenant',
             'itemid' => $tenantid,
             'roleid' => (int)$CFG->tool_tenant_adminrole,
             'userid' => $userid]);
    }
}
