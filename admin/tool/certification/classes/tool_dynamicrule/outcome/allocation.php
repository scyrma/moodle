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
 * This file contains the backend class for certification allocation outcome.
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\tool_dynamicrule\outcome;

use MoodleQuickForm;
use tool_certification\api;
use tool_certification\constants;
use tool_certification\local\helpers\dynamic_rules;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for certification allocation outcome
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocation extends outcome_base {

    /**
     * Adds outcome's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
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
        return get_string('outcomeallocation', 'tool_certification');
    }

    /**
     * Apply this outcome to a given list of users
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
        $certification = $this->get_certification();
        $fullname = format_string($certification->get('fullname'), true, ['escape' => false]);

        return get_string('outcomeallocationdescription', 'tool_certification', $fullname);
    }

    /**
     * Allocates the given users on the configured certification
     *
     * @param array $users
     */
    private function allocate_users(array $users): void {
        $params = [
            'certificationid' => $this->get_certificationid(),
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        $certification = $this->get_certification();
        foreach ($users as $user) {
            $params['userid'] = $user->id;
            api::allocate_user($certification, (object) $params);
        }
    }
}
