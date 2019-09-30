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
 * This file contains the backend class for course unenrol outcome.
 *
 * @package    enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_dynamicrule\tool_dynamicrule\outcome;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for course_enrol outcome
 *
 * @package    enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_unenrol extends \tool_dynamicrule\outcome_base {

    /**
     * @var possible action
     */
    const ACTION_DISABLE_ENROLMENT = 0;

    /**
     * @var possible action
     */
    const ACTION_DISABLE_ENROLMENT_REMOVE_ROLES = 1;

    /**
     * @var possible action
     */
    const ACTION_UNENROL = 2;

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomecourseunenrol', 'enrol_dynamicrule');
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;
        $coursefullname = $DB->get_field('course', 'fullname', ['id' => $this->get_courseid()]);
        return get_string('outcomecourseunenroldescription', 'enrol_dynamicrule', $coursefullname);
    }

    /**
     * Returns string for outcome category.
     *
     * @return string
     */
    public function get_category(): string {
        return get_string('courses');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        $mform->addElement('course', 'coursetounenrol', get_string('course'));
        $mform->addRule('coursetounenrol', null, 'required', null, 'client');
        $mform->setType('coursetounenrol', PARAM_INT);

        $mform->addElement('select', 'action', get_string('action', 'enrol_dynamicrule'), $this->get_actions());
        $mform->addRule('action', null, 'required', null, 'client');
        $mform->setType('action', PARAM_INT);
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
        if (empty($data['coursetounenrol']) || !$DB->record_exists('course', ['id' => $data['coursetounenrol']])) {
            $errors['coursetounenrol'] = get_string('errorinvalidcoursetounenrol', 'enrol_dynamicrule');
        }
        if (!isset($data['action']) || !isset($this->get_actions()[$data['action']])) {
            $errors['action'] = get_string('errorinvalidaction', 'enrol_dynamicrule');
        }
        return $errors;
    }

    /**
     * Apply this outcome on a given list of users
     *
     * @param array $users The users objects to apply the outcome on
     */
    public function apply_to_users(array $users) {
        if (!enrol_is_enabled('dynamicrule')) {
            return;
        }
        if (empty($users)) {
            return;
        }
        $this->unenrol_users($users);
    }

    /**
     * Enrol users on configured course using dynamicrule enrol plugin
     *
     * @param array $users The users objects to apply the outcome on
     */
    private function unenrol_users($users) {
        $plugin = enrol_get_plugin('dynamicrule');
        $enrolments = $this->get_users_enrolments($users);
        switch ($this->get_action()) {
            case self::ACTION_DISABLE_ENROLMENT:
                foreach ($enrolments as $ue) {
                    $instance = (object)['id' => $ue->enrolid, 'enrol' => $ue->enrol, 'courseid' => $ue->courseid];
                    $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                }
                break;
            case self::ACTION_DISABLE_ENROLMENT_REMOVE_ROLES:
                foreach ($enrolments as $ue) {
                    $instance = (object)['id' => $ue->enrolid, 'enrol' => $ue->enrol, 'courseid' => $ue->courseid];
                    $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                    role_unassign_all(array('userid' => $ue->userid, 'contextid' => $ue->contextid,
                                            'component' => 'enrol_dynamicrule'));
                }
                break;
            case self::ACTION_UNENROL:
                foreach ($enrolments as $ue) {
                    $instance = (object)['id' => $ue->enrolid, 'enrol' => $ue->enrol, 'courseid' => $ue->courseid];
                    $plugin->unenrol_user($instance, $ue->userid);
                }
                break;
        }
    }

    /**
     * Return the list of user_enrolments id in the configured course for given users
     *
     * @param array $users The users objects to find user_enrolments for
     * @return array
     */
    private function get_users_enrolments(array $users): array {
        global $DB;
        list($usersql, $params) = $DB->get_in_or_equal(array_column($users, 'id'), SQL_PARAMS_NAMED);
        $params['courseid'] = $this->get_courseid();
        $coursectxlevel = CONTEXT_COURSE;
        $sql = "SELECT ue.id, ue.userid, ue.enrolid, ue.status, e.enrol, ctx.id as contextid, e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON (e.id = ue.enrolid)
                  JOIN {context} ctx
                    ON (ctx.instanceid = e.courseid AND ctx.contextlevel = {$coursectxlevel})
                 WHERE e.courseid = :courseid
                   AND ue.userid {$usersql}
                   AND e.enrol = 'dynamicrule'";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Return the course id configured for this outcome
     *
     * @return int
     */
    private function get_courseid(): int {
        return (int)$this->get_configdata()['coursetounenrol'];
    }

    /**
     * Return the action configured for this outcome
     *
     * @return int
     */
    private function get_action(): int {
        return (int)$this->get_configdata()['action'];
    }

    /**
     * Return the list of possible actions
     *
     * @return array
     */
    private function get_actions(): array {
        return [self::ACTION_DISABLE_ENROLMENT => get_string('actiondisableenrolment', 'enrol_dynamicrule'),
                self::ACTION_DISABLE_ENROLMENT_REMOVE_ROLES => get_string('actiondisableenrolmentremoveroles', 'enrol_dynamicrule'),
                self::ACTION_UNENROL => get_string('actionunenrol', 'enrol_dynamicrule')];
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
}
