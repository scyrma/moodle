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
 * Entry point for a report that shows the progress for all programs of a given user.
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use core_reportbuilder\local\filters\select;
use tool_program\api;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\reportbuilder\local\systemreports\progress;

require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$userid = optional_param('userid', 0, PARAM_INT); // User id.
$programid = optional_param('programid', 0, PARAM_INT);
$type = optional_param('type', -1, PARAM_INT); // Pre-load table filtering by this status type.

require_login();

if (!empty($userid)) {
    permission::require_can_view_user_programs_progress($userid);
} else if (!empty($programid)) {
    $program = new program($programid);
    permission::require_can_view_users_progress($program);
} else {
    permission::require_can_view_list();
}

if ($type !== -1 && !array_key_exists($type, api::get_program_statuses_fieldset())) {
    throw new moodle_exception('errorreporttypedoesnotexist', 'tool_program');
}

// Load required JS module.
$PAGE->requires->js_call_amd('tool_program/program_progress_modal', 'init');

$PAGE->set_url(new moodle_url('/admin/tool/program/programsprogress.php', ['userid' => $userid, 'type' => $type]));

// Set title and heading depending on parameters.
$strprogramprogress = get_string('programprogress', 'tool_program');
if (!empty($userid)) {
    $user = core_user::get_user($userid, '*', MUST_EXIST);
    $PAGE->set_context(context_user::instance($user->id));
    $PAGE->navigation->extend_for_user($user);
    $PAGE->navbar->add($strprogramprogress);
    $PAGE->set_title(fullname($user) . ": {$strprogramprogress}");
} else {
    \tool_wp\admin_externalpage::setup_page('programs', '', [], $PAGE->url);

    if (!empty($program)) {
        \tool_wp\admin_externalpage::setup_subpage($program->get('fullname'),
            new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]));
    }

    \tool_wp\admin_externalpage::setup_subpage($strprogramprogress);
    $PAGE->set_secondary_navigation(false);
}

echo $OUTPUT->header();

// Set initial program status filter if type property is specified.
$report = \tool_tenant\system_report_factory::create(progress::class, ['userid' => $userid, 'programid' => $programid]);
if ($type !== -1) {
    $report->set_filter_values([
        'program_user:filterablestatus_operator' => select::EQUAL_TO,
        'program_user:filterablestatus_value' => $type,
    ]);
}

echo $report->output();
echo $OUTPUT->footer();
