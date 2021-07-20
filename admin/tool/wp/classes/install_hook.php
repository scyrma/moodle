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
 * Class used to execute hook after install all plugins.
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <daniel@moodle.com>
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
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class install_hook {

    /**
     * This is executed by web and cli install, after installing plugins and setting default config.
     *
     */
    public static function execute() {
        global $DB, $CFG;

        // Do not do it in the behat/unittests because it will break all core tests.
        if (!defined('BEHAT_SITE_RUNNING') && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {

            // Remove the "admin bookmarks" block.
            if ($adminbookmarksblock = $DB->get_record('block_instances', ['blockname' => 'admin_bookmarks'])) {
                blocks_delete_instance($adminbookmarksblock);
            }

            // Hide blocks 'recentlyaccessedcourses' and 'myoverview'.
            $DB->execute('UPDATE {block} set visible=? WHERE name=?', [0, 'recentlyaccessedcourses']);

            // Remove instances of recentlyaccessedcourses' and 'myoverview' from all dashboards (and default dashboard).
            if ($CFG->tool_wp_installed >= 2) {
                // Execute if this is the fresh install or it is an upgrade from LMS with $CFG->forcewpsetup.
                $instances = $DB->get_records_select('block_instances',
                    'pagetypepattern=:myindex AND (blockname=:b1 OR blockname=:b2)',
                    ['myindex' => 'my-index', 'b1' => 'myoverview', 'b2' => 'recentlyaccessedcourses']);
                foreach ($instances as $blockinstance) {
                    blocks_delete_instance($blockinstance);
                }
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
                ['plugin' => 'tool_mobile', 'name' => 'forcedurlscheme', 'value' => 'mmworkplace', 'default' => 'moodlemobile'],
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
