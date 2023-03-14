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

declare(strict_types=1);

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use core_competency\api as competency_api;
use core_competency\template;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_dynamicrule\rule;

/**
 * The backend class for learning_plan outcome
 *
 * @package    tool_dynamicrule
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class learning_plan extends \tool_dynamicrule\outcome_base {

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomelearningplan', 'tool_dynamicrule');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform): void {
        // Learning plan templates selector.
        $options = [
            'ajax' => 'tool_dynamicrule/form_lp_selector',
            'multiple' => false,
        ];
        $selected = $this->get_selected();
        $mform->addElement('autocomplete', 'learningplan', get_string('selectlearningplan', 'tool_dynamicrule'),
            $selected, $options);
        $mform->addHelpButton('learningplan', 'selectlearningplan', 'tool_dynamicrule');
        $mform->addRule('learningplan', get_string('required'), 'required', null, 'client');
    }

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];
        if (!isset($data['learningplan']) || !template::record_exists($data['learningplan'])) {
            $errors['learningplan'] = get_string('errorinvalidlearningplan', 'tool_dynamicrule');
        }
        return $errors;
    }

    /**
     * Apply this outcome to a given user.
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $lptemplateid = $this->get_lptemplate()->get('id');
        competency_api::create_plan_from_template($lptemplateid, (int)$user->id);
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        $options = ['context' => \context_system::instance(), 'escape' => false];
        $shortname = format_string($this->get_lptemplate()->get('shortname'), true, $options);
        return get_string('outcomelearningplandescription', 'tool_dynamicrule', $shortname);
    }

    /**
     * Return the description for the outcome when current user does not have permission to edit it
     *
     * @return string
     */
    public function get_uneditable_description(): string {
        if ($this->user_can_edit([])) {
            // User can view this learning plan template.
            return $this->get_description();
        }
        return parent::get_uneditable_description();
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
        return get_string('outcomelearningplanbroken', 'tool_dynamicrule', $this->get_lptemplate()->get('id'));
    }

    /**
     * Check if learning plan configuration is valid.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return template::record_exists($this->get_lptemplate()->get('id'));
    }

    /**
     * If the current user is able to add this outcome.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return has_capability('moodle/competency:planmanage', \context_system::instance());
    }

    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return $this->user_can_add();
    }

    /**
     * Return the configured learning plan template id.
     *
     * @return template|null
     */
    private function get_lptemplate(): ?template {
        if (isset($this->get_configdata()['learningplan'])) {
            return new template((int)$this->get_configdata()['learningplan']);
        }
        return null;
    }

    /**
     * Return id and name of selected learning plan.
     *
     * @return array
     */
    private function get_selected(): array {
        $selected = [];
        if ($this->get_lptemplate()) {
            $options = ['context' => \context_system::instance(), 'escape' => false];
            $shortname = format_string($this->get_lptemplate()->get('shortname'), true, $options);
            $selected[$this->get_lptemplate()->get('id')] = $shortname;
        }
        return $selected;
    }

    /**
     * Add learning plan outcome field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('learningplan', $this->get_lptemplate()->get('id'));
    }

    /**
     * Get learning plan outcome field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['learningplan'] = $importer->get_mapping('learningplan', $configdata['learningplan'], IGNORE_MISSING) ?? 0;

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
