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
 * This file contains the backend class for user_position condition.
 *
 * @package    tool_organisation
 * @copyright  2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation\tool_dynamicrule\condition;

use tool_organisation\helper;
use tool_organisation\organisation;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for user_position condition
 *
 * @package    tool_organisation
 * @copyright  2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_position extends \tool_dynamicrule\condition_sql {

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionuserposition', 'tool_organisation');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        global $CFG;

        $mform->addElement('selectgroups', 'positionid', get_string('entityposition', 'tool_organisation'),
            organisation::get_all_positions_menu(['' => '']));
        $mform->addRule('positionid', null, 'required', null, 'client');
        $mform->setType('positionid', PARAM_INT);

        $mform->addElement('advcheckbox', 'withsubpositions',
            '', get_string('withsubpositions', 'tool_organisation'));

        $options = ['optional' => true, 'timezone' => $CFG->timezone];
        $mform->addElement('date_selector', 'jobstartdate', get_string('jobstartdateafter', 'tool_organisation'), $options);
        $mform->setDefault('jobstartdate', time());
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;
        $name = $DB->get_field('tool_organisation_position', 'name', ['id' => $this->get_positionid()]);
        $a = format_string($name, true, ['escape' => false]);
        return get_string('conditionuserpositiondescription', 'tool_organisation', $a);
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
        if (empty($data['positionid']) || !$DB->record_exists('tool_organisation_position', ['id' => $data['positionid']])) {
            $errors['positionid'] = get_string('errorinvalidposition', 'tool_organisation');
        }
        return $errors;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        list($where, $params) = helper::user_has_position_select($this->get_positionid(),
            $this->get_with_subpositions(), 'u', null, $this->get_tenantid());

        $join = '';
        return [$join, $where, $params];
    }

    /**
     * Return the configured positionid
     *
     * @return int
     */
    private function get_positionid(): int {
        return $this->get_configdata()['positionid'];
    }

    /**
     * Return the configured departmentid
     *
     * @return bool
     */
    private function get_with_subpositions(): bool {
        return !empty($this->get_configdata()['withsubpositions']);
    }

    /**
     * Check if position still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('tool_organisation_position', ['id' => $this->get_positionid()]);
    }

    /**
     * Event subscription.
     *
     * @return string|false eventname or false if no subscription.
     */
    public function get_event_subscription() {
        return '\tool_organisation\event\job_created';
    }
}
