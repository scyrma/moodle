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
 * Class edit_program_details_form
 *
 * @package tool_program
 * @copyright 2018 Mitxel Moriana
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use context_system;
use core_tag_tag;
use stdClass;
use tool_program\api;
use tool_program\constants;
use tool_program\customfield\program_handler;
use tool_program\permission;
use tool_program\persistent\program;
use tool_wp\modal_form;

/**
 * Class edit_program_details_form
 *
 * @package tool_program
 * @copyright 2018 Mitxel Moriana
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_program_details_form extends modal_form {
    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'fullname', get_string('programfullname', 'tool_program'));
        $mform->addRule('fullname', get_string('missingfullname', 'tool_program'), 'required', null, 'client');
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addHelpButton('fullname', 'programfullname', 'tool_program');

        $mform->addElement('text', 'idnumber', get_string('programidnumber', 'tool_program'));
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addHelpButton('idnumber', 'programidnumber', 'tool_program');

        $mform->addElement('editor', 'description_editor', get_string('programdescription', 'tool_program'),
            null, self::description_editor_options());
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addHelpButton('description_editor', 'programdescription', 'tool_program');

        $mform->addElement('filemanager', 'image_filemanager', get_string('programimage', 'tool_program'),
            null, self::image_filemanager_options());
        $mform->addHelpButton('image_filemanager', 'programimage', 'tool_program');

        $choices = [
           constants::VISIBILITY_HIDDEN => get_string('hide'),
           constants::VISIBILITY_AVAILABLE => get_string('show'),
        ];
        $mform->addElement('select', 'visible', get_string('programvisibility', 'tool_program'), $choices);
        $mform->setDefault('visible', constants::VISIBILITY_AVAILABLE);
        $mform->addHelpButton('visible', 'programvisibility', 'tool_program');

        // Tags sections.
        if (core_tag_tag::is_enabled('tool_program', 'tool_program')) {
            $mform->addElement('tags', 'program_tags', get_string('programtags', 'tool_program'),
                ['itemtype' => 'program', 'component' => 'tool_program']);
            $mform->addHelpButton('program_tags', 'programtags', 'tool_program');
        }

        $mform->addElement('selectyesno', 'allowdirectallocation', get_string('allowdirectallocation', 'tool_program'));
        $mform->addHelpButton('allowdirectallocation', 'allowdirectallocation', 'tool_program');
        $mform->setDefault('allowdirectallocation', 1);

        $choices = [
            api::GROUPS_NONE + api::GROUPS_TENANT => get_string('autocreategroupsnone', 'tool_program'),
            api::GROUPS_PROGRAM + api::GROUPS_TENANT => get_string('autocreategroupsprogram', 'tool_program'),
        ];
        $mform->addElement('select', 'autocreategroups', get_string('autocreategroups', 'tool_program'), $choices);
        $mform->addHelpButton('autocreategroups', 'autocreategroups', 'tool_program');
        $mform->setDefault('autocreategroups', api::GROUPS_NONE + api::GROUPS_TENANT);

        // This setting is currently hardcoded. In the future we may implement a site-wide setting (available to admin only)
        // that would allow program managers to uncheck this setting for individual program.
        $mform->addElement('checkbox', 'separatetenants', '',
            get_string('separatetenantsingroups', 'tool_tenant'), ['disabled' => 'disabled']);
        $mform->setDefault('separatetenants', 1);

        // Add custom fields to the form.
        $handler = program_handler::create();
        $programid = empty($this->_ajaxformdata['id']) ? 0 : $this->_ajaxformdata['id'];
        $handler->instance_form_definition($mform, $programid);

        $mform->setDisableShortforms();
        $this->add_action_buttons(false);
    }

    /**
     * Options for description editor.
     *
     * @return array
     */
    public static function description_editor_options(): array {
        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => context_system::instance(),
            'noclean' => true,
            'accepted_types' => '*',
        ];
    }

    /**
     * Options for image filemanager.
     *
     * @return array
     */
    public static function image_filemanager_options(): array {
        global $CFG;

        return [
            'subdirs' => false,
            'maxfiles' => 1,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => [
                '.jpg',
                '.gif',
                '.png',
            ],
        ];
    }

    /**
     * Require access.
     */
    public function require_access(): void {
        $programid = (int)$this->_ajaxformdata['id'];

        if (0 === $programid) {
            permission::require_can_create(context_system::instance());
        } else {
            $program = new program($programid);
            permission::require_can_edit_details($program);
        }
    }

    /**
     * Process data.
     *
     * @param stdClass $data
     * @return array
     */
    public function process(stdClass $data) {
        $dataoutput = [];
        if (0 === (int)$data->id) {
            $newprogram = api::create_program($data);
            if ($newprogram) {
                $programid = $newprogram->get('id');

                // Save custom fields data.
                $data->id = $programid;
                $handler = program_handler::create();
                $handler->instance_form_save($data, true);

                return (new \moodle_url('/admin/tool/program/edit.php', ['id' => $programid]))->out(false);
            }
        } else {
            api::update_program_details($data);
            $dataoutput['id'] = $data->id;
            $dataoutput['type'] = 1;

            // Save custom fields data.
            $handler = program_handler::create();
            $handler->instance_form_save($data, true);

            return $dataoutput;
        }
    }

    /**
     * Sets data for form.
     */
    public function set_data_for_modal(): void {
        if (!empty($this->_ajaxformdata['id']) && 0 !== (int)$this->_ajaxformdata['id']) {
            $context = context_system::instance();
            $program = new program($this->_ajaxformdata['id']);
            $programdata = $program->to_record();
            $programdata->program_tags = core_tag_tag::get_item_tags_array(
                'tool_program', 'tool_program', $programdata->id);

            $descriptioneditoroptions = self::description_editor_options();
            $programdata = file_prepare_standard_editor($programdata, 'description', $descriptioneditoroptions, $context,
                'tool_program', 'program_description', $programdata->id);
            $imagefilemanageroptions = self::image_filemanager_options();
            $programdata = file_prepare_standard_filemanager($programdata, 'image', $imagefilemanageroptions,
                $context, 'tool_program', 'program_image', $programdata->id);
        } else {
            $program = new program();
            $programdata = $program->to_record();
            $programdata->program = null;
        }

        // Prepare custom fields data.
        $handler = program_handler::create();
        $handler->instance_form_before_set_data($programdata);

        $this->set_data($programdata);
    }

    /**
     * Perform some extra moodle validation
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files): array {
        $errors = [];

        if (!api::is_idnumber_unique($data['id'], $data['idnumber'])) {
            $errors['idnumber'] = get_string('erroridnumberuniquetenant', 'tool_program');
        }

        return $errors;
    }
}
