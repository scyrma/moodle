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
 * Entry point for a report that shows the progress of all the users of a given program.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\output\users_progress_view;
use tool_program\permission;
use tool_program\persistent\program;

require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$programid = required_param('id', PARAM_INT);

// Check permissions.
require_login();
$program = new program($programid);
$context = context_system::instance();
permission::require_can_view_users_progress($program);

$PAGE->set_pagelayout('admin');
$progressurl = new moodle_url("/$CFG->admin/tool/program/usersprogress.php", ['id' => $programid]);
$PAGE->set_url($progressurl);
$PAGE->set_context($context);

$fullname = format_string($program->get('fullname'));
$programprogressstr = get_string('progressreport', 'tool_program');

$PAGE->navbar->add(get_string('administrationsite'), new moodle_url("/$CFG->admin/search.php"));
$coursesadminurl = new moodle_url("/$CFG->admin/category.php", ['category' => 'courses']);
$PAGE->navbar->add(get_string('coursesadmintab', 'tool_wp'), $coursesadminurl);
$PAGE->navbar->add(get_string('programs', 'tool_program'), new moodle_url("/$CFG->admin/tool/program/index.php"));
if ($program->is_archived()) {
    $PAGE->navbar->add($fullname);
} else {
    $PAGE->navbar->add($fullname, new moodle_url("/$CFG->admin/tool/program/edit.php", ['id' => $programid]));
}
$PAGE->navbar->add($programprogressstr);
$PAGE->set_title($programprogressstr);
$PAGE->set_heading($fullname);

/** @var tool_program\output\renderer|core_renderer $output */
$output = $PAGE->get_renderer('tool_program');

echo $output->header();
$renderable = new users_progress_view($programid);
echo $output->render($renderable);
echo $output->footer();
