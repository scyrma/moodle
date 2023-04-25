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
 * Class role
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

/**
 * Class role, various methods to work with preset tenant roles
 *
 * Not external API.
 *
 * Use {@see \tool_tenant\tenancy} to get information about current tenant and its users
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class role {

    /**
     * Return default names for the tenant roles that should be used if the name is not specified
     *
     * Used in function role_get_name() in lib/accesslib.php
     *
     * @param \stdClass $role
     * @return mixed
     */
    public static function get_default_role_name($role) {
        if ($role->id == manager::get_tenant_admin_role()) {
            return get_string('tenantadmin', 'tool_tenant');
        }
        if ($role->id == manager::get_tenant_manager_role()) {
            return get_string('tenantmanager', 'tool_tenant');
        }
        if ($role->id == manager::get_tenant_user_role()) {
            return get_string('tenantuser', 'tool_tenant');
        }
        return $role->shortname;
    }

    /**
     * Returns the class name to use for editing tenant roles
     *
     * This is used in admin/roles/define.php
     *
     * @return define_role_table|null
     */
    public static function get_definition_table(): ?define_role_table {
        $action = optional_param('action', 'view', PARAM_ALPHA);
        if ($action === 'view') {
            return null;
        }
        $roleid = optional_param('roleid', 0, PARAM_INT);
        $roles = [manager::get_tenant_admin_role(),
            manager::get_tenant_manager_role(),
            manager::get_tenant_user_role()];
        if (!in_array($roleid, $roles)) {
            return null;
        }

        $showadvanced = get_user_preferences('definerole_showadvanced', false);
        return new define_role_table($roleid, $showadvanced);
    }

    /**
     * The list of capabilities that are considered "safe" for the tenant administrator role
     *
     * This means that users who have these capabilities in the system context:
     * - can browse/search all users (for example in the user pickers) but only users in their tenant
     * - can not change any settings that would affect other tenants
     * - can not create any content that would be visible to other tenants
     *
     * Core capabilities may only be added to this function and we must ensure that the core
     * is modified respectfully.
     *
     * Other plugins may define the callback "tenant_admin_capabilities()" where they list
     * safe capabilities defined in this plugin.
     *
     * @return array A list of default capabilities.
     */
    public static function get_tenant_admin_capabilities() : array {
        $capabilities = [
            'moodle/site:configview' => CAP_ALLOW,
            'tool/tenant:browseusers' => CAP_ALLOW,
            'tool/tenant:managetheme' => CAP_ALLOW,
            'tool/tenant:managedashboard' => CAP_ALLOW,
            'tool/tenant:manageusers' => CAP_ALLOW,
            'moodle/role:assign' => CAP_ALLOW,
            'moodle/site:uploadusers' => CAP_ALLOW,
            'moodle/site:viewuseridentity' => CAP_ALLOW,
            'moodle/site:doclinks' => CAP_ALLOW,
            'moodle/badges:awardbadge' => CAP_ALLOW,
            'moodle/badges:viewawarded' => CAP_ALLOW,
            'moodle/user:viewalldetails' => CAP_ALLOW,
            'moodle/user:viewhiddendetails' => CAP_INHERIT,
            'moodle/reportbuilder:editall' => CAP_ALLOW,
            'tool/tenant:authconfig' => CAP_ALLOW,
            'tool/tenant:mobileconfig' => CAP_INHERIT, // Available but not allowed by default.
            'moodle/block:edit' => CAP_ALLOW,
            'moodle/my:manageblocks' => CAP_INHERIT, // Available, it is default to allow for all users.
        ];

        foreach (\core_component::get_plugin_list('block') as $blockname => $unused) {
            // Capabilities "block/xyz:myaddinstance" capabilities are safe for the tenant administrators.
            // They may be allowed extra block types that they can add to the tenant dashboard.
            $capabilities['block/'.$blockname.':myaddinstance'] = CAP_INHERIT;

            // Capabilities "block/xyz:addinstance" are allowed for the tenant administrators in role creation.
            $capability = 'block/' . $blockname . ':addinstance';
            if (get_capability_info($capability)) {
                $capabilities[$capability] = CAP_ALLOW;
            }
        }

        foreach (\core_component::get_plugin_types() as $ptype => $unused) {
            $plugins = \core_component::get_plugin_list_with_class($ptype, 'tool_tenant');
            foreach ($plugins as $plugin => $classname) {
                $caps = component_class_callback($classname, 'get_tenant_admin_capabilities', []);
                if (empty($caps) || !is_array($caps)) {
                    continue;
                }
                $callback = $classname . '::get_tenant_admin_capabilities';
                foreach ($caps as $capability => $allow) {
                    if (!is_string($capability)) {
                        debugging("Can not read capability name in {$callback}()", DEBUG_DEVELOPER);
                        continue;
                    }
                    list($plugintype, $pluginname) = \core_component::normalize_component($plugin);
                    if (strpos($capability, "{$plugintype}/{$pluginname}:") !== 0) {
                        debugging("Capability '" . s($capability) .
                            "' returned in {$callback}() must belong to the plugin {$plugin}", DEBUG_DEVELOPER);
                        continue;
                    }
                    if ($allow !== CAP_ALLOW && $allow !== CAP_INHERIT) {
                        debugging("Capability '" . s($capability) . "' returned in {$callback}() " .
                            "must be either CAP_ALLOW or CAP_INHERIT", DEBUG_DEVELOPER);
                        continue;
                    }
                    if (!get_capability_info($capability) && during_initial_install()) {
                        // Capabilities may not have been loaded yet.
                        update_capabilities($plugin);
                    }
                    $capabilities[$capability] = $allow;
                }
            }
        }
        ksort($capabilities);
        return $capabilities;
    }

    /**
     * Returns list of capabilities whitelisted for one plugin
     *
     * @param string $pluginname
     * @param bool $onlyallowed
     * @return array associative array $capabilityname=>$permission
     */
    public static function get_plugin_capabilities_for_tenant_admin_role(string $pluginname, bool $onlyallowed = false): array {
        list($type, $name) = \core_component::normalize_component($pluginname);
        $capabilities = array_filter(self::get_tenant_admin_capabilities(),
            function($allow, $cap) use ($type, $name, $onlyallowed) {
                return (!$onlyallowed || $allow == CAP_ALLOW)
                    && strpos($cap, "$type/$name:") === 0;
            }, ARRAY_FILTER_USE_BOTH);
        return $capabilities;
    }

    /**
     * Hook used in core when we need to filter out the multi-tenancy roles
     *
     * @param array $rolestoexclude list of roles to exclude from the list:
     *     'tool_tenant_userrole', 'tool_tenant_adminrole', 'tool_tenant_managerrole'
     *     (short aliases can be used: 'user', 'admin', 'manager' respectively)
     * @param string $rolefield SQL expression for the role id in the query where this subquery is inserted,
     *     for example 'ra.roleid'
     * @param bool $andpostfix append " AND " to the end of the query
     * @return string
     */
    public static function get_exclude_tenant_roles_subquery(array $rolestoexclude, string $rolefield, bool $andpostfix = true) {
        $sqls = [];
        $roles = ['user' => 'tool_tenant_userrole', 'admin' => 'tool_tenant_adminrole', 'manager' => 'tool_tenant_managerrole'];
        foreach ($roles as $short => $full) {
            if (in_array($short, $rolestoexclude) || in_array($full, $rolestoexclude)) {
                if ($roleid = (int)get_config('', $full)) {
                    $sqls[] = "$rolefield <> $roleid";
                }
            }
        }
        if (!$sqls) {
            return $andpostfix ? ' ' : ' 1=1 ';
        }
        return ' ' . join(' AND ', $sqls) . ($andpostfix ? ' AND ' : ' ');
    }
}
