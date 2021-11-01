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
 * Module callbacks
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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/*
 * Definitions for setting notification types.
 */

// TODO: WP-2920 Remove deprecated constants in 3.11.4.
define('MOD_APPOINTMENT_ICAL', 0); // Deprecated.
define('MOD_APPOINTMENT_TEXT', 0); // Deprecated.
define('MOD_APPOINTMENT_BOTH', 0); // Deprecated.
define('MOD_APPOINTMENT_INVITE_BOTH', 0); // Deprecated.
define('MOD_APPOINTMENT_INVITE_TEXT', 0); // Deprecated.
define('MOD_APPOINTMENT_INVITE_ICAL', 0); // Deprecated.
define('MOD_APPOINTMENT_CANCEL_BOTH', 0); // Deprecated.
define('MOD_APPOINTMENT_CANCEL_TEXT', 0); // Deprecated.
define('MOD_APPOINTMENT_CANCEL_ICAL', 0); // Deprecated.

// Utility definitions.
define('MOD_APPOINTMENT_WAITLIST', 2);
define('MOD_APPOINTMENT_INVITE', 4);
define('MOD_APPOINTMENT_CANCEL', 8);
define('MOD_APPOINTMENT_UPDATE', 16);

// Name of the custom field where the manager's email address is stored.
define('MDL_MANAGERSEMAIL_FIELD', 'managersemail');

// Calendar-related constants.
define('MOD_APPOINTMENT_CAL_NONE', 0);
define('MOD_APPOINTMENT_CAL_COURSE', 1);
define('MOD_APPOINTMENT_CAL_SITE', 2);

// Signup status codes (remember to update appointment_statuses()).
define('MOD_APPOINTMENT_STATUS_USER_CANCELLED', 10);
define('MOD_APPOINTMENT_STATUS_DECLINED', 30);
define('MOD_APPOINTMENT_STATUS_REQUESTED', 40);
define('MOD_APPOINTMENT_STATUS_APPROVED', 50);
define('MOD_APPOINTMENT_STATUS_WAITLISTED', 60);
define('MOD_APPOINTMENT_STATUS_BOOKED', 70);
define('MOD_APPOINTMENT_STATUS_NO_SHOW', 80);
define('MOD_APPOINTMENT_STATUS_PARTIALLY_ATTENDED', 90);
define('MOD_APPOINTMENT_STATUS_FULLY_ATTENDED', 100);

/**
 * Returns the list of possible appointment status.
 *
 * @return string $string Human readable code
 */
function appointment_statuses() {
    // This array must match the status codes above, and the values
    // must equal the end of the constant name but in lower case.

    return array(
        MOD_APPOINTMENT_STATUS_USER_CANCELLED => 'user_cancelled',
        MOD_APPOINTMENT_STATUS_DECLINED => 'declined',
        MOD_APPOINTMENT_STATUS_REQUESTED => 'requested',
        MOD_APPOINTMENT_STATUS_APPROVED => 'approved',
        MOD_APPOINTMENT_STATUS_WAITLISTED => 'waitlisted',
        MOD_APPOINTMENT_STATUS_BOOKED => 'booked',
        MOD_APPOINTMENT_STATUS_NO_SHOW => 'no_show',
        MOD_APPOINTMENT_STATUS_PARTIALLY_ATTENDED => 'partially_attended',
        MOD_APPOINTMENT_STATUS_FULLY_ATTENDED => 'fully_attended',
    );
}

/**
 * Returns the human readable code for a appointment status
 *
 * @param int $statuscode One of the MOD_APPOINTMENT_STATUS* constants
 * @return string $string Human readable code
 */
function appointment_get_status($statuscode) {
    $statuses = appointment_statuses();

    // Check code exists.
    if (!isset($statuses[$statuscode])) {
        throw new moodle_exception('Appointment status code does not exist: ' . $statuscode);
    }

    // Get code.
    $string = $statuses[$statuscode];

    // Check to make sure the status array looks to be up-to-date.
    if (constant('MOD_APPOINTMENT_STATUS_' . strtoupper($string)) != $statuscode) {
        throw new moodle_exception('Appointment status code array does not appear to be up-to-date: ' . $statuscode);
    }

    return $string;
}

/**
 * Human-readable version of the duration field used to display it to
 * users
 *
 * @param  int $duration duration in hours
 * @return string
 */
function format_duration($duration) {
    $components = explode(':', $duration);

    // Default response.
    $string = '';

    // Check for bad characters.
    if (trim(preg_match('/[^0-9:\.\s]/', $duration))) {
        return $string;
    }

    if ($components and count($components) > 1) {

        // E.g. "1:30" => "1 hour and 30 minutes".
        $hours = round($components[0]);
        $minutes = round($components[1]);
    } else {

        // E.g. "1.5" => "1 hour and 30 minutes".
        $hours = floor($duration);
        $minutes = round(($duration - floor($duration)) * 60);
    }

    // Check if either minutes is out of bounds.
    if ($minutes >= 60) {
        return $string;
    }

    if (1 == $hours) {
        $string = get_string('onehour', 'appointment');
    } else if ($hours > 1) {
        $string = get_string('xhours', 'appointment', $hours);
    }

    // Insert separator between hours and minutes.
    if ($string != '') {
        $string .= ' ';
    }

    if (1 == $minutes) {
        $string .= get_string('oneminute', 'appointment');
    } else if ($minutes > 0) {
        $string .= get_string('xminutes', 'appointment', $minutes);
    }

    return $string;
}

/**
 * Converts minutes to hours
 *
 * @param int $minutes
 */
function appointment_minutes_to_hours($minutes) {
    if (!intval($minutes)) {
        return 0;
    }

    if ($minutes > 0) {
        $hours = floor($minutes / 60.0);
        $mins = $minutes - ($hours * 60.0);
        return "$hours:$mins";
    } else {
        return $minutes;
    }
}

/**
 * Converts hours to minutes
 *
 * @param int $hours
 */
function appointment_hours_to_minutes($hours) {
    $components = explode(':', $hours);
    if ($components and count($components) > 1) {

        // E.g. "1:45" => 105 minutes.
        $hours = $components[0];
        $minutes = $components[1];
        return $hours * 60.0 + $minutes;
    } else {
        // E.g. "1.75" => 105 minutes.
        return round($hours * 60.0);
    }
}

/**
 * Turn undefined manager messages into empty strings and deal with checkboxes
 *
 * @param stdClass $appointment
 */
function appointment_fix_settings($appointment) {

    if (empty($appointment->emailmanagerconfirmation)) {
        $appointment->confirmationinstrmngr = null;
    }
    if (empty($appointment->emailmanagerreminder)) {
        $appointment->reminderinstrmngr = null;
    }
    if (empty($appointment->emailmanagercancellation)) {
        $appointment->cancellationinstrmngr = null;
    }
    if (empty($appointment->usercalentry)) {
        $appointment->usercalentry = 0;
    }
    if (empty($appointment->thirdpartywaitlist)) {
        $appointment->thirdpartywaitlist = 0;
    }
    if (empty($appointment->approvalreqd)) {
        $appointment->approvalreqd = 0;
    }
}

/**
 * Given an object containing all the necessary data, (defined by the
 * form in mod.html) this function will create a new instance and
 * return the id number of the new instance.
 *
 * @param stdClass $appointment
 */
function appointment_add_instance($appointment) {
    global $DB;

    $appointment->timemodified = time();
    appointment_fix_settings($appointment);

    // Populate default messaging settings.
    $appointment = (object) array_merge((array) $appointment, \mod_appointment\form\messages::get_defaults());

    if ($appointment->id = $DB->insert_record('appointment', $appointment)) {
        appointment_grade_item_update($appointment);
    }

    // Update any calendar entries.
    if ($sessions = appointment_get_sessions($appointment->id)) {
        foreach ($sessions as $session) {
            appointment_update_calendar_entries($session, $appointment);
        }
    }
    if (!empty($appointment->completionexpected)) {
        \core_completion\api::update_completion_date_event($appointment->coursemodule, 'appointment', $appointment->id,
            $appointment->completionexpected);
    }

    return $appointment->id;
}

/**
 * Given an object containing all the necessary data, (defined by the
 * form in mod.html) this function will update an existing instance
 * with new data.
 *
 * @param stdClass $appointment
 * @param bool $instanceflag
 */
function appointment_update_instance($appointment, $instanceflag = true) {
    global $DB;

    if ($instanceflag) {
        $appointment->id = $appointment->instance;
    }

    appointment_fix_settings($appointment);
    if ($return = $DB->update_record('appointment', $appointment)) {
        appointment_grade_item_update($appointment);

        // Update any calendar entries.
        if ($sessions = appointment_get_sessions($appointment->id)) {
            foreach ($sessions as $session) {
                appointment_update_calendar_entries($session, $appointment);
            }
        }
    }
    $completionexpected = (!empty($appointment->completionexpected)) ? $appointment->completionexpected : null;
    \core_completion\api::update_completion_date_event($appointment->coursemodule, 'appointment', $appointment->id,
        $completionexpected);

    return $return;
}

/**
 * Course reset form elements
 *
 * @param MoodleQuickForm $mform
 * @return void
 */
function appointment_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'assignheader', get_string('modulenameplural', 'appointment'));
    $mform->addElement('advcheckbox', 'reset_sessions', get_string('courseresetsessions', 'appointment'));
    $mform->addElement('advcheckbox', 'reset_sessions_signups', get_string('courseresetsignups', 'appointment'));
}

/**
 * Course reset form defaults
 *
 * @param stdClass $course
 * @return array
 */
function appointment_reset_course_form_defaults($course) {
    return [
        'reset_sessions' => 0,
        'reset_sessions_signups' => 1,
    ];
}

/**
 * Perform course reset for the appointment module
 *
 * @param stdClass $data
 * @return array
 */
function appointment_reset_userdata($data) {
    global $DB;

    $componentstr = get_string('modulenameplural', 'appointment');
    $status = [];

    $course = get_course($data->courseid);
    $completion = new completion_info($course);
    $appointments = $DB->get_records('appointment', ['course' => $course->id]);

    foreach ($appointments as $appointment) {
        $cm = get_coursemodule_from_instance('appointment', $appointment->id);

        // Check whether user has selected to reset sessions or their signups.
        if (!empty($data->reset_sessions)) {
            $sessions = $DB->get_records('appointment_sessions', ['appointment' => $appointment->id]);

            foreach ($sessions as $session) {
                appointment_delete_session($session);
            }

            $status[] = [
                'component' => $componentstr,
                'item' => get_string('courseresetsessions', 'appointment'),
                'error' => false,
            ];
        } else if (!empty($data->reset_sessions_signups)) {
            $sessions = $DB->get_records('appointment_sessions', ['appointment' => $appointment->id]);

            foreach ($sessions as $session) {
                $attendees = appointment_get_attendees($session->id);

                foreach ($attendees as $attendee) {
                    // Clean up calendar events.
                    appointment_remove_session_from_calendar($session, 0, $attendee->id);

                    // Clean up the signup data.
                    $DB->delete_records('appointment_signups_status', ['signupid' => $attendee->submissionid]);
                    $DB->delete_records('appointment_signups', ['id' => $attendee->submissionid]);

                    // Recalculate activity completion.
                    if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC && $appointment->completionbooked) {
                        $completion->update_state($cm, COMPLETION_UNKNOWN, $attendee->id);
                    }
                }
            }

            $status[] = [
                'component' => $componentstr,
                'item' => get_string('courseresetsignups', 'appointment'),
                'error' => false,
            ];
        }

        // For any sessions attached to each appointment instance, we need to perform a timeshift on the timestart/finish.
        if (!empty($data->timeshift)) {
            $sessions = $DB->get_records('appointment_sessions', ['appointment' => $appointment->id]);
            $cm = get_coursemodule_from_instance('appointment', $appointment->id, $data->courseid);
            $context = \context_module::instance($cm->id);

            foreach ($sessions as $session) {
                $shiftedsessiondates = [];

                $sessiondates = $DB->get_records('appointment_sessions_dates', ['sessionid' => $session->id]);
                foreach ($sessiondates as $sessiondate) {
                    $shiftedsessiondates[] = (object) [
                        'timestart' => $sessiondate->timestart + $data->timeshift,
                        'timefinish' => $sessiondate->timefinish + $data->timeshift,
                    ];
                }

                appointment_update_session($session, $shiftedsessiondates, $context);
            }

            $status[] = [
                'component' => $componentstr,
                'item' => get_string('datechanged'),
                'error' => false,
            ];
        }
    }

    return $status;
}

/**
 * Given an ID of an instance of this module, this function will
 * permanently delete the instance and any data that depends on it.
 *
 * @param int $id
 */
