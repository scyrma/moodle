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
 * Class for certification not certified status dynamic rules' condition.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\tool_dynamicrule\condition;

use MoodleQuickForm;
use tool_certification\certification;
use tool_certification\constants;
use tool_certification\local\helpers\dynamic_rules;
use tool_dynamicrule\api as dynamicruleapi;
use tool_dynamicrule\condition_sql;
use tool_certification\api;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die;

/**
 * Class for certification not certified status dynamic rules' condition.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certification_not_certified extends condition_sql {
    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditioncertificationnotcertified', 'tool_certification');
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
        $c = dynamicruleapi::generate_alias();
        $cu = dynamicruleapi::generate_alias();
        $cc = dynamicruleapi::generate_alias();
        $pr = dynamicruleapi::generate_alias();
        $cid = dynamicruleapi::generate_param_name();
        $certificationid = $this->get_certificationid();
        $statusid = constants::STATUS_CERTIFIED;

        return api::get_certification_status_sql_query($certificationid, $statusid, true, 'u', $c, $cu, $cc, $pr, $cid);
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $certification = new certification($this->get_certificationid());
        $fullname = format_string($certification->get('fullname'), true, ['escape' => false]);
        $status = get_string('certified', 'tool_certification');
        $stringparams = ['status' => $status, 'fullname' => $fullname];
        $stringid = 'conditioncertificationstatusdescriptionnegated';

        return get_string($stringid, 'tool_certification', $stringparams);
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
}
