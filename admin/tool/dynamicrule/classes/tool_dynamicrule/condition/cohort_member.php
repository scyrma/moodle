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
 * This file contains the backend class for cohort_member condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_wp\exporter_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/cohort/lib.php');

/**
 * The backend class for cohort_member condition
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
        if (!isset($data['cohortid']) || !$DB->record_exists('cohort',
                ['id' => $data['cohortid'], 'contextid' => \context_system::instance()->id])) {
            $errors['cohortid'] = get_string('errorinvalidcohort', 'tool_dynamicrule');
        }
        return $errors;
    }

    /**
     * If the current user is able to add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        // For now this condition works for system context cohorts only.
        // TODO WP-1459.
        return has_any_capability(array('moodle/cohort:manage', 'moodle/cohort:view'), \context_system::instance());
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return \tool_dynamicrule\permission::can_edit_cohort_condition($configdata['cohortid']);
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
     * Return the configured conditiondate
     *
     * @return int|null
     */
    private function get_conditiondate(): ?int {
        return $this->get_configdata()['timeadded'] ?? null;
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        if ($date = $this->get_conditiondate()) {
            $strid = 'conditioncohortmemberdescriptionwithdate';
            $date = userdate($date, get_string('strftimedatefullshort'));
            $strparams = ['name' => $this->get_cohort_name(), 'conditiondate' => $date];
            $description = get_string($strid, 'tool_dynamicrule', $strparams);
        } else {
            $description = get_string('conditioncohortmemberdescription', 'tool_dynamicrule', $this->get_cohort_name());
        }

        return $description;
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
     * If the current user is able to use this condition.
     *
     * @return bool
     */
    public static function is_available(): bool {
        // For now this condition works for system context cohorts only.
        // TODO WP-1459.
        $cohorts = cohort_get_cohorts(\context_system::instance()->id);
        return ($cohorts['totalcohorts'] > 0);
    }

    /**
     * Condition not available label.
     *
     * Conditions may provide more detailed information why it is not available when
     * is_available returns false.
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('noavailablecohorts', 'tool_dynamicrule');
    }

    /**
     * Check if cohort still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('cohort', ['id' => $this->get_cohortid(),
            'contextid' => \context_system::instance()->id]);
    }

    /**
     * Condition broken label.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;
        if (!$DB->record_exists('cohort', ['id' => $this->get_cohortid(),
                'contextid' => \context_system::instance()->id])) {
            return get_string('errorinvalidcohort', 'tool_dynamicrule');
        }

        return parent::get_broken_label();
    }

    /**
     * Event subscription.
     *
     * @return string|false eventname or false if no subscription.
     */
    public function get_event_subscription() {
        return '\core\event\cohort_member_added';
    }

    /**
     * Add cohortid condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('cohort', $this->get_cohortid());
    }

    /**
     * Get cohortid condition field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['cohortid'] = $importer->get_mapping('cohort', $this->get_cohortid(), IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }
}
