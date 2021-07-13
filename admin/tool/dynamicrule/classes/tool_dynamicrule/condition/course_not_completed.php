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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * This file contains the backend class for course_not_completed condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule\outcome_base;
use tool_wp\exporter_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/completionlib.php');

/**
 * The backend class for course_not_completed condition
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
        // TODO: Remove comment below once MDL-68315 has been integrated.
        // Completion filtering is not functioning correctly, see MDL-68315.
        $mform->addElement('course', 'courseid', get_string('course'),
            ['requiredcapabilities' => ['moodle/course:update'], 'onlywithcompletion' => true]);
        $mform->addElement('static', 'includesallnotice', '',
            get_string('conditioncoursenotcompletedformnotice', 'tool_dynamicrule'));
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

        // Check course exists.
        if (!isset($data['courseid']) || !$course = $DB->get_record('course', ['id' => $data['courseid']])) {
            $errors['courseid'] = get_string('errorinvalidcourse', 'tool_dynamicrule');
            return $errors;
        }

        // Check that completion is enabled for selected course.
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            $errors['courseid'] = get_string('errorcompletionnotenabled', 'tool_dynamicrule');
        }

        return $errors;
    }

    /**
     * If the current user is able to add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        // Everyone can add it, but it might not be available.
        return true;
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $coursecontext = \context_course::instance($configdata['courseid']);
        // We check update capability because we are interested in completion data,
        // but view capability is not sufficient for it (and there is no specific
        // capability for completion view/edit, course:update is used for that).
        return has_capability('moodle/course:update', $coursecontext);
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
     * If the current user is able to use this condition.
     *
     * Return true if there are courses with completion available for user.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return (bool) \core_course_category::search_courses_count(['onlywithcompletion' => true],
            [], ['moodle/course:update']);
    }

    /**
     * Condition not available label.
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('noavailablecompletioncourses', 'tool_dynamicrule');
    }

    /**
     * Check if course still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;

        // Check that course exists.
        if (!$course = $DB->get_record('course', ['id' => $this->get_courseid()])) {
            return false;
        }

        // Check that completion is enabled for selected course.
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return false;
        }

        return true;
    }

    /**
     * Condition broken label.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;
        if (!$course = $DB->get_record('course', ['id' => $this->get_courseid()])) {
            return get_string('errorinvalidcourse', 'tool_dynamicrule');
        }

        // Check that completion is enabled for selected course.
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return get_string('errorcompletionnotenabled', 'tool_dynamicrule');
        }

        return parent::get_broken_label();
    }

    /**
     * Available data for outcomes
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_available_data_for_outcome(outcome_base $calleroutcome): array {
        $placeholders = [
            'courseid' => new \lang_string('courseinternalid', 'tool_dynamicrule'),
            'coursefullname' => new \lang_string('fullnamecourse'),
            'courseshortname' => new \lang_string('shortnamecourse'),
            'courseurl' => new \lang_string('courseurl', 'tool_dynamicrule'),
        ];

        // Add course custom fields.
        $handler = \core_course\customfield\course_handler::create();
        foreach ($handler->get_instance_data($this->get_courseid(), true) as $instancedata) {
            $issuefieldname = 'coursecustomfield_' . $instancedata->get_field()->get('shortname');
            $placeholders[$issuefieldname] = $instancedata->get_field()->get_formatted_name();
        }

        return $placeholders;
    }

    /**
     * Data for the outcomes
     * @param array $keys
     * @param array $users array of user objects
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_data_for_outcome(array $keys, array $users, outcome_base $calleroutcome): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

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

    /**
     * Add courseid condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('course', $this->get_courseid());
    }

    /**
     * Get courseid condition field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['courseid'] = $importer->get_mapping('course', $this->get_courseid(), IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }
}
