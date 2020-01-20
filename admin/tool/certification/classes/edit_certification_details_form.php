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
 * File for class edit_certification_details_form.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_tag_tag;
use html_writer;
use tool_certification\customfield\certification_handler;
use tool_program\persistent\program;
use tool_wp\modal_form;

/**
 * Class edit_certification_details_form
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_certification_details_form extends modal_form {

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'duplicatecertification', 0);
        $mform->setType('duplicatecertification', PARAM_INT);

        // Basics section.
        $mform->addElement('header', 'basicshdr', get_string('basic', 'tool_certification'));
        $mform->setExpanded('basicshdr', true);

        $mform->addElement('html', '<hr>');

        $mform->addElement('text', 'fullname', get_string('certificationfullname', 'tool_certification'));
        $mform->addRule('fullname', get_string('missingfullname', 'tool_certification'), 'required', null, 'client');
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addHelpButton('fullname', 'certificationfullname', 'tool_certification');

        $mform->addElement('text', 'idnumber', get_string('certificationidnumber', 'tool_certification'));
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addHelpButton('idnumber', 'certificationidnumber', 'tool_certification');

        // Tags sections.
        if (core_tag_tag::is_enabled('tool_certification', 'tool_certification')) {
            $mform->addElement('tags', 'certification_tags', get_string('certificationtags', 'tool_certification'),
                ['itemtype' => 'certification', 'component' => 'tool_certification']);
            $mform->addHelpButton('certification_tags', 'certificationtags', 'tool_certification');
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
                'ajax' => 'tool_certification/form_potential_program_selector',
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

            // This setting is currently hardcoded. In the future we may implement a site-wide setting (available to admin only)
            // that would allow program managers to uncheck this setting for individual program.
            $mform->addElement('checkbox', 'separatetenants', '',
                get_string('separatetenantsingroups', 'tool_tenant'), ['disabled' => 'disabled']);
            $mform->setDefault('separatetenants', 1);
        }

        // Add custom fields to the form.
        $handler = certification_handler::create();
        $certificationid = empty($this->_ajaxformdata['id']) ? 0 : $this->_ajaxformdata['id'];
        $handler->instance_form_definition($mform, $certificationid);

        $this->add_action_buttons(false);
    }

    /**
     * Require access.
     */
    public function require_access(): void {
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
     * @param \stdClass $data
     * @return int|mixed
     */
    public function process(\stdClass $data) {
        if (0 === (int)$data->id) {
            $newcertification = api::create_certification($data);
            if ($newcertification) {
                $certificationid = $newcertification->get('id');

                return (new \moodle_url('/admin/tool/certification/edit.php', ['id' => $certificationid]))->out(false);
            }
        } else {
            api::update_certification_details($data);

            return $data->id;
        }
    }

    /**
     * Sets data for form.
     */
    public function set_data_for_modal(): void {
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
}
