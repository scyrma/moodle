<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

namespace tool_program\tool_dynamicrule\outcome;

use html_writer;
use MoodleQuickForm;
use tool_program\api;
use tool_program\constants;
use tool_program\local\helpers\dynamic_rules;
use tool_program\persistent\program_user;

/**
 * The backend class for program deallocation outcome
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class deallocation extends outcome_base {

    /**
     * Adds outcome's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        // Warning msg.
        $warningstr = get_string('dynamicrulewarningdeallocation', 'tool_program');
        $mform->addElement('html', html_writer::div($warningstr, 'alert alert-warning'));

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

        // Wheter we want to deallocate or suspend the user, if is already allocated.
        $actions = [
            self::DEALLOCATE_USER => get_string('outcomedeallocate', 'tool_program'),
            self::SUSPEND_USER => get_string('outcomedeallocatesuspend', 'tool_program')
        ];
        $mform->addElement('select', 'action', get_string('action', 'tool_program'), $actions);
        $mform->addRule('action', null, 'required', null, 'client');
        $mform->setType('action', PARAM_INT);
        $mform->setDefault('action', self::DEALLOCATE_USER);
    }

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomedeallocation', 'tool_program');
    }

    /**
     * Apply this outcome to a given user.
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $programid = $this->get_programid();

        switch ((int) $this->get_configdata()['action']) {
            case self::DEALLOCATE_USER:
                // Deallocate user from program.
                api::deallocate_user($programid, $user->id, 0, constants::ALLOCATION_DYNAMIC);
                break;
            case self::SUSPEND_USER:
                // Suspend program user allocation only if allocation type is dynamic.
                $programuser = program_user::get_record(['programid' => $programid, 'userid' => $user->id, 'certificationid' => 0,
                    'allocationtype' => constants::ALLOCATION_DYNAMIC]);
                if ($programuser) {
                    $data = $programuser->to_record();
                    $data->status = constants::STATUS_OVERRIDE_SUSPENDED;
                    $data->timesuspended = time();
                    api::update_program_user_dates_and_status($programuser, $data);
                }
                break;
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

        if ((int) $this->get_configdata()['action'] === self::DEALLOCATE_USER) {
            return get_string('outcomedeallocationdescription', 'tool_program', $fullname);
        } else {
            return get_string('outcomedeallocationdescriptionsuspend', 'tool_program', $fullname);
        }
    }
}
