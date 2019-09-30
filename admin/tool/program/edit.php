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
 * Edit program view
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_program\permission;
use tool_program\persistent\program;
use tool_wp\output\page_header_button;

require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$programid = required_param('id', PARAM_INT); // Program id.

// Check permissions.
require_login();
$context = context_system::instance();

$PAGE->set_pagelayout('admin');
$editprogramurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $programid]);
$PAGE->set_url($editprogramurl);
$PAGE->set_context($context);

$program = new program($programid);
permission::require_can_view_details($program);

$baseset = $program->get_base_set();
$basesetid = $baseset->get('id');
$fullname = format_string($program->get('fullname'));

$PAGE->navbar->add(get_string('administrationsite'), new moodle_url('/admin/search.php'));
$PAGE->navbar->add(get_string('coursesadmintab', 'tool_wp'), new moodle_url('/admin/category.php', ['category' => 'courses']));
$PAGE->navbar->add(get_string('programs', 'tool_program'), new moodle_url('/admin/tool/program/index.php'));
$PAGE->navbar->add($fullname);
$PAGE->set_title(get_string('editprogramsettings', 'tool_program'));
$PAGE->set_heading($fullname);

/** @var tool_program\output\renderer|core_renderer $output */
$output = $PAGE->get_renderer('tool_program');

// Add extra button: Edit details.
if (permission::can_edit_details($program)) {
    $editdetailsstr = get_string('editdetails', 'tool_program');
    $buttonparams = ['data-action' => 'editdetails', 'data-programid' => $programid, 'data-programname' => $fullname];
    $edit = new page_header_button($editdetailsstr, $buttonparams);
    $PAGE->set_button($edit->render($output) . $PAGE->button);
}

echo $output->header();
echo $output->render_edit_program($programid, $basesetid, $context->id);
echo $output->footer();
