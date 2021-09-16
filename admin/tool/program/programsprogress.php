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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Entry point for a report that shows the progress for all programs of a given user.
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\api;
use tool_program\output\programs_progress_view;
use tool_program\permission;

require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$userid = required_param('userid', PARAM_INT); // User id.
$type = optional_param('type', -1, PARAM_INT); // Pre-load table filtering by this status type.

$context = context_system::instance();
require_login();

// Check if can view reports of this user.
permission::require_can_view_user_programs_progress($userid);

if ($type !== -1 && !array_key_exists($type, api::get_program_statuses_fieldset())) {
    throw new moodle_exception('errorreporttypedoesnotexist', 'tool_program');
}

$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url("/$CFG->admin/tool/program/programsprogress.php", ['userid' => $userid, 'type' => $type]));
$PAGE->set_context($context);

$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
$username = fullname($user);
$programsstr = get_string('programs', 'tool_program');
$PAGE->navbar->add($username, new moodle_url('/user/profile.php', ['id' => $userid]));
$PAGE->navbar->add($programsstr);
$PAGE->set_title($programsstr . ' : ' . $username);
$PAGE->set_heading($programsstr . ' : ' . $username);

/** @var tool_program\output\renderer|core_renderer $output */
$output = $PAGE->get_renderer('tool_program');
echo $output->header();
$renderable = new programs_progress_view($userid, $type);
echo $output->render($renderable);
echo $output->footer();
