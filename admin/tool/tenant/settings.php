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
$ADMIN->add('tool_organisation', new \tool_wp\admin_externalpage('tool_tenant_users', new lang_string('userlist', 'admin'),
    new moodle_url('/admin/tool/tenant/edit.php'), function() {
        return \tool_tenant\permission::can_browse_users();
    }));


// Personalisation of theme appearance for the current tenant.
$ADMIN->add('appearance', new \tool_wp\admin_externalpage('tool_tenant_theme', new lang_string('managetheme', 'tool_tenant'),
    new moodle_url('/admin/tool/tenant/edit.php#appearance'),
    function() {
        return \tool_tenant\permission::can_edit_tenant_theme();
    }));

// Substitute frontpage settings with custom settings classes. We override the value for the $SITE->fullname and $SITE->shortname
// in the setup callback but we need to use the actual values in the Frontpage settings.
/** @var admin_settingpage $frontpagesettings */
$frontpagesettings = $ADMIN->locate('frontpagesettings');
if ($frontpagesettings && isset($frontpagesettings->settings->fullname) &&
        get_class($frontpagesettings->settings->fullname) === 'admin_setting_sitesettext') {
    $frontpagesettings->settings->fullname = new tool_tenant\admin_setting_sitesettext($frontpagesettings->settings->fullname);
    $frontpagesettings->settings->shortname = new tool_tenant\admin_setting_sitesettext($frontpagesettings->settings->shortname);
}

if ($optionalsubsystems = $ADMIN->locate('optionalsubsystems')) {
    $optionalsubsystems->add(new admin_setting_configcheckbox('tool_tenant_tenantlimitenabled',
        new lang_string('tenantlimitenabled', 'tool_tenant'),
        new lang_string('tenantlimitenabled_desc', 'tool_tenant'), 0));

    $options = [];
    for ($i = 1; $i < 15; $i++) {
        $options[$i] = $i;
    }
    $optionalsubsystems->add(new admin_setting_configselect('tool_tenant_tenantlimit',
        new lang_string('tenantlimit', 'tool_tenant'),
        new lang_string('tenantlimit_desc', 'tool_tenant'), 1, $options));
    $optionalsubsystems->hide_if('tool_tenant_tenantlimit', 'tool_tenant_tenantlimitenabled');
}