function appointment_delete_instance($id) {
    global $CFG, $DB;

    if (!$appointment = $DB->get_record('appointment', array('id' => $id))) {
        return false;
    }

    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records_select(
        'appointment_signups_status',
        "signupid IN
        (
            SELECT
            id
            FROM
    {appointment_signups}
    WHERE
    sessionid IN
    (
        SELECT
        id
        FROM
    {appointment_sessions}
    WHERE
    appointment = ? ))
    ", array($appointment->id));

    $sessions = $DB->get_records('appointment_sessions', ['appointment' => $appointment->id]);
    $handler = \mod_appointment\customfield\appointment_handler::create();
    foreach ($sessions as $session) {
        $handler->delete_instance($session->id);
    }

    $DB->delete_records_select('appointment_signups',
        "sessionid IN (SELECT id FROM {appointment_sessions} WHERE appointment = ?)", array($appointment->id));
    $DB->delete_records_select('appointment_sessions_dates',
        "sessionid in (SELECT id FROM {appointment_sessions} WHERE appointment = ?)", array($appointment->id));
    $DB->delete_records('appointment_sessions', array('appointment' => $appointment->id));
    $DB->delete_records('appointment', array('id' => $appointment->id));
    $DB->delete_records('event', array('modulename' => 'appointment', 'instance' => $appointment->id)); // Course events.
    $DB->delete_records('event', array('modulename' => '0',
        'eventtype' => 'appointmentsession', 'instance' => $appointment->id)); // User events and Site events.
    appointment_grade_item_delete($appointment);
    $transaction->allow_commit();

    return true;
}

/**
 * Prepare the user data to go into the database.
 *
 * @param stdClass $session
 */
function cleanup_session_data($session) {

    // Only numbers allowed here.
    $session->capacity = preg_replace('/[^\d]/', '', $session->capacity);
    $maxcap = 100000;
    if ($session->capacity < 1) {
        $session->capacity = 1;
    } else if ($session->capacity > $maxcap) {
        $session->capacity = $maxcap;
    }

    return $session;
}

/**
 * Create a new entry in the appointment_sessions table
 *
 * @param \stdClass $session
 * @param array $sessiondates
 * @param \stdClass $context
 * @return int session id
 */
function appointment_add_session(\stdClass $session, array $sessiondates, $context): int {
    global $DB;

    // Check context is matching appointment id.
    $cminstance = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
    if ($cminstance != $session->appointment) {
        throw new \moodle_exception('error:couldnotaddsession', 'appointment');
    }

    $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);

    $now = time();
    $session->timecreated = $now;
    $session->timemodified = $now;
    $session = cleanup_session_data($session);

    $transaction = $DB->start_delegated_transaction();
    $session->id = $DB->insert_record('appointment_sessions', $session);

    if (!empty($sessiondates)) {
        foreach ($sessiondates as $date) {
            $date->sessionid = $session->id;
            $DB->insert_record('appointment_sessions_dates', $date);
        }
    }

    if (isset($session->details_editor)) {
        $editoroptions = [
            'noclean' => false,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => $context,
        ];
        $session = file_postupdate_standard_editor($session, 'details', $editoroptions,
            $context, 'mod_appointment', 'session', $session->id);

        $DB->update_record('appointment_sessions',
            ['id' => $session->id, 'details' => $session->details, 'detailsformat' => $session->detailsformat]);

        $details = file_rewrite_pluginfile_urls($session->details, 'pluginfile.php', $context->id,
            'mod_appointment', 'session', $session->id);

        $session->details = format_text($details, $session->detailsformat);
    }

    $session->sessiondates = $sessiondates;

    // Add customfields.
    $handler = \mod_appointment\customfield\appointment_handler::create();
    $handler->instance_form_save($session);

    // Create any calendar entries.
    appointment_update_calendar_entries($session);

    $transaction->allow_commit();

    // Trigger event.
    $session = appointment_get_session($session->id);
    $params = [
        'context' => $context,
        'objectid' => $session->id
    ];
    $event = \mod_appointment\event\add_session::create($params);
    $event->add_record_snapshot('appointment_sessions', $session);
    $event->add_record_snapshot('appointment', $appointment);
    $event->trigger();

    return $session->id;
}

/**
 * Checks if update and user notification is required.
 *
 * @param stdClass $oldsession Old session object
 * @param stdClass $session New session object
 * @param array $sessiondates New session dates
 * @return array Array of two boolean variables [$updaterequired, $notifyusers].
 */
function appointment_is_session_update_required(\stdClass $oldsession, \stdClass $session, array $sessiondates): array {
    $updaterequired = false;

    // Date check.
    $hashdatescallback = function ($value) {
        return md5(json_encode([(int) $value->timestart, (int) $value->timefinish]));
    };
    $sessiondateshashed = array_map($hashdatescallback, $sessiondates);
    $oldsessiondateshashed = array_map($hashdatescallback, $oldsession->sessiondates);
    $datechanged = !empty(array_merge(array_diff($sessiondateshashed, $oldsessiondateshashed),
        array_diff($oldsessiondateshashed, $sessiondateshashed)));

    if ($datechanged || $session->details != $oldsession->details) {
        // Date or session details changed, update and notification are needed.
        return [true, true];
    }

    // Customfields check.
    $handler = \mod_appointment\customfield\appointment_handler::create();
    foreach ($handler->export_instance_data($oldsession->id, true) as $fielddata) {
        $prop = 'customfield_' . $fielddata->get_shortname();
        if (isset($session->$prop) && $session->$prop != $fielddata->get_data_controller()->get_value()) {
            // At least one field has changed.
            return [true, true];
        }
    }

    // See if any other field changed.
    $fields = ['capacity', 'allowwaitlist', 'allowcancellations'];
    foreach ($fields as $field) {
        if ($session->$field != $oldsession->$field) {
            $updaterequired = true;
            break;
        }
    }
    return [$updaterequired, false];
}

/**
 * Modify an entry in the appointment_sessions table
 *
 * @param stdClass $session
 * @param array $sessiondates
 * @param stdClass $context
 * @return int session id
 */
function appointment_update_session($session, $sessiondates, $context) {
    global $DB;

    // Check context is matching appointment id.
    $cminstance = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
    if ($cminstance != $session->appointment) {
        throw new \moodle_exception('error:couldnotupdatesession', 'appointment');
    }

    if (!$oldsession = appointment_get_session($session->id)) {
        throw new \moodle_exception('error:couldnotupdatesession', 'appointment');
    }

    $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);
    $session = cleanup_session_data($session);

    if (isset($session->details_editor)) {
        $editoroptions = [
            'noclean' => false,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => $context,
        ];
        $session = file_postupdate_standard_editor($session, 'details', $editoroptions,
            $context, 'mod_appointment', 'session', $session->id);
    }

    // Before making changes, determine if we need to update.
    [$updaterequired, $notifyusers] = appointment_is_session_update_required($oldsession, $session, $sessiondates);

    if (!$updaterequired) {
        // Nothing changed, leave record untouched.
        return $session->id;
    }

    $transaction = $DB->start_delegated_transaction();
    $session->timemodified = time();
    $session->countmodified = (int) ++$oldsession->countmodified;
    $DB->update_record('appointment_sessions', $session);
    $DB->delete_records('appointment_sessions_dates', array('sessionid' => $session->id));

    if (empty($sessiondates)) {
        // Insert a dummy date record.
        $date = new stdClass();
        $date->sessionid = $session->id;
        $date->timestart = 0;
        $date->timefinish = 0;
        $DB->insert_record('appointment_sessions_dates', $date);
    } else {
        foreach ($sessiondates as $date) {
            $date->sessionid = $session->id;
            $DB->insert_record('appointment_sessions_dates', $date);
        }
    }

    if (isset($session->details_editor)) {
        $details = file_rewrite_pluginfile_urls($session->details, 'pluginfile.php', $context->id,
            'mod_appointment', 'session', $session->id);
        $session->details = format_text($details, $session->detailsformat);
    }

    // Update customfields.
    $handler = \mod_appointment\customfield\appointment_handler::create();
    $handler->instance_form_save($session);

    // Update any calendar entries.
    $session->sessiondates = $sessiondates;
    appointment_update_calendar_entries($session);

    // Update attendee list status on booking size change.
    appointment_update_attendees($session);

    // Commit changes.
    $transaction->allow_commit();

    // Trigger event.
    $session = appointment_get_session($session->id);
    $params = [
        'context' => $context,
        'objectid' => $session->id
    ];
    $event = \mod_appointment\event\update_session::create($params);
    $event->add_record_snapshot('appointment_sessions', $session);
    $event->add_record_snapshot('appointment', $appointment);
    $event->trigger();

    // Notify users if required.
    if ($notifyusers && $users = appointment_get_attendees($session->id)) {
        foreach ($users as $user) {
            if (in_array($user->statuscode, [MOD_APPOINTMENT_STATUS_BOOKED, MOD_APPOINTMENT_STATUS_WAITLISTED])) {
                appointment_send_update_notice($appointment, $session, $user->id);
            }
        }
    }

    return $session->id;
}

/**
 * Update calendar entries for a given session
 *
 * @param stdClass $session
 * @param stdClass|null $appointment
 * @return bool
 */
function appointment_update_calendar_entries($session, $appointment = null): bool {
    global $DB;

    if (empty($appointment)) {
        $appointment = $DB->get_record('appointment', array('id' => $session->appointment));
    }

    // Remove from all calendars.
    appointment_delete_user_calendar_events($session, 'booking');
    appointment_delete_user_calendar_events($session, 'session');
    appointment_remove_session_from_calendar($session, 0); // Session user event for session creator.
    appointment_remove_session_from_calendar($session, $appointment->course); // Session course event.
    appointment_remove_session_from_calendar($session, SITEID); // Session site event.

    if (empty($appointment->showoncalendar) && empty($appointment->usercalentry)) {
        return true;
    }

    // Add to NEW calendartype.
    if ($appointment->usercalentry) {

        // Get ALL enrolled/booked users.
        $users = appointment_get_attendees($session->id);

        foreach ($users as $user) {
            $eventtype = $user->statuscode == MOD_APPOINTMENT_STATUS_BOOKED ? 'booking' : 'session';
            appointment_add_session_to_calendar($session, $appointment, 'user', $user->id, $eventtype);
        }
    }

    if ($appointment->showoncalendar == MOD_APPOINTMENT_CAL_COURSE) {
        appointment_add_session_to_calendar($session, $appointment, 'course', 0);
    } else if ($appointment->showoncalendar == MOD_APPOINTMENT_CAL_SITE) {
        appointment_add_session_to_calendar($session, $appointment, 'site', 0);
    }

    return true;
}

/**
 * Update attendee list status on booking size change
 *
 * @param stdClass $session
 * @return int session id
 */
function appointment_update_attendees($session) {
    global $DB;

    // Get appointment.
    $appointment = $DB->get_record('appointment', array('id' => $session->appointment));

    // Get course.
    $course = $DB->get_record('course', array('id' => $appointment->course));

    // Update user status'.
    $users = appointment_get_attendees($session->id);

    if ($users) {

        // No/deleted session dates.
        if (empty($session->sessiondates)) {

            // Convert any bookings to waitlists.
            foreach ($users as $user) {
                if ($user->statuscode == MOD_APPOINTMENT_STATUS_BOOKED) {

                    if (!appointment_user_signup($session, $appointment, $course, null,
                            MOD_APPOINTMENT_STATUS_WAITLISTED, $user->id)) {
                        return false;
                    }
                }
            }
        } else {

            // Session dates exist.
            // Convert earliest signed up users to booked, and make the rest waitlisted.
            $capacity = $session->capacity;

            // Count number of booked users.
            $booked = 0;
            foreach ($users as $user) {
                if ($user->statuscode == MOD_APPOINTMENT_STATUS_BOOKED) {
                    $booked++;
                }
            }

            // If booked less than capacity, book some new users.
            if ($booked < $capacity) {
                foreach ($users as $user) {
                    if ($booked >= $capacity) {
                        break;
                    }

                    if ($user->statuscode == MOD_APPOINTMENT_STATUS_WAITLISTED) {

                        if (!appointment_user_signup($session, $appointment, $course, null,
                                MOD_APPOINTMENT_STATUS_BOOKED, $user->id)) {
                            return false;
                        }
                        $booked++;
                    }
                }
            }
        }
    }

    return $session->id;
}

/**
 * Return an array of all appointment activities in the current course
 */
