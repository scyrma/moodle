<?php
// This file is part of Moodle - http://moodle.org/
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

global $DB, $OUTPUT;

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$f = optional_param('f', 0, PARAM_INT); // Appointment ID.
$download = optional_param('download', '', PARAM_ALPHA); // Download attendance.

if ($id) {
    if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
        print_error('error:incorrectcoursemoduleid', 'appointment');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('error:coursemisconfigured', 'appointment');
    }
    if (!$appointment = $DB->get_record('appointment', array('id' => $cm->instance))) {
        print_error('error:incorrectcoursemodule', 'appointment');
    }
} else if ($f) {
    if (!$appointment = $DB->get_record('appointment', array('id' => $f))) {
        print_error('error:incorrectappointmentid', 'appointment');
    }
    if (!$course = $DB->get_record('course', array('id' => $appointment->course))) {
        print_error('error:coursemisconfigured', 'appointment');
    }
    if (!$cm = get_coursemodule_from_instance('appointment', $appointment->id, $course->id)) {
        print_error('error:incorrectcoursemoduleid', 'appointment');
    }
} else {
    print_error('error:mustspecifycoursemoduleappointment', 'appointment');
}

$context = context_module::instance($cm->id);
require_course_login($course, true, $cm);
\mod_appointment\permission::require_can_view_appointment($context);

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

$PAGE->set_url('/mod/appointment/view.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_cm($cm);

$title = format_string($course->shortname . ': ' . $appointment->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

$output = $PAGE->get_renderer('mod_appointment');
echo $output->header();
echo $output->heading(format_string($appointment->name), 2);
$renderable = new \mod_appointment\output\sessions_view($appointment);
echo $output->render($renderable);
echo $output->footer($course);
