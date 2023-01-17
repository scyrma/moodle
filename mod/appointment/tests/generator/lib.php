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
 * mod_appointment data generator.
 *
 * @package     mod_appointment
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * mod_appointment data generator class
 *
 * @package     mod_appointment
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_appointment_generator extends testing_module_generator {

    /**
     * @var int keep track of how many session have been created.
     */
    protected $sessionscount = 0;

    /**
     * To be called from data reset code only, do not use in tests.
     * @return void
     */
    public function reset() {
        $this->sessionscount = 0;
        parent::reset();
    }

    /**
     * Creates a new session.
     *
     * @param array|\stdClass $record
     * @param array $traineruserids
     * @param array $sessiondates
     * @return \stdClass session record object
     */
    public function create_session($record = null, array $traineruserids = [], array $sessiondates = []) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/appointment/lib.php');
        $record = (object)(array)$record;

        if (empty($record->appointment)) {
            throw new coding_exception('Appointment session generator requires $record->appointment');
        }

        if (empty($record->details)) {
            $record->details = "Session{$this->sessionscount}";
        }

        $defaultsettings = [
            'capacity' => 10,
            'allowwaitlist' => false,
            'details' => '',
            'detailsformat' => 1,
        ];

        foreach ($defaultsettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        if (!$sessiondates) {
            foreach ($record as $key => $value) {
                if (preg_match('/^timestart([\d]+)$/', $key, $matches)) {
                    $key2 = "timefinish{$matches[1]}";
                    $sessiondates[] = (object)['timestart' => $value, 'timefinish' => $record->$key2 ?? ($value + HOURSECS)];
                    unset($record->$key);
                    unset($record->$key2);
                }
            }
        }

        $cm = get_coursemodule_from_instance('appointment', $record->appointment);
        $sessionid = appointment_add_session($record, $sessiondates, \context_module::instance($cm->id));
        $this->sessionscount++;

        // Add trainers.
        if (count($traineruserids)) {
            // Determine roleids allowed to use for trainer user.
            if (!$trainerroles = appointment_get_trainer_roles()) {
                throw new coding_exception('Specifying trainers requires $CFG->appointment_session_roles to be defined');
            }
            $trainerroleids = array_map(function($trainerrole) {
                return $trainerrole->id;
            }, $trainerroles);

            $appointment = $DB->get_record('appointment', array('id' => $record->appointment), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('appointment', $appointment->id, $appointment->course);
            $context = \context_module::instance($cm->id);

            // Loop through each trainer, verify they have required role and build the list of trainers.
            $formdata = [];
            foreach ($traineruserids as $traineruserid) {
                $roleids = array_map(function($role) {
                    return $role->roleid;
                }, get_user_roles($context, $traineruserid));
                if (!$requiredroleids = array_intersect($roleids, $trainerroleids)) {
                    throw new coding_exception("Specified trainer userid $traineruserid does not have role " .
                        "listed in \$CFG->appointment_session_roles");
                }
                $roleid = array_shift($requiredroleids);
                $formdata[$roleid][$traineruserid] = $traineruserid;
            }
            appointment_update_trainers($sessionid, $formdata);
        }

        return appointment_get_session($sessionid);
    }

    /**
     * Sign up a user to the session
     *
     * @param array|stdClass $record required: 'sessionid', 'userid'; optional: 'status'
     * @return void
     */
    public function create_signup($record) {
        global $DB;
        $record = (object)(array)$record;
        if (empty($record->sessionid)) {
            throw new coding_exception('Appointment signup generator requires $record->sessionid');
        }
        if (empty($record->userid)) {
            throw new coding_exception('Appointment signup generator requires $record->userid');
        }
        if (!isset($record->status) || ''.$record->status === '') {
            $record->status = MOD_APPOINTMENT_STATUS_BOOKED;
        }
        if (isset($record->status) && !is_numeric($record->status)) {
            if (defined('MOD_APPOINTMENT_STATUS_'.strtoupper($record->status))) {
                $record->status = constant('MOD_APPOINTMENT_STATUS_'.strtoupper($record->status));
            } else {
                throw new coding_exception('Unknown sign-up status '.$record->status);
            }
        }
        $session = appointment_get_session($record->sessionid, MUST_EXIST);
        $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);
        $course = get_course($appointment->course);
        appointment_user_signup($session, $appointment, $course, null,
            $record->status, $record->userid);
    }

    /**
     * Marks user attendance
     *
     * @param array|stdClass $record required: 'sessionid', 'userid'; optional: 'status' -
     *        default value for status is 'fully_attended', also available - 'no_show', 'partially_attended'
     * @return void
     */
    public function create_attendance($record) {
        global $DB;
        $record = (object)(array)$record;
        if (empty($record->sessionid)) {
            throw new coding_exception('Appointment attendance generator requires $record->sessionid');
        }
        if (empty($record->userid)) {
            throw new coding_exception('Appointment attendance generator requires $record->userid');
        }
        if (!isset($record->status) || ''.$record->status === '') {
            $record->status = MOD_APPOINTMENT_STATUS_FULLY_ATTENDED;
        }
        if (isset($record->status) && !is_numeric($record->status)) {
            if (defined('MOD_APPOINTMENT_STATUS_'.strtoupper($record->status))) {
                $record->status = constant('MOD_APPOINTMENT_STATUS_'.strtoupper($record->status));
            } else {
                throw new coding_exception('Unknown sign-up status '.$record->status);
            }
        }
        $signup = $DB->get_record('appointment_signups',
            ['sessionid' => $record->sessionid, 'userid' => $record->userid], 'id', MUST_EXIST);
        $key = "submissionid_".$signup->id;
        $data = (object)[
            's' => $record->sessionid,
            $key => $record->status,
        ];
        appointment_take_attendance($data);
    }
}
