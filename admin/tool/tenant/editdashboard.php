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
 * This script implements the user's view of the tenant dashboard, and allows editing it.
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_tenant\dashboard_manager;
use tool_tenant\manager;
use tool_tenant\tenancy;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

global $SITE, $OUTPUT, $PAGE, $DB;

$id = optional_param('id', 0, PARAM_INT);

$PAGE->requires->js_call_amd('tool_tenant/manage_dashboard', 'init');

$tenantid = $id ?: tenancy::get_tenant_id();
$tenant = (new manager())->get_tenant($tenantid);
// If tenant sitename is not defined, then show tenant name (if user can switch tenants) or site name (if not).
$tenantname = !empty($tenant->get('sitename')) ? $tenant->get('sitename') :
    (\tool_tenant\permission::can_switch_tenant() ? $tenant->get('name') : $SITE->fullname);
$tenantname = format_string($tenantname, true, ['context' => \context_system::instance(), 'escape' => false]);

$PAGE->set_blocks_editing_capability('tool/tenant:managedashboard');
admin_externalpage_setup('tool_tenant_dashboard', '', ['id' => $tenantid], '', ['pagelayout' => 'mydashboard']);
\tool_tenant\permission::require_can_edit_tenant_dashboard_blocks($tenantid);

$PAGE->set_pagetype('my-index');
$PAGE->blocks->add_region('content');

// Get the teannt dashboard page.
$page = dashboard_manager::get_tenant_dashboard_page($tenantid);
$PAGE->set_subpage($page->id);

$PAGE->set_heading($tenantname);

// Display a button to reset everyone's dashboard.
$options = ['class' => 'btn btn-outline-secondary mr-2', 'data-action' => 'reset-tenant-dashboard', 'data-tenantid' => $tenantid,
    'data-caneditsitedashboard' => \tool_tenant\permission::can_edit_site_dashboard()];
$resetallbutton = html_writer::tag('button', get_string('reseteveryonesdashboard', 'my'), $options);
$PAGE->set_button($resetallbutton . $PAGE->button);

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('editingdashboard', 'tool_tenant', $tenantname), 'info');
echo $OUTPUT->custom_block_region('content');
echo $OUTPUT->footer();
