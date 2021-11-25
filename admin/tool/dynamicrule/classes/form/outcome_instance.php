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
 * Class outcome_instance
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\form;

use core_form\dynamic_form;
use tool_dynamicrule\outcome_base;
use tool_dynamicrule\permission;

defined('MOODLE_INTERNAL') || die();

/**
 * Class outcome_instance
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class outcome_instance extends dynamic_form {

    /**
     * Outcome we work with
     *
     * @return outcome_base
     * @throws \coding_exception
     */
    protected function get_outcome(): outcome_base {
        $instanceclass = $this->optional_param('instanceclass', null, PARAM_RAW_TRIMMED);
        $id = $this->optional_param('id', 0, PARAM_INT);

        $record = new \stdClass();
        if (!$id) {
            // New instance, pre-define rule id.
            $record->ruleid = $this->optional_param('ruleid', null, PARAM_INT);
            // Get the full class name.
            list($plugin, $classname) = explode(':', $instanceclass);
            $record->classname = '\\' . $plugin . '\\tool_dynamicrule\\outcome\\' . $classname;
        }
        return outcome_base::instance($id, $record);
    }

    /**
     * Renders the html form.
     *
     * Added in case the outcome needs to add some extra JS to the form.
     *
     * @return string HTML code for the form
     */
    public function render() {
        $this->get_outcome()::before_form_render();
        return parent::render();
    }

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'ruleid');
        $mform->setType('ruleid', PARAM_INT);

        $mform->addElement('hidden', 'instanceclass');
        $mform->setType('instanceclass', PARAM_RAW_TRIMMED);

        // Embed form defined in outcome class.
        $outcome = $this->get_outcome();
        $outcome->get_config_form($mform);

        $this->add_action_buttons();
    }

    /**
     * Form validation.
     *
     * We only validate form elements data here, something that can't be achieved
     * with addRule. For capabilities checks, we use user_can_add and
     * user_can_edit methods in the actual outcome.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $outcome = $this->get_outcome();
        return $outcome->validate_config_form($data);
    }

    /**
     * Import data from configdata field into form.
     */
    public function set_data_for_dynamic_submission(): void {
        $outcome = $this->get_outcome();
        if ($outcome->get_id() !== 0) {
            // Populate form with exisiting data.
            $formdata = [
                'id' => $outcome->get_id(),
                'ruleid' => $outcome->get_ruleid(),
            ];
            $formdata += $outcome->get_configdata();
        } else {
            $formdata['ruleid'] = $this->optional_param('ruleid', null, PARAM_INT);
        }
        $formdata['instanceclass'] = $this->optional_param('instanceclass', null, PARAM_RAW_TRIMMED);
        $this->set_data($formdata);
    }

    /**
     * Check if current user has access to this form, otherwise throw exception
     *
     * Sometimes permission check may depend on the action and/or id of the entity.
     * If necessary, form data is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     */
    protected function check_access_for_dynamic_submission(): void {
        $outcome = $this->get_outcome();

        // Validate that rule exists/belongs to the same tenant.
        $rule = \tool_dynamicrule\api::get_rule($outcome->get_ruleid());
        permission::require_can_edit_rule_outcomes($rule);

        // Validate condition instance.
        if (!$outcome->get_id()) {
            // New instance. Validate adding permission.
            permission::require_can_add_outcome($outcome, $rule);
        } else {
            // Existing instance. Validate editing permission.
            permission::require_can_edit_outcome($outcome);
        }
    }

    /**
     * Process the form submission
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        $formdata = $this->get_data();
        $outcome = $this->get_outcome();

        $configdata = $outcome::retrieve_configdata($formdata);
        if (!$formdata->id) {
            // New outcome. Validate new data for serious violations.
            permission::require_can_edit_outcome($outcome, $configdata);
            $outcome = $outcome::create($formdata->ruleid, $configdata);
        } else {
            // Editing outcome. Validate new data for serious violations.
            permission::require_can_edit_outcome($outcome, $configdata);
            $outcome->update_configdata($configdata, true);
        }
        return ['instanceid' => $outcome->get_id(), 'description' => $outcome->get_description()];
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     * If the form has elements sensitive to the page url this method must be overridden
     *
     * Note: autosave function in Atto 'editor' elements is sensitive to page url
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $id = $this->optional_param('id', null, PARAM_INT);

        $params = ['type' => 'outcome'];
        if ($id) {
            $params['id'] = $id;
        } else {
            $params['instanceclass'] = $this->optional_param('instanceclass', null, PARAM_RAW_TRIMMED);
            $params['ruleid'] = $this->optional_param('ruleid', null, PARAM_INT);
        }
        return new \moodle_url('/admin/tool/dynamicrule/edit.php', $params);
    }

    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }
}
