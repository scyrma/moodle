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
 * Backup
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

/**
 * Class backup_appointment_activity_structure_step.
 *
 * @package    mod_appointment
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @author     Stacey Walker <stacey@catalyst-eu.net>
 * @author     Alastair Munro <alastair.munro@totaralms.com>
 * @author     Aaron Barnes <aaron.barnes@totaralms.com>
 * @author     Francois Marier <francois@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_appointment_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure for the appointment activity
     *
     * @return void
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $appointment = new backup_nested_element('appointment', array('id'), array(
            'name', 'intro', 'introformat', 'thirdparty', 'thirdpartywaitlist', 'display',
            'timecreated', 'timemodified', 'shortname', 'showoncalendar', 'usercalentry',
            'confirmationsubject', 'confirmationinstrmngr', 'confirmationmessage', 'confirmationmessageformat',
            'updatesubject', 'updatemessage', 'updatemessageformat',
            'waitlistedsubject', 'waitlistedmessage', 'waitlistedmessageformat',
            'cancellationsubject', 'cancellationinstrmngr', 'cancellationmessage', 'cancellationmessageformat',
            'remindersubject', 'reminderinstrmngr', 'remindermessage', 'reminderperiod', 'remindermessageformat',
            'requestsubject', 'requestinstrmngr', 'requestmessage',
            'approvalreqd', 'allowcancellationsdefault', 'completionbooked', 'completionattend'));

        $sessions = new backup_nested_element('sessions');

        $session = new backup_nested_element('session', array('id'), array(
            'appointment', 'capacity', 'allowwaitlist', 'details', 'detailsformat',
            'allowcancellations', 'timecreated', 'timemodified'));

        $signups = new backup_nested_element('signups');

        $signup = new backup_nested_element('signup', array('id'), array(
            'sessionid', 'userid', 'mailedreminder'));

        $signupsstatus = new backup_nested_element('signups_status');

        $signupstatus = new backup_nested_element('signup_status', array('id'), array(
            'signupid', 'statuscode', 'superceded', 'grade', 'note', 'advice', 'createdby', 'timecreated'));

        $sessiondata = new backup_nested_element('session_data');

        // May need to replace first item 'data' with better value.
        $sessiondataelement = new backup_nested_element('data', array('id'), array(
            'fieldid', 'sessionid', 'data'));

        $sessionsdates = new backup_nested_element('sessions_dates');

        $sessionsdate = new backup_nested_element('sessions_date', array('id'), array(
            'sessionid', 'timestart', 'timefinish'));

        // Build the tree.
        $appointment->add_child($sessions);
        $sessions->add_child($session);

        $session->add_child($signups);
        $signups->add_child($signup);

        $signup->add_child($signupsstatus);
        $signupsstatus->add_child($signupstatus);

        $session->add_child($sessiondata);
        $sessiondata->add_child($sessiondataelement);

        $session->add_child($sessionsdates);
        $sessionsdates->add_child($sessionsdate);

        // Define sources.
        $appointment->set_source_table('appointment', array('id' => backup::VAR_ACTIVITYID));

        $session->set_source_table('appointment_sessions', array('appointment' => backup::VAR_PARENTID));

        $sessionsdate->set_source_table('appointment_sessions_dates', array('sessionid' => backup::VAR_PARENTID));

        if ($userinfo) {
            $signup->set_source_table('appointment_signups', array('sessionid' => backup::VAR_PARENTID));

            $signupstatus->set_source_table('appointment_signups_status', array('signupid' => backup::VAR_PARENTID));
        }

        // Define id annotations.
        $signup->annotate_ids('user', 'userid');

        // Define file annotations.
        $appointment->annotate_files('mod_appointment', 'intro', null);
        $session->annotate_files('mod_appointment', 'session', 'id');

        // Return the root element (appointment), wrapped into standard activity structure.
        return $this->prepare_activity_structure($appointment);
    }
}
