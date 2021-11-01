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
 * Attendees
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
require_once($CFG->dirroot . '/mod/appointment/lib.php');

// Appointment session ID.
$s = required_param('s', PARAM_INT);

$takeattendance = optional_param('takeattendance', false, PARAM_BOOL); // Take attendance.
$cancelform = optional_param('cancelform', false, PARAM_BOOL); // Cancel request.
$backtoallsessions = optional_param('backtoallsessions', 0, PARAM_INT); // Appointment activity to return to.

// Load data.
if (!$session = appointment_get_session($s)) {
    throw new moodle_exception('error:incorrectcoursemodulesession', 'appointment');
}
if (!$appointment = $DB->get_record('appointment', array('id' => $session->appointment))) {
    throw new moodle_exception('error:incorrectappointmentid', 'appointment');
}
if (!$course = $DB->get_record('course', array('id' => $appointment->course))) {
    throw new moodle_exception('error:coursemisconfigured', 'appointment');
}
if (!$cm = get_coursemodule_from_instance('appointment', $appointment->id, $course->id)) {
    throw new moodle_exception('error:incorrectcoursemodule', 'appointment');
}

// Load attendees.
$attendees = appointment_get_attendees($session->id);

// Load cancellations.
$cancellations = appointment_get_cancellations($session->id);


/*
 * Capability checks to see if the current user can view this page
 *
 * This page is a bit of a special case in this respect as there are four uses for this page.
 *
 * 1) Viewing attendee list
 *   - Requires mod/appointment:viewattendees capability in the course
 *
 * 2) Viewing cancellation list
 *   - Requires mod/appointment:viewcancellations capability in the course
 *
 * 3) Taking attendance
 *   - Requires mod/appointment:takeattendance capabilities in the course
 */
$context = context_course::instance($course->id);
$contextmodule = context_module::instance($cm->id);
require_course_login($course);

// Actions the user can perform.
$canviewattendees = \mod_appointment\permission::can_view_attendees($contextmodule);
$cantakeattendance = has_capability('mod/appointment:takeattendance', $context);
$canviewcancellations = has_capability('mod/appointment:viewcancellations', $context);
$canviewsession = $canviewattendees || $cantakeattendance || $canviewcancellations;
$canapproverequests = false;

$requests = array();
$declines = array();

// If a user can take attendance, they can approve staff's booking requests.
if ($cantakeattendance) {
    $requests = appointment_get_requests($session->id);
}

// If requests found (but not in the middle of taking attendance), show requests table.
if ($requests && !$takeattendance) {
    $canapproverequests = true;
}

// Check the user is allowed to view this page.
if (!$canviewattendees && !$cantakeattendance && !$canapproverequests && !$canviewcancellations) {
    throw new moodle_exception('nopermissions', '', "{$CFG->wwwroot}/mod/appointment/view.php?id={$cm->id}", get_string('view'));
}

// Check user has permissions to take attendance.
if ($takeattendance && !$cantakeattendance) {
    throw new moodle_exception('nopermissions', '', '', get_capability_string('mod/appointment:takeattendance'));
}


/*
 * Handle submitted data
 */
if ($form = data_submitted()) {
    if (!confirm_sesskey()) {
        throw new moodle_exception('confirmsesskeybad');
    }

    $return = "{$CFG->wwwroot}/mod/appointment/attendees.php?s={$s}&backtoallsessions={$backtoallsessions}";

    if ($cancelform) {
        redirect($return);
    } else if ($takeattendance) {
        if (appointment_take_attendance($form)) {

            // Logging and events trigger.
            $params = array(
                'context'  => $contextmodule,
                'objectid' => $session->id
            );
            $event = \mod_appointment\event\take_attendance::create($params);
            $event->add_record_snapshot('appointment_sessions', $session);
            $event->add_record_snapshot('appointment', $appointment);
            $event->trigger();
        } else {

            // Logging and events trigger.
            $params = array(
                'context'  => $contextmodule,
                'objectid' => $session->id
            );
            $event = \mod_appointment\event\take_attendance_failed::create($params);
            $event->add_record_snapshot('appointment_sessions', $session);
            $event->add_record_snapshot('appointment', $appointment);
            $event->trigger();
        }
        redirect($return.'&takeattendance=1');
    }
}

/*
 * Print page header
 */

// Logging and events trigger.
$params = array(
    'context'  => $contextmodule,
    'objectid' => $session->id
);
$event = \mod_appointment\event\attendees_viewed::create($params);
$event->add_record_snapshot('appointment_sessions', $session);
$event->add_record_snapshot('appointment', $appointment);
$event->trigger();

$pagetitle = format_string($appointment->name);

$PAGE->set_url('/mod/appointment/attendees.php', array('s' => $s));
$PAGE->set_context($context);
$PAGE->set_cm($cm);

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

/*
 * Print page content
 */

// If taking attendance, make sure the session has already started.
if ($takeattendance && !appointment_has_session_started($session, time())) {
    $link = "{$CFG->wwwroot}/mod/appointment/attendees.php?s={$session->id}";
    throw new moodle_exception('error:canttakeattendanceforunstartedsession', 'appointment', $link);
}

echo $OUTPUT->box_start();
echo $OUTPUT->heading(format_string($appointment->name));

if ($canviewsession) {
    echo appointment_print_session($session, true);
}

/*
 * Print attendees (if user able to view)
 */
