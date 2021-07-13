<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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

/**
 * Messages settings.
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      Daniel Neis Araujo <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('appointment', $cmid);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$appointment = $DB->get_record('appointment', ['id' => $cm->instance], '*', MUST_EXIST);

$context = context_module::instance($cmid);

require_login($course, true, $cm);

mod_appointment\permission::require_can_add_instance($context->get_course_context());

$url = new moodle_url('/mod/appointment/messages.php', ['cmid' => $cmid]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_pagelayout('admin');

$editingstr = get_string('customisednotifications', 'mod_appointment');
$title = format_string($course->shortname . ': ' . $appointment->name . ': ' . $editingstr);
$PAGE->set_title($title);

$output = $PAGE->get_renderer('mod_appointment');

$returnurl = new moodle_url('/mod/appointment/view.php', ['id' => $cmid]);

$mform = new mod_appointment\form\messages($url, ['cmid' => $cmid]);

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $mform->get_data()) {
    $mform->process($data);
    redirect($returnurl);
} else {
    $appointment = $DB->get_record('appointment', ['id' => $cm->instance]);

    $options = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'context' => $context,
        'noclean' => true,
        'accepted_types' => '*',
    ];
    $appointment = file_prepare_standard_editor($appointment, 'confirmationmessage', $options, $context,
        'appointment', 'notifications', $appointment->id);
    $appointment = file_prepare_standard_editor($appointment, 'remindermessage', $options, $context,
        'appointment', 'notifications', $appointment->id);
    $appointment = file_prepare_standard_editor($appointment, 'waitlistedmessage', $options, $context,
        'appointment', 'notifications', $appointment->id);
    $appointment = file_prepare_standard_editor($appointment, 'cancellationmessage', $options, $context,
        'appointment', 'notifications', $appointment->id);

    $mform->set_data($appointment);
}

echo $output->header(),
     $output->heading($editingstr),
     $mform->render(),
     $output->footer();
