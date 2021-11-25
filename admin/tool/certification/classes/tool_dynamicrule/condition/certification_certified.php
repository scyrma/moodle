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
 * Class for certification certified status dynamic rules' condition.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_dynamicrule\condition;

use MoodleQuickForm;
use tool_certification\certification;
use tool_certification\constants;
use tool_certification\local\helpers\dynamic_rules;
use tool_certification\permission;
use tool_certification\api;
use tool_dynamicrule\outcome_base;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class for certification certified status dynamic rules' condition.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_certified extends condition_base {

    /**
     * Return the configured criteria
     *
     * @return string
     */
    protected function get_criteria(): string {
        return $this->get_configdata()['criteria'] ?? self::CRITERIA_ALL;
    }

    /**
     * Return the configured certificationid as array
     *
     * @return array
     */
    protected function get_certificationids(): array {
        $certificationid = !empty($this->get_configdata()['certificationid']) ? $this->get_configdata()['certificationid'] : [];

        return (array) $certificationid;
    }

    /**
     * Return the first configured certificationid
     *
     * @return int
     */
    protected function get_certificationid(): int {
        $certificationids = $this->get_certificationids();
        return $certificationids ? (int)$certificationids[0] : 0;
    }

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditioncertificationcertified', 'tool_certification');
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $certificationid = (array) $configdata['certificationid'] ?: [];
        $validpcertifications = dynamic_rules::get_certifications_if_valid($certificationid, $this->get_rule());
        $certificationscannotedit = array_filter($validpcertifications, function($certification) {
            return permission::can_edit_dynamicrule_condition($certification, $this->get_rule());
        });

        return count($certificationscannotedit) === count($certificationid);
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        global $OUTPUT;

        $options = dynamic_rules::get_selector_options();
        if ($this->is_configuration_valid()) {
            $options['valuehtmlcallback'] = dynamic_rules::get_certification_fullname_callback();
        }

        // Enable multiple selection (autocomplete) field.
        $options['multiple'] = true;

        $selectstr = get_string('selectcertificationcondition', 'tool_certification');
        $missingcertstr = get_string('missingcertification', 'tool_certification');
        $mform->addElement('autocomplete', 'certificationid', $selectstr, [], $options);
        $mform->addRule('certificationid', $missingcertstr, 'required', null, 'client');
        $mform->addHelpButton('certificationid', 'selectcertificationcondition', 'tool_certification');
        $mform->setType('certificationid', PARAM_INT);

        $groupcriteria = [];
        $groupcriteria[] = $mform->createElement('radio', 'criteria', get_string('criteriaall', 'tool_certification'),
            '', self::CRITERIA_ALL);

        $groupcriteria[] = $mform->createElement('radio', 'criteria', get_string('criteriaany', 'tool_certification'),
            $OUTPUT->help_icon('criteriaany', 'tool_certification'), self::CRITERIA_ANY);

        $groupcriteria[] = $mform->createElement('radio', 'criteria', get_string('criteriaeach', 'tool_certification'),
            $OUTPUT->help_icon('criteriaeach', 'tool_certification'). get_string('conditioncriterianotavailableyet',
                'tool_dynamicrule'),
            self::CRITERIA_EACH, ['disabled' => 'disabled', 'class' => 'dimmed_text']);

        $mform->addGroup($groupcriteria, 'criteria_group',
            get_string('conditioncriteria', 'tool_dynamicrule'),
            \html_writer::div('', 'w-100'),
            false);

        $mform->setType('criteria', PARAM_ALPHANUM);
        $mform->setDefault('criteria', self::CRITERIA_ALL);

        $datelabelstr = get_string('certifieddateisonorafter', 'tool_certification');
        $enablestr = get_string('enable');
        $group = [];
        $group[] =& $mform->createElement('date_time_selector', 'conditiondate', '');
        $group[] =& $mform->createElement('advcheckbox', 'conditiondateenabled', null, $enablestr, 1, [0, 1]);
        $mform->addGroup($group, 'dateformgroup', $datelabelstr, ' ', false);
        $mform->disabledIf('conditiondate[day]', 'conditiondateenabled');
        $mform->disabledIf('conditiondate[month]', 'conditiondateenabled');
        $mform->disabledIf('conditiondate[year]', 'conditiondateenabled');
        $mform->setDefault('conditiondateenabled', '1');

        $group = [];
        $group[] = $mform->createElement('radio', 'withrecert',
            get_string('conditioncertificationcertifiedstatusonly', 'tool_certification'),
            $OUTPUT->help_icon('conditioncertificationcertifiedstatusonly', 'tool_certification'),
            0);
        $group[] = $mform->createElement('radio', 'withrecert',
            get_string('conditioncertificationcertifiedonrecert', 'tool_certification'),
            $OUTPUT->help_icon('conditioncertificationcertifiedonrecert', 'tool_certification'),
            1);
        $mform->addGroup($group, 'withrecertgroup', '', ' ', false);
        $mform->setDefault('withrecert', 0);

    }

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];

        $data['certificationid'] = (array) $data['certificationid'];

        $validpcertifications = dynamic_rules::get_certifications_if_valid($data['certificationid'], $this->get_rule());

        if (count($validpcertifications) !== count($data['certificationid'])) {
            $errors['certificationid'] = get_string('errorinvalidcertification', 'tool_certification');
            return $errors;
        }

        $certificationscannotedit = array_filter($validpcertifications, function($certification) {
            return !permission::can_edit_dynamicrule_condition($certification, $this->get_rule());
        });

        if (!empty($certificationscannotedit)) {
            // We need to check permission here as listed certification might be viewable to user,
            // but user does not have capability to view allocated users.
            $errors['certificationid'] = get_string('errornopermissionviewallocatedusers', 'tool_certification');
        }

        return $errors;
    }

    /**
     * Helps to build SQL to retrieve certifications that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $certificationids = $this->get_certificationids();

        $status = constants::STATUS_CERTIFIED;
        $conditiondate = null;
        $certificationwheres = [];
        $certificationparams = [];
        // If enabled check that user is certified on or after chosen date.
        if ($this->get_conditiondateenabled()) {
            $conditiondate = $this->get_conditiondate();
        }

        foreach ($certificationids as $certificationid) {
            [$where, $params] = api::get_certifications_with_criteria_conditions($certificationid, $status, $conditiondate,
                $this->get_with_recert());

            $certificationwheres[] = $where;
            $certificationparams = array_merge($certificationparams, $params);

        }

        $separator = $this->get_criteria() === self::CRITERIA_ALL ? ' AND ' : ' OR ';
        $certificationwheres = implode($separator, $certificationwheres);

        return ['', $certificationwheres, $certificationparams];

    }

    /**
     * Check if certification still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        $certificationids = $this->get_certificationids();

        $validcertifications = dynamic_rules::get_certifications_if_valid($certificationids, $this->get_rule());

        return !empty($certificationids) && count($validcertifications) === count($certificationids);
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;

        [$whereincertification, $certificationids] = $DB->get_in_or_equal($this->get_certificationids(), SQL_PARAMS_NAMED);

        $sql = "SELECT fullname
                  FROM {tool_certification}
                 WHERE id " . $whereincertification . "
                 ORDER BY fullname";
        $names = $DB->get_fieldset_sql($sql, $certificationids);

        $certificationnames = implode("', '", array_map(function(string $name) {
            return format_string($name, true, ['escape' => false]);
        }, $names));

        $stringdescription = 'conditioncertificationstatusdescription';

        $options = ['fullname' => $certificationnames, 'status' => get_string('certified', 'tool_certification')];

        $withdatefragment = '';
        if ($this->get_conditiondateenabled()) {
            $options['conditiondate'] = userdate($this->get_conditiondate(), get_string('strftimedatefullshort'));
            $stringdescription = 'conditioncertificationcertifieddescription';
            $withdatefragment = 'withdate';
        }

        if (count($names) > 1) {
            // Many certifications.
            $identifier = $stringdescription . $this->get_criteria() . $withdatefragment;
        } else {
            // One certification.
            $identifier = $stringdescription . $withdatefragment;
        }

        if ($this->get_with_recert()) {
            $postfix = get_string('conditioncertificationcertifieddescriptiononrecert', 'tool_certification');
        } else {
            $postfix = get_string('conditioncertificationcertifieddescriptionstatusonly', 'tool_certification');
        }

        return get_string($identifier, 'tool_certification', $options). "<br>" . $postfix;
    }

    /**
     * Return the configured conditiondate
     *
     * @return int
     */
    private function get_conditiondate(): int {
        return $this->get_configdata()['conditiondate'];
    }

    /**
     * Return the configured conditionenabled
     *
     * @return bool
     */
    private function get_conditiondateenabled(): bool {
        return $this->get_configdata()['conditiondateenabled'] ?? false;
    }

    /**
     * Return the configured withrecert - execute on every re-certification
     *
     * @return bool
     */
    private function get_with_recert(): bool {
        return !empty($this->get_configdata()['withrecert']);
    }

    /**
     * Return a list of valid attributes for given instance
     *
     * @return array Perhaps something similar to persistent definition, e.g. name, type, description
     */
    protected function get_config_attributes(): array {
        return [];
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
            'programcompletedcourses' => new \lang_string('displaycompletedcourses', 'tool_program'),
            'programcompletiondate' => new \lang_string('displaycompletiondate', 'tool_program'),
            'certificationid' => new \lang_string('displaycertificationid', 'tool_certification'),
            'certificationname' => new \lang_string('displaycertificationname', 'tool_certification'),
            'certificationdate' => new \lang_string('displaycertificationdate', 'tool_certification'),
            'certificationexpirydate' => new \lang_string('displayexpirydate', 'tool_certification'),
            'expirydatetimestamp' => new \lang_string('displayexpirydatetimestamp', 'tool_certification'),
            'certificationreopen' => new \lang_string('displaycertificationreopen', 'tool_certification'),
            'certificationprogramname' => new \lang_string('displaycertificationprogramname', 'tool_certification'),
            'recertificationprogramname' => new \lang_string('displayrecertificationprogramname', 'tool_certification'),
            'certificationgraceperiodend' => new \lang_string('displaygraceperiodend', 'tool_certification'),
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

        $certificationid = $this->get_certificationids();

        if (!$users || count($certificationid) > 1) {
            return [];
        }

        $certification = new certification($certificationid[0]);
        $placeholders = $this->get_placeholders();
        // Filter keys and make sure only those available on this condition are used.
        $keys = array_filter($keys, function($k) use ($placeholders) {
            return array_key_exists($k, $placeholders);
        });

        foreach ($users as $user) {
            $data[$user->id] = $this->get_key_values($keys, $certification, $user->id, true);
        }

        return $data;
    }

    /**
     * Event subscription.
     *
     * @return string eventname.
     */
    public function get_event_subscription(): string {
        return '\tool_certification\event\certification_completion_created';
    }

    /**
     * Should this rule be processed in scheduled tasks
     *
     * @return bool
     */
    public function is_scheduled_task(): bool {
        // This rule should always be processed in scheduled tasks despite the event subscription.
        // The scheduled task can be removed when we listen to all events/dates:
        // - certification expired,
        // - re-certification started (for the conditions where "withrecert==1"),
        // - certification is revoked.
        // In order to watch the certification expiry date we need to make sure first that every
        // change in the tool_certification_compltion actually triggers an event
        // (including the situation when the certification/program defaults are changed
        // and each user dates are re-calculated).
        return true;
    }

    /**
     * Add certificationid condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        foreach ($this->get_certificationids() as $certificationid) {
            $exporter->add_mapping('tool_certification', $certificationid);
        }
    }

    /**
     * Get certificationid condition field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();

        $configdata['certificationid'] = [];
        foreach ($this->get_certificationids() as $certificationid) {
            $configdata['certificationid'][] = $importer->get_mapping('tool_certification', $certificationid,
                    IGNORE_MISSING) ?? 0;
        }

        $this->update_configdata($configdata);
    }
}
