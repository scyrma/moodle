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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

/**
 * Class customfield for tool_program.
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
