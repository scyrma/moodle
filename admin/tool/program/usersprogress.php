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
 * Entry point for a report that shows the progress of all the users of a given program.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\permission;
use tool_program\persistent\program;
use tool_program\reportbuilder\local\systemreports\progress;

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$programid = required_param('id', PARAM_INT);

// Check permissions.
\tool_wp\admin_externalpage::setup_page('programs', '', [],
    new moodle_url('/admin/tool/program/usersprogress.php', ['id' => $programid]));

$program = new program($programid);
permission::require_can_view_users_progress($program);

// Load required JS module.
$PAGE->requires->js_call_amd('tool_program/program_progress_modal', 'init');

// Form breadcrumb/heading/title, the breadcrumb should look like this:  "... > Programs > Program name > Progress report".
// In order to add two extra items to the breadcrumb we call setup_subpage() twice.
$programurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
\tool_wp\admin_externalpage::setup_subpage($program->get('fullname'), $programurl);
\tool_wp\admin_externalpage::setup_subpage(get_string('programprogress', 'tool_program'));

// Disable secondary navigation on this page, otherwise the "Site administration" tabs are shown.
$PAGE->set_secondary_navigation(false);

echo $OUTPUT->header();
$report = \tool_tenant\system_report_factory::create(progress::class, ['programid' => $program->get('id')]);
echo $report->output();
echo $OUTPUT->footer();
