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
 * Class customfield for tool_program.
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_customfield\output\management;
use core_customfield\output\renderer;
use tool_program\customfield\program_handler;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('programscustomfields');

$PAGE->set_context(context_system::instance());

/** @var renderer|core_renderer $output */
$output = $PAGE->get_renderer('core_customfield');
$handler = program_handler::create();
$outputpage = new management($handler);

echo $output->header(),
$output->heading(new lang_string('programscustomfield', 'tool_program')),
$output->render($outputpage),
$output->footer();
