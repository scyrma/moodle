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
 * Page editing
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

declare(strict_types=1);

use tool_custompage\permission;
use tool_custompage\local\models\page;
use tool_custompage\output\tabs\{content, details};

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

$pageid = required_param('id', PARAM_INT);

\tool_wp\admin_externalpage::setup_page('custompages', '', [],
    new moodle_url('/admin/tool/custompage/edit.php', ['id' => $pageid]));

// See MDL-74192, we need an easier method to ensure given persistent exists.
$page = page::get_record(['id' => $pageid]);
if ($page === false) {
    throw new invalid_parameter_exception('Invalid page');
}

permission::require_can_preview_page($page);

$PAGE->add_body_class('limitedwidth');
$PAGE->set_pagelayout('standardnonav');
$PAGE->set_secondary_navigation(false);

// Set appropriate access checks for the edit toggle.
if (!$page->get('global')) {
    $PAGE->set_blocks_editing_capability('tool/custompage:edit');
} else {
    $PAGE->set_blocks_editing_capability('tool/custompage:editall');
}

$PAGE->set_title($page->get_formatted_name());
$PAGE->navbar->add($page->get_formatted_name());
$PAGE->set_heading($page->get_formatted_name());

// Add content blocks region.
$PAGE->blocks->add_region('content');

// Define pagetype and subpage to store block instance.
$PAGE->set_pagetype('admin-tool-custompage');
$PAGE->set_subpage($page->get('id'));

// If user can only preview the page, disable various editing controls/notifications.
if (!$caneditpage = permission::can_edit_page($page)) {
    $PAGE->force_lock_all_blocks();
}

echo $OUTPUT->header();

// Render the custom navbar.
$navstridentifier = $caneditpage ? 'editingpage' : 'previewingpage';
$closeurl = new moodle_url('/admin/tool/custompage/manage.php', ['id' => $page->get('id')]);
echo $OUTPUT->render_custom_navbar(get_string($navstridentifier, 'tool_custompage', $page->get_formatted_name()), $closeurl,
    $caneditpage);

if (!$PAGE->user_is_editing() && $caneditpage) {
    echo $OUTPUT->notification(get_string('editpagealert', 'tool_custompage'), 'info');
}

echo $OUTPUT->addblockbutton('content');
echo $OUTPUT->custom_block_region('content');

echo $OUTPUT->footer();
