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

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$return = optional_param('return', null, PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

// Use current user's tenant if "id" parameter is not present.
$id = $id ?: \tool_tenant\tenancy::get_tenant_id();

try {
    $url = new moodle_url('/admin/tool/tenant/edit.php', ['id' => $id]);
    admin_externalpage_setup('tool_tenant', '', null, $url);
    \tool_tenant\permission::require_can_access_tenant($id);
} catch (moodle_exception $e) {
    // User does not have access. Try to redirect to the users or details page for the current tenant.
    // Prior to Moodle 3.10 this page was multi-purposed, we need to keep this functionality in case this
    // page was bookmarked.
    if (\tool_tenant\permission::can_browse_users()) {
        redirect(\tool_tenant\manager::get_users_url());
    }
    if (\tool_tenant\permission::can_edit_tenant_theme() ||
            \tool_tenant\permission::can_view_tenant_details(\tool_tenant\tenancy::get_tenant_id())) {
        redirect(\tool_tenant\manager::get_details_url());
    }
    throw new \moodle_exception('nopermissiontab', 'tool_wp');
}

$manager = new \tool_tenant\manager();
$tenant = $manager->get_tenant($id);

// Add extra button: Edit details.
if (\tool_tenant\permission::can_edit_tenant($tenant->get('id'))) {
    $editdetailsstr = get_string('editdetails', 'tool_tenant');
    $buttonparams = ['data-action' => 'editdetails', 'data-tenantid' => $id, 'data-tenantname' => $tenant->get_formatted_name()];
    $edit = new \tool_wp\output\page_header_button($editdetailsstr, $buttonparams);

    $renderer = $PAGE->get_renderer('tool_tenant');
    $PAGE->set_button($edit->render($renderer) . $PAGE->button);
    $PAGE->requires->js_call_amd('tool_tenant/tenant', 'init');
}

// Display the name of the current tenant.
$heading = $tenant->get_formatted_name();
$PAGE->navbar->add($heading);
$PAGE->set_heading($heading);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('tool_wp/tabs',
    (new \tool_tenant\output\edit(['isdefault' => $tenant->get('isdefault'), 'tenantid' => $id]))->export_for_template($OUTPUT));
echo $OUTPUT->footer();