if ($canviewattendees || $cantakeattendance) {
    if ($takeattendance) {
        $heading = get_string('takeattendance', 'appointment');
    } else {
        $heading = get_string('attendees', 'appointment');
    }

    echo $OUTPUT->heading($heading);

    if (empty($attendees)) {
        echo $OUTPUT->notification(get_string('nosignedupusers', 'appointment'));
    } else {
        if ($takeattendance) {
            $attendeesurl = new moodle_url('attendees.php', array('s' => $s, 'takeattendance' => '1'));
            echo html_writer::start_tag('form', array('action' => $attendeesurl, 'method' => 'post'));
            echo html_writer::tag('p', get_string('attendanceinstructions', 'appointment'));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => $USER->sesskey));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 's', 'value' => $s));
            echo html_writer::empty_tag('input', array('type' => 'hidden', ' name' => 'backtoallsessions',
                    'value' => $backtoallsessions)) . '</p>';

            // Prepare status options array.
            $statuses = appointment_statuses();
            $statusoptions = array();
            foreach ($statuses as $key => $value) {
                if ($key <= MOD_APPOINTMENT_STATUS_BOOKED) {
                    continue;
                }

                $statusoptions[$key] = get_string('status_'.$value, 'appointment');
            }
        }

        $table = new html_table();
        $table->head = array(get_string('name'));
        $table->caption = get_string('attendeestablesummary', 'appointment');
        $table->captionhide = true;
        $table->align = array('left');
        $table->size = array('100%');

        if ($takeattendance) {
            $table->head[] = get_string('currentstatus', 'appointment');
            $table->align[] = 'center';
            $table->head[] = get_string('attendedsession', 'appointment');
            $table->align[] = 'center';
        } else {
            $table->head[] = get_string('attendance', 'appointment');
            $table->align[] = 'center';
        }

        foreach ($attendees as $attendee) {
            $data = array();
            $attendeeurl = new moodle_url('/user/view.php', array('id' => $attendee->id, 'course' => $course->id));
            $data[] = html_writer::link($attendeeurl, format_string(fullname($attendee)));

            if ($takeattendance) {

                // Show current status.
                $data[] = get_string('status_'.appointment_get_status($attendee->statuscode), 'appointment');

                $optionid = 'submissionid_'.$attendee->submissionid;
                $status = $attendee->statuscode;
                $select = html_writer::select($statusoptions, $optionid, $status);
                $data[] = $select;
            } else {
                $data[] = str_replace(' ', '&nbsp;',
                    get_string('status_'.appointment_get_status($attendee->statuscode), 'appointment'));
            }
            $table->data[] = $data;
        }

        echo html_writer::table($table);

        if ($takeattendance) {
            echo html_writer::start_tag('p');
            echo html_writer::empty_tag('input', array('type' => 'submit',
                'value' => get_string('saveattendance', 'appointment')));
            echo '&nbsp;' . html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'cancelform',
                    'value' => get_string('cancel')));
            echo html_writer::end_tag('p') . html_writer::end_tag('form');
        } else {

            // Actions.
            print html_writer::start_tag('p');
            if ($cantakeattendance && appointment_has_session_started($session, time())) {

                // Take attendance.
                $attendanceurl = new moodle_url('attendees.php', array('s' => $session->id, 'takeattendance' => '1',
                    'backtoallsessions' => $backtoallsessions));
                echo html_writer::link($attendanceurl, get_string('takeattendance', 'appointment')) . ' - ';
            }
        }
    }

    if (!$takeattendance) {
        if (has_capability('mod/appointment:addattendees', $context) ||
            has_capability('mod/appointment:removeattendees', $context)) {

            // Add/remove attendees.
            $editattendeeslink = new moodle_url('editattendees.php', array('s' => $session->id,
                'backtoallsessions' => $backtoallsessions));
            echo html_writer::link($editattendeeslink, get_string('addremoveattendees', 'appointment')) . ' - ';
        }
    }
}

// Go back.
$url = new moodle_url('/course/view.php', array('id' => $course->id));
if ($backtoallsessions) {
    $url = new moodle_url('/mod/appointment/view.php', array('f' => $appointment->id, 'backtoallsessions' => $backtoallsessions));
}
echo html_writer::link($url, get_string('goback', 'appointment')) . html_writer::end_tag('p');

/*
 * Print cancellations (if user able to view)
 */
if (!$takeattendance && $canviewcancellations && $cancellations) {

    echo html_writer::empty_tag('br');
    echo $OUTPUT->heading(get_string('cancellations', 'appointment'));

    $table = new html_table();
    $table->caption = get_string('cancellationstablesummary', 'appointment');
    $table->captionhide = true;

    $table->head = [get_string('name'), get_string('timesignedup', 'appointment'),
                         get_string('timecancelled', 'appointment'), get_string('cancelreason', 'appointment')];
    $table->align = ['left', 'center', 'center'];

    $dateformat = get_string('strftimedatetime');
    foreach ($cancellations as $attendee) {
        $data = [];
        $attendeelink = new moodle_url('/user/view.php', ['id' => $attendee->id, 'course' => $course->id]);
        $data[] = html_writer::link($attendeelink, format_string(fullname($attendee)));
        $data[] = userdate($attendee->timesignedup, $dateformat);
        $data[] = userdate($attendee->timecancelled, $dateformat);
        $data[] = format_string($attendee->cancelreason);
        $table->data[] = $data;
    }
    echo html_writer::table($table);
}

/*
 * Print page footer
 */
echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
