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
 * Legacy entry point for a report that shows the progress for all programs of a given user.
 *
 * @deprecated since Moodle Workplace 4.2
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$userid = optional_param('userid', 0, PARAM_INT);
$programid = optional_param('programid', 0, PARAM_INT);
$type = optional_param('type', -1, PARAM_INT);

require_login();
$PAGE->set_url(new moodle_url('/admin/tool/program/programsprogress.php'));

// This script used to serve three different types of reports, redirect to the appropriate
// URL depending on the parameters.
$typeparams = $type === -1 ? [] : ['type' => $type];
if (!empty($userid)) {
    debugging('Redirecting to admin/tool/program/user_report.php', DEBUG_DEVELOPER);
    redirect(new moodle_url('/admin/tool/program/user_report.php',
        ['id' => $userid, 'programid' => $programid] + $typeparams));
}
if (!empty($programid)) {
    debugging('Redirecting to admin/tool/program/program_report.php', DEBUG_DEVELOPER);
    redirect(new moodle_url('/admin/tool/program/program_report.php',
        ['id' => $programid] + $typeparams));
}

redirect(new moodle_url('/admin/tool/program/report.php', $typeparams));