function appointment_get_appointment_menu() {
    global $CFG, $DB;

    if ($appointments = $DB->get_records_sql("SELECT f.id, c.shortname, f.name
                                            FROM {course} c, {appointment} f
                                            WHERE c.id = f.course
                                            ORDER BY c.shortname, f.name")) {
        $i = 1;
        foreach ($appointments as $appointment) {
            $f = $appointment->id;
            $appointmentmenu[$f] = $appointment->shortname . ' --- ' . $appointment->name;
            $i++;
        }

        return $appointmentmenu;

    } else {
        return '';
    }
}

/**
 * Delete entry from the appointment_sessions table along with all
 * related details in other tables
 *
 * @param stdClass $session Record from appointment_sessions
 */
function appointment_delete_session($session) {
    global $CFG, $DB;

    $appointment = $DB->get_record('appointment', array('id' => $session->appointment));

    // Cancel user signups (and notify users).
    $signedupusers = $DB->get_records_sql(
        "
            SELECT DISTINCT
                userid
            FROM
                {appointment_signups} s
            LEFT JOIN
                {appointment_signups_status} ss
             ON ss.signupid = s.id
            WHERE
                s.sessionid = ?
            AND ss.superceded = 0
            AND ss.statuscode >= ?
        ", array($session->id, MOD_APPOINTMENT_STATUS_REQUESTED));

    if ($signedupusers and count($signedupusers) > 0) {
        foreach ($signedupusers as $user) {
            if (appointment_user_cancel($session, $user->userid, true)) {
                appointment_send_cancellation_notice($appointment, $session, $user->userid);
            } else {
                return false; // Cannot rollback since we notified users already.
            }
        }
    }

    $transaction = $DB->start_delegated_transaction();

    // Remove entries from user calendars.
    $DB->delete_records_select('event', "modulename = '0' AND
                                         eventtype like 'appointment%' AND
                                         courseid = 0 AND instance = ?",
        array($appointment->id));

    // Remove entry from course calendar.
    appointment_remove_session_from_calendar($session, $appointment->course);

    // Remove entry from site-wide calendar.
    appointment_remove_session_from_calendar($session, SITEID);

    // Delete session custom fields.
    $handler = \mod_appointment\customfield\appointment_handler::create();
    $handler->delete_instance($session->id);

    // Delete session details.
    $DB->delete_records('appointment_sessions', array('id' => $session->id));
    $DB->delete_records('appointment_sessions_dates', array('sessionid' => $session->id));
    $DB->delete_records_select(
        'appointment_signups_status',
        "signupid IN
        (
            SELECT
                id
            FROM
                {appointment_signups}
            WHERE
                sessionid = {$session->id}
        )
        ");
    $DB->delete_records('appointment_signups', array('sessionid' => $session->id));
    $transaction->allow_commit();

    return true;
}

/**
 * Substitute the placeholders in email templates for the actual data
 *
 * Expects the following parameters in the $data object:
 * - details
 * - duration
 * - sessiondates
 *
 * @param   string $msg Email message
 * @param   string $appointmentname Appointment name
 * @param   int $reminderperiod Num business days before event to send reminder
 * @param   stdClass $user The subject of the message
 * @param   stdClass $data Session data
 * @param   int $sessionid Session ID
 * @return  string
 */
function appointment_email_substitutions($msg, $appointmentname, $reminderperiod, $user, $data, $sessionid) {
    global $CFG, $DB;

    if (empty($msg)) {
        return '';
    }

    if (empty($data->sessiondates)) {
        // Session without dates.
        $sessiondate = get_string('unknowndate', 'appointment');
        $alldates = get_string('unknowndate', 'appointment');
        $starttime = get_string('unknowntime', 'appointment');
        $finishtime = get_string('unknowntime', 'appointment');

    } else {
        // Scheduled session.
        $sessiondate = userdate($data->sessiondates[0]->timestart, get_string('strftimedate'));
        $starttime = userdate($data->sessiondates[0]->timestart, get_string('strftimetime'));
        $finishtime = userdate($data->sessiondates[0]->timefinish, get_string('strftimetime'));

        $alldates = '';
        foreach ($data->sessiondates as $date) {
            if ($alldates != '') {
                $alldates .= "\n";
            }
            $alldates .= userdate($date->timestart, get_string('strftimedate')) . ', ';
            $alldates .= userdate($date->timestart, get_string('strftimetime')) .
                ' to ' . userdate($date->timefinish, get_string('strftimetime'));
        }
    }

    $msg = str_replace(get_string('placeholder:appointmentname', 'appointment'), $appointmentname, $msg);
    $msg = str_replace(get_string('placeholder:firstname', 'appointment'), $user->firstname, $msg);
    $msg = str_replace(get_string('placeholder:lastname', 'appointment'), $user->lastname, $msg);
    $msg = str_replace(get_string('placeholder:alldates', 'appointment'), $alldates, $msg);
    $msg = str_replace(get_string('placeholder:sessiondate', 'appointment'), $sessiondate, $msg);
    $msg = str_replace(get_string('placeholder:starttime', 'appointment'), $starttime, $msg);
    $msg = str_replace(get_string('placeholder:finishtime', 'appointment'), $finishtime, $msg);
    if (empty($data->details)) {
        $msg = str_replace(get_string('placeholder:details', 'appointment'), '', $msg);
    } else {
        $msg = str_replace(get_string('placeholder:details', 'appointment'), html_to_text($data->details), $msg);
    }
    $msg = str_replace(get_string('placeholder:reminderperiod', 'appointment'), $reminderperiod, $msg);

    // Replace more meta data.
    $msg = str_replace(get_string('placeholder:attendeeslink', 'appointment'),
        $CFG->wwwroot . '/mod/appointment/attendees.php?s=' . $sessionid, $msg);

    // Custom session fields (they look like "session:shortname" in the templates).
    $handler = \mod_appointment\customfield\appointment_handler::create();
    foreach ($handler->export_instance_data($sessionid) as $fielddata) {
        $placeholder = "[session:{$fielddata->get_shortname()}]";
        $value = $fielddata->get_value() ?? '';
        $msg = str_replace($placeholder, $value, $msg);
    }
    return $msg;
}

/**
 * Function to be run periodically according to the moodle cron
 * Finds all appointment notifications that have yet to be mailed out, and mails them.
 *
 * @return bool
 */
function appointment_cron() {
    global $CFG, $USER, $DB;

    $signupsdata = appointment_get_unmailed_reminders();
    if (!$signupsdata) {
        mtrace(get_string('noremindersneedtobesent', 'appointment'));
        return true;
    }

    $timenow = time();
    foreach ($signupsdata as $signupdata) {
        if (appointment_has_session_started($signupdata, $timenow)) {

            // Too late, the session already started.
            // Mark the reminder as being sent already.
            $newsubmission = new stdClass();
            $newsubmission->id = $signupdata->id;
            $newsubmission->mailedreminder = 1; // Magic number to show that it was not actually sent.
            if (!$DB->update_record('appointment_signups', $newsubmission)) {
                mtrace("ERROR: could not update mailedreminder for submission ID $signupdata->id");
            }
            continue;
        }

        $earlieststarttime = $signupdata->sessiondates[0]->timestart;
        foreach ($signupdata->sessiondates as $date) {
            if ($date->timestart < $earlieststarttime) {
                $earlieststarttime = $date->timestart;
            }
        }

        $reminderperiod = $signupdata->reminderperiod;

        // Convert the period from business days (no weekends) to calendar days.
        for ($reminderday = 0; $reminderday < $reminderperiod + 1; $reminderday++) {
            $reminderdaytime = $earlieststarttime - ($reminderday * 24 * 3600);

            // Use %w instead of %u for Windows compatability.
            $reminderdaycheck = userdate($reminderdaytime, '%w');

            // Note w runs from Sun=0 to Sat=6.
            if ($reminderdaycheck == 0 || $reminderdaycheck == 6) {

                /*
                 * Saturdays and Sundays are not included in the
                 * reminder period as entered by the user, extend
                 * that period by 1
                */
                $reminderperiod++;
            }
        }

        $remindertime = $earlieststarttime - ($reminderperiod * 24 * 3600);
        if ($timenow < $remindertime) {

            // Too early to send reminder.
            continue;
        }

        if (!$user = $DB->get_record('user', array('id' => $signupdata->userid))) {
            continue;
        }

        // Hack to make sure that the timezone and languages are set properly in emails.
        // (i.e. it uses the language and timezone of the recipient of the email).
        $USER->lang = $user->lang;
        $USER->timezone = $user->timezone;
        if (!$course = $DB->get_record('course', array('id' => $signupdata->course))) {
            continue;
        }
        if (!$appointment = $DB->get_record('appointment', array('id' => $signupdata->appointmentid))) {
            continue;
        }

        $postsubject = '';
        $posttext = '';
        $posttextmgrheading = '';
        if (empty($signupdata->mailedreminder)) {
            $postsubject = $appointment->remindersubject;
            $posttext = $appointment->remindermessage;
            $posttextmgrheading = $appointment->reminderinstrmngr;
        }

        if (empty($posttext)) {

            // The reminder message is not set, don't send anything.
            continue;
        }

        $postsubject = appointment_email_substitutions($postsubject, $signupdata->appointmentname, $signupdata->reminderperiod,
            $user, $signupdata, $signupdata->sessionid);
        $posttext = appointment_email_substitutions($posttext, $signupdata->appointmentname, $signupdata->reminderperiod,
            $user, $signupdata, $signupdata->sessionid);
        $posttextmgrheading = appointment_email_substitutions($posttextmgrheading, $signupdata->appointmentname,
            $signupdata->reminderperiod, $user, $signupdata, $signupdata->sessionid);

        $posthtml = ''; // FIXME.
        $from = core_user::get_noreply_user();

        if (email_to_user($user, $from, $postsubject, $posttext, $posthtml)) {
            mtrace(get_string('sentreminderuser', 'appointment') . ": $user->firstname $user->lastname $user->email");

            $newsubmission = new stdClass();
            $newsubmission->id = $signupdata->id;
            $newsubmission->mailedreminder = $timenow;
            if (!$DB->update_record('appointment_signups', $newsubmission)) {
                mtrace("ERROR: could not update mailedreminder for submission ID $signupdata->id");
            }

            if (empty($posttextmgrheading)) {
                continue; // No manager message set.
            }

            $managertext = $posttextmgrheading . $posttext;
            $manager = $user;
            $manager->email = appointment_get_manageremail($user->id);

            if (empty($manager->email)) {
                continue; // Don't know who the manager is.
            }

            // Send email to manager.
            if (email_to_user($manager, $from, $postsubject, $managertext, $posthtml)) {
                mtrace(get_string('sentremindermanager', 'appointment') .
                    ": $user->firstname $user->lastname $manager->email");
            } else {
                $errormsg = array();
                $errormsg['submissionid'] = $signupdata->id;
                $errormsg['userid'] = $user->id;
                $errormsg['manageremail'] = $manager->email;
                mtrace(get_string('error:cronprefix', 'appointment') . ' ' .
                    get_string('error:cannotemailmanager', 'appointment', $errormsg));
            }
        } else {
            $errormsg = array();
            $errormsg['submissionid'] = $signupdata->id;
            $errormsg['userid'] = $user->id;
            $errormsg['useremail'] = $user->email;
            mtrace(get_string('error:cronprefix', 'appointment') . ' ' .
                get_string('error:cannotemailuser', 'appointment', $errormsg));
        }
    }
    return true;
}

/**
 * Returns true if the session has started, that is if one of the
 * session dates is in the past.
 *
 * @param stdClass $session record from the appointment_sessions table
 * @param int $timenow current time
 */
function appointment_has_session_started($session, $timenow) {

    if (empty($session->sessiondates)) {
        return false; // No date set.
    }

    foreach ($session->sessiondates as $date) {
        if ($date->timestart < $timenow) {
            return true;
        }
    }

    return false;
}

/**
 * Returns true if the session has started and has not yet finished.
 *
 * @param stdClass $session record from the appointment_sessions table
 * @param int $timenow current time
 */
function appointment_is_session_in_progress($session, $timenow) {
    if (empty($session->sessiondates)) {
        return false;
    }
    foreach ($session->sessiondates as $date) {
        if ($date->timefinish > $timenow && $date->timestart < $timenow) {
            return true;
        }
    }

    return false;
}

/**
 * Get all of the dates for a given session
 *
 * @param int $sessionid
 */
function appointment_get_session_dates($sessionid) {
    global $DB;

    $ret = array();
    if ($dates = $DB->get_records('appointment_sessions_dates', array('sessionid' => $sessionid), 'timestart')) {
        $i = 0;
        foreach ($dates as $date) {
            $ret[$i++] = $date;
        }
    }

    return $ret;
}

/**
 * Get a record from the appointment_sessions table
 *
 * @param int $sessionid ID of the session record.
 * @return \stdClass|bool $session
 */
function appointment_get_session($sessionid) {
    global $DB;

    $session = $DB->get_record('appointment_sessions', ['id' => $sessionid]);
    if ($session) {
        $session->sessiondates = appointment_get_session_dates($sessionid);
    }

    return $session;
}

/**
 * Get all records from appointment_sessions for a given appointment activity.
 *
 * @param int $appointmentid ID of the activity
 */
function appointment_get_sessions($appointmentid) {
    global $CFG, $DB;

    $sessions = $DB->get_records_sql("SELECT s.*
                                        FROM {appointment_sessions} s
                             LEFT OUTER JOIN (SELECT sessionid, min(timestart) AS mintimestart
                                                FROM {appointment_sessions_dates}
                                            GROUP BY sessionid) m
                                          ON m.sessionid = s.id
                                       WHERE s.appointment = ?
                                    ORDER BY m.mintimestart", [$appointmentid]);

    if ($sessions) {
        foreach ($sessions as $key => $value) {
            $sessions[$key]->sessiondates = appointment_get_session_dates($value->id);
        }
    }

    return $sessions;
}

/**
 * Get a grade for the given user from the gradebook.
 *
 * @param int $userid ID of the user
 * @param int $courseid ID of the course
 * @param int $appointmentid ID of the Appointment activity
 *
 * @return object String grade and the time that it was graded
 */
function appointment_get_grade($userid, $courseid, $appointmentid) {

    $ret = new stdClass();
    $ret->grade = 0;
    $ret->dategraded = 0;

    $gradinginfo = grade_get_grades($courseid, 'mod', 'appointment', $appointmentid, $userid);
    if (!empty($gradinginfo->items)) {
        $ret->grade = $gradinginfo->items[0]->grades[$userid]->str_grade;
        $ret->dategraded = $gradinginfo->items[0]->grades[$userid]->dategraded;
    }

    return $ret;
}

/**
 * Get list of users attending a given session
 *
 * @param int $sessionid Session ID
 * @return array
 */
function appointment_get_attendees($sessionid) {
    global $CFG, $DB;

    $usernamefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $records = $DB->get_records_sql("
        SELECT u.id, {$usernamefields},
            u.email,
            su.id AS submissionid,
            f.id AS appointmentid,
            f.course,
            ss.grade,
            ss.statuscode,
            sign.timecreated
        FROM
            {appointment} f
        JOIN
            {appointment_sessions} s
         ON s.appointment = f.id
        JOIN
            {appointment_signups} su
         ON s.id = su.sessionid
        JOIN
            {appointment_signups_status} ss
         ON su.id = ss.signupid
        LEFT JOIN
            (
            SELECT
                ss.signupid,
                MAX(ss.timecreated) AS timecreated
            FROM
                {appointment_signups_status} ss
            INNER JOIN
                {appointment_signups} s
             ON s.id = ss.signupid
            AND s.sessionid = ?
            WHERE
                ss.statuscode IN (?,?)
            GROUP BY
                ss.signupid
            ) sign
         ON su.id = sign.signupid
        JOIN
            {user} u
         ON u.id = su.userid
        WHERE
            s.id = ?
        AND ss.superceded != 1
        AND ss.statuscode >= ?
        ORDER BY
            sign.timecreated ASC,
            ss.timecreated ASC
    ", array($sessionid, MOD_APPOINTMENT_STATUS_BOOKED, MOD_APPOINTMENT_STATUS_WAITLISTED, $sessionid,
        MOD_APPOINTMENT_STATUS_APPROVED));

    return $records;
}

/**
 * Get a single attendee of a session
 *
 * @param int $sessionid Session ID
 * @param int $userid User ID
 * @return false|object
 */
function appointment_get_attendee($sessionid, $userid) {
    global $CFG, $DB;

    $record = $DB->get_record_sql("
        SELECT
            u.id,
            su.id AS submissionid,
            u.firstname,
            u.lastname,
            u.email,
            f.id AS appointmentid,
            f.course,
            ss.grade,
            ss.statuscode
        FROM
            {appointment} f
        JOIN
            {appointment_sessions} s
         ON s.appointment = f.id
        JOIN
            {appointment_signups} su
         ON s.id = su.sessionid
        JOIN
            {appointment_signups_status} ss
         ON su.id = ss.signupid
        JOIN
            {user} u
         ON u.id = su.userid
        WHERE
            s.id = ?
        AND ss.superceded != 1
        AND u.id = ?
    ", array($sessionid, $userid));

    if (!$record) {
        return false;
    }

    return $record;
}

/**
 * Return all user fields to include in exports
 */
function appointment_get_userfields() {
    global $CFG;

    static $userfields = null;
    if (null == $userfields) {
        $userfields = array();

        if (function_exists('grade_export_user_fields')) {
            $fieldnames = grade_export_user_fields();
            foreach ($fieldnames as $key => $obj) {
                $userfields[$obj->shortname] = $obj->fullname;
            }
        } else {
            // Set default fields if the grade export patch is not detected (see MDL-17346).
            $fieldnames = array('firstname', 'lastname', 'email', 'city',
                'idnumber', 'institution', 'department', 'address');
            foreach ($fieldnames as $shortname) {
                $userfields[$shortname] = get_string($shortname);
            }
            $userfields['managersemail'] = get_string('manageremail', 'appointment');
        }
    }

    return $userfields;
}

/**
 * Return an object with all values for a user's custom fields.
 *
 * This is about 15 times faster than the custom field API.
 *
 * @param int $userid
 * @param array $fieldstoinclude Limit the fields returned/cached to these ones (optional)
 */
function appointment_get_user_customfields($userid, $fieldstoinclude = false) {
    global $CFG, $DB;

    // Cache all lookup.
    static $customfields = null;
    if (null == $customfields) {
        $customfields = array();
    }

    if (!empty($customfields[$userid])) {
        return $customfields[$userid];
    }

    $ret = new stdClass();
    $sql = "SELECT uif.shortname, id.data
              FROM {user_info_field} uif
              JOIN {user_info_data} id ON id.fieldid = uif.id
              WHERE id.userid = ?";

    $customfields = $DB->get_records_sql($sql, array($userid));
    foreach ($customfields as $field) {
        $fieldname = $field->shortname;
        if (false === $fieldstoinclude or !empty($fieldstoinclude[$fieldname])) {
            $ret->$fieldname = $field->data;
        }
    }

    $customfields[$userid] = $ret;
    return $ret;
}

/**
 * Return list of marked submissions that have not been mailed out for currently enrolled students
 */
function appointment_get_unmailed_reminders() {
    global $CFG, $DB;

    $submissions = $DB->get_records_sql("
        SELECT
            su.*,
            f.course,
            f.id as appointmentid,
            f.name as appointmentname,
            f.reminderperiod,
            se.details
        FROM {appointment_signups} su
        JOIN {appointment_signups_status} sus
          ON su.id = sus.signupid
         AND sus.superceded = 0
         AND sus.statuscode = ?
        JOIN {appointment_sessions} se
          ON su.sessionid = se.id
        JOIN {appointment} f
          ON se.appointment = f.id
       WHERE su.mailedreminder = 0
    AND EXISTS (SELECT 1
                  FROM {appointment_sessions_dates} d
                 WHERE d.sessionid = se.id)", [MOD_APPOINTMENT_STATUS_BOOKED]);

    if ($submissions) {
        foreach ($submissions as $key => $value) {
            $submissions[$key]->sessiondates = appointment_get_session_dates($value->sessionid);
        }
    }

    return $submissions;
}

/**
 * Add a record to the appointment submissions table and sends out an
 * email confirmation
 *
 * TODO: WP-2920 Remove $notificationtype attribute.
 *
 * @param \stdClass $session record from the appointment_sessions table
 * @param \stdClass $appointment record from the appointment table
 * @param \stdClass $course record from the course table
 * @param int|null $notificationtype type of notifications to send to user (deprecated WP-2920)
 * @param int $statuscode Status code to set
 * @param int|null $userid user to signup or null for current user
 * @param bool $notifyuser whether or not to send an email confirmation
 * @return bool true on success
 */
function appointment_user_signup($session, $appointment, $course, ?int $notificationtype,
                                 int $statuscode, ?int $userid = null, $notifyuser = true): bool {
    global $DB, $USER;

    if ($notificationtype !== null) {
        debugging('$notificationtype attribute is deprecated and will be removed in upcoming relese, see WP-2920 for details',
            DEBUG_DEVELOPER);
    }

    $userid = $userid ?? $USER->id;
    $timenow = time();

    // Check to see if a signup already exists.
    if ($existingsignup = $DB->get_record('appointment_signups', array('sessionid' => $session->id, 'userid' => $userid))) {
        $usersignup = $existingsignup;
    } else {

        // Otherwise, prepare a signup object.
        $usersignup = new stdclass;
        $usersignup->sessionid = $session->id;
        $usersignup->userid = $userid;
    }

    $usersignup->mailedreminder = 0;

    // Update/insert the signup record.
    if (!empty($usersignup->id)) {
        $success = $DB->update_record('appointment_signups', $usersignup);
    } else {
        $usersignup->id = $DB->insert_record('appointment_signups', $usersignup);
        $success = (bool)$usersignup->id;
    }

    if (!$success) {
        throw new moodle_exception('error:couldnotupdateappointmentrecord', 'appointment');
    }

    // Work out which status to use.

    // If approval not required.
    if (!$appointment->approvalreqd) {
        $newstatus = $statuscode;
    } else {

        // If approval required.
        // Get current status (if any).
        $currentstatus = $DB->get_field('appointment_signups_status', 'statuscode',
            array('signupid' => $usersignup->id, 'superceded' => 0));

        // If approved, then no problem.
        if ($currentstatus == MOD_APPOINTMENT_STATUS_APPROVED) {
            $newstatus = $statuscode;
        } else if (!empty($session->sessiondates)) {
            // Otherwise, send manager request.
            $newstatus = MOD_APPOINTMENT_STATUS_REQUESTED;
        } else {
            $newstatus = MOD_APPOINTMENT_STATUS_WAITLISTED;
        }
    }

    // Update status.
    if (!appointment_update_signup_status($usersignup->id, $newstatus, $userid)) {
        throw new moodle_exception('error:appointmentfailedupdatestatus', 'appointment');
    }

    // Add to user calendar -- if appointment usercalentry is set to true.
    if ($appointment->usercalentry) {
        if (in_array($newstatus, array(MOD_APPOINTMENT_STATUS_BOOKED, MOD_APPOINTMENT_STATUS_WAITLISTED))) {
            $eventtype = $newstatus == MOD_APPOINTMENT_STATUS_BOOKED ? 'booking' : 'session';
            appointment_add_session_to_calendar($session, $appointment, 'user', $userid, $eventtype);
        }
    }

    // Update completion.
    if (in_array($newstatus, array(MOD_APPOINTMENT_STATUS_BOOKED, MOD_APPOINTMENT_STATUS_WAITLISTED))) {
        $completion = new completion_info($course);
        // Course completion.
        if ($completion->is_enabled()) {
            $ccdetails = array(
                'course' => $course->id,
                'userid' => $userid,
            );

            $cc = new completion_completion($ccdetails);
            $cc->mark_inprogress($timenow);
        }
        // Module completion only if booked.
        $cm = get_coursemodule_from_instance('appointment', $appointment->id);
        if ($newstatus == MOD_APPOINTMENT_STATUS_BOOKED && $completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC
            && $appointment->completionbooked) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }
    }

    // If session has already started, do not send a notification.
    if (appointment_has_session_started($session, $timenow)) {
        $notifyuser = false;
    }

    // Send notification.
    if ($notifyuser) {

        // If booked/waitlisted.
        switch ($newstatus) {
            case MOD_APPOINTMENT_STATUS_BOOKED:
                $error = appointment_send_confirmation_notice($appointment, $session, $userid, null, false);
                break;

            case MOD_APPOINTMENT_STATUS_WAITLISTED:
                $error = appointment_send_confirmation_notice($appointment, $session, $userid, null, true);
                break;

            case MOD_APPOINTMENT_STATUS_REQUESTED:
                $error = appointment_send_request_notice($appointment, $session, $userid);
                break;
        }

        if (!empty($error)) {
            throw new moodle_exception($error, 'appointment');
        }

        if (!$DB->update_record('appointment_signups', $usersignup)) {
            throw new moodle_exception('error:couldnotupdateappointmentrecord', 'appointment');
        }
    }

    return true;
}

/**
 * Send booking request notice to user and their manager
 *
 * @param  stdClass $appointment Appointment instance
 * @param  stdClass $session Session instance
 * @param  int $userid ID of user requesting booking
 * @return string Error string, empty on success
 */
function appointment_send_request_notice($appointment, $session, $userid) {
    global $DB;

    if (!$manageremail = appointment_get_manageremail($userid)) {
        return 'error:nomanagersemailset';
    }

    $user = $DB->get_record('user', array('id' => $userid));
    if (!$user) {
        return 'error:invaliduserid';
    }

    $postsubject = appointment_email_substitutions(
        $appointment->requestsubject,
        $appointment->name,
        $appointment->reminderperiod,
        $user,
        $session,
        $session->id
    );

    $posttext = appointment_email_substitutions(
        $appointment->requestmessage,
        $appointment->name,
        $appointment->reminderperiod,
        $user,
        $session,
        $session->id
    );

    $posttextmgrheading = appointment_email_substitutions(
        $appointment->requestinstrmngr,
        $appointment->name,
        $appointment->reminderperiod,
        $user,
        $session,
        $session->id
    );

    // Send to user.
    $from = core_user::get_noreply_user();
    if (!email_to_user($user, $from, $postsubject, $posttext)) {
        return 'error:cannotsendrequestuser';
    }

    // Send to manager.
    $user->email = $manageremail;

    if (!email_to_user($user, $from, $postsubject, $posttextmgrheading . $posttext)) {
        return 'error:cannotsendrequestmanager';
    }

    return '';
}


/**
 * Update the signup status of a particular signup
 *
 * @param int $signupid ID of the signup to be updated
 * @param int $statuscode Status code to be updated to
 * @param int $createdby User ID of the user causing the status update
 * @param string $note Cancellation reason or other notes
 * @param int $grade Grade
 *
 * @return integer ID of newly created signup status, or false
 */
function appointment_update_signup_status($signupid, $statuscode, $createdby, $note = '', $grade = null) {
    global $DB;
    $timenow = time();

    $signupstatus = new stdclass;
    $signupstatus->signupid = $signupid;
    $signupstatus->statuscode = $statuscode;
    $signupstatus->createdby = $createdby;
    $signupstatus->timecreated = $timenow;
    $signupstatus->note = $note;
    $signupstatus->grade = $grade;
    $signupstatus->superceded = 0;
    $signupstatus->mailed = 0;

    $transaction = $DB->start_delegated_transaction();

    if ($statusid = $DB->insert_record('appointment_signups_status', $signupstatus)) {

        // Mark any previous signup_statuses as superceded.
        $where = "signupid = ? AND ( superceded = 0 OR superceded IS NULL ) AND id != ?";
        $whereparams = array($signupid, $statusid);
        $DB->set_field_select('appointment_signups_status', 'superceded', 1, $where, $whereparams);
        $transaction->allow_commit();

        return $statusid;
    } else {
        $transaction->rollback();
        return false;
    }
}

/**
 * Cancel a user who signed up earlier
 *
 * @param stdClass $session Record from the appointment_sessions table
 * @param int $userid ID of the user to remove from the session
 * @param bool $forcecancel Forces cancellation of sessions that have already occurred
 * @param string $errorstr Passed by reference. For setting error string in calling function
 * @param string $cancelreason Optional justification for cancelling the signup
 */
function appointment_user_cancel($session, $userid = false, $forcecancel = false, &$errorstr = null, $cancelreason = '') {
    global $USER, $DB;

    if (!$userid) {
        $userid = $USER->id;
    }

    $timenow = time();
    // If $forcecancel is set, cancel session even if already occurred used by appointment_delete_session().
    if (!$forcecancel) {
        // Don't allow user to cancel a session that has already occurred.
        if (appointment_has_session_started($session, $timenow)) {
            $errorstr = get_string('error:eventoccurred', 'appointment');
            return false;
        }
    }

    if (appointment_user_cancel_submission($session->id, $userid, $cancelreason)) {
        // Remove entry from user's calendar.
        appointment_remove_session_from_calendar($session, 0, $userid);
        appointment_update_attendees($session);
        // Recalculate activity completion (only for non-started sessions).
        $appointment = $DB->get_record('appointment', ['id' => $session->appointment]);
        [$course, $cm] = get_course_and_cm_from_instance($appointment->id, 'appointment');
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC && $appointment->completionbooked
            && !appointment_has_session_started($session, $timenow)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
        return true;
    }

    // Todo: is this necessary?
    $errorstr = get_string('error:cancelbooking', 'appointment');

    return false;
}

/**
 * Common code for sending confirmation and cancellation notices
 *
 * See also {{MOD_APPOINTMENT_INVITE}}
 *
 * @param string $postsubject Subject of the email
 * @param string $posttext Plain text contents of the email
 * @param string $posttextmgrheading Header to prepend to $posttext in manager email
 * @param string $notificationtype The type of notification to send
 * @param stdClass $appointment record from the appointment table
 * @param stdClass $session record from the appointment_sessions table
 * @param int $userid ID of the recipient of the email
 * @return string Error message (or empty string if successful)
 */
function appointment_send_notice($postsubject, $posttext, $posttextmgrheading,
                                 $notificationtype, $appointment, $session, $userid) {
    global $DB;

    $user = $DB->get_record('user', array('id' => $userid));
    if (!$user) {
        return 'error:invaliduserid';
    }

    if (empty($postsubject) || empty($posttext)) {
        return '';
    }

    $skipattachment = false;

    // Set ical attachment file name.
    if ($notificationtype & MOD_APPOINTMENT_INVITE) {
        $attachmentfilename = 'invite.ics';
    } else if ($notificationtype & MOD_APPOINTMENT_CANCEL && !appointment_was_user_on_waitlist($session, $userid)) {
        $attachmentfilename = 'cancel.ics';
    } else if ($notificationtype & MOD_APPOINTMENT_UPDATE && !appointment_is_user_on_waitlist($session, $userid)) {
        $attachmentfilename = 'update.ics';
    } else {
        $skipattachment = true;
        $attachmentfilename = '';
    }

    // Do iCal attachement stuff.
    $icalattachments = array();
    if (!empty($session->sessiondates)) {
        // Keep track of all sessiondates.
        $sessiondates = $session->sessiondates;
        $sessiondatessplit = [];

        if (!get_config(null, 'appointment_oneemailperday') || $skipattachment) {
            $sessiondatessplit[] = $sessiondates;
        } else {
            foreach ($sessiondates as $sessiondate) {
                $sessiondatessplit[] = [$sessiondate]; // One day at a time.
            }
        }

        foreach ($sessiondatessplit as $sessiondatesplit) {
            $session->sessiondates = $sessiondatesplit;

            $filename = $skipattachment ? '' : appointment_get_ical_attachment($notificationtype, $appointment, $session, $user);
            $subject = appointment_email_substitutions($postsubject, $appointment->name, $appointment->reminderperiod,
                $user, $session, $session->id);
            $body = appointment_email_substitutions($posttext, $appointment->name, $appointment->reminderperiod,
                $user, $session, $session->id);
            $htmlbody = ''; // TODO.
            $icalattachments[] = array('filename' => $filename, 'subject' => $subject,
                'body' => $body, 'htmlbody' => $htmlbody);
        }

        // Restore session dates.
        $session->sessiondates = $sessiondates;
    }

    $from = core_user::get_noreply_user();

    // Send email with iCal attachment.
    foreach ($icalattachments as $attachment) {
        if (!email_to_user($user, $from, $attachment['subject'], $attachment['body'],
            $attachment['htmlbody'], $attachment['filename'], $attachmentfilename)) {
            return 'error:cannotsendconfirmationuser';
        }
    }

    // Fill-in the email placeholders.
    $postsubject = appointment_email_substitutions($postsubject, $appointment->name, $appointment->reminderperiod,
        $user, $session, $session->id);
    $posttext = appointment_email_substitutions($posttext, $appointment->name, $appointment->reminderperiod,
        $user, $session, $session->id);

    $posttextmgrheading = appointment_email_substitutions($posttextmgrheading, $appointment->name, $appointment->reminderperiod,
        $user, $session, $session->id);

    $posthtml = ''; // FIXME.

    // Manager notification.
    $manageremail = appointment_get_manageremail($userid);
    if (!empty($posttextmgrheading) and !empty($manageremail) and !empty($session->sessiondates)) {
        $managertext = $posttextmgrheading . $posttext;
        $manager = $user;
        $manager->email = $manageremail;

        // Leave out the ical attachments in the managers notification.
        if (!email_to_user($manager, $from, $postsubject, $managertext, $posthtml)) {
            return 'error:cannotsendconfirmationmanager';
        }
    }

    // Third-party notification.
    if (!empty($appointment->thirdparty) &&
        (!empty($session->sessiondates) || !empty($appointment->thirdpartywaitlist))) {

        $thirdparty = $user;
        $recipients = explode(',', $appointment->thirdparty);
        foreach ($recipients as $recipient) {
            $thirdparty->email = trim($recipient);

            // Leave out the ical attachments in the 3rd parties notification.
            if (!email_to_user($thirdparty, $from, $postsubject, $posttext, $posthtml)) {
                return 'error:cannotsendconfirmationthirdparty';
            }
        }
    }

    return '';
}

/**
 * Send a confirmation email to the user and manager
 *
 * TODO: WP-2920 Remove $notificationtype attribute.
 *
 * @param stdClass $appointment record from the appointment table
 * @param stdClass $session record from the appointment_sessions table
 * @param int $userid ID of the recipient of the email
 * @param int|null $notificationtype Type of notifications to be sent (deprecated WP-2920)
 * @param boolean $iswaitlisted If the user has been waitlisted
 * @return string Error message (or empty string if successful)
 */
function appointment_send_confirmation_notice($appointment, $session, $userid, $notificationtype, $iswaitlisted) {

    if ($notificationtype !== null) {
        debugging('$notificationtype attribute is deprecated and will be removed in upcoming relese, see WP-2920 for details',
            DEBUG_DEVELOPER);
    }

    $posttextmgrheading = $appointment->confirmationinstrmngr;

    if (!$iswaitlisted) {
        $postsubject = $appointment->confirmationsubject;
        $posttext = $appointment->confirmationmessage;
        // Set invite bit.
        $notificationtype = MOD_APPOINTMENT_INVITE;
    } else {
        $postsubject = $appointment->waitlistedsubject;
        $posttext = $appointment->waitlistedmessage;
        // Set invite bit.
        $notificationtype = MOD_APPOINTMENT_WAITLIST;
    }

    return appointment_send_notice($postsubject, $posttext, $posttextmgrheading,
        $notificationtype, $appointment, $session, $userid);
}

/**
 * Send a confirmation email to the user and manager regarding the
 * cancellation
 *
 * @param stdClass $appointment record from the appointment table
 * @param stdClass $session record from the appointment_sessions table
 * @param int $userid ID of the recipient of the email
 * @return string Error message (or empty string if successful)
 */
function appointment_send_cancellation_notice($appointment, $session, $userid) {
    $postsubject = $appointment->cancellationsubject;
    $posttext = $appointment->cancellationmessage;
    $posttextmgrheading = $appointment->cancellationinstrmngr;

    // Set cancellation bit.
    $notificationtype = MOD_APPOINTMENT_CANCEL;

    return appointment_send_notice($postsubject, $posttext, $posttextmgrheading,
        $notificationtype, $appointment, $session, $userid);
}

/**
 * Send session update email to the user
 *
 * @param stdClass $appointment record from the appointment table
 * @param stdClass $session record from the appointment_sessions table
 * @param int $userid ID of the recipient of the email
 * @return string Error message (or empty string if successful)
 */
function appointment_send_update_notice($appointment, $session, $userid) {
    $postsubject = $appointment->updatesubject;
    $posttext = $appointment->updatemessage;

    // Set update bit.
    $notificationtype = MOD_APPOINTMENT_UPDATE;

    return appointment_send_notice($postsubject, $posttext, '',
        $notificationtype, $appointment, $session, $userid);
}

/**
 * Returns true if the user has registered for a session in the given
 * appointment activity
 *
 * @param int $appointmentid
 * @return integer The session id that we signed up for, false otherwise
 */
function appointment_check_signup($appointmentid) {
    global $USER;

    if ($submissions = appointment_get_user_submissions($appointmentid, $USER->id)) {
        return reset($submissions)->sessionid;
    } else {
        return false;
    }
}

/**
 * Return the email address of the user's manager if it is
 * defined. Otherwise return an empty string.
 *
 * @param int $userid User ID of the staff member
 */
function appointment_get_manageremail($userid) {
    global $DB;
    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => MDL_MANAGERSEMAIL_FIELD));
    if ($fieldid) {
        return $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $fieldid));
    } else {
        return ''; // No custom field => no manager's email.
    }
}

