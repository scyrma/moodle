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
 * Required actions when uninstalling this tool.
 *
 * @package    tool_tenant
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Adrian Greeve
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Uninstall the plugin.
 */
function xmldb_tool_tenant_uninstall() {
    global $DB;

    // Remove all role assignments for the tool_tenant component.
    \role_unassign_all(['component' => 'tool_tenant']);

    // Remove custom roles.
    $DB->delete_records_list('role', 'shortname', ['tool_tenant_admin', 'tool_tenant_manager', 'tool_tenant_user']);

    // Reset custom report component for those owned by tool_tenant.
    $DB->execute(
        'UPDATE {reportbuilder_report} SET component=:component, area=:area, itemid=:itemid WHERE component=:componentold',
        [
           'component' => '',
           'area' => '',
           'itemid' => 0,
           'componentold' => 'tool_tenant',
        ]
    );
}
