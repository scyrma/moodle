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
 * Some workplace-specific callbacks
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

/**
 * Class used to display workplace copyright information
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class workplace {

    /**
     * Moodle Workplace copyright information
     *
     * @return string
     */
    public static function copyright() {
        global $OUTPUT;
        $plugin = \core_plugin_manager::instance()->get_plugins_of_type('tool')['wp'];
        $release = !empty($plugin->release) ? $plugin->release : $plugin->versiondb;

        // Copyright notice.
        // IT IS ILLEGAL TO HIDE, REMOVE OR MODIFY THIS COPYRIGHT NOTICE.
        $copyrighttext = 'This installation contains ' .
            \html_writer::link('https://moodle.com/workplace', 'Moodle Workplace ' . $release) . '<br />' .
            'Copyright &copy; 2018 onwards, Moodle Pty Ltd<br />'.
            'Workplace components are dual-licensed via Moodle Workplace License and GPLv3. ' .
            'Do not distribute without permission.';
        // End of copyright notice.

        return $OUTPUT->box($copyrighttext, 'copyright workplacecopyright');
    }
}
