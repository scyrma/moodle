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
 * Entry point for a report that shows the detailed sets and courses progress for a given program and user.
 *
 * @package   tool_program
 * @copyright 2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_program\output\program_progress_view;
use tool_program\permission;
use tool_program\persistent\program;

require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$programid = required_param('programid', PARAM_INT);
$program = new program($programid);
$userid = required_param('userid', PARAM_INT);

$context = context_system::instance();
require_login();

// Check if can view reports of this user.
permission::require_can_view_user_programs_progress($userid, $program);
$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
$username = fullname($user);
$options = ['context' => $context, 'escape' => false];
$programname = format_string($program->get('fullname'), true, $options);
$programsstr = get_string('programs', 'tool_program');

$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url("/$CFG->admin/tool/program/programprogress.php", ['programid' => $programid, 'userid' => $userid]));
$PAGE->set_context($context);

$PAGE->navbar->add($username, new moodle_url('/user/profile.php', ['id' => $userid]));
$PAGE->navbar->add($programsstr, new moodle_url("/$CFG->admin/tool/program/programsprogress.php", ['userid' => $userid]));
$PAGE->navbar->add($programname);
$PAGE->set_title($programname . ' : ' . $username);
$PAGE->set_heading($programname . ' : ' . $username);

/** @var tool_program\output\renderer|core_renderer $output */
$output = $PAGE->get_renderer('tool_program');
echo $output->header();
$renderable = new program_progress_view($programid, $userid);
echo $output->render($renderable);
echo $output->footer();
