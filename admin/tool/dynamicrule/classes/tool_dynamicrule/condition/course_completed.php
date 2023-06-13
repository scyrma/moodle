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
 * This file contains the backend class for course_completed condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule\outcome_base;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_dynamicrule\rule;
use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/grade/querylib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * The backend class for course_completed condition
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_completed extends \tool_dynamicrule\condition_sql {

    /** @var string Course was completed at any time */
    const OPERATOR_ANYTIME = 'any';

    /** @var string Course was completed before given date */
    const OPERATOR_BEFORE = 'before';

    /** @var string Course was completed after given date */
    const OPERATOR_AFTER = 'after';

    /**
     * Which rule types this condition supports.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return rule::TYPE_NORMAL + rule::TYPE_SHARED;
    }

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditioncoursecompleted', 'tool_dynamicrule');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        $mform->addElement('course', 'courseid', get_string('course'),
            ['requiredcapabilities' => ['moodle/course:update'], 'onlywithcompletion' => true]);
        $mform->addRule('courseid', null, 'required', null, 'client');
        $mform->setType('courseid', PARAM_INT);

        $operators = [
            self::OPERATOR_ANYTIME => get_string('operatoranytime', 'tool_dynamicrule'),
            self::OPERATOR_BEFORE => get_string('operatorbefore', 'tool_dynamicrule'),
            self::OPERATOR_AFTER => get_string('operatorafter', 'tool_dynamicrule'),
        ];
        $mform->addElement('select', 'operator', get_string('coursecompletiondate', 'tool_dynamicrule'), $operators);
        $mform->addRule('operator', null, 'required', null, 'client');

        $mform->addElement('date_time_selector', 'timecompleted');
        $mform->hideIf('timecompleted', 'operator', 'eq', self::OPERATOR_ANYTIME);
        $mform->setDefault('timecompleted', usergetmidnight(time()));
    }

    /**
     * Validates the configform of the condition.
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

        if (!api::is_course_category_allowed_in_rule($this->get_rule(), $course->category)) {
            $errors['courseid'] = get_string('errorinvalidcoursetenant', 'tool_dynamicrule');
            return $errors;
        }

        // Check that completion is enabled for selected course.
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            $errors['courseid'] = get_string('errorcompletionnotenabled', 'tool_dynamicrule');
        }

        // Check operator is valid.
        if (!in_array($data['operator'], [self::OPERATOR_ANYTIME, self::OPERATOR_AFTER, self::OPERATOR_BEFORE])) {
            $errors['operator'] = get_string('errorinvalidoperator', 'tool_dynamicrule');
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

        $join = "JOIN {course_completions} {$cc}
                   ON ({$cc}.userid = u.id)";

        $courseparam = \tool_dynamicrule\api::generate_param_name();
        $params = [$courseparam => $this->get_courseid()];

        $conditionoperator = $this->get_conditionoperator();
        $conditiondate = $this->get_conditiondate();

        if ($conditionoperator != self::OPERATOR_ANYTIME && $conditiondate > 0) {
            $operator = ($conditionoperator == self::OPERATOR_BEFORE ? '<' : '>');
            $timeparam = \tool_dynamicrule\api::generate_param_name();

            $where = "{$cc}.course = :{$courseparam} AND {$cc}.timecompleted {$operator} :$timeparam";

            $params[$timeparam] = $conditiondate;
        } else {
            $where = "{$cc}.course = :{$courseparam} AND {$cc}.timecompleted IS NOT NULL";
        }

        return [$join, $where, $params];
    }

    /**
     * Return the configured conditiondate
     *
     * @return int
     */
    private function get_conditiondate(): int {
        return $this->get_configdata()['timecompleted'] ?? 0;
    }

    /**
     * Return the configured condition operator (before/after).
     *
     * @return string
     */
    private function get_conditionoperator(): string {
        return $this->get_configdata()['operator'] ?? self::OPERATOR_ANYTIME;
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $description = get_string('conditioncoursecompleteddescription', 'tool_dynamicrule', $this->get_coursename());

        $conditionoperator = $this->get_conditionoperator();
        $conditiondate = $this->get_conditiondate();

        if ($conditionoperator != self::OPERATOR_ANYTIME && $conditiondate > 0) {
            $strconditiondate = userdate($conditiondate, get_string('strftimedatetimeshort', 'langconfig'));

            $description .= '<br />' . get_string('conditioncoursecompleted' . $conditionoperator, 'tool_dynamicrule',
                $strconditiondate);
        }

        return $description;
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

        // Check that course exists.
        if (!$course = $DB->get_record('course', ['id' => $this->get_courseid()])) {
            return get_string('errorinvalidcourse', 'tool_dynamicrule');
        }

        // Check that completion is enabled for selected course.
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return get_string('errorcompletionnotenabled', 'tool_dynamicrule');
        }

        return parent::get_broken_description();
    }

    /**
     * Available data for outcomes
     *
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_available_data_for_outcome(outcome_base $calleroutcome): array {
        $placeholders = [
            'courseid' => new \lang_string('courseinternalid', 'tool_dynamicrule'),
            'coursefullname' => new \lang_string('fullnamecourse'),
            'courseshortname' => new \lang_string('shortnamecourse'),
            'courseurl' => new \lang_string('courseurl', 'tool_dynamicrule'),
            'coursecompletiondate' => new \lang_string('coursecompletiondate', 'tool_dynamicrule'),
            'coursegrade' => new \lang_string('gradenoun'),
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
     *
     * @param array $keys
     * @param array $users array of user objects
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_data_for_outcome(array $keys, array $users, outcome_base $calleroutcome): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $data = [];
        if (!$users || !$this->is_configuration_valid()) {
            return [];
        }

        if (in_array('coursefullname', $keys) || in_array('courseshortname', $keys)) {
            $course = get_course($this->get_courseid());
        }

        foreach ($users as $user) {
            $v = [];
            $coursecustomfieldfound = false;
            $courseid = $this->get_courseid();
            foreach ($keys as $key) {
                if ($key === 'courseid') {
                    $v[$key] = $courseid;
                } else if ($key === 'coursefullname') {
                    $v[$key] = format_string($course->fullname, true,
                        ['context' => \context_course::instance($course->id), 'escape' => false]);
                } else if ($key === 'courseshortname') {
                    $v[$key] = format_string($course->shortname, true,
                        ['context' => \context_course::instance($course->id), 'escape' => false]);
                } else if ($key === 'courseurl') {
                    $v[$key] = course_get_url($this->get_courseid())->out(false);
                } else if ($key === 'coursecompletiondate') {
                    $completiondate = $DB->get_field('course_completions', 'timecompleted',
                        ['course' => $courseid, 'userid' => $user->id]);
                    $v[$key] = $completiondate ? userdate($completiondate, get_string('strftimedatefullshort')) : '';
                } else if ($key === 'coursegrade') {
                    $grade = grade_get_course_grade($user->id, $courseid);
                    if ($grade && $grade->grade) {
                        $gradestr = $grade->str_grade;
                    }
                    $v[$key] = $gradestr ?? '';
                } else if (!$coursecustomfieldfound && preg_match('/coursecustomfield_.+/', $key)) {
                    // At least one course custom field was found.
                    $coursecustomfieldfound = true;
                }
            }
            // Add course custom fields data.
            if ($coursecustomfieldfound) {
                if (!isset($handler)) {
                    $handler = \core_course\customfield\course_handler::create();
                }
                foreach ($handler->get_instance_data($this->get_courseid(), true) as $instance) {
                    $issuefieldname = 'coursecustomfield_' . $instance->get_field()->get('shortname');
                    if (in_array($issuefieldname, $keys)) {
                        $v[$issuefieldname] = $instance->export_value();
                    }
                }
            }
            $data[$user->id] = $v;
        }
        return $data;
    }

    /**
     * Event subscription.
     *
     * @return array list of event classes to listen to
     */
    public function get_event_subscription() {
        return [
            \core\event\course_completed::class,
            \tool_wp\event\course_reset::class
        ];
    }

    /**
     * Should this rule be processed in scheduled tasks
     *
     * Even though this condition listens to course completed event it still needs to regularly check
     * if the course has been reset and completion for the user removed.
     *
     * TODO WP-3030 we can not listen to the \core\event\course_reset_ended because it is triggered for multiple users
     *
     * @return bool
     */
    public function is_scheduled_task(): bool {
        return true;
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
