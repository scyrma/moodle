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

namespace tool_custompage\form;

use context;
use context_system;
use core_form\dynamic_form;
use moodle_exception;
use moodle_url;
use stdClass;
use tool_custompage\permission;
use tool_custompage\local\audience\base;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/formslib.php");

/**
 * Page audience form
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience extends dynamic_form {

    /**
     * Audience we work with
     *
     * @return base
     */
    protected function get_audience(): base {
        $id = $this->optional_param('id', 0, PARAM_INT);
        $record = new stdClass();
        if (!$id) {
            // New instance, pre-define page id and classname.
            $record->pageid = $this->optional_param('pageid', null, PARAM_INT);
            $record->classname = $this->optional_param('classname', null, PARAM_RAW_TRIMMED);
        }
        return base::instance($id, $record);
    }

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        $mform->addElement('hidden', 'classname');
        $mform->setType('classname', PARAM_RAW_TRIMMED);

        // Embed form defined in audience class.
        $audience = $this->get_audience();
        $audience->get_config_form($mform);

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
        $audience = $this->get_audience();
        return $audience->validate_config_form($data);
    }

    /**
     * Returns context where this form is used
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     *
     * @throws moodle_exception
     */
    protected function check_access_for_dynamic_submission(): void {
        $audience = $this->get_audience();

        $persistent = $audience->get_persistent();
        $page = $persistent->get_page();
        permission::require_can_edit_page($page);

        // Check whether we are able to add/edit the current audience.
        $persistent->get('id') === 0
            ? $audience->require_user_can_add($page->get('global'))
            : $audience->require_user_can_edit();
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     */
    public function process_dynamic_submission() {
        $formdata = $this->get_data();
        $audience = $this->get_audience();

        $configdata = $audience::retrieve_configdata($formdata);
        if (!$formdata->id) {
            // New audience.
            $audience = $audience::create($formdata->pageid, $configdata);
        } else {
            // Editing audience.
            $audience->update_configdata($configdata);
        }

        $persistent = $audience->get_persistent();

        return [
            'instanceid' => $persistent->get('id'),
            'heading' => $audience->get_name(),
            'description' => $audience->get_description(),
        ];
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        $audience = $this->get_audience();
        $persistent = $audience->get_persistent();

        // Populate form data based on whether we are editing/creating an audience.
        if ($persistent->get('id') !== 0) {
            $formdata = [
                'id' => $persistent->get('id'),
                'pageid' => $persistent->get('pageid'),
                'classname' => $persistent->get('classname'),
            ] + $audience->get_configdata();
        } else {
            $formdata = [
                'pageid' => $this->optional_param('pageid', null, PARAM_INT),
                'classname' => $this->optional_param('classname', null, PARAM_RAW_TRIMMED),
            ];
        }

        $this->set_data($formdata);
    }

    /**
     * Page url
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/admin/tool/custompage/manage.php', ['id' => $this->optional_param('pageid', 0, PARAM_INT)]);
    }
}
