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
 * @copyright   2019 Daniel Neis Araujo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

/**
 * Class used to execute hook after install all plugins.
 *
 * @package     tool_wp
 * @copyright   2019 Daniel Neis Araujo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class install_hook {

    /**
     * This is executed by web and cli install, after installing plugins and setting default config.
     *
     */
    public static function execute() {
        global $DB;

        if (!defined('BEHAT_SITE_RUNNING') && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            // Do not do it in the behat/unittests because it will break all core tests.

            // Remove the "admin bookmarks" block.
            if ($adminbookmarksblock = $DB->get_record('block_instances', ['blockname' => 'admin_bookmarks'])) {
                blocks_delete_instance($adminbookmarksblock);
            }

            // Set "Force login" to true.
            set_config('forcelogin', 1);
        }
    }
}
