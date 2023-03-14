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

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule\rule;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_dynamicrule\outcome_base;
use tool_wp\local\helpers\string_helper;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot. '/admin/tool/wp/periodduration.php');

/**
 * Course last access condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_last_access extends \tool_dynamicrule\condition_sql {
    /** @var string Course was never accessed */
    const OPERATOR_NEVER = 'never';

    /** @var string Course was ever accessed */
    const OPERATOR_EVER = 'ever';

    /** @var string Course was accessed before given date */
    const OPERATOR_BEFORE = 'before';

    /** @var string Course was accessed after given date */
    const OPERATOR_AFTER = 'after';

    /** @var string Course was accessed in last n days */
    const OPERATOR_INLAST = 'inlast';

    /** @var string Course was accessed before last n days (was not accessed in last n days) */
    const OPERATOR_BEFORELAST = 'beforelast';

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
        return get_string('conditioncourselastaccess', 'tool_dynamicrule');
    }

    /**
     * Adds condition's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        $mform->addElement('course', 'courseid', get_string('course'),
            ['requiredcapabilities' => ['moodle/course:viewparticipants']]);
        $mform->addRule('courseid', null, 'required', null, 'client');
        $mform->setType('courseid', PARAM_INT);

        $operators = [
            self::OPERATOR_EVER => get_string('datetypeever', 'tool_dynamicrule'),
            self::OPERATOR_NEVER => get_string('datetypenever', 'tool_dynamicrule'),
            self::OPERATOR_BEFORE => get_string('operatorbefore', 'tool_dynamicrule'),
            self::OPERATOR_AFTER => get_string('operatorafter', 'tool_dynamicrule'),
            self::OPERATOR_INLAST => get_string('datetypeinlast', 'tool_dynamicrule'),
            self::OPERATOR_BEFORELAST => get_string('datetypepast', 'tool_dynamicrule'),
        ];
        $mform->addElement('select', 'operator', get_string('courselastaccesstime', 'tool_dynamicrule'), $operators);
        $mform->addHelpButton('operator', 'courselastaccesstime', 'tool_dynamicrule');

        $mform->addElement('date_time_selector', 'lastaccesstimestamp');
        $mform->hideIf('lastaccesstimestamp', 'operator', 'in',
            [self::OPERATOR_NEVER, self::OPERATOR_EVER, self::OPERATOR_INLAST, self::OPERATOR_BEFORELAST]);
        $mform->setDefault('lastaccesstimestamp', usergetmidnight(time()));

        $mform->addElement('periodduration', 'lastaccessrelative');
        $mform->hideIf('lastaccessrelative', 'operator', 'in',
            [self::OPERATOR_NEVER, self::OPERATOR_EVER, self::OPERATOR_BEFORE, self::OPERATOR_AFTER]);
        $mform->addElement('static', 'notice', '', get_string('conditioncourselastaccessnotice', 'tool_dynamicrule'));
    }

    /**
     * Validates the configform of the condition
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];

        // Check operator is valid.
        if (!in_array($data['operator'], [self::OPERATOR_EVER, self::OPERATOR_NEVER, self::OPERATOR_AFTER,
                self::OPERATOR_BEFORE, self::OPERATOR_INLAST, self::OPERATOR_BEFORELAST])) {
            $errors['operator'] = get_string('errorinvalidoperator', 'tool_dynamicrule');
        }

        if (in_array($data['operator'], [self::OPERATOR_BEFORE, self::OPERATOR_AFTER]) &&
                empty($data['lastaccesstimestamp'])) {
            $errors['lastaccesstimestamp'] = get_string('errorinvaliduserlastcourseaccess', 'tool_dynamicrule');
        }

        if (in_array($data['operator'], [self::OPERATOR_INLAST, self::OPERATOR_BEFORELAST]) &&
                empty($data['lastaccessrelative'])) {
            $errors['lastaccessrelative'] = get_string('errorinvaliduserlastcourseaccess', 'tool_dynamicrule');
        }

        if (in_array($data['operator'], [self::OPERATOR_INLAST, self::OPERATOR_BEFORELAST])) {
            // Avoid 0 hours/days/weeks.
            $time = time();
            if (strtotime('-' . $data['lastaccessrelative'], $time) === $time) {
                $errors['lastaccessrelative'] = get_string('errorinvaliduserlastcourseaccess', 'tool_dynamicrule');
            }
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
     * If the current user is able to use this condition.
     *
     * Return true if there are courses available for user.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return (bool) \core_course_category::search_courses_count([], [], ['moodle/course:viewparticipants']);
    }

    /**
     * Condition not available label.
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('noavailableenrolledcourses', 'tool_dynamicrule');
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $coursecontext = \context_course::instance($configdata['courseid']);
        return has_capability('moodle/course:viewparticipants', $coursecontext);
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {

        $ue = \tool_dynamicrule\api::generate_alias();
        $e = \tool_dynamicrule\api::generate_alias();
        $la = \tool_dynamicrule\api::generate_alias();

        $courseid = \tool_dynamicrule\api::generate_param_name();
        $time = \tool_dynamicrule\api::generate_param_name();

        $params = [
            $courseid => $this->get_courseid(),
        ];

        switch ($this->get_conditionoperator()) {
            case self::OPERATOR_BEFORE:
                $condition = "{$la}.timeaccess <= :{$time}";
                $params[$time] = $this->get_lastaccesstimestamp();
                break;
            case self::OPERATOR_AFTER:
                $condition = "{$la}.timeaccess > :{$time}";
                $params[$time] = $this->get_lastaccesstimestamp();
                break;
            case self::OPERATOR_INLAST:
                $condition = "{$la}.timeaccess > :{$time}";
                $params[$time] = strtotime('-' . $this->get_lastaccessrelative());
                break;
            case self::OPERATOR_BEFORELAST:
                $condition = "{$la}.timeaccess <= :{$time}";
                $params[$time] = strtotime('-' . $this->get_lastaccessrelative());
                break;
            case self::OPERATOR_EVER:
                $condition = "{$la}.timeaccess IS NOT NULL";
                break;
            case self::OPERATOR_NEVER:
                $condition = "{$la}.timeaccess IS NULL";
                break;
        }

        $whereconditions = "{$ue}.userid = u.id AND {$e}.courseid = :{$courseid} AND {$condition}";

        $where = "EXISTS (SELECT 1 FROM {user_enrolments} {$ue}
                                   JOIN {enrol} {$e}
                                     ON ({$e}.id = {$ue}.enrolid)
                              LEFT JOIN {user_lastaccess} {$la}
                                     ON ({$la}.courseid = {$e}.courseid
                                    AND {$la}.userid = {$ue}.userid)
                                  WHERE {$whereconditions})";

        return ['', $where, $params];
    }

    /**
     * Return the configured lastaccesstimestamp
     *
     * @return int
     */
    private function get_lastaccesstimestamp(): int {
        return $this->get_configdata()['lastaccesstimestamp'] ?? 0;
    }

    /**
     * Return the configured lastaccessrelative
     *
     * @return int
     */
    private function get_lastaccessrelative(): string {
        return $this->get_configdata()['lastaccessrelative'] ?? '';
    }

    /**
     * Return the configured condition operator.
     *
     * @return string
     */
    private function get_conditionoperator(): string {
        return $this->get_configdata()['operator'] ?? self::OPERATOR_EVER;
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $lastaccesstimestamp = $this->get_lastaccesstimestamp();
        $lastaccessrelative = $this->get_lastaccessrelative();
        $date = (object)[
            'coursename' => $this->get_coursename(),
        ];
        switch ($conditionoperator = $this->get_conditionoperator()) {
            case self::OPERATOR_BEFORE:
                $date->conditiondate = userdate($lastaccesstimestamp, get_string('strftimedatetimeshort', 'langconfig'));
                $str = get_string('conditioncourselastaccessdescriptionbefore', 'tool_dynamicrule', $date);
                break;
            case self::OPERATOR_AFTER:
                $date->conditiondate = userdate($lastaccesstimestamp, get_string('strftimedatetimeshort', 'langconfig'));
                $str = get_string('conditioncourselastaccessdescriptionafter', 'tool_dynamicrule', $date);
                break;
            case self::OPERATOR_INLAST:
                $date->conditiondate = string_helper::translate_relativedate_string($lastaccessrelative);
                $str = get_string('conditioncourselastaccessdescriptioninlast', 'tool_dynamicrule', $date);
                break;
            case self::OPERATOR_BEFORELAST:
                $date->conditiondate = string_helper::translate_relativedate_string($lastaccessrelative);
                $str = get_string('conditioncourselastaccessdescriptionbeforelast', 'tool_dynamicrule', $date);
                break;
            case self::OPERATOR_EVER:
                $str = get_string('conditioncourselastaccessdescriptionever', 'tool_dynamicrule', $date);
                break;
            case self::OPERATOR_NEVER:
                $str = get_string('conditioncourselastaccessdescriptionnever', 'tool_dynamicrule', $date);
                break;
        }
        return $str;
    }

    /**
     * Check if course still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;

        // Check that course exists.
        return $DB->record_exists('course', ['id' => $this->get_courseid()]);
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
        return format_string($coursename, true,
            ['context' => \context_course::instance($this->get_courseid()), 'escape' => false]);
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
            'courselastaccesstime' => new \lang_string('courselastaccesstime', 'tool_dynamicrule'),
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

        // Since 3.10 WP-2353 we expect exactly one user.
        if (count($users) !== 1 || !$this->is_configuration_valid()) {
            return [];
        }

        if (in_array('coursefullname', $keys) || in_array('courseshortname', $keys)) {
            $course = get_course($this->get_courseid());
        }

        // Build general course info.
        $user = array_shift($users);
        $v = [];
        $coursecustomfieldfound = false;
        $courseid = $this->get_courseid();
        foreach ($keys as $key) {
            if ($key === 'courseid') {
                $v[$key] = $courseid;
            } else if ($key === 'coursefullname') {
                $v[$key] = $course->fullname;
            } else if ($key === 'courseshortname') {
                $v[$key] = $course->shortname;
            } else if ($key === 'courseurl') {
                $v[$key] = course_get_url($this->get_courseid())->out(false);
            } else if ($key === 'courselastaccesstime') {
                $lastaccesstime = $DB->get_field('user_lastaccess', 'timeaccess',
                    ['courseid' => $courseid, 'userid' => $user->id]);
                $v[$key] = $lastaccesstime ? userdate($lastaccesstime, get_string('strftimedatefullshort')) : '';
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

        return [$user->id => $v];
    }

    /**
     * Condition broken label.
     *
     * @return string
     */
    public function get_broken_description(): string {
        return get_string('errorinvalidcourse', 'tool_dynamicrule');
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
