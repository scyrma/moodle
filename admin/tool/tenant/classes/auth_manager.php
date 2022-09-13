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
 * Class auth_manager.
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use core\output\inplace_editable;
use tool_tenant\local\auth\issuer_helper;
use tool_tenant\form\auth_email_settings_form;
use tool_tenant\form\auth_manual_settings_form;
use tool_tenant\form\auth_oauth2_settings_form;
use tool_tenant\form\auth_saml2_settings_form;

/**
 * Collection of methods for authentication management
 *
 * Not external API.
 *
 * Use {@see \tool_tenant\tenancy} to get information about current tenant and its users
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class auth_manager {
    /** @var int Auth plugin status, disabled for all tenants */
    const STATUS_DISABLED = 0;
    /** @var int Auth plugin status, enabled for all tenants but individual tenants can disable it */
    const STATUS_ENABLEDOPTIONAL = 2;
    /** @var int Auth plugin status, disabled for all tenants but individual tenants can enable it */
    const STATUS_DISABLEDAVAILABLE = 3;
    /** @var int Auth plugin status, enabled for all tenants */
    const STATUS_ENABLED = 1;

    /** @var string name of the config variable for the list of the auth plugins that are Enabled, optional*/
    const CONFIG_AUTH_OVERRIDABLE_ENABLED = 'tool_tenant_authoverridableenabled';
    /** @var string name of the config variable for the list of the auth plugins that are Disabled, available*/
    const CONFIG_AUTH_OVERRIDABLE_DISABLED = 'tool_tenant_authoverridabledisabled';
    /** @var string name of config variable in the tenant overrides for the list of enabled auth plugins */
    const CONFIG_AUTH_ENABLED = 'tool_tenant_authenabled';
    /** @var string name of config variable in the tenant overrides for the list of disabled auth plugins */
    const CONFIG_AUTH_DISABLED = 'tool_tenant_authdisabled';

    /**
     * List of auth plugins that support multi-tenancy
     *
     * @return string[]
     */
    protected static function get_multitenant_auth_methods(): array {
        return ['email', 'oauth2', 'manual', 'saml2'];
    }

    /**
     * List of core settings (authentication-related) that can be overridden for each tenant
     *
     * @return array[]
     */
    public static function overridable_auth_settings(): array {
        return [
            'registerauth' => [],
            'authpreventaccountcreation' => [],
            'auth_instructions' => [],
            'allowemailaddresses' => [],
            'denyemailaddresses' => [],
            'verifychangedemail' => [],
            'alternateloginurl' => [],
        ];
    }

    /**
     * List of the settings inside the auth plugins that potentially allow overriding
     *
     * @param string $plugin full name of the plugin or 'core' for core settings
     * @return array
     */
    public static function get_multitenant_settings_names(string $plugin): array {
        $settings = [];
        if (in_array($plugin, ['auth_email', 'auth_manual', 'auth_oauth2', 'auth_saml2'])) {
            $fields = \core_user::AUTHSYNCFIELDS;
            foreach ($fields as $field) {
                $settings[] = 'field_lock_' . $field;
            }
        }
        return $settings;
    }

    /**
     * Can given plugin support multi-tenancy?
     *
     * @param string $pluinname full frankenstyle plugin name with the plugintype prefix
     * @return bool
     */
    public static function is_multitenant_auth_plugin(string $pluinname): bool {
        [$type, $plugin] = \core_component::normalize_component($pluinname);
        return $type === 'auth' && in_array($plugin, self::get_multitenant_auth_methods());
    }

    /**
     * List of all auth plugins with self-registration that are not disabled on the system level
     *
     * @return array pluginname=>title
     */
    public static function get_available_registration_plugins(): array {
        $choices = ['' => get_string('disable')];
        $authsenabled = explode(',', config::get_config_default('core', 'auth'));
        foreach ($authsenabled as $auth) {
            if ($auth && exists_auth_plugin($auth) && ($authplugin = get_auth_plugin($auth)) && $authplugin->can_signup()) {
                $choices[$auth] = $authplugin->get_title();
            }
        }
        return $choices;
    }

    /**
     * Menu of available statuses for the given auth method
     *
     * @param string $auth
     * @return array
     */
    protected static function available_default_auth_statuses(string $auth): array {
        if (self::is_multitenant_auth_plugin('auth_' . $auth)) {
            return [
                self::STATUS_DISABLED => get_string('plugindisabled', 'plugin'),
                self::STATUS_DISABLEDAVAILABLE => get_string('authdisabledavailable', 'tool_tenant'),
                self::STATUS_ENABLEDOPTIONAL => get_string('authenabledoptional', 'tool_tenant'),
                self::STATUS_ENABLED => get_string('pluginenabled', 'plugin'),
            ];
        } else if ($auth === 'manual' || $auth === 'nologin') {
            return [self::STATUS_ENABLED => get_string('pluginenabled', 'plugin')];
        } else {
            return [
                self::STATUS_DISABLED => get_string('plugindisabled', 'plugin'),
                self::STATUS_ENABLED => get_string('pluginenabled', 'plugin'),
            ];
        }
    }

    /**
     * Menu of available statuses for the given auth method for the tenant override
     *
     * @param string $auth
     * @return array
     */
    protected static function available_tenant_auth_statuses(string $auth): array {
        if (self::is_multitenant_auth_plugin('auth_' . $auth)) {
            return [
                self::STATUS_DISABLED => get_string('plugindisabled', 'plugin'),
                self::STATUS_ENABLED => get_string('pluginenabled', 'plugin'),
            ];
        }
        return [];
    }

    /**
     * Change status for an authentication plugin (enabled/disabled/etc) that is default for all tenants
     *
     * @param string $auth auth plugin name
     * @param int $newstatus
     * @return inplace_editable
     */
    public static function change_default_auth_status(string $auth, int $newstatus): ?inplace_editable {
        global $CFG;
        if ($auth === 'manual' || $auth === 'nologin') {
            return null;
        }
        $availablestatuses = self::available_default_auth_statuses($auth);
        if (!array_key_exists($newstatus, $availablestatuses)) {
            return null;
        }

        config::push_for_tenant(0);

        // List of auth plugins that are not disabled for all ($CFG->auth).
        $list = preg_split('/,/', $CFG->auth ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $toadd = ($newstatus != self::STATUS_DISABLED) && !in_array($auth, $list);
        $toremove = ($newstatus == self::STATUS_DISABLED) && in_array($auth, $list);
        if ($toadd || $toremove) {
            $list = array_merge(array_diff($list, $toremove ? [$auth] : []), $toadd ? [$auth] : []);
            config::set_config_default('auth', join(',', $list), null);
            if ($toremove && $auth == $CFG->registerauth) {
                config::set_config_default('registerauth', '');
            }
            \core\session\manager::gc(); // Remove stale sessions.
            \core_plugin_manager::reset_caches();
        }

        // List of auth plugins that are Enabled, optional.
        $list = preg_split('/,/', $CFG->{self::CONFIG_AUTH_OVERRIDABLE_ENABLED} ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $toadd = ($newstatus == self::STATUS_ENABLEDOPTIONAL) && !in_array($auth, $list);
        $toremove = ($newstatus != self::STATUS_ENABLEDOPTIONAL) && in_array($auth, $list);
        if ($toadd || $toremove) {
            $list = array_merge(array_diff($list, $toremove ? [$auth] : []), $toadd ? [$auth] : []);
            config::set_config_default(self::CONFIG_AUTH_OVERRIDABLE_ENABLED, join(',', $list), null);
        }

        // List of auth plugins that are Disabled, available.
        $list = preg_split('/,/', $CFG->{self::CONFIG_AUTH_OVERRIDABLE_DISABLED} ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $toadd = ($newstatus == self::STATUS_DISABLEDAVAILABLE) && !in_array($auth, $list);
        $toremove = ($newstatus != self::STATUS_DISABLEDAVAILABLE) && in_array($auth, $list);
        if ($toadd || $toremove) {
            $list = array_merge(array_diff($list, $toremove ? [$auth] : []), $toadd ? [$auth] : []);
            config::set_config_default(self::CONFIG_AUTH_OVERRIDABLE_DISABLED, join(',', $list), null);
        }

        config::pop();

        $authplugin = get_auth_plugin($auth);
        return self::editable_status($auth, $newstatus, $authplugin->get_title(), 0);
    }

    /**
     * Creates an instance of inplace_editable for editing the default auth plugin status or tenant status
     *
     * @param string $auth
     * @param int $value current value
     * @param string $name human-readable name of the auth plugin
     * @param int $tenantid if 0 - edit the default status, >0 - id of the tenant to edit it for
     * @param bool|null $editable should the field be editable (null means detect automatically)
     * @return inplace_editable|null null if there are no options for the statuses (manual/nologin methods),
     *   instance of the inplace editable otherwise
     */
    protected static function editable_status(string $auth, int $value, string $name,
                                           int $tenantid, ?bool $editable = null): ?inplace_editable {
        global $OUTPUT;
        $options = $tenantid ? self::available_tenant_auth_statuses($auth) : self::available_default_auth_statuses($auth);
        $editable = $editable ?? (count($options) > 0);
        $tmpl = null;
        if (count($options) > 1 && !$tenantid) {
            $hint = get_string('autheditstatus', 'tool_tenant');
            $tmpl = new inplace_editable('tool_tenant', 'auth_' . $auth, $tenantid, $editable,
                null, $value, $hint, get_string('authnewstatusfor', 'tool_tenant', $name));
            $tmpl->set_type_select($options);
        } else if ($tenantid) {
            $icon = $value ? 't/hide' : 't/show';
            if ($editable) {
                $label = $value ? get_string('plugindisable', 'plugin') : get_string('pluginenable', 'plugin');
            } else {
                $label = $value ? get_string('pluginenabled', 'plugin') : get_string('plugindisabled', 'plugin');
            }
            $displayvalue = $OUTPUT->pix_icon($icon, $label, 'core', $editable ? [] : ['class' => 'dimmed_text']);
            $tmpl = new inplace_editable('tool_tenant', 'auth_'.$auth, $tenantid, $editable,
                $displayvalue, (int)(bool)$value);
            $tmpl->set_type_toggle([0, 1]);
        }
        return $tmpl;
    }

    /**
     * Change the status (enabled/disabled) for either default setting for auth plugin or tenant override
     *
     * @param string $auth
     * @param int $tenantid if 0 - edit the default status, >0 - id of the tenant to edit it for
     * @param int $newstatus
     * @return inplace_editable|null
     */
    public static function change_tenant_auth_status(string $auth, int $tenantid, int $newstatus): ?inplace_editable {
        if ($auth === 'manual' || $auth === 'nologin') {
            return null;
        }
        $availablestatuses = self::available_tenant_auth_statuses($auth);
        if (!array_key_exists($newstatus, $availablestatuses)) {
            return null;
        }
        $authenabled = preg_split('/,/', config::get_config_tenant_override($tenantid, 'core', self::CONFIG_AUTH_ENABLED),
            -1, PREG_SPLIT_NO_EMPTY);
        $authdisabled = preg_split('/,/', config::get_config_tenant_override($tenantid, 'core', self::CONFIG_AUTH_DISABLED),
            -1, PREG_SPLIT_NO_EMPTY);
        if ($newstatus) {
            $authenabled = array_unique(array_merge($authenabled, [$auth]));
            $authdisabled = array_diff($authdisabled, [$auth]);
        } else {
            $authdisabled = array_unique(array_merge($authdisabled, [$auth]));
            $authenabled = array_diff($authenabled, [$auth]);
        }
        config::set_config_tenant_override($tenantid, self::CONFIG_AUTH_ENABLED, join(',', $authenabled), 'core');
        config::set_config_tenant_override($tenantid, self::CONFIG_AUTH_DISABLED, join(',', $authdisabled), 'core');

        $authplugin = get_auth_plugin($auth);
        return self::editable_status($auth, $newstatus, $authplugin->get_title(), $tenantid);
    }

    /**
     * Get current default status of an authentication plugin (enabled/disabled/enabled,optional/disabled,available)
     *
     * @param string $auth
     * @param array $authsenabled
     * @param array $enabledoptional
     * @param array $disabledoptional
     * @return int
     */
    protected static function get_default_auth_status(string $auth, array $authsenabled,
                                                      array $enabledoptional = [], array $disabledoptional = []): int {
        if ($auth === 'manual' || $auth === 'nologin') {
            return self::STATUS_ENABLED;
        }
        if (!in_array($auth, $authsenabled)) {
            return self::STATUS_DISABLED;
        } else if (in_array($auth, $enabledoptional)) {
            return self::STATUS_ENABLEDOPTIONAL;
        } else if (in_array($auth, $disabledoptional)) {
            return self::STATUS_DISABLEDAVAILABLE;
        }
        return self::STATUS_ENABLED;
    }

    /**
     * Get authentication plugins list and their basic information
     *
     * Used as a base method for {@see self::get_default_auth_plugins()} and {@see self::get_tenant_auth_plugins()}
     *
     * @return array
     */
    protected static function get_auth_plugins_basic(): array {
        global $CFG;
        $authsavailable = array_keys(\core_component::get_plugin_list('auth'));
        $oldauth = config::get_config_default('', 'auth') ?? '';
        $authsenabled = preg_split('/,/', $oldauth, -1, PREG_SPLIT_NO_EMPTY);

        // Automatically fix - remove the plugins that are no longer installed. This is what core does.
        $authsenabled = array_intersect($authsenabled, $authsavailable);
        if (($newauth = join(',', $authsenabled)) !== $oldauth) {
            config::set_config_default('auth', $newauth);
        }

        // Construct the display array, with enabled auth plugins at the top, in order.
        $sortedauths = array_intersect(array_unique(array_merge(['manual', 'nologin'], $authsenabled, $authsavailable)),
            $authsavailable);
        $enabledoptional = preg_split('/,/', $CFG->{self::CONFIG_AUTH_OVERRIDABLE_ENABLED} ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $disabledoptional = preg_split('/,/', $CFG->{self::CONFIG_AUTH_OVERRIDABLE_DISABLED} ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $authplugins = [];
        foreach ($sortedauths as $auth) {
            $authplugin = get_auth_plugin($auth);
            $testurl = null;
            if ($authplugin and method_exists($authplugin, 'test_settings')) {
                $testurl = new \moodle_url('/auth/test_settings.php', array('auth' => $auth, 'sesskey' => sesskey()));
            }
            $authplugins[$auth] = [
                'status' => self::get_default_auth_status($auth, $authsenabled, $enabledoptional, $disabledoptional),
                'title' => $authplugin->get_title(),
                'testsettingsurl' => $testurl,
                'auth' => $auth,
                'ismultitenant' => self::is_multitenant_auth_plugin("auth_$auth"),
            ];
        }
        return $authplugins;

    }

    /**
     * List of all authentication plugins and their default settings (to be used on the "Manage authentication" page)
     *
     * @return array
     */
    public static function get_default_auth_plugins(): array {
        global $DB, $OUTPUT, $CFG;
        $authplugins = self::get_auth_plugins_basic();

        $movableauthcount = count(array_filter($authplugins, function($authproperties, $auth) {
            return $authproperties['status'] && $auth !== 'manual' && $auth !== 'nologin';
        }, ARRAY_FILTER_USE_BOTH));
        $updowncount = 1;
        foreach ($authplugins as $auth => &$authinfo) {
            // Up/down link (only if auth is enabled).
            $authinfo['upurl'] = $authinfo['downurl'] = '';
            if ($authinfo['status'] && $auth !== 'manual' && $auth !== 'nologin') {
                $url = new \moodle_url("/admin/auth.php", ['sesskey' => sesskey(), 'auth' => $auth]);
                $authinfo['upurl'] = ($updowncount > 1) ? $url->out(false, ['action' => 'up']) : '';
                $authinfo['downurl'] = ($updowncount < $movableauthcount) ? $url->out(false, ['action' => 'down']) : '';
                ++ $updowncount;
            }
            $authinfo['users'] = $DB->count_records('user', ['auth' => $auth, 'deleted' => 0]);
            $tmpl = self::editable_status($auth, $authinfo['status'], $authinfo['title'], 0);
            $authinfo['editablestatus'] = $tmpl ? $tmpl->export_for_template($OUTPUT) : null;
            $authinfo['uninstallurl'] = \core_plugin_manager::instance()->get_uninstall_url('auth_'.$auth, 'manage');
            if (file_exists($CFG->dirroot.'/auth/'.$auth.'/settings.php')) {
                $authinfo['settingsurl'] = new \moodle_url('/admin/settings.php', ['section' => "authsetting$auth"]);
            } else if (file_exists($CFG->dirroot.'/auth/'.$auth.'/config.html')) {
                $authinfo['settingsurl'] = new \moodle_url('/admin/auth_config.php', ['auth' => $auth]);
            } else {
                $authinfo['settingsurl'] = null;
            }
        }
        return $authplugins;
    }

    /**
     * Calculates the value of $CFG->auth for the tenant
     *
     * Called from the {@see config::get_config_hook()} which is called from the {@see get_config()}
     *
     * Make sure this method never accesses $CFG or calls get_config() or anything else that may cause recursion
     *
     * @param array $cfg current default config (instead of using $CFG), passed by reference for performance
     * @param array $tenantoverrides all overrides for this tenant, passed by reference for performance
     * @return string
     */
    public static function calculate_config_auth_for_tenant(array &$cfg, array &$tenantoverrides): string {
        $authsdefault = preg_split('/,/', $cfg['auth'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $enabledoptional = preg_split('/,/', $cfg[self::CONFIG_AUTH_OVERRIDABLE_ENABLED] ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $disabledoptional = preg_split('/,/', $cfg[self::CONFIG_AUTH_OVERRIDABLE_DISABLED] ?? '', -1, PREG_SPLIT_NO_EMPTY);

        $authenabledtenant = preg_split('/,/', $tenantoverrides[self::CONFIG_AUTH_ENABLED] ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $authdisabledtenant = preg_split('/,/', $tenantoverrides[self::CONFIG_AUTH_DISABLED] ?? '', -1, PREG_SPLIT_NO_EMPTY);

        $statusfortenant = [];
        foreach ($authsdefault as $auth) {
            if (in_array($auth, $disabledoptional)) {
                // Default status "Disable, available". Check if this auth plugin was specifically enabled for the tenant.
                $statusfortenant[$auth] = in_array($auth, $authenabledtenant);
            } else if (in_array($auth, $enabledoptional)) {
                // Default status "Enable, optional". Check if this auth plugin was not specifically disabled for the tenant.
                $statusfortenant[$auth] = !in_array($auth, $authdisabledtenant);
            } else {
                // Default status "Enabled", tenants can not override.
                $statusfortenant[$auth] = true;
            }
        }
        return join(',', array_keys(array_filter($statusfortenant)));
    }

    /**
     * Hook used in {@see get_config()} - changes some config values for the current tenant
     *
     * @param array $cfg current default config (instead of using $CFG), passed by reference so it can be modified
     * @param array $tenantoverrides all overrides for this tenant, passed by reference for performance
     */
    public static function calculate_config_for_tenant(array &$cfg, array &$tenantoverrides): void {
        foreach (self::get_multitenant_settings_names('core') as $key) {
            if (empty($cfg[$key.'_wforce']) && array_key_exists($key, $tenantoverrides)) {
                $cfg[$key] = $tenantoverrides[$key];
            }
        }
        $cfg['auth'] = self::calculate_config_auth_for_tenant($cfg, $tenantoverrides);
        if (!empty($cfg['registerauth']) && !in_array($cfg['registerauth'], preg_split('/,/', $cfg['auth']))) {
            // Remove 'registerauth' if it points to the plugin that is disabled for the current tenant.
            $cfg['registerauth'] = '';
        }
    }

    /**
     * List of the authentication plugins available for the tenant and their settings (to be used on "Autentication" tab)
     *
     * @param int $tenantid
     * @return array
     */
    public static function get_tenant_auth_plugins(int $tenantid = 0): array {
        global $DB, $OUTPUT;
        $authplugins = array_filter(self::get_auth_plugins_basic(),
            function($plugin) {
                return $plugin['status'] != self::STATUS_DISABLED;
            });

        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        $authenabled = preg_split('/,/', config::get_config_tenant_override($tenantid, 'core', self::CONFIG_AUTH_ENABLED),
            -1, PREG_SPLIT_NO_EMPTY);
        $authdisabled = preg_split('/,/', config::get_config_tenant_override($tenantid, 'core', self::CONFIG_AUTH_DISABLED),
            -1, PREG_SPLIT_NO_EMPTY);
        foreach ($authplugins as $auth => &$authinfo) {
            $authinfo['users'] = $DB->count_records_select('user',
                tenancy::get_users_subquery(false, true, 'id', $tenantid) .
                " auth=:auth AND deleted = :deleted",
                ['auth' => $auth, 'deleted' => 0]);
            $authinfo['defaultstatus'] = $authinfo['status'];
            $editable = false;
            if ($authinfo['defaultstatus'] == self::STATUS_DISABLEDAVAILABLE) {
                $editable = true;
                $authinfo['status'] = in_array($auth, $authenabled) ? self::STATUS_ENABLED : self::STATUS_DISABLED;
            } else if ($authinfo['defaultstatus'] == self::STATUS_ENABLEDOPTIONAL) {
                $editable = true;
                $authinfo['status'] = in_array($auth, $authdisabled) ? self::STATUS_DISABLED : self::STATUS_ENABLED;
            }
            $tmpl = self::editable_status($auth, $authinfo['status'], $authinfo['title'], $tenantid, $editable);
            $authinfo['editablestatus'] = $tmpl ? $tmpl->export_for_template($OUTPUT) : null;
            $authinfo['settingsform'] = '';
            if ($auth === 'email') {
                $authinfo['settingsform'] = auth_email_settings_form::class;
            } else if ($auth === 'manual') {
                $authinfo['settingsform'] = auth_manual_settings_form::class;
            } else if ($auth === 'oauth2') {
                $authinfo['settingsform'] = auth_oauth2_settings_form::class;
            } else if ($auth === 'saml2') {
                $authinfo['settingsform'] = auth_saml2_settings_form::class;
            } else if (self::is_multitenant_auth_plugin("auth_$auth")) {
                // Throw exception so we don't forget to define the form for the plugins we add in the future.
                throw new \coding_exception(
                    "Authentication plugin $auth defined as multi-tenant but does not have settings form");
            }
        }
        return $authplugins;
    }

    /**
     * Called from the hook executed after config, detects the tenant for some auth-related pages.
     *
     * We need to set the tenant for a user on some pages that may be displayed to an admin or an
     * unauthenticated user so we take the config for this tenant.
     * For example: account confirmation callback, user edit form.
     *
     * @return int 0 if the tenant is not detected or the tenant id otherwise
     */
    public static function detect_tenant_on_auth_page(): int {
        global $DB, $CFG, $FULLME, $USER, $SESSION;

        if (!tenancy::is_site_multi_tenant()) {
            return 0;
        }

        if ($CFG->wwwroot.'/login/confirm.php' === strip_querystring($FULLME)) {
            $data = optional_param('data', '', PARAM_RAW);
            $dataelements = explode('/', $data, 2);
            $username   = \core_text::strtolower($dataelements[1]);

            $user = $DB->get_record_select('user', "username=:username AND deleted<>1", ['username' => $username]);
            return $user ? tenancy::get_actual_tenant_id($user->id) : 0;
        }
        if ($CFG->wwwroot.'/auth/oauth2/confirm-account.php' === strip_querystring($FULLME)) {
            $username = optional_param('username', '', PARAM_USERNAME);
            $user = $DB->get_record_select('user', "username=:username AND deleted<>1", ['username' => $username]);
            return $user ? tenancy::get_actual_tenant_id($user->id) : 0;
        }
        if ($CFG->wwwroot.'/auth/oauth2/login.php' === strip_querystring($FULLME)) {
            // TODO MDL-71017 remove this in 3.11 ?
            // This is not detecting tenant, this redirects away if the issuer is not available.
            $issuerid = optional_param('id', '', PARAM_INT);
            if (!$issuerid || !issuer_helper::issuer_available($issuerid, 0, 'oauth2')) {
                $SESSION->loginerrormsg = get_string('issuernologin', 'auth_oauth2');
                redirect(new \moodle_url('/login/index.php'));
            }
        }
        if ($CFG->wwwroot.'/auth/saml2/login.php' === strip_querystring($FULLME)) {
            // We will stop user earlier if issuer is not available for this tenant.
            $idp = optional_param('idp', '', PARAM_ALPHANUM);
            if (!$idp || !issuer_helper::issuer_available($idp, 0, 'saml2')) {
                $SESSION->loginerrormsg = get_string('issuernologin', 'tool_tenant');
                redirect(new \moodle_url('/login/index.php'));
            }
        }
        if ($CFG->wwwroot.'/user/editadvanced.php' === strip_querystring($FULLME)) {
            $id = optional_param('id', $USER->id, PARAM_INT);
            return ($id > 0) ? tenancy::get_actual_tenant_id($id) : 0;
        }
        if ($CFG->wwwroot.'/user/edit.php' === strip_querystring($FULLME)) {
            $id = optional_param('id', $USER->id, PARAM_INT);
            return ($id > 0) ? tenancy::get_actual_tenant_id($id) : 0;
        }
        if ($CFG->wwwroot.'/admin/user.php' === strip_querystring($FULLME)) {
            $confirmuser = optional_param('confirmuser', 0, PARAM_INT);
            $resendemail = optional_param('resendemail', 0, PARAM_INT);
            if ($confirmuser || $resendemail) {
                $user = $DB->get_record('user',
                    ['id' => $confirmuser ?: $resendemail, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
                return $user ? tenancy::get_actual_tenant_id($user->id) : 0;
            }
        }
        if ($CFG->wwwroot.'/login/forgot_password.php' === strip_querystring($FULLME)) {
            $username = optional_param('username', null, PARAM_RAW);
            $email = optional_param('email', null, PARAM_RAW_TRIMMED);
            if ($username) {
                if (($user = \core_user::get_user_by_username(\core_text::strtolower($username)))
                        && !$user->deleted && !$user->suspended) {
                    return tenancy::get_tenant_id($user->id);
                }
            } else if ($email && ($user = self::find_user_by_email($email))) {
                return tenancy::get_tenant_id($user->id);
            }
        }

        return 0;
    }

    /**
     * Tries to locate a user by the username (first in the current tenant then in all)
     *
     * @param string $email
     * @return \stdClass|null
     */
    public static function find_user_by_email(string $email): ?\stdClass {
        global $DB, $CFG;
        // SQL copied from core_login_process_password_reset(), see explanation there.
        $sql = "SELECT *
                  FROM {user}
                 WHERE " . $DB->sql_equal('email', ':email1', false, true) . "
                   AND id IN (SELECT id
                                FROM {user}
                               WHERE mnethostid = :mnethostid
                                 AND deleted = 0
                                 AND suspended = 0
                                 AND " . $DB->sql_equal('email', ':email2', false, false) . ")";

        $params = array(
            'email1' => $email,
            'email2' => $email,
            'mnethostid' => $CFG->mnet_localhost_id,
        );

        // If more than one user found, try to get the one from the current tenant, otherwise - the first one.
        $users = $DB->get_records_sql($sql, $params);
        if (count($users) > 1) {
            foreach ($users as $user) {
                if (tenancy::get_tenant_id($user->id) == tenancy::get_tenant_id()) {
                    return $user;
                }
            }
        }
        return reset($users) ?: null;
    }

    /**
     * Check when it is important that user logs in to the site from the tenant-specific URL,
     * i.e. the sign up/sign on options are different.
     *
     * These 3 cases are checked in this method:
     *  - auth_email is enabled and registerauth is set to email at least for one tenant.
     *  - oauth2 is enabled for some tenants but not all.
     *  - oauth2 is enabled and authpreventaccountcreation is false for at least some tenants.
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function login_auth_is_different_for_different_tenants(): bool {
        global $CFG;
        $tenants = tenant::get_records();
        $tenantsoauthstatuses = [];

        foreach ($tenants as $tenant) {
            if (sharedspace::is_shared_space($tenant->get('id'))) {
                continue;
            }
            config::push_for_tenant($tenant->get('id'));
            $authmethods = explode(',', $CFG->auth);

            $tenantsoauthstatuses[] = in_array('oauth2', $authmethods);
            if ((in_array('email', $authmethods) && $CFG->registerauth === 'email') ||
                (in_array('oauth2', $authmethods) && empty($CFG->authpreventaccountcreation))) {
                config::pop();
                return true;
            }
            config::pop();
        }

        return (count(array_unique($tenantsoauthstatuses)) > 1);
    }

    /**
     * Returns the list of plugins that are either enabled for all tenants or optional
     *
     * Similar to {@see get_enabled_auth_plugins()} but may return more results if some
     * auth methods are not available for the current tenant but available for any other
     *
     * @return array list of auth plugin names
     */
    public static function get_plugins_enabled_anywhere(): array {
        if (!tenancy::is_site_multi_tenant()) {
            return get_enabled_auth_plugins();
        }

        $authsavailable = array_keys(\core_component::get_plugin_list('auth'));
        $oldauth = config::get_config_default('', 'auth') ?? '';
        $authsenabled = preg_split('/,/', $oldauth, -1, PREG_SPLIT_NO_EMPTY);
        $enabledoptional = preg_split('/,/', $CFG->{self::CONFIG_AUTH_OVERRIDABLE_ENABLED} ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $disabledavailable = preg_split('/,/', $CFG->{self::CONFIG_AUTH_OVERRIDABLE_DISABLED} ?? '', -1, PREG_SPLIT_NO_EMPTY);

        return array_intersect(
            array_unique(array_merge(['manual', 'nologin'], $authsenabled, $enabledoptional, $disabledavailable)),
            $authsavailable);
    }
}
