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
 * Manage tenants users
 *
 * @package     tool_tenant
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$return = optional_param('return', null, PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$canviewalltenants = \tool_tenant\permission::can_view_tenants_list();
if (!$id || !$canviewalltenants) {
    // Use current user's tenant.
    $id = \tool_tenant\tenancy::get_tenant_id();
}

try {
    admin_externalpage_setup('tool_tenant_users', '', null, \tool_tenant\manager::get_edit_tenant_url($id));
} catch (moodle_exception $e) {
    admin_externalpage_setup('tool_tenant_theme', '', null, \tool_tenant\manager::get_edit_tenant_url($id) . '#!appearance');
}
$manager = new \tool_tenant\manager();
$tenant = $manager->get_tenant($id);

if ($canviewalltenants) {
    // Display the name of the current tenant.
    $heading = $tenant->get_formatted_name();
    $PAGE->navbar->ignore_active();
    $PAGE->navbar->add(get_string('administrationsite'), new moodle_url('/admin/search.php'));
    $PAGE->navbar->add(get_string('users'), new moodle_url('/admin/category.php', ['category' => 'users']));
    $PAGE->navbar->add(get_string('organisationadmintab', 'tool_tenant'), new moodle_url('/admin/category.php',
            ['category' => 'tool_organisation']));
    $PAGE->navbar->add(get_string('managetenants', 'tool_tenant'), new moodle_url('/admin/tool/tenant/index.php'));
    $PAGE->navbar->add($heading);
} else {
    // Do not display the name of the tenant.
    $heading = get_string('userlist', 'admin');
}

// Add extra button: Edit details.
$canmanage = has_capability('tool/tenant:manage', \context_system::instance());
if ($canmanage) {
    $editdetailsstr = get_string('editdetails', 'tool_tenant');
    $buttonparams = ['data-action' => 'editdetails', 'data-tenantid' => $id, 'data-tenantname' => $tenant->get_formatted_name()];
    $edit = new \tool_wp\output\page_header_button($editdetailsstr, $buttonparams);

    $renderer = $PAGE->get_renderer('tool_tenant');
    $PAGE->set_button($edit->render($renderer) . $PAGE->button);
}
$PAGE->set_heading($heading);
echo $OUTPUT->header();

echo $OUTPUT->render_from_template('tool_wp/tabs',
    (new \tool_tenant\output\users(['tenantid' => $id]))->export_for_template($OUTPUT));

echo $OUTPUT->footer();
