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
 * Entry point for a report that shows the progress for all programs of a given user.
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

declare(strict_types=1);

use tool_program\permission;
use tool_program\reportbuilder\local\systemreports\progress;

require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$type = optional_param('type', -1, PARAM_INT); // Pre-load table filtering by this status type.

// Check access.
require_login();
permission::require_can_view_programs_progress_report();

$PAGE->set_url(new moodle_url('/admin/tool/program/report.php', ['type' => $type]));
\tool_wp\admin_externalpage::setup_page('programs', '', [], $PAGE->url);

// Set title and heading depending on parameters.
$strprogramprogress = get_string('programprogress', 'tool_program');
if (!$PAGE->navbar->has_items()) {
    // This can happen if the person can access the "Programs" page but not "Site administration".
    $setting = admin_get_root(false, false)->locate('programs', true);
    $PAGE->navbar->add($setting->visiblename, $setting->url);
}

\tool_wp\admin_externalpage::setup_subpage($strprogramprogress);
$PAGE->set_secondary_navigation(false);

// Load required JS module and output.
$PAGE->requires->js_call_amd('tool_program/program_progress_modal', 'init');

echo $OUTPUT->header();
/** @var progress $report */
$report = \tool_tenant\system_report_factory::create(progress::class);
$report->prefilter_by_status($type);
echo $report->output();
echo $OUTPUT->footer();
