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
 * Class config.
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

/**
 * Methods for admin settings and config management
 *
 * Not external API.
 *
 * Use {@see \tool_tenant\tenancy} to get information about current tenant and its users
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class config {

    /** @var array keeps track of all the tenants we switched to */
    protected static $tenantstack = [];
    /** @var null $SITE object unmodified */
    protected static $defaultsite = null;

    /**
     * Returns the default value of a config variable (without any tenant overrides)
     *
     * @param string $plugin
     * @param string $name
     * @return string|null
     */
    public static function get_config_default(string $plugin, string $name): ?string {
        // Substitute the $CFG with the "no-tenant" values.
        self::push_for_tenant(0);
        $value = get_config($plugin, $name);
        self::pop();
        return $value !== null ? (string)$value : $value;
    }

    /**
     * Sets the default value of a config variable (before tenant overrides)
     *
     * @param string $name
     * @param string $value
     * @param string|null $plugin
     */
    public static function set_config_default(string $name, string $value, ?string $plugin = null) {
        set_config($name, $value, $plugin);
    }

    /**
     * Returns a tenant override for a config variable or null if the variable is not overwritten
     *
     * Note that this method does not take into account if any config variable is forced, it can still return
     * the old override that was set before the setting was forced.
     *
     * Normally you don't need to use this method outside of tool_tenant plugin.
     * To retrieve the actual values for the current tenant use $CFG->varname or {@see get_config()} function
     *
     * @param int $tenantid
     * @param string $plugin
     * @param string $name
     * @return string|null
     */
    public static function get_config_tenant_override(int $tenantid, string $plugin, string $name): ?string {
        $records = self::get_config_tenant_overrides($tenantid, $plugin);
        return ($records && array_key_exists($name, $records)) ? $records[$name] : null;
    }

    /**
     * Returns all tenant overrides for a plugin
     *
     * @param int $tenantid
     * @param string $plugin
     * @return array
     */
    protected static function get_config_tenant_overrides(int $tenantid, string $plugin): array {
        global $DB;
        $cache = \cache::make('core', 'config');
        $cachekey = $plugin . ':' . $tenantid;
        $result = $cache->get($cachekey);
        if ($result === false) {
            try {
                $result = $DB->get_records_menu('tool_tenant_config',
                    ['tenantid' => $tenantid, 'plugin' => $plugin], '', 'name, value');
            } catch (\dml_exception $e) {
                // We must be inside the installation/upgrade process and the table does not exist.
                $result = [];
            }
            $cache->set($cachekey, $result);
        }
        return $result;
    }

    /**
     * Sets or removes a tenant override for a config variable
     *
     * If some config var is forced for all tenants this method can still be used but the get_config() will not
     * return overridden values
     *
     * @param int $tenantid
     * @param string $name
     * @param string|null $value null to remove override or value to set. All config variables have to be converted to strings
     * @param string $plugin
     */
    public static function set_config_tenant_override(int $tenantid, string $name, ?string $value, string $plugin = 'core'): void {
        global $DB;
        $record = $DB->get_record('tool_tenant_config', ['tenantid' => $tenantid, 'plugin' => $plugin, 'name' => $name]);
        if ($record) {
            if ($value === null) {
                $DB->delete_records('tool_tenant_config', ['id' => $record->id]);
            } else if ($value !== $record->value) {
                $DB->update_record('tool_tenant_config', ['id' => $record->id, 'value' => $value]);
            } else {
                return;
            }
        } else if ($value !== null) {
            $DB->insert_record('tool_tenant_config',
                ['tenantid' => $tenantid, 'plugin' => $plugin, 'name' => $name, 'value' => $value]);
        }
        // Reset cache for overrides.
        \cache_helper::invalidate_by_definition('core', 'config', [], $plugin . ':' . $tenantid);
        if ($tenantid == tenancy::get_tenant_id() && $plugin === 'core') {
            self::reset_cfg_for_tenant();
        }
    }

    /**
     * Checks if a given config variable is forced for all tenants
     *
     * @param string $plugin
     * @param string $name
     * @return bool
     */
    public static function is_default_config_forced(string $plugin, string $name): bool {
        return self::get_config_default($plugin, $name.'_wforce');
    }

    /**
     * Hook executed from the core settings when we add the "Force for all tenants" checkbox to overridable settings
     *
     * @param \admin_setting $setting
     * @return array|null
     */
    public static function add_flag_to_admin_setting(\admin_setting $setting): ?array {
        if (($setting->plugin === null && array_key_exists($setting->name, auth_manager::overridable_auth_settings())) ||
            auth_manager::can_override_plugin_setting($setting->plugin, $setting->name)) {
            return [true, false, 'wforce', get_string('forceforalltenants', 'tool_tenant')];
        }
        return null;
    }

    /**
     * Current tenant whose settings we should return in the $CFG and get_config()
     *
     * This value might be different from {@see \tool_tenant\tenancy::get_tenant_id()} in the situations when
     * we edit/confirm user from another tenant or edit the default settings
     *
     * @return int id of a tenant or 0 which means to use the default values (without any tenant overrides)
     */
    protected static function get_tenant_id(): int {
        return reset(self::$tenantstack) ?: 0;
    }

    /**
     * Recalculates the $CFG (called after the tenant change or the tenant config change)
     */
    protected static function reset_cfg_for_tenant(): void {
        global $CFG, $SITE, $COURSE, $DB;
        $cfg = get_config('core');
        foreach (auth_manager::overridable_auth_settings() + ['auth' => 1] as $key => $unused) {
            $CFG->$key = $cfg->$key;
        }
        if (self::$defaultsite === null) {
            self::$defaultsite = $DB->get_record('course', ['category' => 0]);
        }

        // Override the site name with the tenant's data. Even for the cases where we set config tenant id to 0.
        $tenantid = self::get_tenant_id() ?: tenancy::get_tenant_id();
        if ($tenantid && isset($SITE) && ($tenant = \tool_tenant\tenancy::get_tenants()[$tenantid] ?? null)) {
            $SITE->fullname = $tenant->sitename ?: self::$defaultsite->fullname;
            $SITE->shortname = $tenant->siteshortname ?: self::$defaultsite->shortname;

            if (isset($COURSE->id) && $COURSE->id == $SITE->id) {
                $COURSE->fullname = $tenant->sitename ?: self::$defaultsite->fullname;
                $COURSE->shortname = $tenant->siteshortname ?: self::$defaultsite->shortname;
            }
        }
    }

    /**
     * Notifies the manager that we should return the settings for the given tenant from now on in $CFG and get_config()
     *
     * @param int $tenantid
     */
    public static function push_for_tenant(int $tenantid): void {
        $lasttenantid = self::$tenantstack ? reset(self::$tenantstack) : -1;
        array_unshift(self::$tenantstack, $tenantid);
        if ($tenantid != $lasttenantid) {
            self::reset_cfg_for_tenant();
        }
    }

    /**
     * Notifies the manager that we should return the settings for the given user's tenant from now on in $CFG and get_config()
     *
     * When called for the current user it will tell manager to use the settings for the actual user's tenant
     * which may be different from the current tenant the user switched to.
     *
     * For example, when we edit admin's account, we should use the auth settings for their tenant (normally Default tenant)
     * that may be different from the current tenant's
     *
     * @param int $userid can be 0 if we should look up by username or email
     * @param string|null $username
     * @param string|null $email
     */
    public static function push_for_user(int $userid, ?string $username = null, ?string $email = null): void {
        if (!$userid) {
            if ($username) {
                $user = \core_user::get_user_by_username(\core_text::strtolower($username));
                $userid = $user ? $user->id : 0;
            } else if ($email) {
                $user = auth_manager::find_user_by_email($email);
                $userid = $user ? $user->id : 0;
            }
        }
        if ($userid <= 0 || isguestuser($userid)) {
            // Always push something, even if it is the current tenant (so the following "pop" works correctly).
            self::push_for_tenant(tenancy::get_tenant_id());
        } else {
            self::push_for_tenant(tenancy::get_actual_tenant_id($userid));
        }
    }

    /**
     * Removes the last config tenant override (push_for_tenant() or push_for_user())
     */
    public static function pop(): void {
        $last = self::get_tenant_id();
        if (self::$tenantstack) {
            array_shift(self::$tenantstack);
            if ($last != self::get_tenant_id()) {
                self::reset_cfg_for_tenant();
            }
        }
    }

    /**
     * Removes all config overrides (useful for tests reset)
     */
    public static function pop_all(): void {
        while (count(self::$tenantstack)) {
            self::pop();
        }
        self::$defaultsite = null;
    }

    /**
     * Implementation of the hook into the core {@see get_config()} function
     *
     * @param string $plugin
     * @param array $config
     */
    public static function get_config_hook(string $plugin, array &$config): void {
        if (during_initial_install() || isset($CFG->upgraderunning)) {
            return;
        }
        $tenantid = self::get_tenant_id();
        if (!$tenantid) {
            // Tenantid is zero, means use the defaults.
            return;
        }
        if ($plugin === 'core') {
            $pluginconfig = self::get_config_tenant_overrides($tenantid, $plugin);
            foreach (auth_manager::overridable_auth_settings() as $key => $unused) {
                if (empty($config[$key.'_wforce']) && array_key_exists($key, $pluginconfig)) {
                    $config[$key] = $pluginconfig[$key];
                }
            }
            $config['auth'] = auth_manager::calculate_config_auth_for_tenant($config, $pluginconfig);
        } else if (auth_manager::is_multitenant_auth_plugin($plugin)) {
            $pluginconfig = self::get_config_tenant_overrides($tenantid, $plugin);
            $fields = \core_user::AUTHSYNCFIELDS;
            foreach ($pluginconfig as $key => $value) {
                if (preg_match('/^field_lock_(.*)/', $key, $matches)
                        && in_array($matches[1], $fields) && empty($config["{$key}_wforce"])) {
                    $config[$key] = $value;
                }
            }
        }
    }
}
