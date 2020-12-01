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
 * This file contains the backend class for course unenrol outcome.
 *
 * @package    enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace enrol_dynamicrule\tool_dynamicrule\outcome;

use tool_wp\exporter_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for course_enrol outcome
 *
 * @package    enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_unenrol extends \tool_dynamicrule\outcome_base {
    /** @var \enrol_plugin Enrol plugin instance */
    private $enrolplugin;

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
        $action = $this->get_action();
        $strparams = ['coursename' => $coursefullname, 'action' => $this->get_actions()[$action]];

        return get_string('outcomecourseunenroldescription', 'enrol_dynamicrule', $strparams);
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
        $mform->addElement('course', 'coursetounenrol', get_string('course'),
            ['requiredcapabilities' => ['enrol/dynamicrule:unenrol']]);
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
     * Helper function called before outcome is applied to user.
     */
    public function setup_for_applying(): void {
        if (!enrol_is_enabled('dynamicrule')) {
            // Force plugin enabling.
            $enabled = enrol_get_plugins(true);
            $enabled['dynamicrule'] = true;
            set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        }
        $this->enrolplugin = enrol_get_plugin('dynamicrule');
    }

    /**
     * Apply this outcome to a given user
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $userenrolment = $this->get_user_enrolment($user);
        switch ($this->get_action()) {
            case self::ACTION_DISABLE_ENROLMENT:
                $instance = (object)['id' => $userenrolment->enrolid, 'enrol' => $userenrolment->enrol,
                    'courseid' => $userenrolment->courseid];
                $this->enrolplugin->update_user_enrol($instance, $userenrolment->userid, ENROL_USER_SUSPENDED);
                break;
            case self::ACTION_DISABLE_ENROLMENT_REMOVE_ROLES:
                $instance = (object)['id' => $userenrolment->enrolid, 'enrol' => $userenrolment->enrol,
                    'courseid' => $userenrolment->courseid];
                $this->enrolplugin->update_user_enrol($instance, $userenrolment->userid, ENROL_USER_SUSPENDED);
                role_unassign_all(['userid' => $userenrolment->userid, 'contextid' => $userenrolment->contextid,
                                        'component' => 'enrol_dynamicrule']);
                break;
            case self::ACTION_UNENROL:
                $instance = (object)['id' => $userenrolment->enrolid, 'enrol' => $userenrolment->enrol,
                    'courseid' => $userenrolment->courseid];
                $this->enrolplugin->unenrol_user($instance, $userenrolment->userid);
                break;
        }
    }

    /**
     * Return user enrolment record in the configured course.
     *
     * @param \stdClass $user The user object to find user_enrolments for
     * @return \stdClass|false
     */
    private function get_user_enrolment(\stdClass $user) {
        global $DB;
        $params = [
            'courseid' => $this->get_courseid(),
            'userid' => $user->id,
        ];
        $coursectxlevel = CONTEXT_COURSE;
        $sql = "SELECT ue.id, ue.userid, ue.enrolid, ue.status, e.enrol, ctx.id as contextid, e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON (e.id = ue.enrolid)
                  JOIN {context} ctx
                    ON (ctx.instanceid = e.courseid AND ctx.contextlevel = {$coursectxlevel})
                 WHERE e.courseid = :courseid
                   AND ue.userid = :userid
                   AND e.enrol = 'dynamicrule'";
        return $DB->get_record_sql($sql, $params);
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
     * If the current user is able to use this outcome.
     *
     * Return true if there are courses where user can unenrol.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return (bool) \core_course_category::search_courses_count(['search' => ''],
            [], ['enrol/dynamicrule:unenrol']);
    }

    /**
     * Outcome not available label.
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('noavailablecoursesunenrol', 'enrol_dynamicrule');
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
     * Add coursetounenrol outcome field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('course', $this->get_courseid());
    }

    /**
     * Get coursetounenrol outcome field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['coursetounenrol'] = $importer->get_mapping('course', $this->get_courseid(), IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }

    /**
     * Outcome broken label.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;
        if (!$DB->record_exists('course', ['id' => $this->get_courseid()])) {
            return get_string('errorinvalidcoursetounenrol', 'enrol_dynamicrule');
        }

        return parent::get_broken_description();
    }

    /**
     * User is able to add this outcome, but this might not be listed in the menu,
     * as its availability is determined using is_available() method used in
     * menu rendering.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return true;
    }

    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        // Check permissions to unenrol users in the selected course.
        $coursecontext = \context_course::instance($configdata['coursetounenrol']);
        return has_capability('enrol/dynamicrule:unenrol', $coursecontext);
    }
}
