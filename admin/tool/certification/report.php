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
 * Report entry point for certifications
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

declare(strict_types=1);

use tool_certification\permission;
use tool_certification\reportbuilder\local\systemreports\progress;

require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$userid = optional_param('userid', 0, PARAM_INT); // User id.
$certificationid = optional_param('certificationid', 0, PARAM_INT);
$type = optional_param('type', -1, PARAM_INT); // Pre-load table filtering by this status type.

require_login();
$PAGE->set_url(new moodle_url('/admin/tool/certification/report.php', ['type' => $type]));

// This file used to serve different types of reports, redirect to new locations.
if (!empty($userid)) {
    debugging('Redirecting to admin/tool/certification/user_report.php', DEBUG_DEVELOPER);
    redirect(new moodle_url('/admin/tool/certification/user_report.php', ['id' => $userid]));
}
if (!empty($certificationid)) {
    debugging('Redirecting to admin/tool/certification/certification_report.php', DEBUG_DEVELOPER);
    redirect(new moodle_url('/admin/tool/certification/certification_report.php', ['id' => $certificationid]));
}

// Check access.
\tool_wp\admin_externalpage::setup_page('certifications', '', [], $PAGE->url);
permission::require_can_view_certifications_progress_report();

// Set title and navbar.
if (!$PAGE->navbar->has_items()) {
    // This can happen if the person can access the "Programs" page but not "Site administration".
    $setting = admin_get_root(false, false)->locate('certifications', true);
    $PAGE->navbar->add($setting->visiblename, $setting->url);
}
$strcertificationprogress = get_string('certificationprogress', 'tool_certification');
\tool_wp\admin_externalpage::setup_subpage($strcertificationprogress);
$PAGE->set_secondary_navigation(false);

$PAGE->requires->js_call_amd('tool_certification/certification_user_log', 'init');
$PAGE->requires->js_call_amd('tool_program/program_progress_modal', 'init');

echo $OUTPUT->header();

// Set initial certification status filter if type property is specified.
/** @var progress $report */
$report = \tool_tenant\system_report_factory::create(progress::class, ['userid' => $userid, 'certificationid' => $certificationid]);
$report->prefilter_by_status($type);

echo $report->output();
echo $OUTPUT->footer();
