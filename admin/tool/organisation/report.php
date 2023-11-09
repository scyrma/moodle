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
 * Entry point for a report that shows all the assigned jobs.
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

declare(strict_types=1);

use tool_organisation\permission;

require_once(__DIR__ . '/../../../config.php');

// Check access.
require_login();
permission::require_can_view_jobs();

$PAGE->set_url(new moodle_url('/admin/tool/organisation/report.php'));
\tool_wp\admin_externalpage::setup_page('tool_organisation_structure', '', [], $PAGE->url);

// Set title and heading depending on parameters.
$strprogramprogress = get_string('jobs', 'tool_organisation');
\tool_wp\admin_externalpage::setup_subpage($strprogramprogress);
$PAGE->set_secondary_navigation(false);
$PAGE->set_heading('');

echo $OUTPUT->header();
$jobs = new \tool_organisation\output\tab_jobs([]);
echo $OUTPUT->render_from_template($jobs->get_template(), $jobs->export_for_template($OUTPUT));
echo $OUTPUT->footer();
