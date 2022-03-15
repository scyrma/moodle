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
 * certification tool plugin installation script
 *
 * @package    tool_certification
 * @author     2019 Marina Glancy
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Enable the plugin after installation.
 *
 * @uses \tool_tenant\manager::create_workplace_role()
 * @return bool
 */
function xmldb_tool_certification_install() {
    global $DB, $CFG;
    update_capabilities('tool_certification'); // See MDL-65668 about why this is needed.

    // Create default role "Certification manager".
    component_class_callback('\tool_tenant\manager', 'create_workplace_role',
        ['tool_certification_manager',
            get_string('rolemanager', 'tool_certification'),
            get_string('rolemanagerdescription', 'tool_certification'),
            ['tool/certification:edit', 'tool/certification:allocateuser']]);
    return true;
}
