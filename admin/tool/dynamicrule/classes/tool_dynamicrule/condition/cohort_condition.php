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
 * This file contains the base class for cohort condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_dynamicrule\rule;
use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/cohort/lib.php');

/**
 * The base class for cohort condition
 *
 * @package    tool_dynamicrule
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class cohort_condition extends \tool_dynamicrule\condition_sql {

    /**
     * Return id and name of selected cohort.
     *
     * @return array
     */
    protected function get_selected(): array {
        if ($this->get_cohortid()) {
            $selected = [$this->get_cohortid() => $this->get_cohort_name()];
        } else {
            $selected = [];
        }
        return $selected;
    }

    /**
     * Adds condition's elements to the given mform.
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        global $DB;
        $options = [
            'ajax' => 'tool_dynamicrule/form_cohort_selector',
            'multiple' => false,
            'data-instancetype' => 'condition',
            'valuehtmlcallback' => static function($cohortid) use ($DB) {
                $cohort = $DB->get_record('cohort', ['id' => $cohortid]);
                return format_string($cohort->name, true, ['context' => $cohort->contextid, 'escape' => false]);
            }
        ];
        $selected = $this->get_selected();
        $mform->addElement('autocomplete', 'cohortid', get_string('cohort', 'tool_dynamicrule'), $selected, $options);
        $mform->addRule('cohortid', null, 'required', null, 'client');
        $mform->setType('cohortid', PARAM_INT);
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
        $contextid = $DB->get_field('cohort', 'contextid', ['id' => $data['cohortid']], MUST_EXIST);
        $context = \context::instance_by_id($contextid);
        if ($context->contextlevel == CONTEXT_COURSECAT &&
                !api::is_course_category_allowed_in_rule($this->get_rule(), $context->instanceid)) {
            $errors['cohortid'] = get_string('errorinvalidcohorttenant', 'tool_dynamicrule');
        }
        return $errors;
    }

    /**
     * If the current user is able to add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        // Check system context first.
        if (has_capability('moodle/cohort:view', \context_system::instance())) {
            return true;
        }
        // If there is at least one category with given permissions, user can add.
        return !empty(\core_course_category::make_categories_list('moodle/cohort:view'));
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        global $DB;
        $contextid = $DB->get_field('cohort', 'contextid', ['id' => $configdata['cohortid']], MUST_EXIST);
        $context = \context::instance_by_id($contextid, MUST_EXIST);
        return has_capability('moodle/cohort:view', $context);
    }

    /**
     * Return the configured cohortid.
     *
     * @return null|int
     */
    protected function get_cohortid(): ?int {
        return $this->get_configdata()['cohortid'] ?? null;
    }

    /**
     * Return the formatted cohort name.
     *
     * @return string
     */
    protected function get_cohort_name(): string {
        global $DB;
        $cohort = $DB->get_record('cohort', ['id' => $this->get_cohortid()]);
        return format_string($cohort->name, true, ['context' => $cohort->contextid, 'escape' => false]);
    }

    /**
     * If the current user is able to use this condition.
     *
     * @return bool
     */
    public static function is_available(): bool {
        global $DB;

        $contextids = [];

        // Put together system and category contexts user can access.
        $categories = \core_course_category::make_categories_list('moodle/cohort:view');
        foreach (array_keys($categories) as $categoryid) {
            $contextids[] = \context_coursecat::instance($categoryid)->id;
        }

        $systemcontext = \context_system::instance();
        if (has_capability('moodle/cohort:view', $systemcontext)) {
            $contextids[] = $systemcontext->id;
        }

        if (count($contextids)) {
            // Find if there is at least one cohort in any of contexts.
            [$select, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'cohort');
            return $DB->record_exists_select('cohort', "contextid {$select}", $params);
        }

        return false;
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
        return $DB->record_exists('cohort', ['id' => $this->get_cohortid()]);
    }

    /**
     * Condition broken label.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;
        if (!$DB->record_exists('cohort', ['id' => $this->get_cohortid()])) {
            return get_string('errorinvalidcohort', 'tool_dynamicrule');
        }

        return parent::get_broken_description();
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

    /**
     * Which rule types this condition supports.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return rule::TYPE_NORMAL + rule::TYPE_SHARED;
    }
}
