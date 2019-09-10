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
 * This file contains the backend class for competency outcome.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\tool_dynamicrule\outcome;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for competency outcome
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $html = \html_writer::tag('a', $manageprogramsstr, ['href' => $manageprogurl]);
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
        return $errors;
    }

    /**
     * Apply this outcome on a given list of users
     *
     * @param array $users The users objects to apply the outcome to
     */
    public function apply_to_users(array $users) {
        global $DB;
        foreach ($users as $user) {
            if ($competencyid = $this->get_competencyid()) {
                $uc = \core_competency\api::get_user_competency($user->id, $competencyid);
                \core_competency\api::grade_competency($uc->get('userid'), $uc->get('competencyid'), 2);
            }
        }
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
     * Return subject formatted.
     *
     * @return string
     */
    private function get_competency_name(): string {
        // TODO SP-381 cache.
        global $DB;
        $cid = (int)$this->get_competencyid();
        if ($cid) {
            $c = $DB->get_record_sql("SELECT * FROM {competency} WHERE id=?", [$cid]);
            if ($c) {
                $options = ['context' => \context_system::instance(), 'escape' => false];
                return format_string($c->shortname, true, $options);
            }
        }
        return '';
    }

    /**
     * Check if competency is not empty.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return !empty($this->get_configdata()['competency']);
    }

    /**
     * Return the configured competency id.
     *
     * @return int
     */
    public function get_competencyid() {
        if (isset($this->get_configdata()['competency'])) {
            $c = $this->get_configdata()['competency'];
        } else {
            $c = null;
        }
        return $c;
    }

    /**
     * Return id and name of selected competencies.
     *
     * @return array
     */
    private function get_selected(): array {
        if ($this->get_competencyid()) {
            $selected = [$this->get_competencyid() => $this->get_competency_name()];
        } else {
            $selected = [];
        }
        return $selected;
    }
}
