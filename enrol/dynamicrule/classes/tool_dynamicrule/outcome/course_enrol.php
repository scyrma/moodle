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
 * This file contains the backend class for course enrol outcome.
 *
 * @package    enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace enrol_dynamicrule\tool_dynamicrule\outcome;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot. '/group/lib.php');

/**
 * The backend class for course_enrol outcome
 *
 * @package    enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_enrol extends \tool_dynamicrule\outcome_base {

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomecourseenrol', 'enrol_dynamicrule');
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;
        $coursefullname = $DB->get_field('course', 'fullname', ['id' => $this->get_courseid()]);
        return get_string('outcomecourseenroldescription', 'enrol_dynamicrule', $coursefullname);
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

        $mform->addElement('course', 'coursetoenrol', get_string('course'));
        $mform->addRule('coursetoenrol', null, 'required', null, 'client');
        $mform->setType('coursetoenrol', PARAM_INT);

        $mform->addElement('select', 'role', get_string('role'), $this->get_roles());
        $mform->addRule('role', null, 'required', null, 'client');
        $mform->setType('role', PARAM_INT);

        $options = ['optional' => true];
        $mform->addElement('date_time_selector', 'enddate', get_string('enddate', 'enrol_dynamicrule'), $options);
        $mform->setType('enddate', PARAM_INT);

        $options = ['optional' => true, 'defaultunit' => 86400];
        $mform->addElement('duration', 'duration', get_string('duration', 'enrol_dynamicrule'), $options);
        $mform->setType('duration', PARAM_INT);

        $mform->addElement('text', 'group', get_string('group', 'enrol_dynamicrule'));
        $mform->setType('group', PARAM_TEXT);
        $mform->addHelpButton('group', 'group', 'enrol_dynamicrule');
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
        if (!$DB->record_exists('course', ['id' => $data['coursetoenrol']])) {
            $errors['coursetoenrol'] = get_string('errorinvalidcoursetoenrol', 'enrol_dynamicrule');
        }
        if (!isset($data['role']) || !$DB->record_exists('role', ['id' => $data['role']])) {
            $errors['role'] = get_string('errorinvalidrole', 'enrol_dynamicrule');
        }
        if (!empty($data['duration']) && !empty($data['enddate'])) {
            $errors['duration'] = get_string('errorinvaliddurationandenddate', 'enrol_dynamicrule');
        }
        return $errors;
    }

    /**
     * Returns an array of courses to use on select field
     *
     * @return array
     */
    private function get_roles(): array {
        return role_fix_names(get_archetype_roles('student'), null, ROLENAME_ALIAS, true ) +
            get_default_enrol_roles(\context_system::instance());
    }

    /**
     * Apply this outcome on a given list of users
     *
     * @param array $users The users objects to apply the outcome on
     */
    public function apply_to_users(array $users) {
        if (empty($users)) {
            return;
        }
        if (!enrol_is_enabled('dynamicrule')) {
            // Force plugin enabling.
            $enabled = enrol_get_plugins(true);
            $enabled['dynamicrule'] = true;
            set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        }
        $this->enrol_users($users);
        $this->add_groups_members($users);
    }

    /**
     * Enrol users on configured course using dynamicrule enrol plugin
     *
     * @param array $users The users objects to apply the outcome on
     */
    private function enrol_users($users) {
        $instance = $this->get_enrol_instance();
        $plugin = enrol_get_plugin('dynamicrule');
        foreach ($users as $user) {
            $plugin->enrol_user($instance, $user->id, $this->get_roleid(), 0, $this->get_enrol_timeend());
        }
    }

    /**
     * Add users to configured group on configured course.
     *
     * @param array $users The users objects to apply the outcome on
     */
    private function add_groups_members($users) {
        if ($this->get_groupname()) {
            $groupid = $this->get_groupid();
            foreach ($users as $user) {
                groups_add_member($groupid, $user->id, 'enrol_dynamicrule');
            }
        }
    }

    /**
     * Return the enrol instance for the configured course.
     * This function creates the enrol instance if there is no dynamicrule enrol on the course.
     *
     * @return \stdClass
     */
    private function get_enrol_instance() {
        global $DB;
        $courseid = $this->get_courseid();

        $previousenrolinstance = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'dynamicrule',
            'customint1' => $this->get_ruleid()
        ]);
        if ($previousenrolinstance) {
            return $previousenrolinstance;
        }

        $plugin = enrol_get_plugin('dynamicrule');
        $enrolid = $plugin->add_instance(get_course($courseid), ['customint1' => $this->get_ruleid()]);
        return $DB->get_record('enrol', ['id' => $enrolid]);
    }

    /**
     * Return the groupid for the group name on the course.
     * This function creates the group if there is no group with configured name on configured course.
     *
     * @return int
     */
    private function get_groupid(): int {
        $courseid = $this->get_courseid();
        $groupname = $this->get_groupname();
        if (!$groupid = \groups_get_group_by_name($courseid, $groupname)) {
            $group = (object)['name' => $groupname, 'courseid' => $courseid];
            $groupid = \groups_create_group($group);
        }
        return $groupid;
    }

    /**
     * Return the timeend of enrol, in unix timestamp format, configured for this outcome from duration or timeend.
     *
     * @return int
     */
    private function get_enrol_timeend(): int {
        if ($duration = $this->get_duration()) {
            $timeend = time() + $duration;
        } else if (!$timeend = $this->get_timeend()) {
            $timeend = 0;
        }
        return $timeend;
    }

    /**
     * Return the duration, in seconds, configured for this outcome.
     *
     * @return int
     */
    private function get_duration(): int {
        if (isset($this->get_configdata()['duration'])) {
            return $this->get_configdata()['duration'];
        }
        return 0;
    }

    /**
     * Return the timeend, in unix timestamp format, configured for this outcome.
     *
     * @return int
     */
    private function get_timeend(): int {
        if (isset($this->get_configdata()['timeend'])) {
            return $this->get_configdata()['timeend'];
        }
        return 0;
    }

    /**
     * Return the roleid configured for this outcome.
     *
     * @return int
     */
    private function get_roleid(): int {
        return $this->get_configdata()['role'];
    }

    /**
     * Return the groupname configured for this outcome.
     *
     * @return string
     */
    private function get_groupname(): string {
        if (isset($this->get_configdata()['group'])) {
            return $this->get_configdata()['group'];
        }
        return '';
    }

    /**
     * Return the configured course to enrol users in
     *
     * @return int
     */
    private function get_courseid(): int {
        return $this->get_configdata()['coursetoenrol'];
    }

    /**
     * Check if course still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('course', ['id' => $this->get_courseid()]) &&
               $DB->record_exists('role', ['id' => $this->get_roleid()]);
    }
}