/**
 * Mark the fact that the user attended the appointment session by
 * giving that user a grade of 100
 *
 * @param stdClass $data array containing the sessionid under the 's' key
 *                    and every submission ID to mark as attended
 *                    under the 'submissionid_XXXX' keys where XXXX is
 *                     the ID of the signup
 */
function appointment_take_attendance($data) {
    global $USER;

    $sessionid = $data->s;

    // Load session.
    if (!$session = appointment_get_session($sessionid)) {
        debug('Appointment: Could not load appointment session');
        return false;
    }

    // Check appointment has finished.
    if (!appointment_has_session_started($session, time())) {
        debug('Appointment: Can not take attendance for a session that has not yet started');
        return false;
    }

    /*
     * Record the selected attendees from the user interface - the other attendees will need their grades set
     * to zero, to indicate non attendance, but only the ticked attendees come through from the web interface.
     * Hence the need for a diff
     */
    $selectedsubmissionids = array();

    /*
     * FIXME: This is not very efficient, we should do the grade
     * query outside of the loop to get all submissions for a
     * given Appointment ID, then call
     * appointment_grade_item_update with an array of grade objects.
     */
    foreach ($data as $key => $value) {
        $submissionidcheck = substr($key, 0, 13);
        if ($submissionidcheck == 'submissionid_') {
            $submissionid = substr($key, 13);
            $selectedsubmissionids[$submissionid] = $submissionid;

            // Update status.
            if ($value == MOD_APPOINTMENT_STATUS_NO_SHOW) {
                $grade = 0;
            } else if ($value == MOD_APPOINTMENT_STATUS_PARTIALLY_ATTENDED) {
                $grade = 50;
            } else if ($value == MOD_APPOINTMENT_STATUS_FULLY_ATTENDED ) {
                $grade = 100;
            } else {
                // This use has not had attendance set: jump to the next item in the foreach loop.
                continue;
            }

            appointment_update_signup_status($submissionid, $value, $USER->id, '', $grade);
            if (!appointment_take_individual_attendance($submissionid, $grade)) {
                debug("Appointment: could not mark '$submissionid' as " . $value);
                return false;
            }
        }
    }

    return true;
}

