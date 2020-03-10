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
 * Class for recertification period started dynamic rules' condition.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_dynamicrule\condition;

use MoodleQuickForm;
use tool_certification\certification;
use tool_certification\constants;
use tool_certification\local\helpers\dynamic_rules;
use tool_dynamicrule\api as dynamicruleapi;
use tool_dynamicrule\condition_sql;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die;

/**
 * Class for recertification period started dynamic rules' condition.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class recertification_period_started extends condition_sql {
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
        $group[] =& $mform->createElement('date_selector', 'conditiondate', '');
        $group[] =& $mform->createElement('advcheckbox', 'conditiondateenabled', null, $enablestr, 1, [0, 1]);
        $mform->addGroup($group, 'dateformgroup', $datelabelstr, ' ', false);
        $mform->disabledIf('conditiondate[day]', 'conditiondateenabled');
        $mform->disabledIf('conditiondate[month]', 'conditiondateenabled');
        $mform->disabledIf('conditiondate[year]', 'conditiondateenabled');
        $mform->setDefault('conditiondateenabled', '1');
    }

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        global $DB;
        $errors = [];
        $params = [
            'id' => $data['certificationid'],
            'tenantid' => tenancy::get_tenant_id(),
            'archived' => 0
        ];
        if (!isset($data['certificationid']) || !$DB->record_exists(certification::TABLE, $params)) {
            $errors['certificationid'] = get_string('errorinvalidcertification', 'tool_certification');
        }
        return $errors;
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
        $stringid = 'conditionrecertificationstarteddescription';

        $description = get_string($stringid, 'tool_certification', $stringparams);

        if ($this->get_conditiondateenabled()) {
            $description .= ' ' . get_string('onorafter', 'tool_certification');
            $description .= ' ' . userdate($this->get_conditiondate(), get_string('strftimedatefullshort'));
        }

        return $description;
    }

    /**
     * Return the configured certificationid
     *
     * @return int|null
     */
    private function get_certificationid(): ?int {
        return $this->get_configdata()['certificationid'] ?? null;
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
     * Check if certification still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        if (!$DB->record_exists(certification::TABLE, ['id' => $this->get_certificationid()])) {
            return false;
        }
        $certification = new certification($this->get_certificationid());
        if ($certification->is_archived()) {
            return false;
        }

        return true;
    }

    /**
     * Event subscription.
     *
     * @return string|false eventname or false if no subscription.
     */
    public function get_event_subscription() {
        return '\tool_certification\event\recertification_started';
    }
}