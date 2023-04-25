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

use coding_exception;
use MoodleQuickForm;
use tool_program\api;
use tool_program\constants;
use tool_program\local\helpers\dynamic_rules;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_wp\importer_base;

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
    private program $program;
    /** @var array Program params */
    private array $programparams = [];

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

        // Whether we want to modify current allocation if user is already allocated.
        $actions = [
            self::DONT_MODIFY_ALLOCATION => get_string('outcomeallocationdontmodify', 'tool_program'),
            self::UNSUSPEND_AND_CHANGE_DATES => get_string('outcomeallocationunsuspendchangedate', 'tool_program'),
            self::UNSUSPEND_AND_KEEP_DATES => get_string('outcomeallocationunsuspend', 'tool_program')
        ];
        $mform->addElement('select', 'action', get_string('outcomeallocationsuspendedusers', 'tool_program'), $actions);
        $mform->addRule('action', null, 'required', null, 'client');
        $mform->setType('action', PARAM_INT);
        $mform->addHelpButton('action', 'outcomeallocationsuspendedusers', 'tool_program');
        $mform->setDefault('action', self::UNSUSPEND_AND_KEEP_DATES);
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
                $description = get_string('outcomeallocationdescdate', 'tool_program', [
                    'programname' => $fullname,
                    'startdatestr' => get_string('outcomeallocationdesckeepstartdate', 'tool_program'),
                    'suspendedusers' => $this->get_action_suspended_users_description(),
                ]);
                break;
            case constants::DATE_ABSOLUTE:
                $startdatestr = get_string('outcomeallocationdescstartdate', 'tool_program',
                    ['startdate' => userdate($this->get_outcomedate(), get_string('strftimedatetimeshort'))]);
                $description = get_string('outcomeallocationdescdate', 'tool_program', [
                    'programname' => $fullname,
                    'startdatestr' => $startdatestr,
                    'suspendedusers' => $this->get_action_suspended_users_description(),
                ]);
                break;
        }

        return $description;
    }

    /**
     *  Get description for suspended users action
     *
     * @return string
     * @throws coding_exception
     */
    private function get_action_suspended_users_description(): string {
        switch ($this->get_outcomeaction()) {
            case self::DONT_MODIFY_ALLOCATION:
                $description = get_string('outcomeallocationdontmodify', 'tool_program');
                break;
            case self::UNSUSPEND_AND_KEEP_DATES:
                $description = get_string('outcomeallocationdesckeepdate', 'tool_program');
                break;
            case self::UNSUSPEND_AND_CHANGE_DATES:
                $description = get_string('outcomeallocationdescsuspendchangedate', 'tool_program');
                break;
            default:
                throw new coding_exception('invalidoutcomeaction');
        }

        return $description;
    }

    /**
     * Return the configured outcomeaction
     *
     * @return int
     */
    private function get_outcomeaction(): int {
        // We default to DONT_MODIFY_ALLOCATION in case there is an existing old rule that doesn't have action set.
        return $this->get_configdata()['action'] ?? self::DONT_MODIFY_ALLOCATION;
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

        $programuser = program_user::get_record([
            'userid' => $user->id,
            'programid' => $this->get_programid(),
            'certificationid' => 0
        ]);

        if (empty($programuser)) {
            // We need to allocate the user.
            $params = $this->programparams;
            $params['userid'] = $user->id;
            api::allocate_user($this->program, (object) $params);
        } else {
            // Allocation already exists.
            if ((int)$programuser->get('status') !== constants::STATUS_OVERRIDE_SUSPENDED ||
                $programuser->get('allocationtype') !== constants::ALLOCATION_DYNAMIC) {
                return;
            }

            $data = $programuser->to_record();
            $data->status = constants::STATUS_OVERRIDE_DEFAULT;

            switch ($this->get_outcomeaction()) {
                case self::UNSUSPEND_AND_KEEP_DATES:
                    api::update_program_user_dates_and_status($programuser, $data);
                    break;
                case self::UNSUSPEND_AND_CHANGE_DATES:
                    $startdate = $this->get_startdate();
                    if ($startdate) {
                        $data->startdate = $startdate;
                        $data->startdatelocked = 1;
                    }

                    // Change allocation time to recalculate the user allocation dates as if the user was allocated now.
                    $programuser->set('timecreated', time());
                    $programuser->update();

                    api::update_program_user_dates_and_status($programuser, $data);
                    break;
            }
        }
    }

    /**
     * Set default values during import.
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        parent::get_importer_mapping($importer);
        $configdata = $this->get_configdata();
        if (!isset($configdata['action'])) {
            // Set default "action" value for old allocation actions.
            $configdata['action'] = self::DONT_MODIFY_ALLOCATION;
            $this->update_configdata($configdata);
        }
    }
}