/**
 * Mark users' booking requests as declined or approved
 *
 * @param stdClass $data array containing the sessionid under the 's' key
 *                       and an array of request approval/denies
 */
function appointment_approve_requests($data) {
    global $USER, $DB;

    // Check request data.
    if (empty($data->requests) || !is_array($data->requests)) {
        debug('Appointment: No request data supplied');
        return false;
    }

    $sessionid = $data->s;

    // Load session.
    if (!$session = appointment_get_session($sessionid)) {
        debug('Appointment: Could not load appointment session');
        return false;
    }

    // Load appointment.
    if (!$appointment = $DB->get_record('appointment', array('id' => $session->appointment))) {
        debug('Appointment: Could not load appointment instance');
        return false;
    }

    // Load course.
    if (!$course = $DB->get_record('course', array('id' => $appointment->course))) {
        debug('Appointment: Could not load course');
        return false;
    }

    // Loop through requests.
    foreach ($data->requests as $key => $value) {

        // Check key/value.
        if (!is_numeric($key) || !is_numeric($value)) {
            continue;
        }

        // Load user submission.
        if (!$attendee = appointment_get_attendee($sessionid, $key)) {
            debug('Appointment: User '.$key.' not an attendee of this session');
            continue;
        }

        // Update status.
        switch ($value) {

            // Decline.
            case 1:
                appointment_update_signup_status(
                    $attendee->submissionid,
                    MOD_APPOINTMENT_STATUS_DECLINED,
                    $USER->id
                );

                // Send a cancellation notice to the user.
                appointment_send_cancellation_notice($appointment, $session, $attendee->id);

                break;

            // Approve.
            case 2:
                appointment_update_signup_status(
                    $attendee->submissionid,
                    MOD_APPOINTMENT_STATUS_APPROVED,
                    $USER->id
                );

                if (!$cm = get_coursemodule_from_instance('appointment', $appointment->id, $course->id)) {
                    throw new moodle_exception('error:incorrectcoursemodule', 'appointment');
                }

                $contextmodule = context_module::instance($cm->id);

                // Check if there is capacity.
                if (appointment_session_has_capacity($session, $contextmodule)) {
                    $status = MOD_APPOINTMENT_STATUS_BOOKED;
                } else {
                    if ($session->allowwaitlist) {
                        $status = MOD_APPOINTMENT_STATUS_WAITLISTED;
                    }
                }

                // Signup user.
                if (!appointment_user_signup(
                    $session,
                    $appointment,
                    $course,
                    null,
                    $status,
                    $attendee->id
                )) {
                    continue 2;
                }

                break;

            case 0:
            default:
                // Change nothing.
                continue 2;
        }
    }

    return true;
}

/**
 * Set the grading for an individual submission, to either 0 or 100 to indicate attendance
 *
 * @param int $submissionid The id of the submission in the database
 * @param float $grading Grade to set
 */
function appointment_take_individual_attendance($submissionid, $grading) {
    global $USER, $CFG, $DB;

    $timenow = time();
    $record = $DB->get_record_sql("SELECT f.*, s.userid
                                FROM {appointment_signups} s
                                JOIN {appointment_sessions} fs ON s.sessionid = fs.id
                                JOIN {appointment} f ON f.id = fs.appointment
                                JOIN {course_modules} cm ON cm.instance = f.id
                                JOIN {modules} m ON m.id = cm.module
                                WHERE s.id = ? AND m.name='appointment'",
        array($submissionid));

    $grade = new stdclass();
    $grade->userid = $record->userid;
    $grade->rawgrade = $grading;
    $grade->rawgrademin = 0;
    $grade->rawgrademax = 100;
    $grade->timecreated = $timenow;
    $grade->timemodified = $timenow;
    $grade->usermodified = $USER->id;

    return appointment_grade_item_update($record, $grade);
}

/**
 * Used in many places to obtain properly-formatted session date and time info
 *
 * @param int $start a start time Unix timestamp
 * @param int $end an end time Unix timestamp
 * @param string $tz a session timezone
 * @return object Formatted date, start time, end time and timezone info
 */
function appointment_format_session_times($start, $end, $tz) {

    $displaytimezones = get_config(null, 'appointment_displaysessiontimezones');

    $formattedsession = new stdClass();
    if (empty($tz) or empty($displaytimezones)) {
        $targettz = core_date::get_user_timezone();
    } else {
        $targettz = core_date::get_user_timezone($tz);
    }

    $formattedsession->startdate = userdate($start, get_string('strftimedaydate', 'langconfig'), $targettz);
    $formattedsession->starttime = userdate($start, get_string('strftimetime', 'langconfig'), $targettz);
    $formattedsession->enddate = userdate($end, get_string('strftimedaydate', 'langconfig'), $targettz);
    $formattedsession->endtime = userdate($end, get_string('strftimetime', 'langconfig'), $targettz);
    if (empty($displaytimezones)) {
        $formattedsession->timezone = '';
    } else {
        $formattedsession->timezone = core_date::get_localised_timezone($targettz);
    }
    return $formattedsession;
}

/**
 * Used by course/lib.php to display a few sessions besides the
 * appointment activity on the course page
 *
 * @param cm_info $coursemodule the cm_info object for the Appointment instance
 */
function appointment_cm_info_view(cm_info $coursemodule) {
    global $USER, $DB;

    if (!($appointment = $DB->get_record('appointment', array('id' => $coursemodule->instance)))) {
        return null;
    }

    $coursemodule->set_name($appointment->name);

    $contextmodule = context_module::instance($coursemodule->id);
    if (!\mod_appointment\permission::can_view_appointment($contextmodule)) {
        return null; // Not allowed to view this activity.
    }

    $afterlink = '';
    if ($submissions = appointment_get_user_submissions($appointment->id, $USER->id)) {
        // User has signedup for the instance.

        foreach ($submissions as $submission) {
            $session = appointment_get_session($submission->sessionid);
            if (empty($session->sessiondates)) {
                $afterlink = get_string('booked', 'appointment');
                break;
            } else {
                foreach ($session->sessiondates as $date) {
                    $sessionobj = appointment_format_session_times($date->timestart, $date->timefinish, null);

                    if ($sessionobj->startdate == $sessionobj->enddate) {
                        $sessiondatelangkey = !empty($sessionobj->timezone) ? 'sessionstartdateandtime' :
                            'sessionstartdateandtimewithouttimezone';
                        $afterlink = get_string($sessiondatelangkey, 'appointment', $sessionobj);
                    } else {
                        $sessiondatelangkey = !empty($sessionobj->timezone) ? 'sessionstartfinishdateandtime' :
                            'sessionstartfinishdateandtimewithouttimezone';
                        $afterlink = get_string($sessiondatelangkey, 'appointment', $sessionobj);
                    }
                    break; // We just need first.
                }
            }
        }
    }
    if (empty($afterlink) && $sessions = appointment_get_sessions($appointment->id)) {
        $seatscount = appointment_get_available_seats_count($sessions, $contextmodule);
        $afterlink = get_string('availableseats', 'mod_appointment', $seatscount);
    }
    if (!empty($afterlink)) {
        $afterlink = html_writer::tag('span', $afterlink , ['class' => 'font-small']);
        $coursemodule->set_after_link($afterlink);
    }
}

