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
 * Class manager.
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use context_system;
use core\event\user_created;
use tool_tenant\event\tenant_created;
use tool_tenant\event\tenant_deleted;
use tool_tenant\event\tenant_updated;
use tool_tenant\event\tenant_user_created;
use tool_tenant\event\tenant_user_updated;
use tool_tenant\form\edit_css_form;

/**
 * Methods for managing the list of tenants
 *
 * Not external API.
 *
 * Use {@see \tool_tenant\tenancy} to get information about current tenant and its users
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {

    /** @var array during installation sotres the workplace role that were requested before the tool tenant was installed */
    private static $delayedworkplaceroles = [];
    /** @var array remember to which tenant the future user should be allocated to */
    private static $preallocatedusers = [];

    /**
     * Returns list of tenants in the system
     *
     * This function can only be used inside tool_tenant for the tenant management.
     * Otherwise use tenancy::get_tenants()
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
     * Returns the list of tenants that excludes the shared tenant
     *
     * IMPORTANT! This function is temporary and will be removed when we introduce sub-tenants
     *
     * @return tenant[]
     */
    public function get_tenants_without_shared(): array {
        $tenants = $this->get_tenants();
        unset($tenants[sharedspace::get_shared_space_id()]);
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
     * This function can only be used inside tool_tenant for tenant management
     * Otherwise use tenancy::get_tenants() and tenancy::get_tenant_name_from_id()
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
     * @param \stdClass $data if $data->cssconfig is send then we assign it to the new one tenant
     *      otherwise, set with default values.
     */
    public function create_tenant(\stdClass $data) : tenant {
        $data->parentid = sharedspace::get_shared_space_id();
        $data->depth = 1;
        $data->path = '/';

        // Check if Default tenant exists.
        if (!empty(tenant::get_records())) {
            // Retrieve default tenant instance and copy it's 'cssconfig' value if '$data->cssconfig' is empty.
            $defaulttenantid = tenancy::get_default_tenant_id();
            $defaulttenant = new tenant($defaulttenantid);
            $data->cssconfig = $data->cssconfig ?? $defaulttenant->get('cssconfig');
        }

        $tenant = new tenant(0, $data);
        $tenant->create();
        if (!$tenant->get('isdefault')) {
            // Do not trigger event when default tenant is created, it is done automatically on the first request
            // and may affect core unittests.
            tenant_created::create_from_object($tenant)->trigger();

            // Copy the appearance images from the "Default tenant".
            $this->copy_default_tenant_images($tenant->get('id'));
        }
        // Update tenant path and depth.
        if ($data->parentid) {
            // TODO: this has to be re-written when full hierarchy is introduced.
            if (false) {
                $parent = new tenant($data->parentid);
                $tenant->set('path', $parent->get('path').'/'.$tenant->get('id'));
                $tenant->set('depth', $parent->get('depth') + 1);
            }
            $tenant->set('path', '/'.sharedspace::get_shared_space_id().'/'.$tenant->get('id'));
            $tenant->set('depth', 2);
        } else {
            $tenant->set('path', '/'.$tenant->get('id'));
            $tenant->set('depth', 1);
        }
        $tenant->save();
        // Resent caches and change sortorder.
        $this->reset_tenants_cache();
        $this->change_sortorder($tenant->get('id'));
        return $tenant;
    }

    /**
     * Copy appearance images from the "Default tenant" into the given tenant
     *
     * @param int $tenantid
     */
    private function copy_default_tenant_images(int $tenantid): void {
        $defaulttenantid = tenancy::get_default_tenant_id();
        $fs = get_file_storage();
        $fileareas = ['headerlogo', 'loginlogo', 'tenantselectorlogo', 'loginbackground', 'favicon'];

        foreach ($fileareas as $filearea) {
            $files = $fs->get_area_files(\context_system::instance()->id, 'tool_tenant', $filearea, $defaulttenantid,
                'timecreated DESC', false);
            if (!empty($files)) {
                $file = reset($files);
                $params = (object) [
                    'contextid' => \context_system::instance()->id,
                    'component' => 'tool_tenant',
                    'filearea' => $file->get_filearea(),
                    'filepath' => $file->get_filepath(),
                    'itemid' => $tenantid,
                    'filename' => $file->get_filename(),
                ];
                $fs->create_file_from_storedfile($params, $file);
            }
        }
    }

    /**
     * Remove appearance images for the given tenant
     *
     * @param int $tenantid
     */
    public function remove_tenant_images(int $tenantid): void {
        $fileareas = ['headerlogo', 'loginlogo', 'tenantselectorlogo', 'loginbackground', 'favicon'];

        foreach ($fileareas as $filearea) {
            get_file_storage()->delete_area_files(context_system::instance()->id, 'tool_tenant', $filearea, $tenantid);
        }
    }

    /**
     * Create a tenant with default properties and generated unique name
     *
     * @deprecated use create_tenant()
     *
     * @return tenant
     */
    public function create_tenant_quick() : tenant {
        debugging('This method is deprecated, use create_tenant() or generator for unittests', DEBUG_DEVELOPER);
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
        $tenant = $this->get_tenant($tenantid);
        if (property_exists($newdata, 'categoryid')) {
            if ($tenant->get('categoryid') != $newdata->categoryid) {
                $this->change_tenant_category($tenantid, $newdata->categoryid);
            }
        }
        $tenant = $this->update_tenant_object($this->get_tenant($tenantid), $newdata);
        return $tenant;
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
        $changed = false;
        foreach ($newdata as $key => $value) {
            if (tenant::has_property($key) && $key !== 'id') {
                $tenant->set($key, $value);
                $changed = true;
            }
        }
        if ($changed) {
            $tenant->save();
            tenant_updated::create_from_object($tenant, $oldrecord)->trigger();
            $this->reset_tenants_cache();
        }
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

        // Remove tenant branding images.
        $this->remove_tenant_images($id);

        // Delete tenant record, trigger event.
        $event = tenant_deleted::create_from_object($tenant);
        $tenant->delete();
        $event->trigger();

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
        $tenants = $this->get_tenants_without_shared();
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
     * Executed from the function user_create_user(), checks the preallocation and site/tenant user limit
     *
     * If user is not preallocated, pre-allocate to the current tenant.
     *
     * Throws an exception if the limit was reached and it is impossible to create a new user
     *
     * @param \stdClass $user
     * @throws \moodle_exception
     */
    public static function precheck_create_user(\stdClass $user): void {
        $tenantid = 0;
        if ($preallocation = self::find_user_preallocation($user, false)) {
            [$user, $tenantid, $component, $reason] = $preallocation;
        } else if (tenancy::is_site_multi_tenant()) {
            // User is not preallocated. Pre-allocate to the current tenant.
            $tenantid = tenancy::get_tenant_id();
            self::preallocate_new_user($user, $tenantid, 'tool_tenant', 'Default allocation to the current tenant');
        }
        if (!permission::check_quotas_to_add_users($tenantid, 1)) {
            throw new \moodle_exception('userslimitreached', 'tool_tenant');
        }
    }

    /**
     * Executed from the function user_update_user(), checks the site/tenant user limit
     *
     * Throws an exception if the limit was reached and it is impossible to unsuspend a user
     *
     * @param \stdClass $user
     * @throws \moodle_exception
     */
    public static function precheck_update_user(\stdClass $user): void {
        global $DB, $SESSION;
        // Only if only value is suspended and we are now looking to un-suspend.
        if (isset($user->suspended) && $user->suspended == 0) {
            $oldvalue = $DB->get_field('user', 'suspended', ['id' => $user->id]);
            // If user is un-suspended we create a session value to check in DR update user observer.
            if ($oldvalue) {
                $SESSION->unsuspendinguserid = $oldvalue;
            }

            if ($oldvalue && !permission::check_quotas_to_add_users(tenancy::get_tenant_id($user->id), 1)) {
                throw new \moodle_exception('userslimitreached', 'tool_tenant');
            }
        }
    }

    /**
     * Before user_created event is triggered (and sometimes before user is even created) we remember the future tenant
     *
     * The user will be allocated to this tenant in the observer to user_created event. This observer
     * has very high priority and should be executed before any other observer.
     *
     * @param \stdClass $user
     * @param int $tenantid
     * @param string $component
     * @param string $reason
     */
    public static function preallocate_new_user(\stdClass $user, int $tenantid, string $component, string $reason) {
        // Store it in static variable, this information may only be needed in the same request.
        self::$preallocatedusers[] = [$user, $tenantid, $component, $reason];
    }

    /**
     * Finds the tenant where the user should be allocated after creation
     *
     * @param \stdClass $newuser the user that is about to be created or a user that has just been created
     *     must have 'username' property
     * @param bool $removefrompreallocatedarray this is the last call to this function and the record should
     *     be removed from the self::$preallocatedusers
     * @return array|null if preallocation is found returns [$user, $tenantid, $component, $reason]
     *     where $user is the user object that was preallocated, $tenantid is the tenant where it should
     *     be preallocated and $component and $reason are the component and reason for pre-allocation
     *     (for example, manual creation, oauth sign up, email registration, etc)
     */
    protected static function find_user_preallocation(\stdClass $newuser,
                                                      bool $removefrompreallocatedarray = true): ?array {
        // First find in $preallocatedusers if there is a user with the same id.
        if (!empty($newuser->id) && $newuser->id > 0) {
            foreach (self::$preallocatedusers as $key => $entry) {
                list($user, $tenantid, $component, $reason) = $entry;
                if (!empty($user->id) && $user->id == $newuser->id) {
                    if ($removefrompreallocatedarray) {
                        unset(self::$preallocatedusers[$key]);
                    }
                    return $entry;
                }
            }
        }

        // Now search $preallocatedusers if there is a user with the same username.
        foreach (self::$preallocatedusers as $key => $entry) {
            list($user, $tenantid, $component, $reason) = $entry;
            if (empty($user->id) && !empty($user->username) && $user->username === $newuser->username) {
                if ($removefrompreallocatedarray) {
                    unset(self::$preallocatedusers[$key]);
                }
                return $entry;
            }
        }
        return null;
    }

    /**
     * Returns the tenant id where the user will be allocated after he is created
     *
     * @param \stdClass $newuser
     * @return int tenant id or 0 if no pre-allocation found
     */
    public static function guess_future_user_tenant(\stdClass $newuser): int {
        if ($entry = self::find_user_preallocation($newuser, false)) {
            return $entry[1];
        }
        return 0;
    }

    /**
     * Called from user_event observer, allocates the user to either previously specified tenant or to the default tenant
     *
     * @param user_created $event
     */
    public function allocate_user_on_creation(user_created $event) {
        $userid = $event->objectid;
        $user = $event->get_record_snapshot('user', $userid);

        if ($preallocation = self::find_user_preallocation($user)) {
            [$user, $tenantid, $component, $reason] = $preallocation;
            $this->allocate_user($userid, $tenantid, $component, $reason, true);
            return;
        }

        // No preallocation found, make sure the tenant user role is assigned.
        $tenantid = \tool_tenant\tenancy::get_tenant_id($userid);
        $this->assign_tenant_user_role($userid, $tenantid);
    }

    /**
     * Allocate the user to the tenant
     *
     * @param int $userid
     * @param int $tenantid
     * @param string $component component that called this method
     * @param string $reason
     * @param bool $triggercreatedevent trigger tenant_user_created event (only in cases when allocation happend right during
     *     user creation)
     */
    public function allocate_user(int $userid, int $tenantid, string $component, string $reason,
                                  bool $triggercreatedevent = false) {
        global $USER;
        if (isguestuser($userid)) {
            return;
        }
        $usertenant = tenant_user::create_for_user($userid);
        $oldtenantid = $usertenant->get('id') ? $usertenant->get('tenantid') : tenancy::get_default_tenant_id();
        $usertenant->set('tenantid', $tenantid);
        $usertenant->set('component', $component);
        $usertenant->set('reason', $reason);
        $usertenant->save();
        if (!$triggercreatedevent) {
            tenant_user_updated::create_from_object($usertenant, $oldtenantid)->trigger();
        } else {
            // This event is only triggered when user is allocated to a tenant during user creation.
            tenant_user_created::create_from_object($usertenant)->trigger();
        }
        // Check to see if this user has been assigned a tenant role.
        $this->assign_tenant_user_role($userid, $tenantid);

        if ($userid == $USER->id) {
            // Current user is moved, reset the mytenant cache.
            $cache = \cache::make('tool_tenant', 'mytenant');
            $cache->purge();
        } else if ($tenantid == tenancy::get_tenant_id()) {
            // The user is allocated into the current tenant, remember it in the cache.
            tenancy::mark_user_as_same_tenant($userid);
        } else if ($oldtenantid == tenancy::get_tenant_id() && $tenantid != tenancy::get_tenant_id()) {
            // The user was previously allocated to the current tenant but is now moved elsewhere,
            // remove them from the current tenant's cache.
            tenancy::mark_user_as_same_tenant($userid, false);
        }
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
        $currentadmins = $this->get_tenant_admins($tenantid);
        $adminstoremove = array_diff($currentadmins, $userids);
        self::unassign_tenant_admin_roles($adminstoremove, $tenantid);

        // Make sure all admins have roles assigned.
        self::assign_tenant_admin_roles($userids, $tenantid);
        \cache_helper::purge_by_event('changesincoursecat');
    }

    /**
     * Returns the users with the tenantadmin role for this tenant.
     *
     * @param  int    $tenantid The tenant ID.
     * @return array a list of user IDs of people with the tenantadmin role.
     */
    public function get_tenant_admins(int $tenantid) : array {
        global $DB;

        $adminroleid = self::get_tenant_admin_role();
        $users = $DB->get_fieldset_select('role_assignments', 'userid',
            'component = :component AND itemid = :itemid AND roleid = :roleid',
            ['component' => 'tool_tenant', 'itemid' => $tenantid, 'roleid' => $adminroleid]);
        return $users ? array_combine($users, $users) : [];
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
        $info->tenantselectorlogo = $this->get_file_draftid('tenantselectorlogo', $tenantid, $filemanageroptions);
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
        foreach (['headerlogo', 'loginlogo', 'tenantselectorlogo', 'loginbackground'] as $logoname) {
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
     * @return \moodle_url|null The favicon url.
     */
    public function get_favicon(int $tenantid): ?\moodle_url {
        $url = null;
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
     * Returns the url for the file from a given tenantid and filearea.
     *
     * @param  int    $tenantid The ID of the tenant to get the favicon for.
     * @param  string $filearea Name of the filearea to retrieve the file from.
     * @return string The url.
     */
    public function get_tenant_file_url(int $tenantid, string $filearea) : string {
        $url = '';
        $fs = \get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'tool_tenant', $filearea, $tenantid);
        foreach ($files as $file) {
            if ($file->get_filesize() > 0) {
                $url = \moodle_url::make_pluginfile_url(\context_system::instance()->id, 'tool_tenant', $filearea,
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
        $this->save_css_files($data->tenantselectorlogo, 'tenantselectorlogo', $tenantid);
        unset($data->tenantselectorlogo);
        $this->save_css_files($data->loginbackground, 'loginbackground', $tenantid);
        unset($data->loginbackground);
        $this->save_css_files($data->favicon, 'favicon', $tenantid);
        unset($data->favicon);
        $cssconfig = json_encode($data);
        $tenant = $this->update_tenant($tenant->get('id'), (object) ['cssconfig' => $cssconfig]);
    }

    /**
     * Resets the CSS config data.
     *
     * @param \stdClass $data reset appearance data. See reset_tenant_appearance_form.php for expected elements.
     */
    public function reset_css_config(\stdClass $data) {
        $tenant = $this->get_tenant($data->tenantid);
        $cssconfig = $tenant->get('cssconfig');
        $info = $cssconfig ? json_decode($cssconfig, true) : [];

        $resetconfigs = [
            'resetcss' => ['customcss'],
            'resetfooter' => ['footertext'],
            'resetcolours' => edit_css_form::get_colour_values(),
        ];
        foreach ($resetconfigs as $setting => $configs) {
            if (!empty($data->$setting)) {
                foreach ($configs as $config) {
                    $info[$config] = '';
                }
            }
        }

        if (!empty($data->resetimages)) {
            $this->remove_tenant_images($tenant->get('id'));
        }

        $this->update_tenant($tenant->get('id'), (object) ['cssconfig' => json_encode($info)]);
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
     * Generates and returns scss for the specific tenant
     *
     * @param int $tenantid
     * @return string
     */
    public function get_theme_scss(int $tenantid) : string {
        if (!$tenantid || !array_key_exists($tenantid, tenancy::get_tenants())
                || $tenantid == sharedspace::get_shared_space_id()) {
            $tenantid = tenancy::get_default_tenant_id();
        }

        $tenant = $this->get_tenant($tenantid);

        // Get images.
        $scss = $this->get_logo_css($tenantid);

        $storedcssconfig = @json_decode($tenant->get('cssconfig'), true);
        if (empty($storedcssconfig)) {
            return $scss;
        }

        // Get primary colors.
        foreach (edit_css_form::get_colour_values() as $key) {
            if (array_key_exists($key, $storedcssconfig) && strlen($storedcssconfig[$key])) {
                // TODO SP-363 just in case validate format again?
                // This shouldn't have !default added as this goes before all the theme scss.
                $scss .= '$' . $key . ': ' . $storedcssconfig[$key] . ';';
            }
        }

        // Get Custom SCSS.
        if (isset($storedcssconfig['customcss'])) {
            // Strip out the tabs and carriage returns.
            $scss .= str_replace(["\r\n", "\r", "\n"], '', $storedcssconfig['customcss']);
        }
        return $scss;
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
     * @deprecated since 3.8 use permission::can_create_users()
     *
     * @param  int $tenantid The current tenant ID.
     * @return bool True is this user can create users in the tenant.
     */
    public static function can_create_users($tenantid) : bool {
        debugging('This function is deprecated, please use permission class', DEBUG_DEVELOPER);
        return permission::can_create_users($tenantid);
    }

    /**
     * Can this user update users in the supplied tenant.
     *
     * @deprecated since 3.8 use permission::can_update_users()
     *
     * @param  int $tenantid The current tenant ID.
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_update_users($tenantid) : bool {
        debugging('This function is deprecated, please use permission class', DEBUG_DEVELOPER);
        return permission::can_update_users($tenantid);
    }

    /**
     * Can this user browse users in tenants. Will first check to see if the user can browse users in all tenants
     * and if not then check if they have the capability to browser users in the specified tenant (will use the default
     * tenant by default).
     *
     * @deprecated since 3.8 use permission::can_browse_users
     *
     * @param  int $currenttenantid The current tenant ID.
     * @return bool True is this user can browse users.
     */
    public static function can_browse_users(int $currenttenantid = 0) : bool {
        debugging('This function is deprecated, please use permission class', DEBUG_DEVELOPER);
        return permission::can_browse_users($currenttenantid);
    }

    /**
     * Can this user delete users in the supplied tenant.
     *
     * @deprecated since 3.8 use permission::can_delete_users
     *
     * @param  int $tenantid The current tenant ID.
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_delete_users($tenantid) : bool {
        debugging('This function is deprecated, please use permission class', DEBUG_DEVELOPER);
        return permission::can_delete_users($tenantid);
    }

    /**
     * Can this user suspend users in the supplied tenant.
     *
     * @deprecated since 3.8 use permission::can_suspend_users()
     *
     * @param  int $tenantid The current tenant ID.
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_suspend_users($tenantid) : bool {
        debugging('This function is deprecated, please use permission class', DEBUG_DEVELOPER);
        return permission::can_suspend_users($tenantid);
    }

    /**
     * Can user view "Roles" tab for the given tenant id
     *
     * @deprecated since 3.8 use permission::can_see_roles_tab
     *
     * @param int $currenttenantid
     * @return bool
     */
    public static function can_see_roles_tab(int $currenttenantid = 0) : bool {
        debugging('This function is deprecated, please use permission class', DEBUG_DEVELOPER);
        return permission::can_see_roles_tab($currenttenantid);
    }

    /**
     * Can the user move other users between different tenants.
     *
     * @deprecated since 3.8 use permission::can_move_users_between_tenants
     *
     * @return bool True if the user has permission to move users between tenants.
     */
    public static function can_move_users_between_tenants() : bool {
        debugging('This function is deprecated, please use permission class', DEBUG_DEVELOPER);
        return permission::can_move_users_between_tenants();
    }

    /**
     * Can the user edit the theme for tenants.
     *
     * @deprecated since 3.8 use permission::can_edit_tenant_themes
     *
     * @param int $tenantid
     * @return bool True if the user has permission to edit the themes of tenants, else false.
     */
    public static function can_edit_tenant_themes(int $tenantid = 0) : bool {
        debugging('This function is deprecated, please use permission class', DEBUG_DEVELOPER);
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
     * URL to view details page in the current tenant
     * @return \moodle_url
     */
    public static function get_details_url() : \moodle_url {
        return new \moodle_url('/admin/tool/tenant/details.php');
    }

    /**
     * URL to view users page in the current tenant
     * @return \moodle_url
     */
    public static function get_users_url() : \moodle_url {
        return new \moodle_url('/admin/tool/tenant/users.php');
    }

    /**
     * URL to view all users
     * @return \moodle_url
     */
    public static function get_allusers_url() : \moodle_url {
        return new \moodle_url('/admin/tool/tenant/allusers.php');
    }

    /**
     * URL to edit tenant dashboard
     *
     * @param int $tenantid
     * @return \moodle_url
     */
    public static function get_dashboard_url(int $tenantid = 0) : \moodle_url {
        return new \moodle_url('/admin/tool/tenant/editdashboard.php', $tenantid ? ['id' => $tenantid] : []);
    }

    /**
     * Returns role id for tenant admins, creates if it does not exist
     *
     * @return int
     */
    public static function get_tenant_admin_role() : int {
        global $CFG;
        self::create_tenant_roles();
        return (int)$CFG->tool_tenant_adminrole;
    }

    /**
     * Returns tenantmanager role, creates if it does not exist
     *
     * @return int
     */
    public static function get_tenant_manager_role() : int {
        global $CFG;
        self::create_tenant_roles();
        return (int)$CFG->tool_tenant_managerrole;
    }

    /**
     * Returns the tenantuser role, creates if it does not exist
     *
     * @return int
     */
    public static function get_tenant_user_role() : int {
        global $CFG;
        self::create_tenant_roles();
        return (int)$CFG->tool_tenant_userrole;
    }

    /**
     * List of default capabilities for the tenant admin.
     *
     * @return array A list of default capabilities.
     */
    protected static function get_tenant_admin_capabilities() : array {
        return array_keys(array_filter(role::get_tenant_admin_capabilities()));
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
                $rv['view'][] = $role->id;
            }
            if (in_array($role->shortname, ['student', 'teacher', 'editingteacher', 'guest'])) {
                $rv['switch'][] = $role->id;
            }
            if (in_array($role->shortname, ['user', 'guest', 'frontpage'])) {
                $rv['view'][] = $role->id;
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
            if (preg_match('/^tool_(program|certification|organisation|reportbuilder|dynamicrule)_/',
                    $role->shortname)) {
                $rv['assign'][] = $role->id;
                $rv['view'][] = $role->id;
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
            'moodle/category:viewcourselist', // Normally given to all users but in workplace we remove it from user/guest roles.
        ]);

        return array_unique($caps);
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
        }

        // Add capabilities to the role.
        foreach ($capabilities as $capability) {
            if (get_capability_info($capability)) {
                assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
            }
        }

        // Tenant roles can not be assigned manually.
        set_role_contextlevels($roleid, []);

        return $roleid;
    }


    /**
     * Adds plugin capabilities to the "Tenant administrator" role
     *
     * This function should only be called from the plugin's install.php
     *
     * @param string $pluginname
     */
    public static function add_plugin_capabilities_to_tenant_admin_role(string $pluginname) {
        global $CFG;
        if (empty($CFG->tool_tenant_adminrole)) {
            // The tenant administrator role has not been created yet. Nothing to do.
            return;
        }

        // Get list of capabilities that are whitelisted for this plugin.
        $capabilities = role::get_plugin_capabilities_for_tenant_admin_role($pluginname, true);
        if (!$capabilities) {
            // This plugin defines no safe capabilities for the "Tenant admninistrator" role. Nothing to do.
            return;
        }

        $roleid = $CFG->tool_tenant_adminrole;
        foreach ($capabilities as $capability => $allow) {
            if (get_capability_info($capability)) {
                assign_capability($capability, $allow, $roleid, \context_system::instance()->id, true);
            }
        }

        // Reset caches.
        accesslib_reset_role_cache();
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
        global $CFG;
        if (!empty($CFG->tool_tenant_adminrole) && !empty($CFG->tool_tenant_managerrole) && !empty($CFG->tool_tenant_userrole)) {
            // Already created.
            return;
        }

        // Tenant admin.
        $capabilities = self::get_tenant_admin_capabilities();
        $roleid = self::create_role('tool_tenant_admin', '',
            get_string('tenantadmindescription', 'tool_tenant'), $capabilities);
        set_config('tool_tenant_adminrole', $roleid);
        self::add_role_assignments($roleid, self::get_tenant_admin_roles_associations());

        // Tenant manager.
        $capabilities = self::get_tenant_manager_capabilities();
        $roleid = self::create_role('tool_tenant_manager', '',
            get_string('tenantmanagerdescription', 'tool_tenant'), $capabilities, 'manager',
            [CONTEXT_COURSECAT, CONTEXT_COURSE]);
        set_config('tool_tenant_managerrole', $roleid);
        self::add_role_assignments($roleid, self::get_tenant_manager_roles_associations());

        // Tenant user.
        $capabilities = self::get_tenant_user_capabilities();
        $roleid = self::create_role('tool_tenant_user', '',
            get_string('tenantuserdescription', 'tool_tenant'), $capabilities);
        set_config('tool_tenant_userrole', $roleid);

        // Reset caches.
        accesslib_reset_role_cache();

        // Create all workplace roles that were requested earlier.
        while (self::$delayedworkplaceroles) {
            $newrole = array_shift(self::$delayedworkplaceroles);
            self::create_workplace_role($newrole[0], $newrole[1], $newrole[2], $newrole[3]);
        }
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

        if (empty($CFG->tool_tenant_adminrole) || empty($CFG->tool_tenant_managerrole)) {
            // Admin roles have to be created first. The setup callbacks from the plugins may be executed in random order.
            self::$delayedworkplaceroles[] = [$shortname, $name, $description, $capabilities];
            return;
        }

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
        // Add associations with other roles.
        $roleassoc = self::get_tenant_admin_roles_associations();
        foreach ($roleassoc as $key => $roles) {
            $roleassoc[$key] = array_intersect($roles, [$roleid]);
        }
        self::add_role_assignments($CFG->tool_tenant_adminrole, $roleassoc);

        // Add associations with other roles.
        $roleassoc = self::get_tenant_manager_roles_associations();
        foreach ($roleassoc as $key => $roles) {
            $roleassoc[$key] = array_intersect($roles, [$roleid]);
        }
        self::add_role_assignments($CFG->tool_tenant_managerrole, $roleassoc);

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
            setcookie($cookiename, $tenantid, 0, $CFG->sessioncookiepath, $CFG->sessioncookiedomain,
                is_moodle_cookie_secure(), $CFG->cookiehttponly);
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
            $_COOKIE[$cookiename] = $tenantid;
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
             'roleid' => self::get_tenant_admin_role(),
             'userid' => $userid]);
    }

    /**
     * Footer text for a given tenant.
     *
     * @return string
     * @throws \moodle_exception
     */
    public static function get_footer_text(): string {
        $tenantid = \tool_tenant\tenancy::get_tenant_id();
        $tenantid = \tool_tenant\sharedspace::is_shared_space($tenantid) ?
            \tool_tenant\tenancy::get_default_tenant_id() : $tenantid;
        $footertext = '';
        if (!$tenantid || !array_key_exists($tenantid, tenancy::get_tenants())
            || $tenantid == sharedspace::get_shared_space_id()) {
            $tenantid = tenancy::get_default_tenant_id();
        }
        $tenant = new tenant($tenantid);
        $storedcssconfig = @json_decode($tenant->get('cssconfig'), true);
        // Include the footertext if set.
        if (isset($storedcssconfig['footertext'])) {
            $footertext = $storedcssconfig['footertext'];
        }
        return $footertext;
    }

    /**
     * Returns all possible tenants for login tenant selector.
     *
     * @return array
     */
    public function get_login_selector_tenants(): array {
        global $DB;

        $selectortenants = [];
        if (get_config('tool_tenant', 'showtenantselector')) {
            $defaultsite = $DB->get_record('course', ['category' => 0]);
            foreach ($this->get_tenants_without_shared() as $tenant) {
                if ($tenant->get('showinloginselector')) {
                    // Skip this tenant if it has no available login URLs.
                    if (empty($urls = $tenant->get_login_urls(false))) {
                        continue;
                    }
                    $tenantname = $tenant->get('sitename');
                    // Default sitename is used if tenant sitename is not defined.
                    if (empty($tenantname)) {
                        $tenantname = $defaultsite->fullname;
                    }
                    $tenant = [
                        'name' => external_format_string($tenantname, \context_system::instance()),
                        'url' => reset($urls),
                        'logourl' => $this->get_tenant_file_url($tenant->get('id'), 'tenantselectorlogo') ?:
                            $this->get_tenant_file_url($tenant->get('id'), 'loginlogo')

                    ];
                    $selectortenants[] = $tenant;
                }
            }
        }
        return $selectortenants;
    }
}
