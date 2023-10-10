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
 * Show all data related to user jobs and manually assigned managers.
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_organisation\output\user_jobs;

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

$userid = required_param('id', PARAM_INT);
$user = core_user::get_user($userid, '*', MUST_EXIST);

$context = \context_system::instance();
\tool_wp\admin_externalpage::setup_page(
    'tool_organisation_structure',
    '', ['id' => $userid],
    '/admin/tool/organisation/user.php');
\tool_wp\admin_externalpage::setup_subpage(fullname($user));

$PAGE->set_secondary_navigation(false);
$PAGE->set_title(fullname($user));
$PAGE->add_header_action(html_writer::link(
    (new moodle_url('/user/profile.php', ['id' => $user->id]))->out(false),
    $OUTPUT->pix_icon('i/user', get_string('profile')) . get_string('gotouserprofile', 'tool_organisation'),
    ['class' => 'btn btn-secondary']
));

echo $OUTPUT->header();

$userjobs = new user_jobs($user);
echo $OUTPUT->render_from_template('tool_organisation/user_jobs', $userjobs->export_for_template($OUTPUT));

echo $OUTPUT->footer();