/**
 * Returns the ICAL data for a appointment meeting.
 *
 * @param int $method The method, see {{MOD_APPOINTMENT_INVITE}}
 * @param stdClass $appointment A appointment object containing activity details
 * @param stdClass $session A session object containing session details
 * @param stdClass $user
 * @return string Complete path to the temporary ical attachment
 */
function appointment_get_ical_attachment($method, $appointment, $session, $user) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/bennu/bennu.inc.php');

    $cm = get_coursemodule_from_instance('appointment', $appointment->id);
    $context = \context_module::instance($cm->id);

    $ical = new iCalendar();
    $icalmethod = ($method & MOD_APPOINTMENT_CANCEL) ? 'CANCEL' : 'PUBLISH';
    $ical->add_property('method', $icalmethod);
    $ical->add_property('prodid', '-//Moodle Pty Ltd//NONSGML Moodle Version ' . $CFG->version . '//EN');

    // Sequence. Effectively the number of times session was updated. When cancelling, increment sequence.
    $sequence = ($method & MOD_APPOINTMENT_CANCEL) ? (int) $session->countmodified + 1 : $session->countmodified;

    // Events for each session date.
    $datecounter = 1;
    foreach ($session->sessiondates as $date) {
        $ev = new iCalendar_event();

        // Change management properties.
        $ev->add_property('dtstamp', Bennu::timestamp_to_datetime());
        $ev->add_property('created', Bennu::timestamp_to_datetime($session->timecreated));
        $ev->add_property('last-modified', Bennu::timestamp_to_datetime($session->timemodified));
        $ev->add_property('sequence', $sequence);

        // Relationship properties.
        $sql = "SELECT COUNT(*)
            FROM {appointment_signups} su
            INNER JOIN {appointment_signups_status} sus ON su.id = sus.signupid
            WHERE su.userid = ?
                AND su.sessionid = ?
                AND sus.superceded = 1
                AND sus.statuscode = ? ";
        $params = [$user->id, $session->id, MOD_APPOINTMENT_STATUS_USER_CANCELLED];
        $host = (new moodle_url($CFG->wwwroot))->get_host();
        // UIDs should be globally unique. It should be the same for bookings,
        // updates and cancellation of this booking.
        $uid = Bennu::timestamp_to_datetime($session->timecreated) .
            '-' . substr(md5($CFG->siteidentifier . $session->id . $datecounter), -8) . // Unique identifier, salted.
            '-' . $DB->count_records_sql($sql, $params) .                            // New UID if this is a re-signup.
            '@' . $host;                                                             // Hostname for this moodle installation.
        $ev->add_property('uid', $uid);
        // We don't add 'organizer' property for now, as it is pointless without real contact reference,
        // but this might be required in WP-1210. Also be aware of MDL-71556.

        $linkurl = new moodle_url('/mod/appointment/view.php', ['id' => $cm->id, 's' => $session->id]);
        $ev->add_property('url', $linkurl->out(false));

        // Date and Time.
        $ev->add_property('dtstart', Bennu::timestamp_to_datetime($date->timestart));
        $ev->add_property('dtend', Bennu::timestamp_to_datetime($date->timefinish));

        // Descriptive properties.
        if ($method & MOD_APPOINTMENT_CANCEL) {
            $ev->add_property('status', 'CANCELLED');
        }
        $ev->add_property('class', 'PRIVATE');
        $ev->add_property('summary', format_string($appointment->name));
        $details = file_rewrite_pluginfile_urls($session->details, 'pluginfile.php', $context->id,
            'mod_appointment', 'session', $session->id);
        $details = format_text($details, $session->detailsformat, ['context' => $context->id]);
        // Strip italic before passing to html_to_text, so it won't wrap it as '_text_'.
        $ev->add_property('description', html_to_text(preg_replace('/<\/?(em|i|ins)(\\s+.*?>|>)/', '', $details), 0));
        // Alternative html formatted description for MS Outlook (not in iCal spec).
        $xaltdescr = '<!DOCTYPE HTML><HTML><HEAD></HEAD><BODY>' . $details . '</BODY></HTML>';
        $ev->add_property('X-ALT-DESC', $xaltdescr, ["FMTTYPE" => "text/html"]);

        $ical->add_component($ev);
        $datecounter++;
    }

    $template = $ical->serialize();
    $tempfilepathname = make_request_directory() . '/' . md5($template);
    file_put_contents($tempfilepathname, $template);
    return $tempfilepathname;
}

/**
 * Determine if a user is in the waitlist of a session.
 *
 * @param stdClass $session A session object
 * @param int $userid The user ID
 * @return bool True if the user is on waitlist, false otherwise.
 */
function appointment_is_user_on_waitlist($session, $userid = null) {
    global $DB, $USER;

    if ($userid === null) {
        $userid = $USER->id;
    }

    $sql = "SELECT 1
            FROM {appointment_signups} su
            JOIN {appointment_signups_status} ss ON su.id = ss.signupid
            WHERE su.sessionid = ?
              AND ss.superceded != 1
              AND su.userid = ?
              AND ss.statuscode = ?";

    return $DB->record_exists_sql($sql, array($session->id, $userid, MOD_APPOINTMENT_STATUS_WAITLISTED));
}

/**
 * Determine if a user was in the waitlist in previous status.
 *
 * @param stdClass $session A session object
 * @param int $userid The user ID
 * @return bool True if the user was on waitlist in the previous status, false otherwise.
 */
function appointment_was_user_on_waitlist($session, $userid = null) {
    global $DB, $USER;

    if ($userid === null) {
        $userid = $USER->id;
    }

    $sql = "SELECT ss.statuscode
            FROM {appointment_signups} su
            JOIN {appointment_signups_status} ss ON su.id = ss.signupid
            WHERE su.sessionid = :sessionid
              AND ss.superceded = :superceded
              AND su.userid = :userid
            ORDER BY ss.timecreated DESC, ss.id DESC";
    $params = ['sessionid' => $session->id, 'superceded' => 1, 'userid' => $userid];

    $signupstatus = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
    return $signupstatus && ($signupstatus->statuscode == MOD_APPOINTMENT_STATUS_WAITLISTED);
}

/**
 * Update grades by firing grade_updated event
 *
 * @param stdClass $appointment null means all appointment activities
 * @param int $userid specific user only, 0 mean all (not used here)
 */
function appointment_update_grades($appointment = null, $userid = 0) {
    global $DB;

    if ($appointment != null) {
        appointment_grade_item_update($appointment);
    } else {
        $sql = "SELECT f.*, cm.idnumber as cmidnumber
                  FROM {appointment} f
                  JOIN {course_modules} cm ON cm.instance = f.id
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name='appointment'";
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $appointment) {
                appointment_grade_item_update($appointment);
            }
            $rs->close();
        }
    }

    return true;
}

/**
 * Create grade item for given Appointment session
 *
 * @param stdClass $appointment  Appointment activity (not the session) to grade
 * @param mixed $grades    grades objects or 'reset' (means reset grades in gradebook)
 * @return int 0 if ok, error code otherwise
 */
function appointment_grade_item_update($appointment, $grades = null) {
    global $CFG, $DB;

    if (!isset($appointment->cmidnumber)) {

        $sql = "SELECT cm.idnumber as cmidnumber
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name='appointment' AND cm.instance = ?";
        $appointment->cmidnumber = $DB->get_field_sql($sql, array($appointment->id));
    }

    $params = array('itemname' => $appointment->name,
        'idnumber' => $appointment->cmidnumber);

    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademin'] = 0;
    $params['gradepass'] = 100;
    $params['grademax'] = 100;

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    $retcode = grade_update('mod/appointment', $appointment->course, 'mod', 'appointment',
        $appointment->id, 0, $grades, $params);
    return ($retcode === GRADE_UPDATE_OK);
}

/**
 * Delete grade item for given appointment
 *
 * @param stdClass $appointment object
 * @return bool
 */
function appointment_grade_item_delete($appointment) {
    $retcode = grade_update('mod/appointment', $appointment->course, 'mod', 'appointment',
        $appointment->id, 0, null, array('deleted' => 1));
    return ($retcode === GRADE_UPDATE_OK);
}

/**
 * Return number of attendees signed up to a appointment session
 *
 * @param int $sessionid
 * @param int $status MOD_APPOINTMENT_STATUS_* constant (optional)
 * @return integer
 */
function appointment_get_num_attendees($sessionid, $status = MOD_APPOINTMENT_STATUS_BOOKED) {
    global $CFG, $DB;

    $sql = 'SELECT count(ss.id)
        FROM
            {appointment_signups} su
        JOIN
            {appointment_signups_status} ss
        ON
            su.id = ss.signupid
        WHERE
            sessionid = ?
        AND
            ss.superceded=0
        AND
        ss.statuscode >= ?';

    // For the session, pick signups that haven't been superceded, or cancelled.
    return (int)$DB->count_records_sql($sql, array($sessionid, $status));
}

/**
 * Return all of a users' submissions to a appointment
 *
 * @param int $appointmentid
 * @param int $userid
 * @param bool $includecancellations
 * @return array|bool Submissions (false if no submissions)
 */
