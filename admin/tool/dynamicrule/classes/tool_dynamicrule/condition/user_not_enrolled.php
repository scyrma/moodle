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
 * This file contains the backend class for user_not_enrolled condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for user_not_enrolled condition
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_not_enrolled extends \tool_dynamicrule\condition_sql {

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionusernotenrolled', 'tool_dynamicrule');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        $mform->addElement('course', 'courseid', get_string('course'));
        $mform->addRule('courseid', null, 'required', null, 'client');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('select', 'enrol', get_string('enrolmentmethod', 'enrol'), $this->get_enrols());
        $mform->setType('enrol', PARAM_COMPONENT);
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
        if (empty($data['courseid']) || !$DB->record_exists('course', ['id' => $data['courseid']])) {
            $errors['courseid'] = get_string('errorinvalidcourse', 'tool_dynamicrule');
        }
        if (!empty($data['enrol']) && !enrol_get_plugin($data['enrol'])) {
            $errors['enrol'] = get_string('errorinvalidenrol', 'enrol_dynamicrule');
        }
        return $errors;
    }

    /**
     * Returns an array of enrolment methods to use on select field
     *
     * @return array
     */
    private function get_enrols() {
        $enrols = ['' => get_string('any')];
        foreach (enrol_get_plugins(true) as $p) {
            $enrol = $p->get_name();
            $enrols[$enrol] = get_string('pluginname', 'enrol_'.$enrol);
        }
        return $enrols;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {

        $ue = \tool_dynamicrule\api::generate_alias();
        $e = \tool_dynamicrule\api::generate_alias();

        $courseid = \tool_dynamicrule\api::generate_param_name();
        $params = [$courseid => $this->get_courseid()];

        $joinconditions = "{$e}.id = {$ue}.enrolid AND {$e}.courseid = :{$courseid}";

        if (!empty($this->get_enrol())) {
            $enrol = \tool_dynamicrule\api::generate_param_name();
            $joinconditions .= " AND {$e}.enrol = :{$enrol}";
            $params[$enrol] = $this->get_enrol();
        }

        $join = "LEFT JOIN {user_enrolments} {$ue}
                        ON ({$ue}.userid = u.id)
                 LEFT JOIN {enrol} {$e}
                        ON ({$joinconditions})";

        $where = "{$ue}.id IS NULL";

        return [$join, $where, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $a = new \stdClass();
        $a->course = $this->get_coursename();
        if ($enrol = $this->get_enrol()) {
            $a->enrol = get_string('pluginname', 'enrol_'.$enrol);
            $str = get_string('conditionusernotenrolleddescriptionwithenrol', 'tool_dynamicrule', $a);
        } else {
            $str = get_string('conditionusernotenrolleddescription', 'tool_dynamicrule', $a);
        }
        return $str;
    }

    /**
     * Return the configured enrolment method.
     *
     * @return string
     */
    private function get_enrol(): string {
        if (isset($this->get_configdata()['enrol'])) {
            return $this->get_configdata()['enrol'];
        }
        return '';
    }

    /**
     * Return the configured course id.
     *
     * @return int
     */
    private function get_courseid(): int {
        return $this->get_configdata()['courseid'];
    }

    /**
     * Return the formatted course name.
     *
     * @return string
     */
    private function get_coursename(): string {
        global $DB;
        $coursename = $DB->get_field('course', 'fullname', ['id' => $this->get_courseid()]);
        return format_string($coursename, true, ['context' => \context_system::instance(), 'escape' => false]);
    }

    /**
     * Check if course and enrol method still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        if (empty($this->get_enrol())) {
            $ret = true;
        } else {
            $ret = enrol_get_plugin($this->get_enrol());
        }
        return ($ret && $DB->record_exists('course', ['id' => $this->get_courseid()]));
    }
}
