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
 * Class edit_program_details_form
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use context_system;
use core_form\dynamic_form;
use core_tag_tag;
use html_writer;
use stdClass;
use tool_program\api;
use tool_program\constants;
use tool_program\customfield\program_handler;
use tool_program\permission;
use tool_program\persistent\program;

/**
 * Class edit_program_details_form
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_program_details_form extends dynamic_form {

    /** @var program current program */
    protected $program = null;

    /**
     * Current program
     *
     * @return program
     */
    protected function get_program() : program {
        if (!$this->program) {
            $this->program = new program($this->optional_param('id', 0, PARAM_INT));
        }
        return $this->program;
    }

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;

        $program = $this->get_program();
        $caneditdetails = !$mform->_freezeAll;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'fullname', get_string('programfullname', 'tool_program'));
        $mform->addRule('fullname', get_string('missingfullname', 'tool_program'), 'required', null, 'client');
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addHelpButton('fullname', 'programfullname', 'tool_program');

        $mform->addElement('text', 'idnumber', get_string('programidnumber', 'tool_program'));
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addHelpButton('idnumber', 'programidnumber', 'tool_program');

        if ($caneditdetails) {
            $mform->addElement('editor', 'description_editor', get_string('programdescription', 'tool_program'),
                null, self::description_editor_options());
            $mform->setType('description_editor', PARAM_RAW);
            $mform->addHelpButton('description_editor', 'programdescription', 'tool_program');
        } else {
            // TODO MDL-29421 currently editor does not support freezing.
            $description = file_rewrite_pluginfile_urls($program->get('description'), 'pluginfile.php', $program->get_context()->id,
                'tool_program', 'program_description', $program->get('id'));
            $data = format_text($description, $program->get('descriptionformat'));
            $mform->addElement('static', 'description_editor2', get_string('programdescription', 'tool_program'), $data);
            $mform->addHelpButton('description_editor2', 'programdescription', 'tool_program');
        }

        if ($caneditdetails) {
            $mform->addElement('filemanager', 'image_filemanager', get_string('programimage', 'tool_program'),
                null, self::image_filemanager_options());
            $mform->addHelpButton('image_filemanager', 'programimage', 'tool_program');
        } else {
            // TODO MDL-29421 currently filemanager does not support freezing.
            $str = get_string('programimage', 'tool_program');
            $imageurl = $program->get_image_url();
            $imageoutput = '';
            if ($imageurl) {
                $imageoutput = html_writer::img($imageurl, $str, ['width' => '75', 'height' => '55', 'background-size' => 'cover']);
            }
            $mform->addElement('static', 'image_filemanager2', $str, $imageoutput);
            $mform->addHelpButton('image_filemanager2', 'programimage', 'tool_program');
        }

        $choices = [
           constants::VISIBILITY_HIDDEN => get_string('hide'),
           constants::VISIBILITY_AVAILABLE => get_string('show'),
        ];
        $mform->addElement('select', 'visible', get_string('programvisibility', 'tool_program'), $choices);
        $mform->setDefault('visible', constants::VISIBILITY_AVAILABLE);
        $mform->addHelpButton('visible', 'programvisibility', 'tool_program');

        // Tags section.
        if ($caneditdetails) {
            if (core_tag_tag::is_enabled('tool_program', 'tool_program')) {
                $mform->addElement('tags', 'program_tags', get_string('programtags', 'tool_program'),
                    ['itemtype' => 'program', 'component' => 'tool_program']);
                $mform->addHelpButton('program_tags', 'programtags', 'tool_program');
            }
        } else {
            // TODO MDL-61395 currently tag elements don't support freezing.
            $tags = [];
            $content = '';
            $programtags = \core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $program->get('id'));
            if (!empty($programtags)) {
                $tags = array_values($programtags);
            }
            foreach ($tags as $tag) {
                $content .= "<span class='border p-1 text-uppercase font-small my-2 mr-2' data-region='programtags'>$tag</span>";
            }
            $mform->addElement('static', 'programtags', get_string('programtags', 'tool_program'),
                $content);
            $mform->addHelpButton('programtags', 'programtags', 'tool_program');
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

        if ($caneditdetails) {
            // This setting is currently hardcoded. In the future we may implement a site-wide setting (available to admin only)
            // that would allow program managers to uncheck this setting for individual program.
            $warningstr = get_string('separatetenantsingroupswarning', 'tool_program');
            $mform->addElement('static', 'separatetenantsingroupswarning', '', html_writer::span($warningstr));
        }

        // Add custom fields to the form.
        $handler = program_handler::create();
        $programid = $this->optional_param('id', 0, PARAM_INT);
        if ($caneditdetails) {
            $handler->instance_form_definition($mform, $programid);
        } else {
            $customfields = $handler->export_instance_data_object($programid);
            foreach ($customfields as $field => $value) {
                $mform->addElement('static', 'customfields', $field, $value);
            }
        }

        $mform->setDisableShortforms();
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
    public function check_access_for_dynamic_submission(): void {
        $program = $this->get_program();

        if (!$program->get('id')) {
            permission::require_can_create(context_system::instance());
        } else {
            permission::require_can_edit_details($program);
        }
    }

    /**
     * Process data.
     *
     * @return array|string|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
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
    public function set_data_for_dynamic_submission(): void {
        $program = $this->get_program();
        $programdata = $program->to_record();
        if ($program->get('id')) {
            $context = context_system::instance();
            $programdata->program_tags = core_tag_tag::get_item_tags_array(
                'tool_program', 'tool_program', $programdata->id);

            $descriptioneditoroptions = self::description_editor_options();
            $programdata = file_prepare_standard_editor($programdata, 'description', $descriptioneditoroptions, $context,
                'tool_program', 'program_description', $programdata->id);
            $imagefilemanageroptions = self::image_filemanager_options();
            $programdata = file_prepare_standard_filemanager($programdata, 'image', $imagefilemanageroptions,
                $context, 'tool_program', 'program_image', $programdata->id);
        } else {
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

    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'id' => $id,
        ]);
    }
}
