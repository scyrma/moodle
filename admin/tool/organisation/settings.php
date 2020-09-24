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
 * Plugin administration pages are defined here.
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

if ($item = $ADMIN->locate('tool_organisation')) {
    // The category "Organisation" was already added by tool_tenant. Rename it to use the string from this plugin.
    $item->visiblename = new lang_string('organisationadmintab', 'tool_organisation');
} else {
    // Add category "Organisation".
    $ADMIN->add('users', new admin_category('tool_organisation', new lang_string('organisationadmintab', 'tool_organisation')),
        'accounts');
}

// Add item "Organisation structure".
$ADMIN->add('tool_organisation', new \tool_wp\admin_externalpage('tool_organisation_structure',
    get_string('orgstructure', 'tool_organisation'),
    new moodle_url('/admin/tool/organisation/index.php'),
    [\tool_organisation\permission::class, 'can_view_index']));
