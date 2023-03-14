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
 * Entry point for a report that shows courses progress for a given course or user.
 *
 * @package   block_myteams
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use block_myteams\permission;
use block_myteams\reportbuilder\local\systemreports\course_progress;

require_once(__DIR__ . '/../../config.php');

// Get URL parameters.
$userid = optional_param('userid', 0, PARAM_INT);

require_login();
permission::require_can_view_course_progress($userid);

$strcourseprogress = get_string('courseprogress', 'block_myteams');

$PAGE->set_url(new moodle_url("/blocks/myteams/courseprogress.php", ['userid' => $userid]));
$PAGE->set_secondary_navigation(false);
$PAGE->navbar->add($strcourseprogress);

if (!empty($userid)) {
    $user = core_user::get_user($userid, '*', MUST_EXIST);
    $PAGE->set_context(context_user::instance($user->id));
    $PAGE->navigation->extend_for_user($user);
    $PAGE->set_title("{$strcourseprogress}: " . fullname($user));
} else {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_heading($strcourseprogress);
    $PAGE->set_title($strcourseprogress);
}

echo $OUTPUT->header();
$report = \tool_tenant\system_report_factory::create(course_progress::class, ['userid' => $userid]);
echo $report->output();
echo $OUTPUT->footer();
