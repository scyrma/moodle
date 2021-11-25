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
 * This file contains the backend class for competency outcome.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_dynamicrule\rule;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for competency outcome
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class competency extends \tool_dynamicrule\outcome_base {

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomecompetency', 'tool_dynamicrule');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {

        // Competencies selector.
        $options = [
            'ajax' => 'tool_dynamicrule/form_competency_selector',
            'multiple' => false,
            'class' => 'select_competency',
        ];
        $selected = $this->get_selected();
        $mform->addElement('autocomplete', 'competency', get_string('selectcompetency', 'tool_dynamicrule'), $selected, $options);
        $mform->addHelpButton('competency', 'selectcompetency', 'tool_dynamicrule');
        $mform->addRule('competency', get_string('required'), 'required', null, 'client');

        // Manage competencies link.
        $manageprogurl = new \moodle_url('/admin/tool/lp/competencyframeworks.php?pagecontextid=1');
        $manageprogramsstr = get_string('managecompetencies', 'tool_dynamicrule');
        $html = \html_writer::tag('a', $manageprogramsstr, ['href' => $manageprogurl, 'target' => '_blank']);
        $mform->addElement('static', 'managecompetencies', '', $html);
    }

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];
        if (!isset($data['competency']) || !\core_competency\competency::record_exists($data['competency'])) {
            $errors['competency'] = get_string('errorinvalidcompetency', 'tool_dynamicrule');
        }
        return $errors;
    }

    /**
     * Apply this outcome to a given user.
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $competencyid = $this->get_competencyid();
        $uc = \core_competency\api::get_user_competency($user->id, $competencyid);
        // TODO: Investigate purpose and use of scale id '2' WP-2362.
        \core_competency\api::grade_competency($uc->get('userid'), $uc->get('competencyid'), 2);
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('outcomecompetencydescription', 'tool_dynamicrule', $this->get_competency_name());
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
        return get_string('outcomecompetencybroken', 'tool_dynamicrule', $this->get_competencyid());
    }

    /**
     * Return subject formatted.
     *
     * @return string
     */
    private function get_competency_name(): string {
        $id = $this->get_competencyid();
        $competency = new \core_competency\competency($id);
        $options = ['context' => \context_system::instance(), 'escape' => false];
        return format_string($competency->get('shortname'), true, $options);
    }

    /**
     * Check if competency configuration is valid.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return \core_competency\competency::record_exists($this->get_competencyid());
    }

    /**
     * If the current user is able to add this outcome.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        // For now this outcome works for system context compentency only.
        // TODO WP-1616.
        return has_capability('moodle/competency:competencygrade', \context_system::instance());
    }

    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $competency = new \core_competency\competency($configdata['competency']);
        return has_capability('moodle/competency:competencygrade', $competency->get_context());
    }

    /**
     * Return the configured competency id.
     *
     * @return int|null
     */
    public function get_competencyid() : ?int {
        if (isset($this->get_configdata()['competency'])) {
            return $this->get_configdata()['competency'];
        }
        return null;
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
     * Add competency outcome field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('competency', $this->get_competencyid());
    }

    /**
     * Get competency outcome field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['competency'] = $importer->get_mapping('competency', $this->get_competencyid(), IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }

    /**
     * Which rule types this outcome supports.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return rule::TYPE_NORMAL + rule::TYPE_SHARED;
    }
}
