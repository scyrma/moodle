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
 * This file contains the backend class for user_enrolled condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_dynamicrule\rule;
use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for user_enrolled condition
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_enrolled extends \tool_dynamicrule\condition_sql {

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
        return get_string('conditionuserenrolled', 'tool_dynamicrule');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        $mform->addElement('course', 'courseid', get_string('course'),
            ['requiredcapabilities' => ['moodle/course:viewparticipants']]);
        $mform->addRule('courseid', null, 'required', null, 'client');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('select', 'enrol', get_string('enrolmentmethod', 'enrol'), $this->get_enrolment_methods());
        $mform->setType('enrol', PARAM_COMPONENT);

        $options = ['optional' => true];
        $mform->addElement('date_time_selector', 'timeenrolled', get_string('timeenrolled', 'tool_dynamicrule'), $options);
        $mform->setDefault('timeenrolled', time());
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
        if (empty($data['courseid']) || !$course = $DB->get_record('course', ['id' => $data['courseid']])) {
            $errors['courseid'] = get_string('errorinvalidcourse', 'tool_dynamicrule');
            return $errors;
        }

        if (!api::is_course_category_allowed_in_rule($this->get_rule(), $course->category)) {
            $errors['courseid'] = get_string('errorinvalidcoursetenant', 'tool_dynamicrule');
            return $errors;
        }

        // Check enrolment method exists in the course and enabled.
        if (!empty($data['enrol'])) {
            $enrolinstances = enrol_get_instances($data['courseid'], true);
            if (!in_array($data['enrol'], array_column($enrolinstances, 'enrol'))) {
                $errors['enrol'] = get_string('errorinvalidenrolmentmethod', 'tool_dynamicrule');
            }
        }

        return $errors;
    }

    /**
     * Returns an array of enrolment methods to use on select field
     *
     * @return array
     */
    private function get_enrolment_methods() {
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
        $now1 = \tool_dynamicrule\api::generate_param_name();
        $now2 = \tool_dynamicrule\api::generate_param_name();
        $params = [$courseid => $this->get_courseid(), $now1 => time(), $now2 => time()];

        $whereconditions = "{$ue}.userid = u.id AND {$e}.courseid = :{$courseid} AND {$ue}.status = 0";
        $whereconditions .= " AND {$ue}.timestart <= :{$now1} AND ({$ue}.timeend = 0 OR {$ue}.timeend >= :{$now2})";

        if (!empty($this->get_enrol())) {
            $enrol = \tool_dynamicrule\api::generate_param_name();
            $whereconditions .= " AND {$e}.enrol = :{$enrol}";
            $params[$enrol] = $this->get_enrol();
        }

        $timeenrolled = $this->get_timeenrolled();
        if (!is_null($timeenrolled)) {
            $ptimec = \tool_dynamicrule\api::generate_param_name();
            $ptimes = \tool_dynamicrule\api::generate_param_name();

            $whereconditions .= " AND (({$ue}.timestart = 0 AND {$ue}.timecreated >= :{$ptimec})
                          OR {$ue}.timestart >= :{$ptimes})";

            $params[$ptimec] = $timeenrolled;
            $params[$ptimes] = $timeenrolled;
        }

        $where = "EXISTS (SELECT 1 FROM {user_enrolments} {$ue}
                                   JOIN {enrol} {$e}
                                     ON ({$e}.id = {$ue}.enrolid AND {$e}.status = 0)
                                  WHERE {$whereconditions})";

        return ['', $where, $params];
    }

    /**
     * Return the configured conditiondate
     *
     * @return int|null
     */
    private function get_conditiondate(): ?int {
        return $this->get_configdata()['timeenrolled'] ?? null;
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $strparams = ['course' => $this->get_coursename()];
        if ($enrol = $this->get_enrol()) {
            $strparams['enrol'] = get_string('pluginname', 'enrol_' . $enrol);
        } else {
            $strparams['enrol'] = get_string('any');
        }

        if ($date = $this->get_conditiondate()) {
            $strparams['conditiondate'] = userdate($this->get_conditiondate(), get_string('strftimedatefullshort'));
            $description = get_string('conditionuserenrolleddescriptionwithdate', 'tool_dynamicrule', $strparams);
        } else {
            $description = get_string('conditionuserenrolleddescription', 'tool_dynamicrule', $strparams);
        }

        return $description;
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
     * Return the configured enrolment start date.
     *
     * @return int|null
     */
    private function get_timeenrolled() {
        if (isset($this->get_configdata()['timeenrolled'])) {
            return $this->get_configdata()['timeenrolled'];
        }
        return null;
    }

    /**
     * If the current user is able to use this condition.
     *
     * Return true if there are courses where user can see participants.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return (bool) \core_course_category::search_courses_count(['search' => ''],
            [], ['moodle/course:viewparticipants']);
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
     * Check if course and enrol method still exists and enrol method is enabled.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;

        // Check that course exists.
        if (!$course = $DB->get_record('course', ['id' => $this->get_courseid()])) {
            return false;
        }

        // Check enrolment method exists in the course and enabled.
        if (!empty($this->get_enrol())) {
            $enrolinstances = enrol_get_instances($this->get_courseid(), true);
            if (!in_array($this->get_enrol(), array_column($enrolinstances, 'enrol'))) {
                return false;
            }
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

        // Check enrolment method exists in the course and enabled.
        if (!empty($data['enrol'])) {
            $enrolinstances = enrol_get_instances($this->get_courseid(), true);
            if (!in_array($this->get_enrol(), array_column($enrolinstances, 'enrol'))) {
                return get_string('errorinvalidenrolmentmethod', 'tool_dynamicrule');
            }
        }

        return parent::get_broken_description();
    }

    /**
     * If the current user is able to add this condition.
     *
     * @see self::is_available()
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
        // Check permissions to view participants in the selected course.
        $coursecontext = \context_course::instance($configdata['courseid']);
        return has_any_capability(['moodle/course:viewparticipants', 'moodle/course:enrolreview'], $coursecontext);
    }

    /**
     * Event subscription.
     *
     * @return array of events to observe
     */
    public function get_event_subscription() {
        return [
            '\core\event\user_enrolment_created',
            '\core\event\user_enrolment_updated',
            '\core\event\user_enrolment_deleted',
        ];
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

    /**
     * Should this rule be processed in scheduled tasks
     *
     * @return bool
     */
    public function is_scheduled_task(): bool {
        if (!\core_component::get_plugin_directory('tool', 'datewatch')) {
            // If tool_datewatch is not installed, we can not watch the job start and end date and must
            // evaluate this rule in the scheduled task.
            return true;
        }
        return parent::is_scheduled_task();
    }

    /**
     * Return the list of date watchers for this particular condition
     *
     * @return \tool_datewatch\watcher[]
     */
    public function get_date_watchers(): array {
        // All conditions of this type listen to the same fields without any offset.
        // Default callback will be added in the tool_dynamicrule_datewatch().
        // We need to watch both start and enddate, so we can match and unmatch user respectively.
        return [
            \tool_datewatch\watcher::instance('user_enrolments', 'timestart', 0),
            \tool_datewatch\watcher::instance('user_enrolments', 'timeend', 0),
        ];
    }
}
