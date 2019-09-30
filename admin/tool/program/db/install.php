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
 * program tool plugin installation script
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Enable the plugin after installation.
 *
 * @uses \tool_tenant\manager::create_workplace_role()
 * @return bool
 */
function xmldb_tool_program_install() {
    global $DB, $CFG;
    update_capabilities('tool_program'); // TODO MDL-65668 remove.

    // Create default role "Program manager".
    component_class_callback('\tool_tenant\manager', 'create_workplace_role',
        ['tool_program_manager',
            get_string('rolemanager', 'tool_program'),
            get_string('rolemanagerdescription', 'tool_program'),
            ['tool/program:edit', 'tool/program:allocateuser']]);
    return true;
}
