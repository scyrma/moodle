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
 * Class for recertification period started dynamic rules' condition.
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
use tool_dynamicrule\api as dynamicruleapi;
use tool_dynamicrule\outcome_base;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * Class for recertification period started dynamic rules' condition.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class recertification_period_started extends condition_base {
    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionrecertificationstarted', 'tool_certification');
    }

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
        $selectstr = get_string('selectcertificationcondition', 'tool_certification');
        $missingcertstr = get_string('missingcertification', 'tool_certification');
        $mform->addElement('autocomplete', 'certificationid', $selectstr, [], $options);
        $mform->addRule('certificationid', $missingcertstr, 'required', null, 'client');
        $mform->addHelpButton('certificationid', 'selectcertificationcondition', 'tool_certification');
        $mform->setType('certificationid', PARAM_INT);

        $datelabelstr = get_string('recertificationstartedonorafter', 'tool_certification');
        $enablestr = get_string('enable');
        $group = [];
        $group[] =& $mform->createElement('date_time_selector', 'conditiondate', '');
        $group[] =& $mform->createElement('advcheckbox', 'conditiondateenabled', null, $enablestr, 1, [0, 1]);
        $mform->addGroup($group, 'dateformgroup', $datelabelstr, ' ', false);
        $mform->disabledIf('conditiondate[day]', 'conditiondateenabled');
        $mform->disabledIf('conditiondate[month]', 'conditiondateenabled');
        $mform->disabledIf('conditiondate[year]', 'conditiondateenabled');
        $mform->setDefault('conditiondateenabled', '1');
    }

    /**
     * Helps to build SQL to retrieve certifications that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $tc = dynamicruleapi::generate_alias();
        $tcu = dynamicruleapi::generate_alias();
        $enabled = dynamicruleapi::generate_param_name();
        $certificationid = dynamicruleapi::generate_param_name();
        $join = "
            INNER JOIN {tool_certification_users} {$tcu}
            ON {$tcu}.userid = u.id
            INNER JOIN {tool_certification} {$tc}
            ON {$tc}.id = {$tcu}.certificationid
        ";

        $where = "
            {$tc}.archived = 0
            AND {$tc}.id = :{$certificationid}
            AND {$tcu}.isrecertification = 1
            AND {$tcu}.status = :{$enabled}
            AND {$tcu}.nextstartdate > 0
            AND {$tcu}.currentprogramid IS NOT NULL
        ";

        // If enabled check that user certification has due date on or after chosen date.
        if ($this->get_conditiondateenabled()) {
            $where .= "AND {$tcu}.nextstartdate >= " . $this->get_conditiondate();
        }

        $params = [$enabled => constants::STATUS_OVERRIDE_DEFAULT, $certificationid => $this->get_certificationid()];

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
        $stringparams = ['fullname' => $fullname];

        if ($this->get_conditiondateenabled()) {
            $conditiondate = userdate($this->get_conditiondate(), get_string('strftimedatetimeshort'));
            $stringparams = ['fullname' => $fullname, 'conditiondate' => $conditiondate];
            $stringid = 'conditionrecertificationstarteddescriptionwithdate';
        } else {
            $stringid = 'conditionrecertificationstarteddescription';
        }

        return get_string($stringid, 'tool_certification', $stringparams);
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
     * Return a list of valid attributes for given instance
     *
     * @return array Perhaps something similar to persistent definition, e.g. name, type, description
     */
    protected function get_config_attributes(): array {
        return [];
    }

    /**
     * Event subscription.
     *
     * @return array|false eventnames or false if no subscription.
     */
    public function get_event_subscription() {
        return [
            \tool_certification\event\recertification_started::class,
            \tool_certification\event\certification_completion_created::class,
        ];
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
            'certificationdate' => new \lang_string('displaycertificationdate', 'tool_certification'),
            'certificationexpirydate' => new \lang_string('displayexpirydate', 'tool_certification'),
            'expirydatetimestamp' => new \lang_string('displayexpirydatetimestamp', 'tool_certification'),
            'certificationreopen' => new \lang_string('displaycertificationreopen', 'tool_certification'),
            'certificationprogramname' => new \lang_string('displaycertificationprogramname', 'tool_certification'),
            'recertificationprogramname' => new \lang_string('displayrecertificationprogramname', 'tool_certification'),
            'certificationgraceperiodend' => new \lang_string('displaygraceperiodend', 'tool_certification'),
            'certificationduedate' => new \lang_string('displaycertificationduedate', 'tool_certification'),
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
            $data[$user->id] = $this->get_key_values($keys, $certification, $user->id, true);
        }

        return $data;
    }
}
