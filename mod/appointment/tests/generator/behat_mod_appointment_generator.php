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
 * mod_appointment behat generator class
 *
 * @package     mod_appointment
 * @author      2022 Marina Glancy
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_appointment_generator extends behat_generator_base {

    /**
     * @var mod_appointment_generator
     */
    protected $componentdatagenerator;

    /**
     * Get a list of the entities that can be created for this component.
     *
     * See {@see behat_core_generator::get_creatable_entities} for an example.
     *
     * @return array entity name => information about how to generate.
     */
    protected function get_creatable_entities(): array {
        return [
            'sessions' => [
                'singular' => 'session',
                'datagenerator' => 'session',
                'required' => ['appointment'],
                'switchids' => ['appointment' => 'appointment'],
            ],
            'signups' => [
                'singular' => 'signup',
                'datagenerator' => 'signup',
                'required' => ['session', 'user'],
                'switchids' => ['session' => 'sessionid', 'user' => 'userid'],
            ],
            'attendance' => [
                'datagenerator' => 'attendance',
                'required' => ['session', 'user'],
                'switchids' => ['session' => 'sessionid', 'user' => 'userid'],
            ],
        ];
    }

    /**
     * Look up the id of a appointment from its name.
     *
     * @param string $appointmentname the appointment name, for example 'Test appointment'.
     * @return int corresponding id.
     */
    protected function get_appointment_id(string $appointmentname): int {
        $cm = $this->get_cm_by_activity_name('appointment', $appointmentname);
        return $cm->instance;
    }

    /**
     * Look up the id of a appointment from its name.
     *
     * @param string $sessionname the appointment name, for example 'Test appointment'.
     * @return int corresponding id.
     */
    protected function get_session_id(string $sessionname): int {
        global $DB;
        $sql = "SELECT id FROM {appointment_sessions}
                 WHERE " . $DB->sql_compare_text('details') . " = " . $DB->sql_compare_text(':details');
        $session = $DB->get_record_sql($sql, ['details' => $sessionname], MUST_EXIST);
        return $session->id;
    }

}
