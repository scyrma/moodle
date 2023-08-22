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

use MoodleQuickForm;
use tool_program\api;
use tool_program\constants;
use tool_program\local\helpers\dynamic_rules;
use tool_program\persistent\program_user;

/**
 * The backend class for program allocation outcome
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class allocation extends outcome_base {
    /** @var program program */
    private $program;
    /** @var array Program params */
    private $programparams = [];

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

        // Start date.
        $startdatestr = get_string('startdate', 'tool_program');
        $selectdatestr = get_string('datetypeabsolute', 'tool_program');
        $keepdefaultsstr = get_string('keepprogramdefaults', 'tool_program');
        $choices = [
            constants::DATE_NONE => $keepdefaultsstr,
            constants::DATE_ABSOLUTE => $selectdatestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'startdatetype', '', $choices);
        $group[] =& $mform->createElement('date_time_selector', 'startdateabsolute', '');
        $mform->addGroup($group, 'startdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('startdateabsolute', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->addHelpButton('startdateformgroup', 'startdate', 'tool_program');
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
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        $program = $this->get_program();
        $fullname = format_string($program->get('fullname'), true, ['escape' => false]);

        switch ($this->get_outcomedatetype()) {
            case constants::DATE_NONE:
                $description = get_string('outcomeallocationdescription', 'tool_program', $fullname);
                break;
            case constants::DATE_ABSOLUTE:
                $options['programname'] = $fullname;
                $options['startdate'] = userdate($this->get_outcomedate(), get_string('strftimedatetimeshort'));
                $description = get_string('outcomeallocationdescriptionwithdate', 'tool_program', $options);
                break;
        }

        return $description;
    }

    /**
     * Return the configured outcomedate
     *
     * @return int
     */
    private function get_outcomedate(): int {
        return $this->get_configdata()['startdateabsolute'];
    }

    /**
     * Return the configured outcomedatetype
     *
     * @return int
     */
    private function get_outcomedatetype(): int {
        // We default to DATE_NONE in case there is an existing old rule that doesn't have start date set.
        return $this->get_configdata()['startdatetype'] ?? constants::DATE_NONE;
    }

    /**
     * Helper function called before outcome is applied to user.
     */
    public function setup_for_applying(): void {
        $params = [
            'programid' => $this->get_programid(),
            'certificationid' => 0,
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
        ];
        // If startdate is not the default add it to parameters.
        if ($startdate = $this->get_startdate()) {
            $params['startdatelocked'] = constants::DATE_LOCKED;
            $params['startdate'] = $startdate;
        }
        $this->programparams = $params;
        $this->program = $this->get_program();
    }

    /**
     * Apply this outcome to a given user.
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        if (empty(program_user::get_records([
            'userid' => $user->id,
            'programid' => $this->get_programid(),
            'certificationid' => 0
        ]))) {
            $params = $this->programparams;
            $params['userid'] = $user->id;
            api::allocate_user($this->program, (object) $params);
        }
    }
}
