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
 * This file contains the class for program overdue dynamic rules' condition.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\tool_dynamicrule\condition;

use MoodleQuickForm;
use tool_dynamicrule\api as dynamicruleapi;
use tool_dynamicrule\condition_sql;
use tool_program\api;
use tool_program\constants;
use tool_program\local\helpers\dynamic_rules;
use tool_program\persistent\program;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die;

/**
 * Class for program overdue dynamic rules' condition.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_overdue extends condition_sql {
    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionprogramoverdue', 'tool_program');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        // Program select (autocomplete) field.
        $selectprogramstr = get_string('selectprogramcondition', 'tool_program');
        $missingprogramstr = get_string('missingprogram', 'tool_program');
        $options = dynamic_rules::get_selector_options();
        if ($this->is_configuration_valid()) {
            $options['valuehtmlcallback'] = dynamic_rules::get_program_fullname_callback();
        }
        $mform->addElement('autocomplete', 'programid', $selectprogramstr, [], $options);
        $mform->addRule('programid', $missingprogramstr, 'required', null, 'client');
        $mform->addHelpButton('programid', 'selectprogramcondition', 'tool_program');
        $mform->setType('programid', PARAM_INT);

        // Optional date field.
        $dateisonorafterstr = get_string('duedateonorafter', 'tool_program');
        $enablestr = get_string('enable');
        $group = [];
        $group[] =& $mform->createElement('date_selector', 'conditiondate', '');
        $group[] =& $mform->createElement('advcheckbox', 'conditiondateenabled', $enablestr, '', 1, [0, 1]);
        $mform->addGroup($group, 'dateformgroup', $dateisonorafterstr, ' ', false);
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
            'id' => $data['programid'],
            'tenantid' => tenancy::get_tenant_id(),
            'archived' => 0
        ];
        if (!isset($data['programid']) || !$DB->record_exists(program::TABLE, $params)) {
            $errors['programid'] = get_string('errorinvalidprogram', 'tool_program');
        }
        return $errors;
    }

    /**
     * Helps to build SQL to retrieve programs that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $pu = dynamicruleapi::generate_alias();
        $pro = dynamicruleapi::generate_alias();
        $pse = dynamicruleapi::generate_alias();
        $psc = dynamicruleapi::generate_alias();
        $pid = dynamicruleapi::generate_param_name();
        $programid = $this->get_programid();
        $status = constants::STATUS_OVERDUE;

        [$join, $where, $params] = api::get_program_status_sql_query($programid, $status, false, 'u', $pu, $pro, $pse, $psc, $pid);

        // If date enabled, check that user has the due date on or after the chosen date.
        if ($this->get_conditiondateenabled()) {
            $where .= " AND {$pu}.duedate >= " . $this->get_conditiondate();
        }

        return [$join, $where, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $program = new program($this->get_programid());
        $fullname = format_string($program->get('fullname'), true, ['escape' => false]);
        $description = get_string('conditionprogramoverduedescription', 'tool_program', $fullname);

        if ($this->get_conditiondateenabled()) {
            $description .= ' ' . get_string('onorafter', 'tool_program');
            $description .= ' ' . userdate($this->get_conditiondate(), get_string('strftimedatefullshort'));
        }

        return $description;
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
     * Wether configured date condition is enabled
     *
     * @return int|null
     */
    private function get_conditiondateenabled(): ?int {
        return $this->get_configdata()['conditiondateenabled'] ?? null;
    }

    /**
     * Return the configured programid
     *
     * @return int|null
     */
    private function get_programid(): ?int {
        return $this->get_configdata()['programid'] ?? null;
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
     * Check if program still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        if (!$DB->record_exists('tool_program', ['id' => $this->get_programid()])) {
            return false;
        }
        $program = new program($this->get_programid());
        if ($program->is_archived()) {
            return false;
        }

        return true;
    }
}
