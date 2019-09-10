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
 * This file contains the backend class for user_not_in_department condition.
 *
 * @package    tool_organisation
 * @copyright  2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation\tool_dynamicrule\condition;

use tool_organisation\helper;
use tool_organisation\organisation;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for user_not_in_department condition
 *
 * @package    tool_organisation
 * @copyright  2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_not_in_department extends \tool_dynamicrule\condition_sql {

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionusernotindepartment', 'tool_organisation');
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;
        $name = $DB->get_field('tool_organisation_department', 'name', ['id' => $this->get_departmentid()]);
        $a = format_string($name, true, ['escape' => false]);
        return get_string('conditionuserdepartmentdescriptionnegated', 'tool_organisation', $a);
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        $mform->addElement('selectgroups', 'departmentid', get_string('entitydepartment', 'tool_organisation'),
            organisation::get_all_departments_menu(['' => '']));
        $mform->addRule('departmentid', null, 'required', null, 'client');
        $mform->setType('departmentid', PARAM_INT);

        $mform->addElement('advcheckbox', 'withsubdepartments',
            '', get_string('withsubdepartments', 'tool_organisation'));
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
        if (empty($data['departmentid']) || !$DB->record_exists('tool_organisation_department', ['id' => $data['departmentid']])) {
            $errors['departmentid'] = get_string('errorinvaliddepartment', 'tool_organisation');
        }
        return $errors;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        list($where, $params) = helper::user_is_in_department_select($this->get_departmentid(),
            $this->get_with_subdepartments(), 'u', null, $this->get_tenantid());

        $join = '';
        return [$join, " NOT " . $where, $params];
    }

    /**
     * Return the configured departmentid
     *
     * @return int
     */
    private function get_departmentid(): int {
        return $this->get_configdata()['departmentid'];
    }

    /**
     * Return the configured departmentid
     *
     * @return bool
     */
    private function get_with_subdepartments(): bool {
        return !empty($this->get_configdata()['withsubdepartments']);
    }

    /**
     * Check if department still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('tool_organisation_department', ['id' => $this->get_departmentid()]);

    }
}
