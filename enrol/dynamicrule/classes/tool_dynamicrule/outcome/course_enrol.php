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
 * This file contains the backend class for course enrol outcome.
 *
 * @package    enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace enrol_dynamicrule\tool_dynamicrule\outcome;

use tool_wp\exporter_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot. '/group/lib.php');

/**
 * The backend class for course_enrol outcome
 *
 * @package    enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_enrol extends \tool_dynamicrule\outcome_base {
    /** @var \stdClass Enrolment instance */
    private $enrolinstance;
    /** @var \enrol_plugin Enrol plugin instance */
    private $enrolplugin;
    /** @var int Group ID */
    private $groupid = 0;

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

        $strparams['coursename'] = $coursefullname;
        $roleid = $this->get_roleid();
        $strparams['role'] = $this->get_roles()[$roleid];

        $enddate = $this->get_enddate();
        $duration = $this->get_duration();
        if ($enddate > 0) {
            $strid = 'outcomecourseenroldescriptionwithenddate';
            $strparams['enddate'] = userdate($enddate, get_string('strftimedatefullshort'));
        } else if ($duration > 0) {
            $strid = 'outcomecourseenroldescriptionwithduration';
            if ($duration < 60) {
                $strparams['duration'] = $duration;
                $strparams['durationtype'] = get_string('seconds');
            } else if ($duration < HOURSECS) {
                $strparams['duration'] = $duration / 60;
                $strparams['durationtype'] = get_string('minutes');
            } else if ($duration < DAYSECS) {
                $strparams['duration'] = $duration / HOURSECS;
                $strparams['durationtype'] = get_string('hours');
            } else if ($duration < (DAYSECS * 7)) {
                $strparams['duration'] = $duration / DAYSECS;
                $strparams['durationtype'] = get_string('days');
            } else {
                $strparams['duration'] = $duration / (DAYSECS * 7);
                $strparams['durationtype'] = get_string('weeks');
            }
        } else {
            $strid = 'outcomecourseenroldescription';
        }

        $strparams['groupname'] = $this->get_groupname();
        if (!$strparams['groupname']) {
            $strparams['groupname'] = get_string('none');
        }

        return get_string($strid, 'enrol_dynamicrule', $strparams);
    }

    /**
     * Return the end date for this outcome.
     *
     * @return int
     */
    private function get_enddate(): int {
        if (isset($this->get_configdata()['enddate'])) {
            return $this->get_configdata()['enddate'];
        }
        return 0;
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

        $mform->addElement('course', 'coursetoenrol', get_string('course'),
            ['requiredcapabilities' => ['enrol/dynamicrule:enrol']]);
        $mform->addRule('coursetoenrol', null, 'required', null, 'client');
        $mform->setType('coursetoenrol', PARAM_INT);

        $mform->addElement('select', 'role', get_string('role'), $this->get_roles());
        $mform->addRule('role', null, 'required', null, 'client');
        $mform->setType('role', PARAM_INT);

        $options = ['optional' => true];
        $mform->addElement('date_time_selector', 'enddate', get_string('enddate', 'enrol_dynamicrule'), $options);
        $mform->setType('enddate', PARAM_INT);

        $options = ['optional' => true, 'defaultunit' => DAYSECS];
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
     * Helper function called before outcome is applied to user.
     */
    public function setup_for_applying(): void {
        if (!enrol_is_enabled('dynamicrule')) {
            // Force plugin enabling.
            $enabled = enrol_get_plugins(true);
            $enabled['dynamicrule'] = true;
            set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        }

        // Prepare required class instances we need for enrolments.
        $this->enrolinstance = $this->get_enrol_instance();
        $this->enrolplugin = enrol_get_plugin('dynamicrule');
        if ($this->get_groupname()) {
            $this->groupid = $this->get_groupid();
        }
    }

    /**
     * Apply this outcome to a given user
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $this->enrolplugin->enrol_user($this->enrolinstance, $user->id, $this->get_roleid(),
            0, $this->get_enrol_timeend());
        if ($this->groupid) {
            groups_add_member($this->groupid, $user->id, 'enrol_dynamicrule');
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
     * Return the enddate, in unix timestamp format, configured for this outcome.
     *
     * @return int
     */
    private function get_timeend(): int {
        if (isset($this->get_configdata()['enddate'])) {
            return $this->get_configdata()['enddate'];
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
     * If the current user is able to use this outcome.
     *
     * Return true if there are courses where user can enrol.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return (bool) \core_course_category::search_courses_count(['search' => ''],
            [], ['enrol/dynamicrule:enrol']);
    }

    /**
     * Outcome not available label.
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('noavailablecoursesenrol', 'enrol_dynamicrule');
    }

    /**
     * Check configuration.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('course', ['id' => $this->get_courseid()]) &&
            $DB->record_exists('role', ['id' => $this->get_roleid()]);
    }

    /**
     * Outcome broken label.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;
        if (!$DB->record_exists('course', ['id' => $this->get_courseid()])) {
            return get_string('errorinvalidcoursetoenrol', 'enrol_dynamicrule');
        }

        if (!$DB->record_exists('role', ['id' => $this->get_roleid()])) {
            return get_string('errorinvalidrole', 'enrol_dynamicrule');
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
        // Check permissions to enrol users in the selected course.
        $coursecontext = \context_course::instance($configdata['coursetoenrol']);
        return has_capability('enrol/dynamicrule:enrol', $coursecontext);
    }

    /**
     * Add coursetoenrol outcome field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('course', $this->get_courseid());
    }

    /**
     * Get coursetoenrol outcome field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['coursetoenrol'] = $importer->get_mapping('course', $this->get_courseid(), IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }
}
