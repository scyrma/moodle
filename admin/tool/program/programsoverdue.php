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
 * Entry point for a report that shows a list of users with programs close to due date or overdued.
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_program\permission;
use \tool_program\output\programs_overdue_view;

require_once(__DIR__ . '/../../../config.php');

$context = context_system::instance();
require_login();

// Check if can view reports.
permission::require_can_view_programs_overdue();

$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url("/$CFG->admin/tool/program/programsoverdue.php"));
$PAGE->set_context($context);

$programsstr = get_string('fullcompletionreport', 'tool_program');
$PAGE->navbar->add($programsstr);
$PAGE->set_title($programsstr);
$PAGE->set_heading($programsstr);

/** @var tool_program\output\renderer|core_renderer $output */
$output = $PAGE->get_renderer('tool_program');
echo $output->header();
$renderable = new programs_overdue_view();
echo $output->render($renderable);
echo $output->footer();
