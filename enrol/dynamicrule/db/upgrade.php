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
 * Dynamicrule enrol plugin upgrade script
 *
 * @package    enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute enrol_dynamicrule upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_enrol_dynamicrule_upgrade($oldversion) {

    if ($oldversion < 2019102900) {
        // Enable plugin globally.
        $enabled = enrol_get_plugins(true);

        if (!isset($enabled['dynamicrule'])) {
            $enabled['dynamicrule'] = true;
            $enabled = array_keys($enabled);
            set_config('enrol_plugins_enabled', implode(',', $enabled));
        }

        // Dynamicrule savepoint reached.
        upgrade_plugin_savepoint(true, 2019102900, 'enrol', 'dynamicrule');
    }

    return true;
}
