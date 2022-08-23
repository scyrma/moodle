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
 * Class tenancy.
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use Matrix\Exception;
use tool_tenant\form\edit_css_form;
use tool_wp\db;

/**
 * To be used to get information about current tenant and its users
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenancy {

    /** @var int */
    protected static $forcetenantid = 0;
    /**
     * Maximum number of tenants in menu dropdown.
     */
    const MAX_TENANTS = 10;

    /**
     * Gets the list of tenants
     *
     * @return \stdClass[]
     */
    public static function get_tenants() : array {
        global $DB;
        $cache = \cache::make('tool_tenant', 'tenants');
        if (!($tenants = $cache->get('list'))) {
            if (get_config('tool_tenant', 'version') >= 2021070900) {
                hierarchy::fix_hierarchy_paths();
                $tenants = $DB->get_records('tool_tenant', ['archived' => 0],
                    'isdefault DESC, sortorder, id', 'id, name, idnumber, isdefault, sitename, categoryid, '.
                    'siteshortname, useloginurlid, useloginurlidnumber, showinloginselector, dashboardlinked, '.
                    'timemodified, parentid, path');
            } else if (get_config('tool_tenant', 'version') >= 2021030402) {
                // In the version 2021070900 we added new field "dashboardlinked". During upgrade retrieve tenant
                // information without dashboardlinked or otherwise this query throws an exception.
                hierarchy::fix_hierarchy_paths();
                $tenants = $DB->get_records('tool_tenant', ['archived' => 0],
                    'isdefault DESC, sortorder, id', 'id, name, idnumber, isdefault, sitename, categoryid, '.
                    'siteshortname, useloginurlid, useloginurlidnumber, showinloginselector, 1 AS dashboardlinked, '.
                    'timemodified, parentid, path');
            } else {
                // In the version 2020090202 we added new fields "parentid, path, depth". During upgrade retrieve tenant
                // information without parent or otherwise this query throws an exception. Tenants list is retrieved
                // during the upgrade process (to build CSS and to display the site name).
                // In the version 2021030402 we added new field "showinloginselector". Tenants list is retrieved
                // in login page, before upgrading, to generate the tenant selector. Retrieve tenants list without
                // "showinloginselector".
                $tenants = $DB->get_records('tool_tenant', ['archived' => 0],
                    'isdefault DESC, sortorder, id', 'id, name, idnumber, isdefault, sitename, categoryid, '.
                    'siteshortname, useloginurlid, useloginurlidnumber, 0 as showinloginselector, 1 AS dashboardlinked, '.
                    'timemodified, null AS parentid');
            }
            $first = reset($tenants);
            if (!$tenants || !$first->isdefault) {
                // Create default tenant.
                $tenant = (new manager())->create_tenant((object)[
                    'name' => get_string('defaultname', 'tool_tenant'),
                    'isdefault' => 1]);
                $tenants = [$tenant->get('id') => $tenant->to_record()] + $tenants;
            }
            if ($sharedid = sharedspace::get_shared_space_id()) {
                $tenants[$sharedid]->name = get_string('sharedspace', 'tool_tenant');
            }
            // Calculate which tenants have children (they will see the "Tenant" column in some reports/listings).
            foreach ($tenants as $tenant) {
                $tenants[$tenant->id]->haschildren = $tenants[$tenant->id]->haschildren ?? false;
                if ($tenant->parentid) {
                    $tenants[$tenant->parentid]->haschildren = true;
                }
            }
            $cache->set('list', $tenants);
        }
        return $tenants;
    }

    /**
     * Check if site is configured to have multiple tenants
     *
     * @return bool
     */
    public static function is_site_multi_tenant() : bool {
        $tenants = self::get_tenants();
        return count($tenants) > 1;
    }

    /**
     * Does an SQL query to retrieve tenant id for the given user
     *
     * @param int $userid
     * @return int
     */
    protected static function get_tenant_id_int(int $userid) : int {
        global $DB;
        $tenantid = $DB->get_field_sql("SELECT t.id
            FROM {tool_tenant_user} tu
            JOIN {tool_tenant} t ON tu.tenantid = t.id AND t.archived = 0
            WHERE tu.userid = ?", [$userid]);
        if ($tenantid) {
            return $tenantid;
        }
        return self::get_default_tenant_id();
    }

    /**
     * Returns tenant ids for the list of users
     *
     * @param array $userids
     * @return array array with userid as the index and tenantid as the value
     */
    public static function get_tenant_ids_bulk(array $userids): array {
        global $DB;
        if (empty($userids)) {
            return [];
        }
        $userids = array_unique(array_map(function($id) {
            return clean_param($id, PARAM_INT);
        }, $userids));
        [$sql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Normally we'd add as a parameter to the query, but Oracle doesn't like that. Given it's an INT we can add it safely.
        $defaulttenantid = self::get_default_tenant_id();

        return $DB->get_records_sql_menu("SELECT u.id, COALESCE(t.id, {$defaulttenantid}) AS tenantid
            FROM {user} u
            LEFT JOIN {tool_tenant_user} tu ON tu.userid = u.id
            LEFT JOIN {tool_tenant} t ON tu.tenantid = t.id AND t.archived = 0
            WHERE u.id " . $sql, $params);
    }

    /**
     * Id of the tenant user belongs to
     * For the current user who can switch between the tenants it will return the tenant user has switched to
     *
     * @param int $userid userid, if omitted current user
     * @return int
     */
    public static function get_tenant_id(?int $userid = null) : int {
        global $USER;

        if (!self::is_site_multi_tenant()) {
            return self::get_default_tenant_id();
        }

        // User is not logged in.
        if ($userid === null && (!isloggedin() || isguestuser())) {
            if (($tenantid = optional_param('tenantid', 0, PARAM_INT)) &&
                    ($tenantid = self::find_tenant_by_id($tenantid))) {
                return $tenantid;
            }
            if (($tenantidnumber = optional_param('tenant', null, PARAM_RAW)) &&
                    ($tenantid = self::find_tenant_by_idnumber($tenantidnumber))) {
                return $tenantid;
            }
            if ($tenantid = manager::get_tenant_cookie()) {
                return $tenantid;
            }
            return self::get_default_tenant_id();
        }

        // User is logged in.
        $userid = $userid ?: ($USER ? $USER->id : 0);
        $cache = \cache::make('tool_tenant', 'mytenant');

        // First make sure that the tenant for the current user is in the cache.
        $cacheidx = 'tenantid_' . $USER->id;
        if (!($mytenantid = $cache->get($cacheidx))) {
            $mytenantid = self::get_switched_tenant_id() ?: self::get_tenant_id_int($USER->id);
            $cache->set($cacheidx, $mytenantid);
        }

        // Requesting tenant for the current user.
        if ($userid == $USER->id) {
            return $mytenantid;
        }

        // Requesting tenant for another user.
        $otherusers = $cache->get('otherusers_'.$mytenantid);
        $otherusers = is_array($otherusers) ? $otherusers : [];
        if (in_array($userid, $otherusers)) {
            return $mytenantid;
        }

        $usertenantid = self::get_tenant_id_int($userid);
        if ($usertenantid == $mytenantid) {
            $otherusers[] = $userid;
            $cache->set('otherusers_'.$mytenantid, $otherusers);
        }
        return $usertenantid;
    }

    /**
     * This gets the actual tenant id for the user from the db
     * for the current user that can switch between tenants
     * ,not the one they have currently switched to
     *
     * @param int $userid
     * @return int
     */
    public static function get_actual_tenant_id(?int $userid = null): int {
        global $USER;
        $userid = $userid ?: $USER->id;
        // We only care about the one they originally belong to.
        if ($userid == $USER->id && permission::can_switch_tenant()) {
            return self::get_tenant_id_int($userid);
        }
        return self::get_tenant_id($userid);
    }
    /**
     * Tenant name for situations when we need to display it. Only use for users who can access this information!
     *
     * @param int $tenantid
     * @param bool $formatted
     * @return string|null
     */
    public static function get_tenant_name_from_id(int $tenantid, bool $formatted = true): ?string {
        $tenant = self::get_tenants()[$tenantid] ?? null;
        if ($tenant === null) {
            return null;
        }
        return $formatted ?
            format_string($tenant->name, true, ['context' => \context_system::instance()->id, 'escape' => false]) :
            $tenant->name;
    }

    /**
     * In some cases we already know that some other user belongs to the same tenant, mark it as such to reduce queries elsewhere.
     *
     * @param int $userid
     * @param bool $belongstothesametenant if true marks that the user has the same tenant as the current user, if false marks that
     *     the user's tenant is different
     */
    public static function mark_user_as_same_tenant(int $userid, bool $belongstothesametenant = true) {
        if (!self::is_site_multi_tenant()) {
            return;
        }
        $cache = \cache::make('tool_tenant', 'mytenant');
        $mytenantid = self::get_tenant_id();

        $otherusers = $cache->get('otherusers_'.$mytenantid);
        $otherusers = is_array($otherusers) ? $otherusers : [];
        if ($belongstothesametenant && !in_array($userid, $otherusers)) {
            $otherusers[] = $userid;
            $cache->set('otherusers_'.$mytenantid, $otherusers);
        } else if (!$belongstothesametenant && in_array($userid, $otherusers)) {
            $cache->set('otherusers_'.$mytenantid, array_diff($otherusers, [$userid]));
        }
    }

    /**
     * Find a tenant that has a given id and also has 'useloginurlid' enabled
     *
     * @param int $value
     * @return int
     */
    protected static function find_tenant_by_id(int $value) {
        $tenants = self::get_tenants();
        foreach ($tenants as $tenant) {
            if ($tenant->useloginurlid && $tenant->id == $value) {
                return $tenant->id;
            }
        }
        return 0;
    }

    /**
     * Find a tenant that has a given idnumber and also has 'useloginurlidnumber' enabled
     *
     * @param string $value
     * @return int
     */
    protected static function find_tenant_by_idnumber(string $value) {
        $tenants = self::get_tenants();
        foreach ($tenants as $tenant) {
            if ($tenant->useloginurlidnumber && $tenant->idnumber === $value) {
                return $tenant->id;
            }
        }
        return 0;
    }

    /**
     * Get tenant details by course category id
     *
     * @param int $value
     * @return tenant|null
     */
    public static function find_tenant_by_category_id(int $value): ?tenant {
        return ($tenant = tenant::get_record(['categoryid' => $value])) ? $tenant : null;
    }

    /**
     * Helps to build SQL to retrieve users that belong to the current tenant
     *
     * Example of usage:
     *
     * $ualias = \tool_wp\db::generate_alias();
     * list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql($ualias);
     * $sql = "SELECT {$ualias}.* FROM {user} {$ualias} " . $join . ' WHERE ' . $where;
     * $DB->get_records_sql($sql, $params);
     *
     * This query never returns deleted users or guest user.
     *
     * @param string $usertablealias
     * @param int $tenantid tenant id, by default tenant of the current user
     * @return array array of three elements [$join, $where, $params]
     */
    public static function get_users_sql(string $usertablealias = 'u', int $tenantid = 0) : array {
        global $CFG;
        static $cnt = 0;
        $cnt++;
        $pg = db::generate_param_name();
        $params = [$pg => (int)$CFG->siteguest];
        $where = " {$usertablealias}.deleted = 0 AND {$usertablealias}.id <> :{$pg} ";
        $join = '';

        $tenants = self::get_tenants();
        if (count($tenants) > 1) {
            $param = db::generate_param_name();
            $tu = db::generate_alias();
            $t = db::generate_alias();
            $params[$param] = $tenantid ?: self::get_tenant_id();
            if ($params[$param] == self::get_default_tenant_id()) {
                $join = " LEFT JOIN {tool_tenant_user} {$tu} ON {$tu}.userid = {$usertablealias}.id " .
                    "LEFT JOIN {tool_tenant} {$t} ON {$t}.id = {$tu}.tenantid AND {$t}.archived = 0";
                $where .= " AND ({$t}.id IS NULL OR {$t}.id = :{$param}) ";
            } else {
                $join = " JOIN {tool_tenant_user} {$tu} ON {$tu}.userid = {$usertablealias}.id AND {$tu}.tenantid = :{$param} ";
            }
        }
        return [$join, $where, $params];
    }

    /**
     * Tenant Search SQL used by Autocomplete. Matches on tenant name only
     *
     * @param string $search
     * @param string $t tool_tenant table alias
     * @param bool $searchanywhere
     * @param array $extrafields Unused
     * @param array|null $exclude
     * @param array|null $includeonly Unused
     * @return array
     */
    public static function tenants_search_sql($search, $t = 't', $searchanywhere = true, array $extrafields = array(),
                                              array $exclude = null, array $includeonly = null) {
        global $DB;

        $params = array();
        $tests = array();

        if ($t) {
            $t .= '.';
        }

        if ($search) {
            if ($searchanywhere) {
                $searchparam = '%' . $search . '%';
            } else {
                $searchparam = $search . '%';
            }

            $param1 = \tool_wp\db::generate_param_name();
            $tests[] = $DB->sql_like($t . 'name', ":{$param1}", false, false);
            $params[$param1] = $searchparam;
        } else {
            // In case there are no tests, add one result (this makes it easier to combine
            // this with an existing query as you can always add AND $sql).
            $tests[] = '1 = 1';
        }

        // If we are being asked to exclude any tenants, do that.
        if (!empty($exclude)) {
            [$tenanttest, $tenantparams] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'ex', false);
            $tests[] = $t . 'id ' . $tenanttest;
            $params = array_merge($params, $tenantparams);
        }
        // Combing the conditions and return.
        return array(implode(' AND ', $tests), $params);
    }
    /**
     * Allows to temporarily "fix" the tenant id in get_users_subquery() calls
     *
     * @param int $tenantid
     */
    public static function force_tenantid_for_users_subquery(int $tenantid = 0) {
        self::$forcetenantid = $tenantid;
    }

    /**
     * Builds SQL to use in WHERE clause to filter users that belong to the specific tenant
     *
     * Note 1: guest user is never returned (Note that before Moodle 3.10 guest was assumed to belong to the default tenant)
     * Note 2: this function does not exclude deleted or suspended users
     *
     * @param bool $canseeall do not add tenant check if user has capability 'tool/tenant:manage'
     * @param bool $andpostfix append " AND " to the end of the query
     * @param string $useridfield field to join with
     * @param int $tenantid id of the tenant to filter or 0 for the current tenant
     * @param bool $withsubtenants include users from sub-tenants
     * @return string
     */
    public static function get_users_subquery(bool $canseeall = true, bool $andpostfix = true,
                                      string $useridfield = 'u.id', int $tenantid = 0, bool $withsubtenants = true) : string {
        global $DB, $CFG;
        $basesql = " $useridfield <> ".((int)$CFG->siteguest);
        if (!self::is_site_multi_tenant()) {
            return $basesql . ($andpostfix ? ' AND ' : ' ');
        }
        if (!self::$forcetenantid && $canseeall && permission::can_view_users_in_all_tenants()) {
            return $basesql . ($andpostfix ? ' AND ' : ' ');
        }
        $tenantid = $tenantid ?: (self::$forcetenantid ?: self::get_tenant_id());

        // Shortcut for shared space (to be removed later when we no longer have shared space).
        if (sharedspace::is_shared_space($tenantid)) {
            if ($withsubtenants) {
                return $basesql . ($andpostfix ? ' AND ' : ' ');
            } else {
                // There are no users in the shared tenant, shortcut.
                return $andpostfix ? ' 1=0 AND ' : ' 1=0 ';
            }
        }

        $defaulttenantid = self::get_default_tenant_id();
        $tu = db::generate_alias();
        $tenant = self::get_tenants()[$tenantid];
        $withsubtenants = $withsubtenants && $tenant->haschildren;
        if (!$withsubtenants && $defaulttenantid != $tenantid) {
            // Users who belong to a specific tenant (not default) without subtenants - the easiest case.
            $query = " {$useridfield} IN (SELECT {$tu}.userid FROM {tool_tenant_user} {$tu}
                WHERE {$tu}.tenantid = {$tenantid})";
        } else if (!$withsubtenants && $defaulttenantid == $tenantid) {
            // Users who belong to default tenant without subtenants.
            $tt = db::generate_alias();
            $query = " {$useridfield} NOT IN (SELECT {$tu}.userid FROM {tool_tenant_user} {$tu}
                JOIN {tool_tenant} {$tt} ON {$tu}.tenantid = {$tt}.id AND {$tt}.archived = 0
                WHERE {$tu}.tenantid <> $defaulttenantid)";
        } else {
            // Users in any tenant with subtenants.
            $tp = db::generate_alias();
            $pathlike = preg_replace('/:XYZ/', "'{$tenant->path}/%'", $DB->sql_like("{$tp}.path", ":XYZ"));
            $includedefault = $defaulttenantid == $tenantid || hierarchy::is_subtenant_of($defaulttenantid, $tenantid);
            $query = " {$useridfield} IN (SELECT {$tu}.userid FROM {tool_tenant_user} {$tu} " .
                "JOIN {tool_tenant} {$tp} on {$tu}.tenantid = {$tp}.id
                WHERE {$tp}.id = {$tenantid} OR ($pathlike))";
            if ($includedefault) {
                // If default tenant is part of this hierarchy, also add all users who do not belong to any active tenants.
                $tt = db::generate_alias();
                $query = "( $query OR {$useridfield} NOT IN (SELECT {$tu}.userid FROM {tool_tenant_user} {$tu}
                JOIN {tool_tenant} {$tt} ON {$tu}.tenantid = {$tt}.id AND {$tt}.archived = 0
                WHERE {$tu}.tenantid <> $defaulttenantid))";
            }
        }

        return $basesql . ' AND ' . $query . ($andpostfix ? ' AND' : '') . ' ';
    }

    /**
     * Returns if user should not be visible to the current user at all because of multitenancy
     *
     * To use in core hacks:
     * component_class_callback('tool_tenant\\tenancy', 'is_user_hidden_by_tenancy', [$user]);
     *
     * @param int|\stdClass $user
     * @param int|null $currentuserid by default current user
     * @return bool
     */
    public static function is_user_hidden_by_tenancy($user, $currentuserid = null): bool {
        if (permission::can_view_users_in_all_tenants($currentuserid)) {
            return false;
        }
        $usertenantid = self::get_tenant_id(is_object($user) ? $user->id : $user);
        $currenttenantid = self::get_tenant_id($currentuserid);
        return $usertenantid != $currenttenantid &&
            !hierarchy::is_subtenant_of($usertenantid, $currenttenantid);
    }

    /**
     * Returns the users with the tenantadmin role for this tenant.
     *
     * @deprecated since 3.8
     *
     * @param  int    $tenantid The tenant ID.
     * @return array a list of user IDs of people with the tenantadmin role.
     */
    public static function get_tenant_admins(int $tenantid) : array {
        debugging('This method is deprecated, please use manager::get_tenant_admins()');
        return (new manager())->get_tenant_admins($tenantid);
    }

    /**
     * Returns the default tenant in the system, all unallocated users belong to this tenant
     *
     * @return int
     */
    public static function get_default_tenant_id() : int {
        $tenants = self::get_tenants();
        return key($tenants);
    }

    /**
     * Return site name for the current tenant (without applying format_string)
     *
     * @return string
     */
    public static function get_site_name() : ?string {
        $tenant = self::get_tenants()[self::get_tenant_id()];
        return $tenant->sitename;
    }

    /**
     * Generates and returns scss for the specific tenant
     *
     * @deprecated since 3.8
     *
     * @param int $tenantid
     * @return string
     */
    public static function get_theme_scss(int $tenantid) : string {
        return (new manager())->get_theme_scss($tenantid);
    }

    /**
     * Returns the tenant id for the curren user if it was switched
     *
     * @return int
     */
    protected static function get_switched_tenant_id(): int {
        global $SESSION;
        if (\tool_tenant\permission::can_switch_tenant()) {
            $preference = !empty($SESSION->tenantid) ? $SESSION->tenantid : 0;
            if ($preference && !array_key_exists($preference, self::get_tenants())) {
                $preference = 0;
                $SESSION->tenantid = 0;
            }
            return $preference;
        }
        return 0;
    }

    /**
     * Set the selected tenant id for current user.
     *
     * @param int $tenantid (set to zero to return to actual tenantid)
     */
    public static function set_switched_tenant_id(int $tenantid) {
        global $SESSION;
        $SESSION->tenantid = $tenantid;
        if (($switchedtenantid = self::get_switched_tenant_id()) && $switchedtenantid == $tenantid) {
            config::pop_all();
            \cache::make('tool_tenant', 'mytenant')->purge();
            config::push_for_tenant($switchedtenantid);
        }
    }

    /**
     * Get Tenantswitch URL used by autocomplete and tenant menu.
     *
     * @param int $tenantid
     * @param \moodle_url|null $redirecturl optional parameter where to redirect after switching, if not specified, we will
     *     try to find the best possible place to redirect
     * @return \moodle_url
     */
    public static function get_tenantswitch_url(int $tenantid, ?\moodle_url $redirecturl = null) {
        global $PAGE, $CFG;
        $url = new \moodle_url('/admin/tool/tenant/switchtenant.php', ['switchtenantid' => $tenantid, 'sesskey' => sesskey()]);
        if ($redirecturl) {
            $url->param('redirecturl', $redirecturl->out_as_local_url(false));
            return $url;
        }

        $pageurltrimmed = new \moodle_url($PAGE->url);
        $pageurltrimmed->set_anchor(null);
        $pageurltrimmed->remove_all_params();

        $currenturl = explode('/', $pageurltrimmed->out_as_local_url());
        $wptools = array('certificate', 'certification', 'dynamicrule', 'organisation', 'program', 'reportbuilder', 'tenant');
        if (count($currenturl) >= 4 &&
            ($currenturl[1] == $CFG->admin) && ($currenturl[2] == 'tool') && in_array($currenturl[3], $wptools)) {
            $url->param('redirecturl', '/' . $CFG->admin . '/tool/' . $currenturl[3] . '/index.php');
        } else if ($pageurltrimmed->out_as_local_url(false, []) === '/'.$CFG->admin.'/tool/wp/exportimport.php') {
            $url->param('redirecturl', '/' . $CFG->admin . '/tool/wp/exportimport.php');
        }
        return $url;
    }

    /**
     * Create the menu with tenants to switch.
     *
     * @param \core_renderer $renderer
     * @return string HTML containing the tenant menu.
     */
    public static function tenant_menu($renderer): string {
        global $PAGE;
        $menu = '';
        if (!\tool_tenant\permission::can_switch_tenant()) {
            return $menu;
        }
        if (self::is_site_multi_tenant()) {
            $tenants = self::get_tenants();
            $sharedid = sharedspace::get_shared_space_id();

            // Before offering to create the shared space, ensure user is able to create tenants while observing limit.
            $cancreatetenant = permission::can_create_tenant();

            if ($sharedid > 0) {
                // Shared tenant always first.
                $tenants = [$sharedid => $tenants[$sharedid]] + array_diff_key($tenants, [$sharedid => 1]);
            } else if (sharedspace::show_shared_space_in_switch_operations() && $cancreatetenant) {
                // Aad dummy entry for Shared Space.
                $sharedtenant = new \stdClass();
                $sharedtenant->id = 0;
                $sharedtenant->name = get_string('sharedspace', 'tool_tenant');
                $tenants = [$sharedtenant->id => $sharedtenant] + array_diff_key($tenants, [$sharedtenant->id => 1]);
            }
            $currenttenantid = self::get_tenant_id();
            $url = self::get_tenantswitch_url($currenttenantid);
            $menu = ['haschildren' => [
                'url' => $url->out(false),
                'text' => $tenants[$currenttenantid]->name,
                'children' => []
            ]];
            foreach ($tenants as $t) {
                // Prevent showing current tenant id in menu.
                if ($t->id == $currenttenantid) {
                    continue;
                }
                $url->param('switchtenantid', $t->id);
                $menu['haschildren']['children'][] = [
                    'url' => $url->out(),
                    'text' => $t->name
                ];
            }

            // If shared space is not enabled and user can create a new tenant, then load JS module to enable shared space.
            if (sharedspace::show_shared_space_in_switch_operations() && $sharedid === null && $cancreatetenant) {
                $sharedspaceurl = new \moodle_url($menu['haschildren']['children'][0]['url']);
                $PAGE->requires->js_call_amd('tool_tenant/switch', 'disablesharedspacereminder',
                    [$sharedspaceurl->out(false)]);
            }

            // If the number of tenants are more than x , just give the first tenant name with a  font-awesome arrow sign.
            if (count($tenants) > self::MAX_TENANTS) {
                $issharedspace = sharedspace::is_shared_space();

                // The second param to switch "init" method controls visibility of "Go to Shared space" button (false = show).
                $PAGE->requires->js_call_amd('tool_tenant/switch', 'init', [
                    $url->out_omit_querystring(false),
                    $issharedspace || ($sharedid === null && !$cancreatetenant),
                ]);

                $menu['haschildren'] = false;
                $menu['text'] = $tenants[$currenttenantid]->name . " " .
                    $renderer->pix_icon('arrow-circle-right', get_string('switchtenant', 'tool_tenant'), 'tool_wp');
                $menu['url'] = self::get_tenantswitch_url($currenttenantid)->out(false);
            }
            $menu = $renderer->render_from_template('core/custom_menu_item', $menu);
        }
        return $menu;
    }

    /**
     * Is this course inside the category of this tenant?
     *
     * @param \stdClass $course
     * @param int $tenantid
     * @return bool
     */
    protected static function is_course_inside_tenant_category(\stdClass $course, ?int $tenantid = null) {
        $currenttenant = self::get_tenants()[$tenantid ?: self::get_tenant_id()];
        $categoryid = $currenttenant->categoryid;
        if (!$categoryid) {
            return false;
        }
        // Quick check.
        if ($course->category == $categoryid) {
            return true;
        }
        // Longer check (for subcategories).
        $parentcontexts = \context_course::instance($course->id)->get_parent_context_ids();
        $categorycontextid = \context_coursecat::instance($categoryid)->id;
        return in_array($categorycontextid, $parentcontexts);
    }

    /**
     * Detects if a course belongs to a different tenant or to no tenant at all
     *
     * This means that we need to try to use separate groups when enrolling users from this tenant
     *
     * @param \stdClass $course
     * @param int $tenantid
     * @return bool false - this is not multitenant site OR the given course belongs to this tenant's category
     *      true - otherwise
     */
    public static function is_shared_course(\stdClass $course, ?int $tenantid = null): bool {
        return self::is_site_multi_tenant() && !self::is_course_inside_tenant_category($course, $tenantid);
    }

    /**
     * Returns a groupid to use for enrolment of a user in a course
     *
     * @param \stdClass $course
     * @param string $defaultgroupname
     * @param int|null $tenantid
     * @param null|string $component
     * @param null|string $area
     * @param int|null $itemid
     * @return int|mixed
     */
    public static function get_course_group(\stdClass $course, string $defaultgroupname, ?int $tenantid, ?string $component,
            ?string $area, ?int $itemid) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/group/lib.php');

        if (!$tenantid && !$component) {
            return 0;
        }

        $params = [
            'courseid' => $course->id,
            'tenantid' => $tenantid ?: null,
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
        ];

        if ($tenantgroup = tenant_group::get_record($params)) {
            // Make sure the group actually exists.
            if (!$DB->record_exists('groups', ['id' => $tenantgroup->get('groupid'), 'courseid' => $course->id])) {
                $tenantgroup->delete();
                $tenantgroup = false;
            }
        }

        if (!$tenantgroup) {
            // Create a new group for this tenant.
            $groupid = groups_create_group((object)['courseid' => $course->id,
                'name' => $defaultgroupname]);
            $tenantgroup = new tenant_group(0, (object)($params + ['groupid' => $groupid]));
            $tenantgroup->save();

            if ($course->defaultgroupingid && $DB->record_exists('groupings', ['id' => $course->defaultgroupingid])) {
                groups_assign_grouping($course->defaultgroupingid, $groupid);
            }
        }

        return $tenantgroup->get('groupid');
    }

    /**
     * Adds plugin capabilities to the "Tenant administrator" role
     *
     * This function should only be called from the plugin's install.php
     *
     * @param string $pluginname
     */
    public static function add_plugin_capabilities_to_tenant_admin_role(string $pluginname) {
        manager::add_plugin_capabilities_to_tenant_admin_role($pluginname);
    }

    /**
     * Checks if tenanturl has been passed to WS and loads tenant config settings for specified tenant
     *
     * @param string $functionname
     * @param array $parameters
     */
    public static function load_tenant_config_from_tenant_url(string $functionname, array $parameters): void {
        if (($functionname === 'tool_mobile_get_public_config'
                || $functionname === 'auth_email_signup_user'
                || $functionname === 'auth_email_get_signup_settings')
            && isset($parameters['tenanturl'])) {

            $query = parse_url($parameters['tenanturl'], PHP_URL_QUERY);
            parse_str($query, $tenantparams);

            $tenantid = 0;
            if (isset($tenantparams['tenantid'])) {
                $tenantid = self::find_tenant_by_id($tenantparams['tenantid']);
            } else if (isset($tenantparams['tenant'])) {
                $tenantid = self::find_tenant_by_idnumber($tenantparams['tenant']);
            }

            if ($tenantid > 0) {
                if ($functionname === 'auth_email_signup_user') {
                    // Mark user tenant so that event observer to user_created event allocates him.
                    manager::preallocate_new_user((object)$parameters, $tenantid, 'tool_tenant', 'manual');
                }

                // Load auth settings for this tenant.
                config::push_for_tenant($tenantid);
            }
        }
    }
}
