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
 * This file contains the backend class for user_not_allocated condition.
 *
 * @package    tool_certification
 * @copyright  2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\tool_dynamicrule\condition;

use Closure;
use context_system;
use tool_certification\api;
use tool_certification\certification;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for user_not_allocated condition
 *
 * @package    tool_certification
 * @copyright  2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_not_allocated extends \tool_dynamicrule\condition_sql {

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
        $options = [
            'ajax' => 'tool_certification/form_potential_certification_selector',
            'multiple' => false,
            'valuehtmlcallback' => $this->get_format_certification_fullname_callback()
        ];
        $selectstr = get_string('selectcertificationcondition', 'tool_certification');
        $missingcertstr = get_string('missingcertification', 'tool_certification');
        $mform->addElement('autocomplete', 'certificationid', $selectstr, [], $options);
        $mform->addHelpButton('certificationid', 'selectcertificationcondition', 'tool_certification');
        $mform->addRule('certificationid', $missingcertstr, 'required', null, 'client');
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
        if (!isset($data['certificationid']) || !$DB->record_exists('tool_certification', ['id' => $data['certificationid']])) {
            $errors['certificationid'] = get_string('errorinvalidcertification', 'tool_certification');
        }
        return $errors;
    }

    /**
     * Returns an array of courses to use on select field
     *
     * @return array
     */
    private function get_certifications(): array {
        return api::get_certifications_in_tenant_fieldset();
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {

        $u = \tool_dynamicrule\api::generate_alias();
        $certificationid = \tool_dynamicrule\api::generate_param_name();

        $join = "LEFT JOIN {tool_certification_users} {$u}
                        ON ({$u}.userid = u.id AND {$u}.certificationid = :{$certificationid})";
        $where = "{$u}.id IS NULL";

        $params = [$certificationid => $this->get_certificationid()];

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
     * Return the configured certificationid
     *
     * @return int
     */
    private function get_certificationid(): int {
        return $this->get_configdata()['certificationid'];
    }

    /**
     * Check if certification still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('tool_certification', ['id' => $this->get_certificationid()]);
    }

    /**
     * Returns format fullname callback given a program id.
     *
     * @return Closure
     */
    private function get_format_certification_fullname_callback(): Closure {
        return static function($certificationid) {
            $certification = new certification($certificationid);
            $formatparams = ['context' => context_system::instance(), 'escape' => false];
            return format_string($certification->get('fullname'), true, $formatparams);
        };
    }
}
