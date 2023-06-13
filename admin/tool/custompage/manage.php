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
 * Page management
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

declare(strict_types=1);

use tool_custompage\permission;
use tool_custompage\local\models\page;
use tool_custompage\output\tabs\{content, details, audience, access};
use tool_tenant\tenancy;
use tool_wp\output\secondary_tabs;

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

$pageid = required_param('id', PARAM_INT);

\tool_wp\admin_externalpage::setup_page('custompages', '', [],
    new moodle_url('/admin/tool/custompage/manage.php', ['id' => $pageid]));
navigation_node::override_active_url(new moodle_url('/admin/tool/custompage/index.php'));

// See MDL-74192, we need an easier method to ensure given persistent exists.
$page = page::get_record(['id' => $pageid]);
if ($page === false) {
    throw new invalid_parameter_exception('Invalid page');
}

permission::require_can_preview_page($page);
$caneditpage = permission::can_edit_page($page);

\tool_wp\admin_externalpage::setup_subpage($page->get('name'));

$PAGE->requires->js_call_amd('tool_custompage/manage', 'init');

// Action menu.
$actions = new action_menu();
$icon = $OUTPUT->pix_icon('i/menu', get_string('actions', 'core_reportbuilder'));
$actions->set_menu_trigger($icon, 'btn btn-icon d-flex align-items-center justify-content-center no-caret my-1');

if (!$page->get('global') || (permission::can_create_global_page() && tenancy::is_site_multi_tenant())) {
    $actions->add(new action_menu_link(
        new moodle_url('#'),
        new pix_icon('t/copy', ''),
        new lang_string('duplicate'),
        false,
        ['data-action' => 'page-duplicate', 'data-page-id' => $page->get('id'), 'data-page-name' => $page->get_formatted_name()],
    ));
}

// Allow user to duplicate page to tenant/globally.
if (!$page->get('global') && (permission::can_create_global_page() && tenancy::is_site_multi_tenant())) {
    $actions->add(new action_menu_link(
        new moodle_url('#'),
        new pix_icon('t/globe', '', 'tool_custompage'),
        new lang_string('duplicatepageglobal', 'tool_custompage'),
        false,
        ['data-action' => 'page-duplicate', 'data-page-id' => $page->get('id'), 'data-page-name' => $page->get_formatted_name(),
            'data-page-global' => 1],
    ));
} else if ($page->get('global')) {
    $actions->add(new action_menu_link(
        new moodle_url('#'),
        new pix_icon('t/down', ''),
        new lang_string('duplicatepagetenant', 'tool_custompage'),
        false,
        ['data-action' => 'page-duplicate', 'data-page-id' => $page->get('id'), 'data-page-name' => $page->get_formatted_name(),
            'data-page-global' => 0],
    ));
}

if ($caneditpage) {
    $actions->add(new action_menu_link(
        new moodle_url('#'),
        new pix_icon('t/delete', ''),
        new lang_string('delete'),
        false,
        ['data-action' => 'page-delete', 'data-page-id' => $page->get('id'), 'data-page-name' => $page->get_formatted_name(),
            'data-page-url' => (new moodle_url('/admin/tool/custompage/index.php'))->out()],
    ));
}

$heading = $page->get_formatted_name();
if ($page->get('global')) {
    $heading .= html_writer::span(get_string('globalpage', 'tool_custompage'),
        'badge badge-pill badge-secondary font-small ml-2 align-middle');
}

$PAGE->set_heading($heading, false);
$PAGE->add_header_action($OUTPUT->render($actions));

// Tabs.
$tabdata = ['pageid' => $page->get('id')];
$tabs = [
    new content($tabdata),
    new details($tabdata),
];
if ($caneditpage) {
    $tabs[] = new audience($tabdata);
}
$tabs[] = new access($tabdata);

$tabsoutput = new secondary_tabs($tabdata, $tabs);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('tool_wp/secondary_tabs', $tabsoutput->export_for_template($OUTPUT));

echo $OUTPUT->footer();
