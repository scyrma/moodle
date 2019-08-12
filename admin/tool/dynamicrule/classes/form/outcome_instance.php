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
 * Class outcome_instance
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\form;

use tool_dynamicrule\outcome;
use tool_dynamicrule\outcome_base;
use tool_dynamicrule\permission;
use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class outcome_instance
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_instance extends modal_form {

    /** @var outcome_base */
    protected $outcome;

    /**
     * Outcome we work with
     *
     * @return outcome_base
     * @throws \coding_exception
     */
    protected function get_outcome(): outcome_base {

        $instanceclass = $this->optional_param('instanceclass', null, PARAM_RAW_TRIMMED);
        $id = $this->optional_param('id', null, PARAM_INT);

        // Get the full class name.
        list($plugin, $classname) = explode(':', $instanceclass);
        $outcomeclass = '\\' . $plugin . '\\tool_dynamicrule\\outcome\\' . $classname;
        if (class_exists($outcomeclass) && is_subclass_of($outcomeclass, \tool_dynamicrule\outcome_base::class)) {
            return new $outcomeclass($id);
        }

        throw new \coding_exception('Insufficient parameters');
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
    public function set_data_for_modal() {
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
    public function require_access() {
        $outcome = $this->get_outcome();
        $ruleid = $outcome->get_ruleid() ?: $this->optional_param('ruleid', null, PARAM_INT);
        // Validate that rule exists/belongs to the same tenant.
        $rule = \tool_dynamicrule\api::get_rule($ruleid);
        permission::require_can_edit_rule($rule);
    }

    /**
     * Process the form submission
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * @param \stdClass $formdata
     * @return mixed
     */
    public function process(\stdClass $formdata) {
        $outcome = $this->get_outcome();

        $configdata = $outcome::retrieve_configdata($formdata);
        if (!$formdata->id) {
            // New outcome.
            $outcome = $outcome::create($formdata->ruleid, $configdata);
        } else {
            // Editing outcome.
            $outcome->update_configdata($configdata);
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
    protected function get_page_url_for_modal(): \moodle_url {
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
}
