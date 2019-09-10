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
 * This file contains the backend class for program allocation outcome.
 *
 * @package    tool_program
 * @copyright  2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\tool_dynamicrule\outcome;

use MoodleQuickForm;
use tool_program\api;
use tool_program\constants;
use tool_program\local\helpers\dynamic_rules;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for program allocation outcome
 *
 * @package    tool_program
 * @copyright  2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocation extends outcome_base {

    /**
     * Adds outcome's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        // Program select (autocomplete) field.
        $selectprogramstr = get_string('selectprogramoutcome', 'tool_program');
        $missingprogramstr = get_string('missingprogram', 'tool_program');
        $options = dynamic_rules::get_selector_options();
        if ($this->is_configuration_valid()) {
            $options['valuehtmlcallback'] = dynamic_rules::get_program_fullname_callback();
        }
        $mform->addElement('autocomplete', 'programid', $selectprogramstr, [], $options);
        $mform->addRule('programid', $missingprogramstr, 'required', null, 'client');
        $mform->addHelpButton('programid', 'selectprogramoutcome', 'tool_program');
        $mform->setType('programid', PARAM_INT);
    }

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomeallocation', 'tool_program');
    }

    /**
     * Apply this outcome on a given list of users
     *
     * @param array $users The users objects to apply the outcome to
     */
    public function apply_to_users(array $users): void {
        if (!empty($users)) {
            $this->allocate_users($users);
        }
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        $program = $this->get_program();
        $fullname = format_string($program->get('fullname'), true, ['escape' => false]);

        return get_string('outcomeallocationdescription', 'tool_program', $fullname);
    }

    /**
     * Allocate users on the configured program
     *
     * @param array $users
     */
    private function allocate_users(array $users): void {
        $params = [
            'programid' => $this->get_programid(),
            'certificationid' => 0,
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
        ];
        $program = $this->get_program();
        foreach ($users as $user) {
            $params['userid'] = $user->id;
            api::allocate_user($program, (object) $params);
        }
    }
}
