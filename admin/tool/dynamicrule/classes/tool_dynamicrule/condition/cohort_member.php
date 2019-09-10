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
 * This file contains the backend class for cohort_member condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/cohort/lib.php');

/**
 * The backend class for cohort_member condition
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_member extends \tool_dynamicrule\condition_sql {

    /**
     * Returns the title of the condition.
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditioncohortmember', 'tool_dynamicrule');
    }

    /**
     * Adds outcome's elements to the given mform.
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        $mform->addElement('cohort', 'cohortid', get_string('cohort', 'tool_dynamicrule'));
        $mform->addRule('cohortid', null, 'required', null, 'client');
        $mform->setType('cohortid', PARAM_INT);

        $options = ['optional' => true];
        $mform->addElement('date_time_selector', 'timeadded', get_string('timeadded', 'tool_dynamicrule'), $options);
        $mform->setDefault('timeadded', time());
    }

    /**
     * Validates the configform of the outcome.
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        global $DB;
        $errors = [];
        if (!isset($data['cohortid']) || !$DB->record_exists('cohort', ['id' => $data['cohortid']])) {
            $errors['cohortid'] = get_string('errorinvalidcohort', 'tool_dynamicrule');
        }
        return $errors;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {

        $cm = \tool_dynamicrule\api::generate_alias();

        $join = "JOIN {cohort_members} {$cm}
                   ON ({$cm}.userid = u.id)";

        $cohortid = \tool_dynamicrule\api::generate_param_name();

        $where = "{$cm}.cohortid = :{$cohortid}";

        $params = [$cohortid => $this->get_cohortid()];

        $timeadded = $this->get_timeadded();
        if (!is_null($timeadded)) {
            $timeparam = \tool_dynamicrule\api::generate_param_name();

            $where .= " AND {$cm}.timeadded >= :{$timeparam}";
            $params[$timeparam] = $timeadded;
        }

        return [$join, $where, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $str = 'conditioncohortmemberdescription';
        return get_string($str, 'tool_dynamicrule', $this->get_cohort_name());
    }

    /**
     * Return the configured cohortid.
     *
     * @return int
     */
    private function get_cohortid(): int {
        return $this->get_configdata()['cohortid'];
    }

    /**
     * Return the formatted cohort name.
     *
     * @return string
     */
    private function get_cohort_name(): string {
        global $DB;
        $cohortname = $DB->get_field('cohort', 'name', ['id' => $this->get_cohortid()]);
        return format_string($cohortname, true, ['context' => \context_system::instance(), 'escape' => false]);
    }

    /**
     * Return the configured time.
     *
     * @return int|null
     */
    private function get_timeadded() {
        if (isset($this->get_configdata()['timeadded'])) {
            return $this->get_configdata()['timeadded'];
        }
        return null;
    }

    /**
     * If the current user is able to use this condition (for any purpose).
     * Condition classes may override this method to make permissions check or
     * avoid access to information the user does not have access to.
     *
     * @return bool
     */
    public static function is_available(): bool {
        $cohorts = cohort_get_all_cohorts();
        return ($cohorts['totalcohorts'] > 0);
    }

    /**
     * Check if cohort still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('cohort', ['id' => $this->get_cohortid()]);
    }

    /**
     * Event subscription.
     *
     * @return string|false eventname or false if no subscription.
     */
    public function get_event_subscription() {
        return '\core\event\cohort_member_added';
    }
}
