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
 * mod_appointment data generator.
 *
 * @package     mod_appointment
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

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
     * Creates a new appointment.
     *
     * @param array|\stdClass $record data for module being generated.
     * @param null|array $options general options for course module.
     * @return \stdClass record from appointment table
     */
    public function create_instance($record = null, array $options = null) {
        $record = (object)(array)$record;

        $defaultsettings = [
            'requestsubject' => get_string('setting:defaultrequestsubjectdefault', 'appointment'),
            'requestmessage' => get_string('setting:defaultrequestmessagedefault', 'appointment'),
            'requestinstrmngr' => get_string('setting:defaultrequestinstrmngrdefault', 'appointment'),
            'confirmationsubject' => get_string('setting:defaultconfirmationsubjectdefault', 'appointment'),
            'confirmationmessage' => get_string('setting:defaultconfirmationmessagedefault', 'appointment'),
            'confirmationinstrmngr' => get_string('setting:defaultconfirmationinstrmngrdefault', 'appointment'),
            'remindersubject' => get_string('setting:defaultremindersubjectdefault', 'appointment'),
            'remindermessage' => get_string('setting:defaultremindermessagedefault', 'appointment'),
            'reminderinstrmngr' => get_string('setting:defaultreminderinstrmngrdefault', 'appointment'),
            'reminderperiod' => 2,
            'waitlistedsubject' => get_string('setting:defaultwaitlistedsubjectdefault', 'appointment'),
            'waitlistedmessage' => get_string('setting:defaultwaitlistedmessagedefault', 'appointment'),
            'cancellationsubject' => get_string('setting:defaultcancellationsubjectdefault', 'appointment'),
            'cancellationmessage' => get_string('setting:defaultcancellationmessagedefault', 'appointment'),
            'cancellationinstrmngr' => get_string('setting:defaultcancellationinstrmngrdefault', 'appointment'),
        ];

        foreach ($defaultsettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        return parent::create_instance($record, (array)$options);
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
            'duration' => 1,
        ];

        foreach ($defaultsettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        $sessionid = appointment_add_session($record, $sessiondates);
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
}
