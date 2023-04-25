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
 * Edit program view
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\permission;
use tool_program\persistent\program;

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$programid = required_param('id', PARAM_INT); // Program id.

$context = context_system::instance();
$editprogramurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $programid]);
\tool_wp\admin_externalpage::setup_page('programs', '', [], $editprogramurl);

$program = new program($programid);
permission::require_can_view_details($program);

$baseset = $program->get_base_set();
$basesetid = $baseset->get('id');
$heading = $program->get_formatted_name() .
    html_writer::tag('div', get_string('hiddenfromlearners', 'tool_program'),
    ['class' => 'badge badge-secondary ml-2 font-small align-middle hiddenfromlearnersbadge'
        . ($program->get('visible') ? ' hidden' : '')]);
\tool_wp\admin_externalpage::setup_subpage($program->get('fullname'));
$PAGE->set_heading($heading, false);

$outputpage = new \tool_program\output\edit_page($programid, $basesetid, $context->id);
/** @var tool_program\output\renderer|core_renderer $output */
$output = $PAGE->get_renderer('tool_program');

$actionmenulinks = $output->get_action_menu_links($program);
if (!empty($actionmenulinks)) {
    $actionmenu = new action_menu($actionmenulinks);
    $icon = $output->pix_icon('i/menu', get_string('actions'));
    $actionmenu->set_menu_trigger($icon, 'btn btn-icon pt-2 rounded-circle no-caret');
    $PAGE->add_header_action($output->render($actionmenu));
}

// Edit in shared space button.
if (!permission::can_edit_details($program) && permission::can_edit_details_in_program_tenant($program)) {
    $redirect = new moodle_url('/admin/tool/tenant/switchtenant.php', ['switchtenantid' => $program->get('tenantid'),
        'redirecturl' => $editprogramurl->out_as_local_url(false), 'sesskey' => sesskey()]);
    $PAGE->requires->js_call_amd('tool_program/edit_shared_button', 'init', [$redirect->out(false)]);

    $editdetailsstr = get_string('editdetailsinsharedspace', 'tool_tenant');
    $btn = html_writer::div($editdetailsstr, 'btn btn-sm btn-outline-secondary editdetailsswitchtenant');
    $PAGE->set_heading($PAGE->heading . ' ' . $btn, false);
}

echo $output->header();
echo $output->render_from_template('tool_program/program', $outputpage->export_for_template($output));
echo $output->footer();
