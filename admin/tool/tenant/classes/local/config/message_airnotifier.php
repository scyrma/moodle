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

namespace tool_tenant\local\config;

/**
 * Collection of methods for multi-tenancy support in message_airnotifier
 *
 * @package     tool_tenant
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class message_airnotifier {

    /**
     * List of the settings defined by this plugin that potentially allow overriding
     *
     * @param string $plugin  full name of the plugin or 'core' for core settings
     * @return array
     */
    public static function get_multitenant_settings_names(string $plugin) {
        // Plugin message_airnotifier defines its settings but they are stored in the global config.
        if ($plugin === 'core') {
            return [
                'airnotifierurl',
                'airnotifierport',
                'airnotifiermobileappname',
                'airnotifierappname',
                'airnotifieraccesskey',
            ];
        }
        return [];
    }

    /**
     * Tests whether the airnotifier settings have been configured anywhere on the site
     *
     * called from {@see \message_output_airnotifier::is_system_configured()
     *
     * @return boolean true if airnotifier is configured
     */
    public static function is_system_configured() {
        $cache = \cache::make_from_params(\cache_store::MODE_REQUEST, 'tool_tenant', 'config_message_airnotifier');
        $cachekey = 'is_system_configured';
        $result = $cache->get($cachekey);
        if ($result === false) {
            $airnotifiermanager = new \message_airnotifier_manager();
            foreach (\tool_tenant\tenancy::get_tenants() as $tenant) {
                \tool_tenant\config::push_for_tenant($tenant->id);
                $result = $airnotifiermanager->is_system_configured();
                \tool_tenant\config::pop();
                if ($result) {
                    break;
                }
            }
            $cache->set($cachekey, (int)$result);
        }
        return (bool)$result;
    }

    /**
     * Are the message processor's user specific settings configured?
     *
     * called from {@see \message_output_airnotifier::is_user_configured()}
     *
     * @param \stdClass|null $user the user object, defaults to $USER.
     * @return bool True if the user has all necessary settings in their messaging preferences
     */
    public static function is_user_configured($user = null) {
        \tool_tenant\config::push_for_user($user ? $user->id : 0);
        $rv = (new \message_airnotifier_manager())->is_system_configured();
        \tool_tenant\config::pop();
        return $rv;
    }
}
