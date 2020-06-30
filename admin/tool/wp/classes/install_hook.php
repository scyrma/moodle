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
 * Class used to execute hook after install all plugins.
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

/**
 * Class used to execute hook after install all plugins.
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class install_hook {

    /**
     * This is executed by web and cli install, after installing plugins and setting default config.
     *
     */
    public static function execute() {
        global $DB;

        // Do not do it in the behat/unittests because it will break all core tests.
        if (!defined('BEHAT_SITE_RUNNING') && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {

            // Remove the "admin bookmarks" block.
            if ($adminbookmarksblock = $DB->get_record('block_instances', ['blockname' => 'admin_bookmarks'])) {
                blocks_delete_instance($adminbookmarksblock);
            }

            // Set "Force login" to true.
            set_config('forcelogin', 1);
            // Disable donation banner.
            set_config('showcampaigncontent', 0);
            // Disable "user feedback" banner.
            set_config('enableuserfeedback', 0);
            // Disable Moodle.net integration.
            set_config('enablemoodlenet', 0, 'tool_moodlenet');

            // Update mobile config to point to Workplace app.
            $settings = [
                ['plugin' => 'tool_mobile', 'name' => 'enablesmartappbanners', 'value' => 1, 'default' => 0],
                ['plugin' => 'tool_mobile', 'name' => 'iosappid', 'value' => '1470929705', 'default' => '633359593'],
                ['plugin' => 'tool_mobile', 'name' => 'androidappid', 'value' => 'com.moodle.workplace',
                    'default' => 'com.moodle.moodlemobile'],
                ['plugin' => 'tool_mobile', 'name' => 'setuplink', 'value' => 'https://download.moodle.org/mobile',
                    'default' => ''],
                ['name' => 'airnotifiermobileappname', 'value' => 'com.moodle.workplace', 'default' => 'com.moodle.moodlemobile'],
                ['name' => 'airnotifierappname', 'value' => 'commoodleworkplace', 'default' => 'commoodlemoodlemobile'],
            ];

            foreach ($settings as $setting) {
                $plugin = $setting['plugin'] ?? null;

                // Compare current value to default value, if they match then set our own.
                $currentvalue = get_config($plugin, $setting['name']);
                if (empty($currentvalue) || (strcasecmp($currentvalue, $setting['default']) === 0)) {
                    set_config($setting['name'], $setting['value'], $plugin);
                }
            }
        }
    }
}