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

/**
 * This file contains the backend class for user_not_allocated condition.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_dynamicrule\condition;

use core_reportbuilder\local\helpers\database;
use tool_certification\certification;
use tool_certification\constants;
use tool_certification\event\user_allocation_created;
use tool_certification\event\user_allocation_deleted;
use tool_certification\event\user_allocation_updated;
use tool_certification\local\helpers\dynamic_rules;
use tool_dynamicrule\outcome_base;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * The backend class for user_not_allocated condition
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_not_allocated extends condition_base {

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionusernotallocated', 'tool_certification');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform): void {
        $options = dynamic_rules::get_selector_options();
        if ($this->is_configuration_valid()) {
            $options['valuehtmlcallback'] = dynamic_rules::get_certification_fullname_callback();
        }
        $selectstr = get_string('selectcertificationcondition', 'tool_certification');
        $missingcertstr = get_string('missingcertification', 'tool_certification');
        $mform->addElement('autocomplete', 'certificationid', $selectstr, [], $options);
        $mform->addHelpButton('certificationid', 'selectcertificationcondition', 'tool_certification');
        $mform->addRule('certificationid', $missingcertstr, 'required', null, 'client');
        $mform->setType('certificationid', PARAM_INT);
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {

        $u = database::generate_alias();
        $pu = database::generate_alias();
        $certificationid = database::generate_param_name();

        $join = "LEFT JOIN {tool_certification_users} {$u}
                        ON ({$u}.userid = u.id AND {$u}.certificationid = :{$certificationid})
                 LEFT JOIN {tool_program_users} {$pu}
                        ON {$pu}.programid={$u}.currentprogramid AND {$pu}.userid=u.id
                           AND {$pu}.certificationid={$u}.certificationid";
        $now1 = database::generate_param_name();
        $where = "{$u}.id IS NULL ".
            " OR {$u}.status = ". constants::STATUS_OVERRIDE_SUSPENDED .
            " OR {$pu}.startdate > :{$now1}";

        $params = [$certificationid => $this->get_certificationid(), $now1 => time()];

        return [$join, $where, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $certification = new certification($this->get_certificationid());
        $fullname = format_string($certification->get('fullname'), true, ['escape' => false]);

        return get_string('conditionusernotallocateddescription', 'tool_certification', $fullname);
    }

    /**
     * Add certificationid condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('tool_certification', $this->get_certificationid());
    }

    /**
     * Get certificationid condition field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['certificationid'] = $importer->get_mapping('tool_certification', $this->get_certificationid(),
                IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }

    /**
     * Available data for outcomes
     *
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_available_data_for_outcome(outcome_base $calleroutcome): array {
        return $this->get_placeholders();
    }

    /**
     * Available placeholders for this condition
     *
     * @return \lang_string[]
     */
    private function get_placeholders(): array {
        return [
            'programid' => new \lang_string('displayprogramid', 'tool_program'),
            'programname' => new \lang_string('displayprogramname', 'tool_program'),
            'certificationid' => new \lang_string('displaycertificationid', 'tool_certification'),
            'certificationname' => new \lang_string('displaycertificationname', 'tool_certification'),
        ];
    }

    /**
     * Data for the outcomes
     *
     * @param array $keys
     * @param array $users array of user objects
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_data_for_outcome(array $keys, array $users, outcome_base $calleroutcome): array {
        $data = [];
        if (!$users) {
            return [];
        }

        $certification = new certification($this->get_certificationid());
        $placeholders = $this->get_placeholders();
        // Filter keys and make sure only those available on this condition are used.
        $keys = array_filter($keys, function($k) use ($placeholders) {
            return array_key_exists($k, $placeholders);
        });

        foreach ($users as $user) {
            $data[$user->id] = $this->get_key_values($keys, $certification, $user->id, false);
        }

        return $data;
    }

    /**
     * Immediately evaluate the rule when user allocation has been created
     *
     * @return string[]
     */
    public function get_event_subscription() {
        return [user_allocation_created::class, user_allocation_deleted::class, user_allocation_updated::class];
    }

    /**
     * Always evaluate the condition in the scheduled task (for now)
     *
     * The condition also must be evaluated when start or end date is changed and there are not
     * enough events to be able to change it to event-only condition or use datewatch.
     *
     * @return bool
     */
    public function is_scheduled_task(): bool {
        // TODO WP-2923 remove when all events are implemented and we can use datewatch and event subscriptions.
        return true;
    }
}
