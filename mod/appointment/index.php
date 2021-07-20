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
 * View all modules in a course
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

$id = required_param('id', PARAM_INT); // Course ID.

if (!$course = $DB->get_record('course', array('id' => $id))) {
    throw new moodle_exception('error:coursemisconfigured', 'appointment');
}

require_course_login($course);
$context = context_course::instance($course->id);

// Logging and events trigger.
$params = array(
    'context'  => $context,
    'objectid' => $course->id
);
$event = \mod_appointment\event\course_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strappointments = get_string('modulenameplural', 'appointment');
$strappointment = get_string('modulename', 'appointment');
$strappointmentname = get_string('appointmentname', 'appointment');
$strweek = get_string('week');
$strtopic = get_string('topic');
$strcourse = get_string('course');
$strname = get_string('name');

$pagetitle = format_string($strappointments);

$PAGE->set_url('/mod/appointment/index.php', array('id' => $id));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

if (!$appointments = get_all_instances_in_course('appointment', $course)) {
    notice(get_string('noappointments', 'appointment'), "../../course/view.php?id=$course->id");
    die;
}

$timenow = time();

$table = new html_table();
$table->width = '100%';

if ($course->format == 'weeks') {
    $table->head  = array ($strweek, $strappointmentname, get_string('sign-ups', 'appointment'));
    $table->align = array ('center', 'left', 'center');
} else if ($course->format == 'topics') {
    $table->head  = array ($strcourse, $strappointmentname, get_string('sign-ups', 'appointment'));
    $table->align = array ('center', 'left', 'center');
} else {
    $table->head  = array ($strappointmentname);
    $table->align = array ('left', 'left');
}

$currentsection = '';

foreach ($appointments as $appointment) {
    $cm = get_coursemodule_from_instance('appointment', $appointment->id, $course->id);
    $context = context_module::instance($cm->id);
    if (!\mod_appointment\permission::can_view_appointment($context)) {
        // No permission to view this appointment.
        break;
    }

    $submitted = get_string('no');

    if (!$appointment->visible) {
        // Show dimmed if the mod is hidden.
        $link = html_writer::link("view.php?f=$appointment->id", $appointment->name, array('class' => 'dimmed'));
    } else {
        // Show normal if the mod is visible.
        $link = html_writer::link("view.php?f=$appointment->id", $appointment->name);
    }

    $printsection = '';
    if ($appointment->section !== $currentsection) {
        if ($appointment->section) {
            $printsection = $appointment->section;
        }
        $currentsection = $appointment->section;
    }

    $totalsignupcount = 0;
    if ($sessions = appointment_get_sessions($appointment->id)) {
        foreach ($sessions as $session) {
            if (!appointment_has_session_started($session, $timenow)) {
                $signupcount = appointment_get_num_attendees($session->id);
                $totalsignupcount += $signupcount;
            }
        }
    }
    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    $courselink = html_writer::link($url, $course->shortname, array('title' => $course->shortname));
    if ($course->format == 'weeks' or $course->format == 'topics') {
        if (\mod_appointment\permission::can_view_attendees($context)) {
            $table->data[] = array ($courselink, $link, $totalsignupcount);
        } else {
            $table->data[] = array ($courselink, $link, '-');
        }
    } else {
        $table->data[] = array ($link, $submitted);
    }
}

echo html_writer::empty_tag('br');

echo html_writer::table($table);
echo $OUTPUT->footer($course);
