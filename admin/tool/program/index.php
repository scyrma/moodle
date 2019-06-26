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
 * Programs manager view. Shows the programs list.
 *
 * @package    tool_program
 * @copyright  2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_program\permission;

require_once(__DIR__ . '/../../../config.php');

// Check permissions.
require_login();
$context = context_system::instance();
permission::require_can_view_list($context);

$PAGE->set_pagelayout('admin');
$programsmanagerurl = new moodle_url('/admin/tool/program/index.php');
$PAGE->set_url($programsmanagerurl);
$PAGE->set_context($context);

$programstr = get_string('programs', 'tool_program');
$PAGE->set_title($programstr);
$PAGE->set_heading($programstr);

/** @var tool_program\output\renderer|core_renderer $output */
$output = $PAGE->get_renderer('tool_program');
echo $output->header();
echo $output->render_program_manager();
echo $output->footer();