function appointment_get_user_submissions($appointmentid, $userid, $includecancellations = false) {
    global $CFG, $DB;

    $whereclause = "s.appointment = ? AND su.userid = ? AND ss.superceded != 1";
    $whereparams = array($appointmentid, $userid);

    // If not show cancelled, only show requested and up status'.
    if (!$includecancellations) {
        $whereclause .= ' AND ss.statuscode >= ? AND ss.statuscode < ?';
        $whereparams = array_merge($whereparams, array(MOD_APPOINTMENT_STATUS_REQUESTED, MOD_APPOINTMENT_STATUS_NO_SHOW));
    }

    // TODO fix mailedconfirmation, timegraded, timecancelled, etc.
    return $DB->get_records_sql("
        SELECT
            su.id,
            s.appointment,
            s.id as sessionid,
            su.userid,
            0 as mailedconfirmation,
            su.mailedreminder,
            ss.timecreated,
            ss.timecreated as timegraded,
            s.timemodified,
            0 as timecancelled,
            ss.statuscode
        FROM
            {appointment_sessions} s
        JOIN
            {appointment_signups} su
         ON su.sessionid = s.id
        JOIN
            {appointment_signups_status} ss
         ON su.id = ss.signupid
        WHERE
            {$whereclause}
        ORDER BY
            s.timecreated
    ", $whereparams);
}

/**
 * Cancel users' submission to a appointment session
 *
 * @param int $sessionid ID of the appointment_sessions record
 * @param int $userid ID of the user record
 * @param string $cancelreason Short justification for cancelling the signup
 * @return bool
 */
function appointment_user_cancel_submission($sessionid, $userid, $cancelreason = '') {
    global $DB;

    $signup = $DB->get_record('appointment_signups', array('sessionid' => $sessionid, 'userid' => $userid));
    if (!$signup) {
        return true; // Not signed up, nothing to do.
    }

    return appointment_update_signup_status($signup->id, MOD_APPOINTMENT_STATUS_USER_CANCELLED, $userid, $cancelreason);
}

/**
 * A list of actions in the logs that indicate view activity for participants
 */
function appointment_get_view_actions() {
    return array('view', 'view all');
}

/**
 * A list of actions in the logs that indicate post activity for participants
 */
function appointment_get_post_actions() {
    return array('cancel booking', 'signup');
}

/**
 * Return a small object with summary information about what a user
 * has done with a given particular instance of this module (for user
 * activity reports.)
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param mixed $mod
 * @param stdClass $appointment
 * @return stdClass
 */
function appointment_user_outline($course, $user, $mod, $appointment) {

    $result = new stdClass;
    $grade = appointment_get_grade($user->id, $course->id, $appointment->id);
    if ($grade->grade > 0) {
        $result = new stdClass;
        $result->info = get_string('gradenoun') . ': ' . $grade->grade;
        $result->time = $grade->dategraded;
    } else if ($submissions = appointment_get_user_submissions($appointment->id, $user->id)) {
        $result->info = get_string('usersignedup', 'appointment');
        $result->time = reset($submissions)->timecreated;
    } else {
        $result->info = get_string('usernotsignedup', 'appointment');
    }

    return $result;
}

/**
 * Print a detailed representation of what a user has done with a
 * given particular instance of this module (for user activity
 * reports).
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param mixed $mod
 * @param stdClass $appointment
 * @return bool
 */
function appointment_user_complete($course, $user, $mod, $appointment) {
    $grade = appointment_get_grade($user->id, $course->id, $appointment->id);
    if ($submissions = appointment_get_user_submissions($appointment->id, $user->id, true)) {
        print get_string('gradenoun') . ': ' . $grade->grade . html_writer::empty_tag('br');
        if ($grade->dategraded > 0) {
            $timegraded = trim(userdate($grade->dategraded, get_string('strftimedatetime')));
            print '(' . format_string($timegraded) . ')' . html_writer::empty_tag('br');
        }
        echo html_writer::empty_tag('br');

        foreach ($submissions as $submission) {
            $timesignedup = trim(userdate($submission->timecreated, get_string('strftimedatetime')));
            print get_string('usersignedupon', 'appointment', format_string($timesignedup)) .
                html_writer::empty_tag('br');

            if ($submission->timecancelled > 0) {
                $timecancelled = userdate($submission->timecancelled, get_string('strftimedatetime'));
                print get_string('usercancelledon', 'appointment', format_string($timecancelled)) .
                    html_writer::empty_tag('br');
            }
        }
    } else {
        print get_string('usernotsignedup', 'appointment');
    }

    return true;
}

/**
 * Add a link to the session to the courses calendar.
 *
 * @param stdClass $session Record from the appointment_sessions table
 * @param stdClass $appointment
 * @param string $calendartype Which calendar to add the event to (user, course, site)
 * @param int $userid Optional param for user calendars
 * @param string $eventtype Optional param for user calendar (booking/session)
 */
function appointment_add_session_to_calendar($session, $appointment, $calendartype = 'none', $userid = 0,
                                             $eventtype = 'session') {
    global $CFG, $DB, $PAGE;

    if (empty($session->sessiondates)) {
        return true; // Date unknown, can't add to calendar.
    }

    if (empty($appointment->showoncalendar) && empty($appointment->usercalentry)) {
        return true; // Appointment calendar settings prevent calendar.
    }

    $cm = get_coursemodule_from_instance('appointment', $appointment->id);

    $output = $PAGE->get_renderer('mod_appointment');

    $details = new \mod_appointment\output\session_details($session, \context_module::instance($cm->id));
    $description = $output->render_from_template('mod_appointment/session_details', $details->export_for_template($output));

    $fields = new \mod_appointment\output\session_customfields($session);
    $description .= $output->render_from_template('mod_appointment/session_customfields', $fields->export_for_template($output));

    $linkurl = new moodle_url('/mod/appointment/view.php', ['id' => $cm->id, 's' => $session->id]);
    $linktext = get_string('signupforthissession', 'appointment');

    if ($calendartype == 'site' && $appointment->showoncalendar == MOD_APPOINTMENT_CAL_SITE) {
        $courseid = SITEID;
        $modulename = '0';
        $description .= html_writer::link($linkurl, $linktext);
    } else if ($calendartype == 'course' && $appointment->showoncalendar == MOD_APPOINTMENT_CAL_COURSE) {
        $courseid = $appointment->course;
        $modulename = 'appointment';
        $description .= html_writer::link($linkurl, $linktext);
    } else if ($calendartype == 'user' && $appointment->usercalentry) {
        $courseid = 0;
        $modulename = '0';
        if ($eventtype == 'session') {
            $linkurl = new moodle_url("/mod/appointment/attendees.php", ['s' => $session->id]);
        }
        $description .= get_string("calendareventdescription{$eventtype}", 'appointment', $linkurl->out());
    } else {
        return true;
    }

    $shortname = $appointment->shortname;
    if (empty($shortname)) {
        $shortname = $appointment->name;
    }

    $result = true;
    foreach ($session->sessiondates as $date) {
        $newevent = new stdClass();
        $newevent->name = $shortname;
        $newevent->description = $description;
        $newevent->format = FORMAT_HTML;
        $newevent->courseid = $courseid;
        $newevent->groupid = 0;
        $newevent->userid = $userid;
        $newevent->uuid = "{$session->id}";
        $newevent->instance = $session->appointment;
        $newevent->modulename = $modulename;
        $newevent->eventtype = "appointment{$eventtype}";
        $newevent->type = 0; // CALENDAR_EVENT_TYPE_STANDARD: Only display on the calendar, not needed on the block_myoverview.
        $newevent->timestart = $date->timestart;
        $newevent->timeduration = $date->timefinish - $date->timestart;
        $newevent->visible = 1;
        $newevent->timemodified = time();

        if ($calendartype == 'user' && $eventtype == 'booking') {

            // Check for and Delete the 'created' calendar event to reduce multiple entries for the same event.
            $DB->delete_records_select('event', 'userid = ? AND instance = ? AND '
                . $DB->sql_compare_text('eventtype') . ' = ? AND ' . $DB->sql_compare_text('name') . ' = ?',
                array($userid, $session->appointment, 'appointmentsession', $shortname));
        }

        $result = $result && $DB->insert_record('event', $newevent);
    }

    return $result;
}

/**
 * Remove all entries in the course calendar which relate to this session.
 *
 * @param stdClass $session Record from the appointment_sessions table
 * @param int $courseid ID of the course - 0 for user event, SITEID for global event, 2+ for course event.
 * @param string $userid ID of the user. If not specified, will match any used ID.
 */
function appointment_remove_session_from_calendar($session, $courseid = 0, $userid = 0) {
    global $DB;

    $modulename = '0';         // User events and Site events.
    if ($courseid > SITEID) {  // Course event.
        $modulename = 'appointment';
    }
    if (empty($userid)) { // Match any UserID.
        $params = array($modulename, $session->appointment, $courseid, $session->id);
        return $DB->delete_records_select('event', "modulename = ? AND
                                                    instance = ? AND
                                                    courseid = ? AND
                                                    uuid = ?", $params);
    } else {
        $params = array($modulename, $session->appointment, $userid, $courseid, $session->id);
        return $DB->delete_records_select('event', "modulename = ? AND
                                                    instance = ? AND
                                                    userid = ? AND
                                                    courseid = ? AND
                                                    uuid = ?", $params);
    }
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every data event in the site is checked, else
 * only data events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance Appointment module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function appointment_refresh_events(int $courseid = 0, $instance = null, $cm = null): bool {
    global $DB, $CFG;

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('appointment', ['id' => $instance], '*', MUST_EXIST);
        }
        // Update any calendar entries.
        if ($sessions = appointment_get_sessions($instance->id)) {
            foreach ($sessions as $session) {
                appointment_update_calendar_entries($session, $instance);
            }
        }
        return true;
    }

    if ($courseid) {
        if (!$data = $DB->get_records('appointment', ['course' => $courseid])) {
            return false;
        }
    } else {
        if (!$data = $DB->get_records('appointment')) {
            return false;
        }
    }

    foreach ($data as $modinstance) {
        // Update any calendar entries.
        if ($sessions = appointment_get_sessions($modinstance->id)) {
            foreach ($sessions as $session) {
                appointment_update_calendar_entries($session, $modinstance);
            }
        }
    }
    return true;
}

/**
 * Update the date/time of events in the Moodle Calendar when a
 * session's dates are changed.
 *
 * @param stdClass $session Record from the appointment_sessions table
 * @param string $eventtype Type of event to update
 */
function appointment_update_user_calendar_events($session, $eventtype) {
    global $DB;

    $appointment = $DB->get_record('appointment', array('id' => $session->appointment));
    if (empty($appointment->usercalentry) || $appointment->usercalentry == 0) {
        return true;
    }

    $users = appointment_delete_user_calendar_events($session, $eventtype);

    // Add this session to these users' calendar.
    foreach ($users as $user) {
        appointment_add_session_to_calendar($session, $appointment, 'user', $user->userid, $eventtype);
    }

    return true;
}

/**
 * Delete all user level calendar events for a appointment session
 *
 * @param stdClass $session Record from the appointment_sessions table
 * @param string $eventtype Type of the event (booking or session)
 * @return array    $users      Array of users who had the event deleted
 */
function appointment_delete_user_calendar_events($session, $eventtype) {
    global $CFG, $DB;

    $whereclause = "modulename = '0' AND
                    eventtype = 'appointment$eventtype' AND
                    instance = ? AND
                    uuid = ?";

    $whereparams = array($session->appointment, $session->id);

    if ('session' == $eventtype) {
        $likestr = "%attendees.php?s={$session->id}%";
        $like = $DB->sql_like('description', '?');
        $whereclause .= " AND $like";

        $whereparams[] = $likestr;
    }

    // Users calendar.
    $users = $DB->get_records_sql("SELECT DISTINCT userid
        FROM {event}
        WHERE $whereclause", $whereparams);

    if ($users && count($users) > 0) {

        // Delete the existing events.
        $DB->delete_records_select('event', $whereclause, $whereparams);
    }

    return $users;
}

/**
 * Confirm that a user can be added to a session.
 *
 * @param stdClass $session Record from the appointment_sessions table
 * @param context_module|null $contextmodule
 * @return bool True if user can be added to session
 **/
function appointment_session_has_capacity($session, $contextmodule = false) {
    if (empty($session)) {
        return false;
    }

    $signupcount = appointment_get_num_attendees($session->id);
    if ($signupcount >= $session->capacity) {

        // If session is full, check if overbooking is allowed for this user.
        if (!$contextmodule || !has_capability('mod/appointment:overbook', $contextmodule)) {
            return false;
        }
    }

    return true;
}

/**
 * Get the number of available seats on all bookable sessions in given sessions.
 *
 * @param array $sessions
 * @param context_module $contextmodule
 * @return int
 */
function appointment_get_available_seats_count(array $sessions, context_module $contextmodule): int {
    $placesleft = 0;
    foreach ($sessions as $session) {
        if (\mod_appointment\permission::is_session_bookable($session, $contextmodule)) {
            $signupcount = appointment_get_num_attendees($session->id);
            $placesleft += $session->capacity - $signupcount;
        }
    }
    return max(0, $placesleft);
}

/**
 * Print the details of a session
 *
 * @param stdClass $session Record from appointment_sessions
 * @param boolean $showcapacity Show the capacity (true) or only the seats available (false)
 * @param boolean $calendaroutput Whether the output should be formatted for a calendar event
 * @param boolean $return Whether to return (true) the html or print it directly (true)
 * @param boolean $hidesignup Hide any messages relating to signing up
 */
function appointment_print_session($session, $showcapacity, $calendaroutput = false, $return = false, $hidesignup = false) {
    global $CFG, $DB, $PAGE;

    $table = new html_table();
    $table->caption = get_string('sessionsdetailstablesummary', 'appointment');
    $table->captionhide = true;
    $table->attributes['class'] = 'generaltable appointmentsession';
    $table->align = array('right', 'left');
    if ($calendaroutput) {
        $table->tablealign = 'left';
    }

    $strdatetime = str_replace(' ', '&nbsp;', get_string('sessiondatetime', 'appointment'));
    if (empty($session->sessiondates)) {
        $table->data[] = array($strdatetime, html_writer::tag('i', get_string('wait-listed', 'appointment')));
    } else {
        $html = '';
        foreach ($session->sessiondates as $date) {
            if (!empty($html)) {
                $html .= html_writer::empty_tag('br');
            }
            $timestart = userdate($date->timestart, get_string('strftimedatetime'));
            $timefinish = userdate($date->timefinish, get_string('strftimedatetime'));
            $html .= "$timestart &ndash; $timefinish";
        }
        $table->data[] = array($strdatetime, $html);
    }

    $signupcount = appointment_get_num_attendees($session->id);
    $placesleft = $session->capacity - $signupcount;

    if ($showcapacity) {
        if ($session->allowwaitlist) {
            $table->data[] = array(get_string('capacity', 'appointment'), $session->capacity .
                ' (' . strtolower(get_string('allowwaitlist', 'appointment')) . ')');
        } else {
            $table->data[] = array(get_string('capacity', 'appointment'), $session->capacity);
        }
    } else if (!$calendaroutput) {
        $table->data[] = array(get_string('seatsavailable', 'appointment'), max(0, $placesleft));
    }

    // Display requires approval notification.
    $appointment = $DB->get_record('appointment', array('id' => $session->appointment));

    if ($appointment->approvalreqd) {
        $table->data[] = array('', get_string('sessionrequiresmanagerapproval', 'appointment'));
    }

    // Display waitlist notification.
    if (!$hidesignup && $session->allowwaitlist && $placesleft < 1) {
        $table->data[] = array('', get_string('userwillbewaitlisted', 'appointment'));
    }

    if (!empty($session->details)) {
        $details = file_rewrite_pluginfile_urls($session->details, 'pluginfile.php', $PAGE->context->id,
            'mod_appointment', 'session', $session->id);
        $details = format_text($details, $session->detailsformat);
        $table->data[] = array(get_string('details', 'appointment'), $details);
    }

    // Display trainers.
    $trainerroles = appointment_get_trainer_roles();

    if ($trainerroles) {

        // Get trainers.
        $trainers = appointment_get_trainers($session->id);
        foreach ($trainerroles as $role => $rolename) {
            $rolename = $rolename->name;

            if (empty($trainers[$role])) {
                continue;
            }

            $trainernames = array();
            foreach ($trainers[$role] as $trainer) {
                $trainerurl = new moodle_url('/user/view.php', array('id' => $trainer->id));
                $trainernames[] = html_writer::link($trainerurl, fullname($trainer));
            }

            $table->data[] = array($rolename, implode(', ', $trainernames));
        }
    }

    return html_writer::table($table, $return);
}

/**
 * Return a cached copy of session custom fields.
 *
 * @return field_controller[]
 */
function appointment_get_session_customfields(): array {
    static $customfields = null;
    if (null == $customfields) {
        $handler = \mod_appointment\customfield\appointment_handler::create();
        $customfields = $handler->get_fields();
    }
    return $customfields;
}

/**
 * Update trainers
 *
 * @param int $sessionid
 * @param array $form trainers array extracted from form $form[$roleid][$userid] = $userid.
 * @return bool true on success
 */
function appointment_update_trainers($sessionid, $form) {
    global $DB;

    // If we recieved bad data.
    if (!is_array($form)) {
        return false;
    }

    // Load current trainers.
    $oldtrainers = appointment_get_trainers($sessionid);

    $transaction = $DB->start_delegated_transaction();

    // Loop through form data and add any new trainers.
    foreach ($form as $roleid => $trainers) {

        // Loop through trainers in this role.
        foreach ($trainers as $trainer) {

            if (!$trainer) {
                continue;
            }

            // If the trainer doesn't exist already, create it.
            if (!isset($oldtrainers[$roleid][$trainer])) {

                $newtrainer = new stdClass();
                $newtrainer->userid = $trainer;
                $newtrainer->roleid = $roleid;
                $newtrainer->sessionid = $sessionid;

                if (!$DB->insert_record('appointment_session_roles', $newtrainer)) {
                    $transaction->force_transaction_rollback();
                    throw new moodle_exception('error:couldnotaddtrainer', 'appointment');
                }
            } else {
                unset($oldtrainers[$roleid][$trainer]);
            }
        }
    }

    // Loop through what is left of old trainers, and remove (as they have been deselected).
    if ($oldtrainers) {
        foreach ($oldtrainers as $roleid => $trainers) {

            // If no trainers left.
            if (empty($trainers)) {
                continue;
            }

            // Delete any remaining trainers.
            foreach ($trainers as $trainer) {
                if (!$DB->delete_records('appointment_session_roles',
                        array('sessionid' => $sessionid, 'roleid' => $roleid, 'userid' => $trainer->id))) {
                    $transaction->force_transaction_rollback();
                    throw new moodle_exception('error:couldnotdeletetrainer', 'appointment');
                }
            }
        }
    }

    $transaction->allow_commit();

    return true;
}


/**
 * Return array of trainer roles configured for mod_appointment
 *
 * @return array|false
 */
function appointment_get_trainer_roles() {
    global $CFG, $DB;

    // Check that roles have been selected.
    if (empty($CFG->appointment_session_roles)) {
        return false;
    }

    // Parse roles.
    $cleanroles = clean_param($CFG->appointment_session_roles, PARAM_SEQUENCE);
    $roles = explode(',', $cleanroles);
    list($rolesql, $params) = $DB->get_in_or_equal($roles);

    // Load role names.
    $rolenames = $DB->get_records_sql("
        SELECT
            r.id,
            r.name
        FROM
            {role} r
        WHERE
            r.id {$rolesql}
        AND r.id <> 0
    ", $params);

    // Return roles and names.
    if (!$rolenames) {
        return array();
    }

    return $rolenames;
}


/**
 * Get all trainers associated with a session, optionally
 * restricted to a certain roleid
 *
 * If a roleid is not specified, will return a multi-dimensional
 * array keyed by roleids, with an array of the chosen roles
 * for each role
 *
 * @param  int $sessionid
 * @param  int $roleid (optional)
 * @return array|false
 */
function appointment_get_trainers($sessionid, $roleid = null) {
    global $CFG, $DB;

    $usernamefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $sql = "
        SELECT
            u.id,
            r.roleid,
            {$usernamefields}
        FROM
            {appointment_session_roles} r
        LEFT JOIN
            {user} u
         ON u.id = r.userid
        WHERE
            r.sessionid = ?
        ";
    $params = array($sessionid);

    if ($roleid) {
        $sql .= "AND r.roleid = ?";
        $params[] = $roleid;
    }

    $rs = $DB->get_recordset_sql($sql, $params);
    $return = array();
    foreach ($rs as $record) {

        // Create new array for this role.
        if (!isset($return[$record->roleid])) {
            $return[$record->roleid] = array();
        }
        $return[$record->roleid][$record->id] = $record;
    }
    $rs->close();

    // If we are only after one roleid.
    if ($roleid) {
        if (empty($return[$roleid])) {
            return false;
        }
        return $return[$roleid];
    }

    // If we are after all roles.
    if (empty($return)) {
        return false;
    }

    return $return;
}

/**
 * Determines whether an activity requires the user to have a manager (either for
 * manager approval or to send notices to the manager)
 *
 * @param  stdClass $appointment A database fieldset object for the appointment activity
 * @return boolean whether a person needs a manager to sign up for that activity
 */
function appointment_manager_needed($appointment) {
    return $appointment->approvalreqd
        || $appointment->confirmationinstrmngr
        || $appointment->reminderinstrmngr
        || $appointment->cancellationinstrmngr;
}

/**
 * Get session cancellations
 *
 * @param   int $sessionid
 * @return  array
 */
function appointment_get_cancellations($sessionid) {
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $usernamefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $instatus = array(MOD_APPOINTMENT_STATUS_BOOKED, MOD_APPOINTMENT_STATUS_WAITLISTED);
    list($insql, $inparams) = $DB->get_in_or_equal($instatus);

    // Nasty SQL follows:
    // Load currently cancelled users, include most recent booked/waitlisted time also.
    $sql = "
            SELECT
                u.id,
                {$usernamefields},
                su.id AS signupid,
                MAX(ss.timecreated) AS timesignedup,
                c.timecreated AS timecancelled,
                " . $DB->sql_compare_text('c.note', 250) . " AS cancelreason
            FROM
                {appointment_signups} su
            JOIN
                {user} u
             ON u.id = su.userid
            JOIN
                {appointment_signups_status} c
             ON su.id = c.signupid
            AND c.statuscode = ?
            AND c.superceded = 0
            LEFT JOIN
                {appointment_signups_status} ss
             ON su.id = ss.signupid
             AND ss.statuscode $insql
            AND ss.superceded = 1
            WHERE
                su.sessionid = ?
            GROUP BY
                u.id, su.id,
                {$usernamefields},
                c.timecreated,
                " . $DB->sql_compare_text('c.note', 250) . "
            ORDER BY
                {$fullname},
                c.timecreated
    ";
    $params = array_merge(array(MOD_APPOINTMENT_STATUS_USER_CANCELLED), $inparams);
    $params[] = $sessionid;
    return $DB->get_records_sql($sql, $params);
}


/**
 * Get session unapproved requests
 *
 * @param   int $sessionid
 * @return  array
 */
function appointment_get_requests($sessionid) {
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $usernamefields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;

    $params = array($sessionid, MOD_APPOINTMENT_STATUS_REQUESTED);

    $sql = "SELECT u.id, su.id AS signupid, {$usernamefields},
                   ss.timecreated AS timerequested
              FROM {appointment_signups} su
              JOIN {appointment_signups_status} ss ON su.id=ss.signupid
              JOIN {user} u ON u.id = su.userid
             WHERE su.sessionid = ? AND ss.superceded != 1 AND ss.statuscode = ?
          ORDER BY $fullname, ss.timecreated";

    return $DB->get_records_sql($sql, $params);
}


/**
 * Get session declined requests
 *
 * @param   int $sessionid
 * @return  array
 */
function appointment_get_declines($sessionid) {
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $usernamefields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;

    $params = array($sessionid, MOD_APPOINTMENT_STATUS_DECLINED);

    $sql = "SELECT u.id, su.id AS signupid, {$usernamefields},
                   ss.timecreated AS timerequested
              FROM {appointment_signups} su
              JOIN {appointment_signups_status} ss ON su.id=ss.signupid
              JOIN {user} u ON u.id = su.userid
             WHERE su.sessionid = ? AND ss.superceded != 1 AND ss.statuscode = ?
          ORDER BY $fullname, ss.timecreated";
    return $DB->get_records_sql($sql, $params);
}


/**
 * Returns all other caps used in module
 *
 * @return array
 */
function appointment_get_extra_capabilities() {
    return array('moodle/site:viewfullnames');
}


/**
 * Module supports
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function appointment_supports($feature) {
    switch ($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

/**
 * Appointment assignment candidates
 *
 * @package    mod_appointment
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appointment_candidate_selector extends user_selector_base {
    /** @var int */
    protected $sessionid;

    /**
     * appointment_candidate_selector constructor.
     *
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options) {
        $this->sessionid = $options['sessionid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     *
     * @param mixed $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        // All non-signed up system user.
        list($wherecondition, $params) = $this->search_sql($search, 'u');

        $session = appointment_get_session($this->sessionid);
        $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);
        $context = context_course::instance($appointment->course);
        [$enrolsql, $enrolparams] = get_enrolled_sql($context);
        [$sort, $sortparams] = users_order_by_sql();

        $fields = 'SELECT DISTINCT u.id AS userid, ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(DISTINCT u.id)';
        $sql = "
                  FROM {user} u
                 WHERE $wherecondition
                   AND u.id IN ($enrolsql)
                   AND u.id NOT IN
                       (
                       SELECT u2.id
                         FROM {appointment_signups} s
                         JOIN {appointment_signups_status} ss ON s.id = ss.signupid
                         JOIN {user} u2 ON u2.id = s.userid
                        WHERE s.sessionid = :sessid
                          AND ss.statuscode >= :statuswaitlisted
                          AND ss.superceded = 0
                       )
               ";
        $order = " ORDER BY $sort";
        $params = array_merge($params, $enrolparams,
            array(
                'sessid' => $this->sessionid,
                'statuswaitlisted' => MOD_APPOINTMENT_STATUS_WAITLISTED,
            ));

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > 100) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, $params + $sortparams);

        // Filter users list based on availability restrictions.
        $instance = get_coursemodule_from_instance('appointment', $session->appointment, $appointment->course, false, MUST_EXIST);
        $info = new \core_availability\info_module(cm_info::create($instance));
        $availableusers = $info->filter_user_list($availableusers);

        if (empty($availableusers)) {
            return array();
        }

        $groupname = get_string('potentialusers', 'role', count($availableusers));

        return array($groupname => $availableusers);
    }

    /**
     * get_options
     *
     * @return array
     */
    protected function get_options() {
        $options = parent::get_options();
        $options['sessionid'] = $this->sessionid;
        $options['file'] = 'mod/appointment/lib.php';
        return $options;
    }
}

/**
 * Appointment assignment candidates
 *
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appointment_existing_selector extends user_selector_base {
    /** @var int */
    protected $sessionid;

    /**
     * appointment_existing_selector constructor.
     *
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options) {
        $this->sessionid = $options['sessionid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     *
     * @param <type> $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $whereparams) = $this->search_sql($search, 'u');

        $fields = 'SELECT ' . $this->required_fields_sql('u');
        $fields .= ', su.id AS submissionid, f.id AS appointmentid,
            f.course, ss.grade, ss.statuscode, sign.timecreated';
        $countfields = 'SELECT COUNT(1)';
        $sql = "
            FROM
                {appointment} f
            JOIN
                {appointment_sessions} s
             ON s.appointment = f.id
            JOIN
                {appointment_signups} su
             ON s.id = su.sessionid
            JOIN
                {appointment_signups_status} ss
             ON su.id = ss.signupid
            LEFT JOIN
                (
                SELECT
                    ss.signupid,
                    MAX(ss.timecreated) AS timecreated
                FROM
                    {appointment_signups_status} ss
                INNER JOIN
                    {appointment_signups} s
                 ON s.id = ss.signupid
                AND s.sessionid = :sessid1
                WHERE
                    ss.statuscode IN (:statusbooked, :statuswaitlisted)
                GROUP BY
                    ss.signupid
                ) sign
             ON su.id = sign.signupid
            JOIN
                {user} u
             ON u.id = su.userid
            WHERE
                $wherecondition
            AND s.id = :sessid2
            AND ss.superceded != 1
            AND ss.statuscode >= :statusapproved
        ";
        $order = " ORDER BY sign.timecreated ASC, ss.timecreated ASC";
        $params = array('sessid1' => $this->sessionid, 'statusbooked' => MOD_APPOINTMENT_STATUS_BOOKED,
            'statuswaitlisted' => MOD_APPOINTMENT_STATUS_WAITLISTED);
        $params = array_merge($params, $whereparams);
        $params['sessid2'] = $this->sessionid;
        $params['statusapproved'] = MOD_APPOINTMENT_STATUS_APPROVED;
        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > 100) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, $params);
        if (empty($availableusers)) {
            return array();
        }

        $groupname = get_string('existingusers', 'role', count($availableusers));
        return array($groupname => $availableusers);
    }

    /**
     * get_options
     *
     * @return array
     */
    protected function get_options() {
        $options = parent::get_options();
        $options['sessionid'] = $this->sessionid;
        $options['file'] = 'mod/appointment/lib.php';
        return $options;
    }
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param settings_navigation $settings
 * @param navigation_node $node
 * @return void
 */
function appointment_extend_settings_navigation($settings, $node) {
    global $PAGE;

    if (\mod_appointment\permission::can_add_instance($PAGE->context->get_course_context())) {
        // We want to add these new nodes after the Edit settings node, and before the
        // Locally assigned roles node. Of course, both of those are controlled by capabilities.
        $keys = $node->get_children_key_list();
        $beforekey = null;
        $i = array_search('modedit', $keys);
        if ($i === false and array_key_exists(0, $keys)) {
            $beforekey = $keys[0];
        } else if (array_key_exists($i + 1, $keys)) {
            $beforekey = $keys[$i + 1];
        }

        $url = new moodle_url('/mod/appointment/messages.php', ['cmid' => $PAGE->cm->id]);
        $messagesnode = navigation_node::create(get_string('customisednotifications', 'mod_appointment'),
                $url, navigation_node::TYPE_SETTING, null, 'mod_appointment_messages');
        $node->add_node($messagesnode, $beforekey);
    }
}

/**
 * Serves session details and other files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool|null false if file not found, does not return anything if found - just send the file
 */
function mod_appointment_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;

    require_once($CFG->libdir . '/filelib.php');

    // Serving files added to session details.
    if ($filearea === 'session') {
        if (!\mod_appointment\permission::can_view_appointment($context)) {
            return false;
        }

        $relativepath = implode('/', $args);
        $fullpath = '/' . $context->id . '/mod_appointment/session/' . $relativepath;

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        send_stored_file($file, 0, 0, $forcedownload, $options);
    }

    return false;
}

/**
 * Returns appointment session information (status, isbooked, isstarted, isfull)
 *
 * @param stdClass $session
 * @return array
 */
function appointment_get_session_info(stdClass $session): array {
    $isbookedsession = false;
    $sessionstarted = false;
    $sessionfull = false;
    $timenow = time();
    $status = get_string('bookingopen', 'appointment');
    $signupcount = appointment_get_num_attendees($session->id, MOD_APPOINTMENT_STATUS_APPROVED);

    $hassessiondates = !empty($session->sessiondates);
    if ($hassessiondates && appointment_has_session_started($session, $timenow)
        && appointment_is_session_in_progress($session, $timenow)) {
        $status = get_string('sessioninprogress', 'appointment');
        $sessionstarted = true;
    } else if ($hassessiondates && appointment_has_session_started($session, $timenow)) {
        $status = get_string('sessionfinished', 'appointment');
        $sessionstarted = true;
    } else if (isset($session->usersubmission->statuscode)) {
        $signupstatus = appointment_get_status($session->usersubmission->statuscode);
        $status = get_string('status_' . $signupstatus, 'appointment');
        $isbookedsession = true;
    } else if ($signupcount >= $session->capacity) {
        $status = get_string('bookingfull', 'appointment');
        $sessionfull = true;
    }

    return [
        'status' => $status,
        'isbooked' => $isbookedsession,
        'isstarted' => $sessionstarted,
        'isfull' => $sessionfull,
    ];
}

/**
 * Add a get_coursemodule_info function in case any appointment type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info|false An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function appointment_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionbooked';
    if (!$appointment = $DB->get_record('appointment', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $appointment->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('choice', $appointment, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionbooked'] = $appointment->completionbooked;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_appointment_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }
    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionbooked':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionbooked', 'appointment');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * Checks if a user has any booked sessions in an appointment.
 *
 * @param int $userid
 * @param int $appointmentid
 * @return bool
 */
function user_has_booked_sessions(int $userid, int $appointmentid): bool {
    global $DB;

    $sql = "SELECT COUNT(DISTINCT(ass.id)) FROM {appointment_signups_status} ass
                JOIN {appointment_signups} asu ON asu.id = ass.signupid AND asu.userid = :userid
                JOIN {appointment_sessions} ase ON ase.id = asu.sessionid AND ase.appointment = :appointmentid
                WHERE ass.statuscode = :status AND ass.superceded = 0";
    $params = ['userid' => $userid, 'appointmentid' => $appointmentid, 'status' => MOD_APPOINTMENT_STATUS_BOOKED];
    $result = $DB->count_records_sql($sql, $params);

    return !empty($result);
}
