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
 * Manage tenants users
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

// Use current user's tenant if "id" parameter is not present.
$id = optional_param('id', 0, PARAM_INT) ?: \tool_tenant\tenancy::get_tenant_id();

try {
    \tool_wp\admin_externalpage::setup_page('tool_tenant', '', ['id' => $id], '/admin/tool/tenant/edit.php');
    \tool_tenant\permission::require_can_access_tenant($id);
} catch (moodle_exception $e) {
    // User does not have access. Try to redirect to the users or details page for the current tenant.
    // Prior to Moodle 3.10 this page was multi-purposed, we need to keep this functionality in case this
    // page was bookmarked.
    if (\tool_tenant\permission::can_browse_users()) {
        redirect(\tool_tenant\manager::get_users_url());
    }
    if (\tool_tenant\permission::can_access_tenant_settings_page()) {
        redirect(\tool_tenant\manager::get_details_url());
    }
    throw new \moodle_exception('nopermissiontab', 'tool_wp');
}

$manager = new \tool_tenant\manager();
$tenant = $manager->get_tenant($id);
tool_wp\admin_externalpage::setup_subpage($tenant->get('name'));

/** @var tool_tenant\output\renderer|core_renderer $renderer */
$renderer = $PAGE->get_renderer('tool_tenant');

$actionmenulinks = $renderer->get_action_menu_links($tenant);
if (!empty($actionmenulinks)) {
    $PAGE->requires->js_call_amd('tool_tenant/kebab_menu', 'init');

    $actionmenu = new action_menu($actionmenulinks);
    $icon = $renderer->pix_icon('i/menu', get_string('actions'));
    $actionmenu->set_menu_trigger($icon, 'btn btn-icon pt-2 rounded-circle no-caret');
    $PAGE->add_header_action($renderer->render($actionmenu));
}

if (\tool_tenant\permission::can_edit_tenant_theme($tenant->get('id'))) {
    $PAGE->requires->js_call_amd('tool_tenant/tenant', 'init');
}

$tabsoutput = new \tool_tenant\output\edit(['isdefault' => $tenant->get('isdefault'), 'tenantid' => $id, 'id' => $id]);

echo $renderer->header();
echo $renderer->render_from_template('tool_wp/secondary_tabs', $tabsoutput->export_for_template($renderer));
echo $renderer->footer();
