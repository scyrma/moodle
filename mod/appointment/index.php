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

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

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
    notice(get_string('thereareno', 'moodle', $strappointments), "$CFG->wwwroot/course/view.php?id=$course->id");
    die;
}

$timenow = time();

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = array ($strsectionname, $strappointmentname, get_string('sign-ups', 'appointment'));
    $table->align = array ('left', 'left', 'center');
} else {
    $table->head  = array ($strappointmentname, get_string('sign-ups', 'appointment'));
    $table->align = array ('left', 'center');
}

$currentsection = '';

foreach ($appointments as $appointment) {
    $cm = get_coursemodule_from_instance('appointment', $appointment->id, $course->id);
    $context = context_module::instance($cm->id);
    if (!\mod_appointment\permission::can_view_appointment($context)) {
        // No permission to view this appointment.
        continue;
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
            $printsection = get_section_name($course, $appointment->section);
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
    if (!\mod_appointment\permission::can_view_attendees($context)) {
        $totalsignupcount = '-';
    }
    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    $courselink = html_writer::link($url, $course->shortname, array('title' => $course->shortname));
    if ($usesections) {
        $table->data[] = array ($printsection, $link, $totalsignupcount);
    } else {
        $table->data[] = array ($link, $totalsignupcount);
    }
}

echo html_writer::empty_tag('br');

echo html_writer::table($table);
echo $OUTPUT->footer($course);
