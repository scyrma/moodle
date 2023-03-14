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
 * View module
 *
 * Copyright (C) 2007-2011 Catalyst IT (http://www.catalyst.net.nz)
 * Copyright (C) 2011-2013 Totara LMS (http://www.totaralms.com)
 * Copyright (C) 2014 onwards Catalyst IT (http://www.catalyst-eu.net)
 *
 * @package    mod_appointment
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @author     Stacey Walker <stacey@catalyst-eu.net>
 * @author     Alastair Munro <alastair.munro@totaralms.com>
 * @author     Aaron Barnes <aaron.barnes@totaralms.com>
 * @author     Francois Marier <francois@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('lib.php');

global $DB, $OUTPUT, $USER;

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$f = optional_param('f', 0, PARAM_INT); // Appointment ID.
$s = optional_param('s', 0, PARAM_INT); // Signup session ID.
$download = optional_param('download', '', PARAM_ALPHA); // Download attendance.

if ($f && !$id) {
    list($course, $cm) = get_course_and_cm_from_instance($f, 'appointment');
} else {
    $id = required_param('id', PARAM_INT);
    list($course, $cm) = get_course_and_cm_from_cmid($id, 'appointment');
}
$appointment = $DB->get_record('appointment', ['id' => $cm->instance], '*', MUST_EXIST);

$context = context_module::instance($cm->id);
require_course_login($course, true, $cm);
\mod_appointment\permission::require_can_view_appointment($context);

if ($s) {
    // If signup requested, verify session exists.
    $DB->get_record('appointment_sessions', ['id' => $s, 'appointment' => $appointment->id], 'id', MUST_EXIST);
}

// Logging and events trigger. TODO: Move to class method.
$params = array(
    'context'  => $context,
    'objectid' => $appointment->id
);
$event = \mod_appointment\event\course_module_viewed::create($params);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('appointment', $appointment);
$event->trigger();

// Completion update.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/appointment/view.php', ['id' => $cm->id]);

$title = format_string($course->shortname . ': ' . $appointment->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

$output = $PAGE->get_renderer('mod_appointment');
$PAGE->activityheader->set_attrs([]);
echo $output->header();

$renderable = new \mod_appointment\output\sessions_view($appointment, $s);
echo $output->render($renderable);
echo $output->footer($course);
