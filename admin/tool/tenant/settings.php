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
 * Add admin settings to the tree
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

if (!$ADMIN->locate('tool_organisation')) {
    // Add category "Organisation".
    $ADMIN->add('users', new admin_category('tool_organisation', new lang_string('organisationadmintab', 'tool_tenant')),
        'accounts');
}

// Manage all tenants.
$ADMIN->add('tool_organisation', new admin_externalpage('tool_tenant', new lang_string('managetenants', 'tool_tenant'),
    new moodle_url('/admin/tool/tenant/index.php'), ['tool/tenant:manage', 'tool/tenant:allocate']));


// Browse list of users in the current tenant.
$ADMIN->add('tool_organisation', new \tool_wp\admin_externalpage('tool_tenant_users',
    new lang_string('usermanagement', 'tool_tenant'),
    \tool_tenant\manager::get_users_url(),
    function() {
        return \tool_tenant\permission::can_browse_users(\tool_tenant\tenancy::get_tenant_id());
    }));


// Personalisation of theme appearance for the current tenant.
$ADMIN->add('appearance', new \tool_wp\admin_externalpage('tool_tenant_theme', new lang_string('appearance'),
    \tool_tenant\manager::get_details_url(),
    function() {
        return \tool_tenant\permission::can_edit_tenant_theme() ||
            \tool_tenant\permission::can_view_tenant_details(\tool_tenant\tenancy::get_tenant_id());
    }));


// Add a link to "All users".
$insertbefore = null;
$accounts = $ADMIN->locate('accounts');
$insertbefore = $accounts && $accounts->get_children() ? $accounts->get_children()[0]->name : null;
$ADMIN->add('accounts', new \tool_wp\admin_externalpage('tool_tenant_allusers', new lang_string('allusers', 'tool_tenant'),
    \tool_tenant\manager::get_allusers_url(),
    function() {
        return \tool_tenant\permission::can_switch_tenant();
    }), $insertbefore);


// Hide core site admin pages "Browse list of users", "Add new user" and "User management".
foreach (['editusers', 'addnewuser', 'usermanagement'] as $elname) {
    $element = $ADMIN->locate($elname);
    if ($element) {
        $element->hidden = true;
    }
}

// Substitute frontpage settings with custom settings classes. We override the value for the $SITE->fullname and $SITE->shortname
// in the setup callback but we need to use the actual values in the Frontpage settings.
/** @var admin_settingpage $frontpagesettings */
$frontpagesettings = $ADMIN->locate('frontpagesettings');
if ($frontpagesettings && isset($frontpagesettings->settings->fullname) &&
        get_class($frontpagesettings->settings->fullname) === 'admin_setting_sitesettext') {
    $frontpagesettings->settings->fullname = new tool_tenant\admin_setting_sitesettext($frontpagesettings->settings->fullname);
    $frontpagesettings->settings->shortname = new tool_tenant\admin_setting_sitesettext($frontpagesettings->settings->shortname);
    $frontpagesettings->settings->shortname->description = get_string('siteshortnamedesc', 'tool_tenant');
}

if ($optionalsubsystems = $ADMIN->locate('optionalsubsystems')) {
    $optionalsubsystems->add(new admin_setting_configcheckbox('tool_tenant_tenantlimitenabled',
        new lang_string('tenantlimitenabled', 'tool_tenant'),
        new lang_string('tenantlimitenabled_desc', 'tool_tenant'), 0));

    $optionalsubsystems->add(new admin_setting_configtext('tool_tenant_tenantlimit',
        new lang_string('tenantlimit', 'tool_tenant'),
        new lang_string('tenantlimit_desc', 'tool_tenant'), 1, PARAM_INT));

    $optionalsubsystems->hide_if('tool_tenant_tenantlimit', 'tool_tenant_tenantlimitenabled');

    $optionalsubsystems->add(new admin_setting_configcheckbox('tool_tenant_userlimitenabled',
        new lang_string('tenantuserlimitenabled', 'tool_tenant'),
        '', 0));

    $optionalsubsystems->add(new admin_setting_configtext('tool_tenant_userlimit',
        new lang_string('tenantuserlimit', 'tool_tenant'),
        new lang_string('tenantuserlimit_desc', 'tool_tenant'), 0, PARAM_INT));

    $optionalsubsystems->hide_if('tool_tenant_userlimit', 'tool_tenant_userlimitenabled');

    $optionalsubsystems->add(new admin_setting_configcheckbox('userlimitenabled',
        new lang_string('siteuserlimitenabled', 'tool_tenant'),
        '', 0));

    $optionalsubsystems->add(new admin_setting_configtext('userlimit',
        new lang_string('siteuserlimit', 'tool_tenant'),
        new lang_string('siteuserlimit_desc', 'tool_tenant'), 0, PARAM_INT));

    $optionalsubsystems->hide_if('userlimit', 'userlimitenabled');

}

// Substitute "Available authentication plugins" element with the custom multi-tenant one.
/** @var admin_settingpage $authsettings */
$authsettings = $ADMIN->locate('manageauths');
if (isset($authsettings->settings->authsui) && $authsettings->settings->authsui instanceof admin_setting_manageauths) {
    $authsettings->settings->authsui = new \tool_tenant\admin_setting_manageauths();
}

// Add tenant selector setting to auth settings settings.
if ($authsettings) {
    $authsettings->add(new admin_setting_configcheckbox(
        'tool_tenant/showtenantselector',
        get_string('showtenantselector', 'tool_tenant'),
        get_string('showtenantselector_help', 'tool_tenant'), 0));
}
