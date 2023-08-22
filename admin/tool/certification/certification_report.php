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
 * Certification progress report view.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

declare(strict_types=1);

use tool_certification\certification;
use tool_certification\permission;
use tool_certification\reportbuilder\local\systemreports\progress;

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$certificationid = required_param('id', PARAM_INT);
$type = optional_param('type', -1, PARAM_INT); // Pre-load table filtering by this status type.

// Check permissions.
\tool_wp\admin_externalpage::setup_page('certifications', '', [],
    new moodle_url('/admin/tool/certification/certification_report.php',
    ['id' => $certificationid, 'type' => $type]));

$certification = new certification($certificationid);
permission::require_can_view_users_progress($certification);

$PAGE->requires->js_call_amd('tool_certification/certification_user_log', 'init');
$PAGE->requires->js_call_amd('tool_program/program_progress_modal', 'init');

// Form breadcrumb/heading/title, the breadcrumb should look like this:  "... > Certifications > Cert name > Progress report".
// In order to add two extra items to the breadcrumb we call setup_subpage() twice.
if (!$PAGE->navbar->has_items()) {
    // This can happen if the person can access the "Programs" page but not "Site administration".
    $setting = admin_get_root(false, false)->locate('certifications', true);
    $PAGE->navbar->add($setting->visiblename, $setting->url);
}
$editcertificationurl = new moodle_url('/admin/tool/certification/edit.php', ['id' => $certificationid]);
\tool_wp\admin_externalpage::setup_subpage($certification->get('fullname'), $editcertificationurl);
\tool_wp\admin_externalpage::setup_subpage(get_string('certificationprogress', 'tool_certification'));

// Disable secondary navigation on this page, otherwise the "Site administration" tabs are shown.
$PAGE->set_secondary_navigation(false);

echo $OUTPUT->header();
/** @var progress $report */
$report = \tool_tenant\system_report_factory::create(progress::class, ['certificationid' => $certificationid]);
$report->prefilter_by_status($type);
echo $report->output();
echo $OUTPUT->footer();
