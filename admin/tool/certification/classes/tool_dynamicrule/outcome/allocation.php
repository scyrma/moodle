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

namespace tool_certification\tool_dynamicrule\outcome;

use coding_exception;
use MoodleQuickForm;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_certification\local\helpers\dynamic_rules;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * The backend class for certification allocation outcome
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class allocation extends outcome_base {
    /** @var certification Certification */
    private certification $certification;
    /** @var array Certification params */
    private array $certificationparams = [];

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

        // Start date.
        $startdatestr = get_string('startdate', 'tool_certification');
        $selectdatestr = get_string('selectdate', 'tool_certification');
        $keepdefaultsstr = get_string('keepcertificationdefaults', 'tool_certification');
        $choices = [
            constants::DATE_NONE => $keepdefaultsstr,
            constants::DATE_ABSOLUTE => $selectdatestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'startdatetype', '', $choices);
        $group[] =& $mform->createElement('date_time_selector', 'startdateabsolute', '');
        $mform->addGroup($group, 'startdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('startdateabsolute', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->addHelpButton('startdateformgroup', 'startdate', 'tool_certification');

        // Whether we want to modify current allocation if user is already allocated.
        $actions = [
            self::DONT_MODIFY_ALLOCATION => get_string('outcomeallocationdontmodify', 'tool_certification'),
            self::UNSUSPEND_AND_CHANGE_DATES => get_string('outcomeallocationunsuspendchangedate', 'tool_certification'),
            self::UNSUSPEND_AND_KEEP_DATES => get_string('outcomeallocationunsuspend', 'tool_certification')
        ];
        $mform->addElement('select', 'action', get_string('outcomeallocationsuspendedusers', 'tool_certification'), $actions);
        $mform->addRule('action', null, 'required', null, 'client');
        $mform->setType('action', PARAM_INT);
        $mform->addHelpButton('action', 'outcomeallocationsuspendedusers', 'tool_certification');
        $mform->setDefault('action', self::UNSUSPEND_AND_KEEP_DATES);
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
     * Helper function called before outcome is applied to user.
     */
    public function setup_for_applying(): void {
        $params = [
            'certificationid' => $this->get_certificationid(),
            'allocationtype' => constants::ALLOCATION_DYNAMIC,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        // If startdate is not the default add it to parameters.
        if ($startdate = $this->get_startdate()) {
            $params['startdatelocked'] = constants::DATE_LOCKED;
            $params['startdate'] = $startdate;
        }
        $this->certificationparams = $params;
        $this->certification = $this->get_certification();
    }

    /**
     * Apply this outcome to a given user.
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $certificationuser = certification_user::get_record(['userid' => $user->id,
            'certificationid' => $this->certification->get('id')]);

        if (empty($certificationuser)) {
            // We need to allocate the user.
            $params = $this->certificationparams;
            $params['userid'] = $user->id;
            api::allocate_user($this->certification, (object) $params);
        } else {
            // Allocation already exists.
            if ((int) $certificationuser->get('status') !== constants::STATUS_OVERRIDE_SUSPENDED ||
                $certificationuser->get('allocationtype') !== constants::ALLOCATION_DYNAMIC) {
                return;
            }

            $data = $certificationuser->to_record();
            $data->status = constants::STATUS_OVERRIDE_DEFAULT;

            switch ($this->get_outcomeaction()) {
                case self::UNSUSPEND_AND_KEEP_DATES:
                    api::update_certification_user_dates_and_status($certificationuser, $data);
                    break;
                case self::UNSUSPEND_AND_CHANGE_DATES:
                    $startdate = $this->get_startdate();
                    if ($startdate) {
                        $data->startdate = $startdate;
                        $data->startdatelocked = 1;
                    }

                    // Change allocation time to recalculate the user allocation dates as if the user was allocated now.
                    $certificationuser->set('timecreated', time());
                    $certificationuser->update();

                    api::update_certification_user_dates_and_status($certificationuser, $data);
                    break;
            }
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

        $description = get_string('outcomeallocationdescription', 'tool_certification', $fullname);

        switch ($this->get_outcomedatetype()) {
            case constants::DATE_NONE:
                $description = get_string('outcomeallocationdescdate', 'tool_certification', [
                    'certificationname' => $fullname,
                    'startdatestr' => get_string('outcomeallocationdesckeepstartdate', 'tool_certification'),
                    'suspendedusers' => $this->get_action_suspended_users_description(),
                ]);
                break;
            case constants::DATE_ABSOLUTE:
                $startdatestr = get_string('outcomeallocationdescstartdate', 'tool_certification',
                    ['startdate' => userdate($this->get_outcomedate(), get_string('strftimedatetimeshort'))]);
                $description = get_string('outcomeallocationdescdate', 'tool_certification',
                    [
                        'certificationname' => $fullname,
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
                $description = get_string('outcomeallocationdontmodify', 'tool_certification');
                break;
            case self::UNSUSPEND_AND_KEEP_DATES:
                $description = get_string('outcomeallocationdesckeepdate', 'tool_certification');
                break;
            case self::UNSUSPEND_AND_CHANGE_DATES:
                $description = get_string('outcomeallocationdescsuspendchangedate', 'tool_certification');
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
     * Add certificationid outcome field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('tool_certification', $this->get_certificationid());
    }

    /**
     * Get certificationid outcome field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['certificationid'] = $importer->get_mapping('tool_certification', $this->get_certificationid(),
                IGNORE_MISSING) ?? 0;
        // Set default "action" value for old allocation actions in case this value is not set.
        $configdata['action'] = $this->get_outcomeaction();

        $this->update_configdata($configdata);
    }
}
