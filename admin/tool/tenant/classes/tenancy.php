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
 * Class tenancy.
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use tool_tenant\form\edit_css_form;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

/**
 * To be used to get information about current tenant and its users
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenancy {

    /** @var int */
    protected static $forcetenantid = 0;

    /**
     * Gets the list of tenants
     *
     * @return \stdClass[]
     */
    public static function get_tenants() : array {
        global $DB;
        $cache = \cache::make('tool_tenant', 'tenants');
        if (!($tenants = $cache->get('list'))) {
            $tenants = $DB->get_records('tool_tenant', ['archived' => 0],
                'isdefault DESC, sortorder, id', 'id, name, idnumber, isdefault, sitename, categoryid, '.
                'siteshortname, useloginurlid, useloginurlidnumber, timemodified');
            $first = reset($tenants);
            if (!$tenants || !$first->isdefault) {
                // Create default tenant.
                $tenant = (new manager())->create_tenant((object)[
                    'name' => get_string('defaultname', 'tool_tenant'),
                    'isdefault' => 1]);
                $tenants = [$tenant->get('id') => $tenant->to_record()] + $tenants;
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
     * Id of the tenant user belongs to
     *
     * @param int $userid userid, if omitted current user
     * @return int
     */
    public static function get_tenant_id(?int $userid = null) : int {
        global $USER;

        if (!self::is_site_multi_tenant()) {
            return self::get_default_tenant_id();
        }

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

        $userid = $userid ?: ($USER ? $USER->id : 0);
        if ($userid == $USER->id) {
            $cache = \cache::make('tool_tenant', 'mytenant');
            $cacheidx = 'tenantid-' . $userid;
            if (!($tenantid = $cache->get($cacheidx))) {
                $tenantid = self::get_switched_tenant_id() ?: self::get_tenant_id_int($userid);
                $cache->set($cacheidx, $tenantid);
            }
            return $tenantid;
        }

        return self::get_tenant_id_int($userid);
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
     * Note 1: guest user is assumed to belong to the default tenant
     * Note 2: this function does not exclude deleted or suspended users
     *
     * @param bool $canseeall do not add tenant check if user has capability 'tool/tenant:manage'
     * @param bool $andpostfix append " AND " to the end of the query
     * @param string $useridfield field to join with
     * @param int $tenantid id of the tenant to filter or 0 for the current tenant
     * @return string
     */
    public static function get_users_subquery(bool $canseeall = true, bool $andpostfix = true,
                                              string $useridfield = 'u.id', int $tenantid = 0) : string {
        if (!self::is_site_multi_tenant()) {
            return $andpostfix ? '' : '1=1';
        }
        if (!self::$forcetenantid && $canseeall && permission::can_view_users_in_all_tenants()) {
            return $andpostfix ? '' : '1=1';
        }
        $tenantid = $tenantid ?: (self::$forcetenantid ?: self::get_tenant_id());
        $defaulttenantid = self::get_default_tenant_id();
        $tu = db::generate_alias();
        if ($tenantid != $defaulttenantid) {
            $query = " {$useridfield} IN (SELECT {$tu}.userid FROM {tool_tenant_user} {$tu}
                WHERE {$tu}.tenantid = {$tenantid})";
        } else {
            $query = " {$useridfield} NOT IN (SELECT {$tu}.userid FROM {tool_tenant_user} {$tu}
                WHERE {$tu}.tenantid <> $defaulttenantid)";
        }

        return $query . ($andpostfix ? ' AND' : '') . ' ';
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
        if (permission::can_view_users_in_all_tenants()) {
            return false;
        }
        return self::get_tenant_id($currentuserid) != self::get_tenant_id(is_object($user) ? $user->id : $user);
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
        $tenantid = key($tenants);
        return $tenantid;
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
        if (\tool_tenant\permission::can_switch_tenant()) {
            $preference = (int)get_user_preferences('currenttenantid');
            $preferencesession = get_user_preferences('currenttenantidsessionid');
            if ($preference && ($preferencesession !== session_id() || !array_key_exists($preference, self::get_tenants()))) {
                $preference = 0;
                self::set_switched_tenant_id($preference);
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
        set_user_preference('currenttenantid', $tenantid ?: null);
        set_user_preference('currenttenantidsessionid', $tenantid ? session_id() : null);
    }

    /**
     * Create the menu with tenants to switch.
     *
     * @param \core_renderer $renderer
     * @return string HTML containing the tenant menu.
     */
    public static function tenant_menu($renderer): string {
        global $PAGE, $CFG;
        $menu = '';
        if (!\tool_tenant\permission::can_switch_tenant()) {
            return $menu;
        }
        if (self::is_site_multi_tenant()) {
            $tenants = self::get_tenants();
            $url = new \moodle_url('/admin/tool/tenant/switchtenant.php', ['switchtenantid' => 0, 'sesskey' => sesskey()]);

            $currenturl = explode('/', $PAGE->url->out_as_local_url());
            $wptools = array('certificate', 'certification', 'dynamicrule', 'organisation', 'program', 'reportbuilder', 'tenant');
            if (count($currenturl) >= 4 &&
                    ($currenturl[1] == $CFG->admin) && ($currenturl[2] == 'tool') && in_array($currenturl[3], $wptools)) {
                $url->param('redirecturl', '/admin/tool/' . $currenturl[3] . '/index.php');
            }

            $currenttenantid = self::get_tenant_id();
            $menu = ['haschildren' => [
                'url' => $url->out(),
                'text' => $tenants[$currenttenantid]->name,
                'children' => []
            ]];
            foreach ($tenants as $t) {
                $url->param('switchtenantid', $t->id);
                $menu['haschildren']['children'][] = [
                    'url' => $url->out(),
                    'text' => $t->name
                ];
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
}
