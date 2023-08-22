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
 * Structure step to restore one appointment activity.
 *
 * @package    mod_appointment
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @author     Stacey Walker <stacey@catalyst-eu.net>
 * @author     Alastair Munro <alastair.munro@totaralms.com>
 * @author     Aaron Barnes <aaron.barnes@totaralms.com>
 * @author     Francois Marier <francois@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_appointment_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the structure of the restore workflow.
     *
     * @return \restore_path_element $structure
     */
    protected function define_structure() {
        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('appointment', '/activity/appointment');
        $paths[] = new restore_path_element('appointment_session', '/activity/appointment/sessions/session');
        $paths[] = new restore_path_element('appointment_sessions_dates',
            '/activity/appointment/sessions/session/sessions_dates/sessions_date');
        if ($userinfo) {
            $paths[] = new restore_path_element('appointment_signup', '/activity/appointment/sessions/session/signups/signup');
            $paths[] = new restore_path_element('appointment_signups_status',
                '/activity/appointment/sessions/session/signups/signup/signups_status/signup_status');
            $paths[] = new restore_path_element('appointment_session_roles',
                '/activity/appointment/sessions/session/session_roles/session_role');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process appointment
     *
     * @param stdClass $data The data in object form
     * @return void
     */
    protected function process_appointment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Insert the appointment record.
        $newitemid = $DB->insert_record('appointment', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process appointment session
     *
     * @param stdClass $data The data in object form
     * @return void
     */
    protected function process_appointment_session($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->appointment = $this->get_new_parentid('appointment');

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the entry record.
        $newitemid = $DB->insert_record('appointment_sessions', $data);
        $this->set_mapping('appointment_session', $oldid, $newitemid, true); // Childs and files by itemname.
    }

    /**
     * Process appointment signup
     *
     * @param stdClass $data The data in object form
     * @return void
     */
    protected function process_appointment_signup($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('appointment_session');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // Insert the entry record.
        $newitemid = $DB->insert_record('appointment_signups', $data);
        $this->set_mapping('appointment_signup', $oldid, $newitemid, true); // Childs and files by itemname.
    }

    /**
     * Process appointment signups status
     *
     * @param stdClass $data The data in object form
     * @return void
     */
    protected function process_appointment_signups_status($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->signupid = $this->get_new_parentid('appointment_signup');

        $data->timecreated = $this->apply_date_offset($data->timecreated);

        // Insert the entry record.
        $newitemid = $DB->insert_record('appointment_signups_status', $data);
    }

    /**
     * Process appointment session roles
     *
     * @param stdClass $data The data in object form
     * @return void
     */
    protected function process_appointment_session_roles($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('appointment_session');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->roleid = $this->get_mappingid('role', $data->roleid);

        // Insert the entry record.
        $newitemid = $DB->insert_record('appointment_session_roles', $data);
    }

    /**
     * Process appointment sessions dates
     *
     * @param stdClass $data The data in object form
     * @return void
     */
    protected function process_appointment_sessions_dates($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('appointment_session');

        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);

        // Insert the entry record.
        $newitemid = $DB->insert_record('appointment_sessions_dates', $data);
    }

    /**
     * Once the database tables have been fully restored, restore the files
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_appointment', 'intro', null);
        $this->add_related_files('mod_appointment', 'session', 'appointment_session');
    }
}
