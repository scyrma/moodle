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
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     tool_organisation
 * @category    upgrade
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Custom code to be run on installing the plugin.
 *
 * @uses \tool_tenant\manager::create_workplace_role()
 */
function xmldb_tool_organisation_install() {
    global $DB, $CFG;
    update_capabilities('tool_organisation'); // See MDL-65668 about why this is needed.

    // Create default role "Organisation manager".
    component_class_callback('\tool_tenant\manager', 'create_workplace_role',
        ['tool_organisation_manager',
            get_string('rolemanager', 'tool_organisation'),
            get_string('rolemanagerdescription', 'tool_organisation'),
            ['tool/organisation:managedepartments', 'tool/organisation:managepositions', 'tool/organisation:assignjobs']]);

    return true;
}
