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
 * This file contains the backend class for course_not_completed condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule\outcome_base;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for course_not_completed condition
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_not_completed extends \tool_dynamicrule\condition_sql {

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditioncoursenotcompleted', 'tool_dynamicrule');
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
        if (!isset($data['courseid']) || !$DB->record_exists('course', ['id' => $data['courseid']])) {
            $errors['courseid'] = get_string('errorinvalidcourse', 'tool_dynamicrule');
        }
        return $errors;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {

        $cc = \tool_dynamicrule\api::generate_alias();

        $courseid = \tool_dynamicrule\api::generate_param_name();

        $join = "LEFT JOIN {course_completions} {$cc}
                        ON ({$cc}.userid = u.id AND {$cc}.course = :{$courseid})";

        $where = "{$cc}.timecompleted IS NULL OR {$cc}.id IS NULL";

        $params = [$courseid => $this->get_courseid()];

        return [$join, $where, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $str = 'conditioncoursenotcompleteddescription';
        return get_string($str, 'tool_dynamicrule', $this->get_coursename());
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
     * Check if course still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('course', ['id' => $this->get_courseid()]);
    }

    /**
     * Available data for outcomes
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_available_data_for_outcome(outcome_base $calleroutcome): array {
        return [
            'courseid' => new \lang_string('courseinternalid', 'tool_dynamicrule'),
            'coursefullname' => new \lang_string('fullnamecourse'),
            'courseshortname' => new \lang_string('shortnamecourse'),
            'courseurl' => new \lang_string('courseinternalid', 'tool_dynamicrule'),
        ];
        // TODO add completion time and grade.
    }

    /**
     * Data for the outcomes
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

        if (in_array('coursefullname', $keys) || in_array('courseshortname', $keys)) {
            $course = get_course($this->get_courseid());
        }

        foreach ($users as $user) {
            $v = [];
            foreach ($keys as $key) {
                if ($key === 'courseid') {
                    $v[$key] = $this->get_courseid();
                } else if ($key === 'coursefullname') {
                    $v[$key] = $course->fullname;
                } else if ($key === 'courseshortname') {
                    $v[$key] = $course->shortname;
                } else if ($key === 'courseurl') {
                    $v[$key] = course_get_url($this->get_courseid())->out(false);
                }
            }
            $data[$user->id] = $v;
        }
        return $data;
    }
}

