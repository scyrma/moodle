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

use html_writer;
use MoodleQuickForm;
use stdClass;
use tool_certification\api;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_certification\local\helpers\dynamic_rules;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * The backend class for deallocation outcome
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
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

        // Whether we want to deallocate or suspend the user, if is already allocated.
        $actions = [
            self::DEALLOCATE_USER => get_string('outcomedeallocate', 'tool_certification'),
            self::SUSPEND_USER => get_string('outcomedeallocatesuspend', 'tool_certification')
        ];
        $mform->addElement('select', 'action', get_string('action', 'tool_certification'), $actions);
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
        return get_string('outcomedeallocation', 'tool_certification');
    }

    /**
     * Apply this outcome to a given user.
     *
     * @param stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(stdClass $user): void {
        $certificationid = $this->get_certificationid();

        switch ((int) $this->get_configdata()['action']) {
            case self::DEALLOCATE_USER:
                // Deallocate user from certification.
                api::deallocate_user($certificationid, $user->id);
                break;
            case self::SUSPEND_USER:
                // Suspend certification user allocation only if allocation type is dynamic.
                $certificationuser = certification_user::get_record(['certificationid' => $certificationid, 'userid' => $user->id,
                    'allocationtype' => constants::ALLOCATION_DYNAMIC]);
                if ($certificationuser) {
                    $data = $certificationuser->to_record();
                    $data->status = constants::STATUS_OVERRIDE_SUSPENDED;
                    $data->timesuspended = time();
                    api::update_certification_user_dates_and_status($certificationuser, $data);
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
        $certification = $this->get_certification();
        $fullname = format_string($certification->get('fullname'), true, ['escape' => false]);

        if ((int) $this->get_configdata()['action'] === self::DEALLOCATE_USER) {
            return get_string('outcomedeallocationdescription', 'tool_certification', $fullname);
        } else {
            return get_string('outcomedeallocationdescriptionsuspend', 'tool_certification', $fullname);
        }
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

        $this->update_configdata($configdata);
    }
}
