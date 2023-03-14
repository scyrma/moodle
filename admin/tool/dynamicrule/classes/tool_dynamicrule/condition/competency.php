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
 * This file contains the base class for competency condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use core_reportbuilder\local\helpers\database;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_dynamicrule\rule;

/**
 * The base class for competency condition
 *
 * @package    tool_dynamicrule
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class competency extends \tool_dynamicrule\condition_sql {

    /**
     * Validates the configform of the condition.
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        // We don't expect invalid competency. Error will be thrown on saving if there is one.
        return [];
    }

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
        return get_string('conditioncompetencytitle', 'tool_dynamicrule');
    }

    /**
     * Adds condition's elements to the given mform.
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        // Competencies selector.
        $options = [
            'ajax' => 'tool_dynamicrule/form_competency_selector',
            'multiple' => false,
            'class' => 'select_competency',
            'valuehtmlcallback' => static function($competencyid) {
                $competency = new \core_competency\competency($competencyid);
                $options = ['context' => \context_system::instance(), 'escape' => false];
                return format_string($competency->get('shortname'), true, $options);
            }

        ];
        $str = get_string('conditioncompetencyselector', 'tool_dynamicrule');
        $mform->addElement('autocomplete', 'competencyid', $str, $this->get_selected(), $options);
        $mform->addHelpButton('competencyid', 'conditioncompetencyselector', 'tool_dynamicrule');
        $mform->addRule('competencyid', get_string('required'), 'required', null, 'client');
    }

    /**
     * Return id and name of selected competencies.
     *
     * @return array
     */
    private function get_selected(): array {
        $selected = [];
        if ($this->get_competencyid()) {
            $selected[$this->get_competencyid()] = $this->get_competency_name();
        }
        return $selected;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $cu = database::generate_alias();
        $join = "JOIN {competency_usercomp} {$cu}
                   ON {$cu}.userid = u.id";

        $competencyid = database::generate_param_name();
        $proficiency = database::generate_param_name();
        // Setting proficiency = 1 'where' condition means "proficient" (0 is used for non-proficient).
        $where = "{$cu}.competencyid = :{$competencyid} AND {$cu}.proficiency = :{$proficiency}";
        $params = [$competencyid => $this->get_competencyid(), $proficiency => 1];

        return [$join, $where, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('conditioncompetencydescription', 'tool_dynamicrule', $this->get_competency_name());
    }

    /**
     * If the current user is able to add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        // For now this outcome works for system context compentency only.
        // TODO WP-1616.
        return has_capability('moodle/competency:competencyview', \context_system::instance());
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $competency = new \core_competency\competency($configdata['competencyid']);
        return has_capability('moodle/competency:competencyview', $competency->get_context());
    }

    /**
     * Return the configured competencyid.
     *
     * @return null|int
     */
    protected function get_competencyid(): ?int {
        return $this->get_configdata()['competencyid'] ?? null;
    }

    /**
     * Return formatted competency name.
     *
     * @param int|null $competencyid
     * @return string
     */
    private function get_competency_name(?int $competencyid = null): string {
        $competency = new \core_competency\competency($competencyid ?? $this->get_competencyid());
        $options = ['context' => $competency->get_context(), 'escape' => false];
        return format_string($competency->get('shortname'), true, $options);
    }

    /**
     * If the current user is able to use this condition.
     *
     * @return bool
     */
    public static function is_available(): bool {
        global $DB;

        $query = "SELECT c.*
                    FROM {competency} c
                    JOIN {competency_framework} cf ON (c.competencyframeworkid = cf.id)
                   WHERE cf.contextid = :contextid";
        $params = ['contextid' => \context_system::instance()->id];
        return $DB->record_exists_sql($query, $params);
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
        return get_string('noavailablecompetencies', 'tool_dynamicrule');
    }

    /**
     * Check if competency still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('competency', ['id' => $this->get_competencyid()]);
    }

    /**
     * Condition broken label.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;
        if (!$DB->record_exists('competency', ['id' => $this->get_competencyid()])) {
            return get_string('errorinvalidcompetency', 'tool_dynamicrule');
        }

        return $this->get_broken_description();
    }

    /**
     * Add competencyid condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('competency', $this->get_competencyid());
    }

    /**
     * Get competencyid condition field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['competencyid'] = $importer->get_mapping('competency', $this->get_competencyid(), IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }
}
