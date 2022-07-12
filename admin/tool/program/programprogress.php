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
 * Entry point for a report that shows the detailed sets and courses progress for a given program and user.
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\output\program_progress_view;
use tool_program\permission;
use tool_program\persistent\program;

require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$userid = required_param('userid', PARAM_INT);
$programid = optional_param('programid', null, PARAM_INT);
// In case this request comes from certification allocations table currentprogramid can be null and need to check certified one.
$lastprogramid = optional_param('lastprogramid', null, PARAM_INT);
$programid = !empty($programid) ? $programid : $lastprogramid;
if (empty($programid)) {
    throw new moodle_exception('missingparam', '', '', 'programid');
}
$program = new program($programid);

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
$PAGE->set_secondary_navigation(false);

/** @var tool_program\output\renderer|core_renderer $output */
$output = $PAGE->get_renderer('tool_program');
echo $output->header();
$renderable = new program_progress_view($programid, $userid);
echo $output->render($renderable);
echo $output->footer();
