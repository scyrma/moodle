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
 * This file contains the backend class for cohort outcome.
 *
 * @package    tool_dynamicrule
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use tool_wp\exporter_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/cohort/lib.php');

/**
 * The backend class for cohort outcome
 *
 * @package    tool_dynamicrule
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cohort extends \tool_dynamicrule\outcome_base {

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomecohort', 'tool_dynamicrule');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        // For now we use only context system.
        $options = ['contextid' => \context_system::instance()->id, 'multiple' => false];
        $mform->addElement('cohort', 'cohortid', get_string('cohort', 'tool_dynamicrule'), $options);
        $mform->addRule('cohortid', null, 'required', null, 'client');
        $mform->setType('cohortid', PARAM_INT);

        // Manage cohorts link.
        $managecohortsurl = new \moodle_url('/cohort/index.php');
        $managecohortsstr = get_string('managecohorts', 'tool_dynamicrule');
        $html = \html_writer::link($managecohortsurl, $managecohortsstr);
        $mform->addElement('static', 'managecohorts', '', $html);
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
        $params = ['id' => $data['cohortid'], 'contextid' => \context_system::instance()->id];
        if (!isset($data['cohortid']) || !$DB->record_exists('cohort', $params)) {
            $errors['cohortid'] = get_string('errorinvalidcohort', 'tool_dynamicrule');
        }
        return $errors;
    }

    /**
     * Apply this outcome on a given list of users
     *
     * @param array $users The users objects to apply the outcome to
     */
    public function apply_to_users(array $users): void {
        if ($cohortid = $this->get_cohortid()) {
            foreach ($users as $user) {
                cohort_add_member($cohortid, $user->id);
            }
        }
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('outcomecohortdescription', 'tool_dynamicrule', $this->get_cohort_name());
    }

    /**
     * Check if cohort is not empty.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('cohort', ['id' => $this->get_cohortid(), 'contextid' => \context_system::instance()->id]);
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
     * If the current user is able to add this outcome.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return has_capability('moodle/cohort:assign', \context_system::instance()) &&
            has_any_capability(['moodle/cohort:manage', 'moodle/cohort:view'], \context_system::instance());
    }

    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        global $DB;
        $cohort = $DB->get_record('cohort', ['id' => $configdata['cohortid']]);
        $context = \context::instance_by_id($cohort->contextid, MUST_EXIST);

        return has_capability('moodle/cohort:assign', $context) &&
            has_any_capability(['moodle/cohort:manage', 'moodle/cohort:view'], $context);
    }

    /**
     * If the current user is able to use this outcome.
     *
     * @return bool
     */
    public static function is_available(): bool {
        // For now this outcome works for system context cohorts only.
        // TODO WP-1459.
        $cohorts = cohort_get_cohorts(\context_system::instance()->id);
        return ($cohorts['totalcohorts'] > 0);
    }


    /**
     * Add cohortid outcome field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('cohort', $this->get_cohortid());
    }

    /**
     * Get cohortid outcome field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['cohortid'] = $importer->get_mapping('cohort', $this->get_cohortid(), IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }

    /**
     * Outcome not available label.
     *
     * Outcomes may provide more detailed information why it is not available when
     * is_available returns false.
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('noavailablecohorts', 'tool_dynamicrule');
    }

    /**
     * Outcome broken label.
     *
     * Outcomes may provide more detailed information on what is broken when
     * is_configuration_valid returns false.
     *
     * @return string
     */
    public function get_broken_description(): string {
        return get_string('outcomecohortbroken', 'tool_dynamicrule', $this->get_cohortid());
    }
}