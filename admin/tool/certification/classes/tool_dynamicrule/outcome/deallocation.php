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
 * This file contains the backend class for certification deallocation outcome.
 *
 * @package    tool_certification
 * @copyright  2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\tool_dynamicrule\outcome;

use html_writer;
use MoodleQuickForm;
use tool_certification\api;
use tool_certification\local\helpers\dynamic_rules;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for deallocation outcome
 *
 * @package    tool_certification
 * @copyright  2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deallocation extends outcome_base {

    /**
     * Adds outcome's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        // Warning msg.
        $warningstr = get_string('dynamicrulewarningdeallocation', 'tool_certification');
        $mform->addElement('html', html_writer::div($warningstr, 'alert alert-warning'));

        $options = dynamic_rules::get_selector_options();
        if ($this->is_configuration_valid()) {
            $options['valuehtmlcallback'] = dynamic_rules::get_certification_fullname_callback();
        }
        $selectstr = get_string('selectcertificationoutcome', 'tool_certification');
        $missingcertstr = get_string('missingcertification', 'tool_certification');
        $mform->addElement('autocomplete', 'certificationid', $selectstr, [], $options);
        $mform->addRule('certificationid', $missingcertstr, 'required', null, 'client');
        $mform->addHelpButton('certificationid', 'selectcertificationoutcome', 'tool_certification');
        $mform->setType('certificationid', PARAM_INT);
    }

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomedeallocation', 'tool_certification');
    }

    /**
     * Apply this outcome to a given list of users
     *
     * @param array $users The users objects to apply the outcome to
     */
    public function apply_to_users(array $users): void {
        if (!empty($users)) {
            $this->deallocate_users($users);
        }
    }

    /**
     * Deallocates the given users from the configured certification
     *
     * @param array $users
     */
    public function deallocate_users(array $users): void {
        $certificationid = $this->get_certificationid();
        foreach ($users as $user) {
            api::deallocate_user($certificationid, $user->id);
        }
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        $certification = $this->get_certification();
        $fullname = format_string($certification->get('fullname'), true, ['escape' => false]);

        return get_string('outcomedeallocationdescription', 'tool_certification', $fullname);
    }
}
