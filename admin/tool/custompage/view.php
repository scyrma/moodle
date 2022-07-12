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
 * Page viewing
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

declare(strict_types=1);

use tool_custompage\permission;
use tool_custompage\local\models\page;
use tool_custompage\output\tabs\{content, details};

require_once(__DIR__ . '/../../../config.php');
require_once("{$CFG->libdir}/adminlib.php");

require_login();

$pageid = required_param('id', PARAM_INT);

// See MDL-74192, we need an easier method to ensure given persistent exists.
$page = page::get_record(['id' => $pageid]);
if ($page === false) {
    throw new invalid_parameter_exception('Invalid page');
}

permission::require_can_view_page($page);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/admin/tool/custompage/view.php'), ['id' => $pageid]);
$PAGE->add_body_class('limitedwidth');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$PAGE->set_title($page->get_formatted_name());
$PAGE->navbar->add($page->get_formatted_name());
$PAGE->set_heading($page->get_formatted_name());

// Add content blocks region.
$PAGE->blocks->add_region('content');

// Define pagetype and subpage to show the correct blocks.
$PAGE->set_pagetype('admin-tool-custompage');
$PAGE->set_subpage($page->get('id'));

// User needs extra capabilities to edit page blocks.
$PAGE->set_blocks_editing_capability('tool/custompage:edit');

echo $OUTPUT->header();

if ($PAGE->user_is_editing() && permission::can_edit_page($page)) {
    $icon = $OUTPUT->pix_icon('exclamation-triangle', get_string('warning', 'core'), 'tool_wp', ['class' => 'mt-1 icon-lg']);
    $message = html_writer::span(get_string('viewpagealert', 'tool_custompage'), 'pr-2');
    echo $OUTPUT->notification(html_writer::div($icon . $message, 'd-flex'), 'warning');
}

echo $OUTPUT->custom_block_region('content');

echo $OUTPUT->footer();
