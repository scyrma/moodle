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

$id = optional_param('id', 0, PARAM_INT);

$canviewalltenants = \tool_tenant\permission::can_view_tenants_list();
if (!$id || !$canviewalltenants) {
    // Use current user's tenant.
    $id = \tool_tenant\tenancy::get_tenant_id();
}

admin_externalpage_setup('tool_tenant_theme');
$manager = new \tool_tenant\manager();
$tenant = $manager->get_tenant($id);

if ($canviewalltenants) {
    // Display the name of the current tenant.
    $heading = get_string('managethemefor', 'tool_tenant', $tenant->get_formatted_name());
} else {
    // Do not display the name of the tenant.
    $heading = get_string('managetheme', 'tool_tenant');
}

$form = new \tool_tenant\form\edit_css_form();
$manager = new \tool_tenant\manager();
$form->set_data($manager->get_css_config($id, $form->get_filemanager_options()));

if ($data = $form->get_data()) {
    $manager->save_css_config($data);
    redirect($PAGE->url, get_string('themesettingssaved', 'tool_tenant'));
}

$PAGE->set_heading($heading);
echo $OUTPUT->header();

$form->display();

echo $OUTPUT->footer();