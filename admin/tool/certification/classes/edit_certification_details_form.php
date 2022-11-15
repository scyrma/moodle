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
 * File for class edit_certification_details_form.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use context_system;
use core_form\dynamic_form;
use core_tag_tag;
use html_writer;
use tool_certification\customfield\certification_handler;
use tool_program\persistent\program;

/**
 * Class edit_certification_details_form
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_certification_details_form extends dynamic_form {

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->setDisableShortforms();
        // Add empty header for consistency.
        $mform->addElement('header', 'hdr', '');

        $certification = new certification($this->optional_param('id', 0, PARAM_INT));
        $caneditdetails = !$mform->_freezeAll;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'duplicatecertification', 0);
        $mform->setType('duplicatecertification', PARAM_INT);

        $mform->addElement('text', 'fullname', get_string('certificationfullname', 'tool_certification'));
        $mform->addRule('fullname', get_string('missingfullname', 'tool_certification'), 'required', null, 'client');
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addHelpButton('fullname', 'certificationfullname', 'tool_certification');

        $mform->addElement('text', 'idnumber', get_string('certificationidnumber', 'tool_certification'));
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addHelpButton('idnumber', 'certificationidnumber', 'tool_certification');

        // Tags sections.
        if ($caneditdetails) {
            if (core_tag_tag::is_enabled('tool_certification', 'tool_certification')) {
                $mform->addElement('tags', 'certification_tags', get_string('certificationtags', 'tool_certification'),
                    ['itemtype' => 'certification', 'component' => 'tool_certification']);
                $mform->addHelpButton('certification_tags', 'certificationtags', 'tool_certification');
            }
        } else {
            // TODO MDL-61395 currently tag elements don't support freezing.
            $tags = [];
            $content = '';
            $certificationtags = \core_tag_tag::get_item_tags_array('tool_certification', 'tool_certification',
                $certification->get('id'));
            if (!empty($certificationtags)) {
                $tags = array_values($certificationtags);
            }
            $class = 'border p-1 text-uppercase font-small my-2 mr-2';
            foreach ($tags as $tag) {
                $content .= "<span class='$class' data-region='certificationtags'>$tag</span>";
            }
            $mform->addElement('static', 'certificationtags', get_string('certificationtags', 'tool_certification'),
                $content);
            $mform->addHelpButton('certificationtags', 'certificationtags', 'tool_certification');
        }

        // Only show Select program form in this modal when creating a new certification.
        if (empty($this->optional_param('id', 0, PARAM_INT))) {
            // Program section.
            $mform->addElement('header', 'programhdr', get_string('program', 'tool_certification'));
            $mform->setExpanded('programhdr', true);

            // New or duplicate certification.
            $params = null;
            $duplicatecertificationid = $this->optional_param('duplicatecertification', 0, PARAM_INT);
            if (!empty($duplicatecertificationid)) {
                $params = $this->get_program($duplicatecertificationid);
            }
            $selectprogramstr = get_string('selectprogram', 'tool_certification');
            $options = array(
                'ajax' => 'tool_program/form_potential_program_selector',
                'multiple' => false,
                'class' => 'select_program_field',
            );

            $mform->addElement('autocomplete', 'program', $selectprogramstr, $params, $options);
            $mform->addRule('program', get_string('missingprogram', 'tool_certification'), 'required', null, 'client');
            $mform->addHelpButton('program', 'selectprogram', 'tool_certification');
            $mform->setType('programname', PARAM_RAW);

            // Manage programs link.
            $manageprogurl = new \moodle_url('/admin/tool/program/index.php');
            $manageprogramsstr = get_string('manageprograms', 'tool_certification');
            $html = html_writer::tag('a', $manageprogramsstr, array('href' => $manageprogurl));
            $mform->addElement('static', 'manageprogram', '', $html);

            $choices = [
                -1 => get_string('autocreategroupsasinprogram', 'tool_certification'),
                \tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT =>
                    get_string('autocreategroupscertification', 'tool_certification'),
            ];
            $mform->addElement('select', 'autocreategroups', get_string('autocreategroups', 'tool_certification'), $choices);
            $mform->addHelpButton('autocreategroups', 'autocreategroups', 'tool_certification');

            if ($caneditdetails) {
                // This setting is currently hardcoded. In the future we may implement a site-wide setting (available to admin only)
                // that would allow program managers to uncheck this setting for individual program.
                $warningstr = get_string('separatetenantsingroupswarning', 'tool_certification');
                $mform->addElement('static', 'separatetenantsingroupswarning', '', html_writer::span($warningstr));
            }
        }

        // Add custom fields to the form.
        $handler = certification_handler::create();
        $certificationid = empty($this->_ajaxformdata['id']) ? 0 : $this->_ajaxformdata['id'];
        if ($caneditdetails) {
            $handler->instance_form_definition($mform, $certificationid);
            if (empty($this->_ajaxformdata['isajax'])) {
                $this->add_action_buttons(false);
            }
        } else {
            $customfields = $handler->export_instance_data_object($certificationid);
            foreach ($customfields as $field => $value) {
                $mform->addElement('static', 'customfields', $field, $value);
            }
        }
    }

    /**
     * Require access.
     */
    public function check_access_for_dynamic_submission(): void {
        $certificationid = $this->optional_param('id', 0, PARAM_INT);
        $duplicatecertificationid = $this->optional_param('duplicatecertification', 0, PARAM_INT);
        if (0 === $certificationid) {
            if ($duplicatecertificationid) {
                permission::require_can_duplicate(new certification($duplicatecertificationid));
            } else {
                permission::require_can_create(context_system::instance());
            }
        } else {
            $certification = new certification($certificationid);
            permission::require_can_edit_details($certification);
        }
    }

    /**
     * Process data.
     *
     * @return int|mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $dataoutput = [];
        if (0 === (int)$data->id) {
            $newcertification = api::create_certification($data);
            if ($newcertification) {
                $certificationid = $newcertification->get('id');

                return (new \moodle_url('/admin/tool/certification/edit.php', ['id' => $certificationid]))->out(false);
            }
        } else {
            api::update_certification_details($data);
            $dataoutput['id'] = $data->id;
            $dataoutput['success'] = 1;

            return $dataoutput;
        }
    }

    /**
     * Sets data for form.
     */
    public function set_data_for_dynamic_submission(): void {
        // Ajaxformdata comes from attributes.
        $certificationid = $this->optional_param('id', 0, PARAM_INT);
        $duplicatecertificationid = $this->optional_param('duplicatecertification', 0, PARAM_INT);

        // Retrieve certification data, tags and custom fields.
        $certification = new certification($certificationid ?: $duplicatecertificationid);
        $certificationdata = $certification->to_record();
        $certificationdata->program = $certificationdata->program ?: null;
        if ($certificationdata->id) {
            $certificationdata->certification_tags = \core_tag_tag::get_item_tags_array(
                'tool_certification', 'tool_certification', $certificationdata->id);
        }
        certification_handler::create()->instance_form_before_set_data($certificationdata);

        if (!$certificationid && $duplicatecertificationid) {
            $certificationdata->duplicatecertification = $certificationdata->id;
            $certificationdata->id = null;
            $certificationdata->fullname .= ' (' . get_string('copy') . ')';
        }

        $this->set_data($certificationdata);
    }

    /**
     * Returns associated program ID and fullname
     *
     * @param int $certificationid
     * @return array
     * @throws \coding_exception
     */
    private function get_program(int $certificationid): array {
        $certification = new certification($certificationid);
        $programid = $certification->get('program');
        try {
            $program = new program($programid);
        } catch (\dml_missing_record_exception $ex) {
            throw new \moodle_exception('errormissingassociatedprogram', 'tool_certification');
        }
        $programname = format_string($program->get('fullname'));
        return [$programid => $programname];
    }

    /**
     * Perform some extra moodle validation
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function validation($data, $files): array {
        $errors = [];

        if (!api::is_idnumber_unique($data['id'], $data['idnumber'])) {
            $errors['idnumber'] = get_string('erroridnumberuniquetenant', 'tool_certification');
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
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'id' => $this->optional_param('id', 0, PARAM_INT),
            'duplicatecertification' => $this->optional_param('duplicatecertification', 0, PARAM_INT),
        ]);
    }
}
