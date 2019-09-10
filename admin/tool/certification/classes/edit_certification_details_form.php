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
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../wp/periodduration.php');

use context_system;
use core_tag_tag;
use html_writer;
use tool_program\persistent\program;
use tool_wp\modal_form;

/**
 * Class edit_certification_details_form
 *
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        // Program section.
        $mform->addElement('header', 'programhdr', get_string('program', 'tool_certification'));
        $mform->setExpanded('programhdr', true);

        if (empty($this->_ajaxformdata['id']) || 0 === (int)$this->_ajaxformdata['id']) {
            $options = array(
                'ajax' => 'tool_certification/form_potential_program_selector',
                'multiple' => false,
                'class' => 'select_program_field',
            );
            $mform->addElement('autocomplete', 'program', get_string('selectprogram', 'tool_certification'), null, $options);
            $mform->addRule('program', get_string('missingprogram', 'tool_certification'), 'required', null, 'client');
            $mform->addHelpButton('program', 'selectprogram', 'tool_certification');
            $mform->setType('programname', PARAM_RAW);
        } else {
            $certification = new certification($this->_ajaxformdata['id']);
            $programid = $certification->get('program');
            $program = new program($programid);
            $programname = format_string($program->get('fullname'));
            $programurl = new \moodle_url('/admin/tool/program/edit.php', ['id' => $programid]);
            $mform->addElement('hidden', 'program');
            $mform->setType('program', PARAM_INT);

            $html = html_writer::tag('a', $programname, array('href' => $programurl));
            $selectedprogram = get_string('program', 'tool_certification');
            $mform->addElement('static', 'programname', $selectedprogram, $html);
            $mform->addHelpButton('programname', 'program', 'tool_certification');
        }

        // Warning msg.
        if (empty($this->_ajaxformdata['id']) || 0 === (int)$this->_ajaxformdata['id']) {
            // Manage programs link.
            $manageprogurl = new \moodle_url('/admin/tool/program/index.php');
            $manageprogramsstr = get_string('manageprograms', 'tool_certification');
            $html = html_writer::tag('a', $manageprogramsstr, array('href' => $manageprogurl));
            $mform->addElement('static', 'manageprogram', '', $html);

            $warningstr = get_string('warningcertificationprogram', 'tool_certification');
            $mform->addElement('static', 'warning', '', html_writer::tag('div', $warningstr, ['class' => 'alert alert-warning']));
        }

        // Start date.
        $selectdatestr = get_string('selectdate', 'tool_certification');
        $startdatestr = get_string('startdate', 'tool_certification');
        $allocationdatestr = get_string('allocationdate', 'tool_certification');
        $afterallocdatestr = get_string('afterallocationdate', 'tool_certification');
        $choices = [
            constants::DATE_USER_ALLOCATION_DATE => $allocationdatestr,
            constants::DATE_ABSOLUTE => $selectdatestr,
            constants::DATE_RELATIVE_TO_ALLOCATION_DATE => $afterallocdatestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'startdatetype', '', $choices);
        $group[] =& $mform->createElement('date_selector', 'startdateabsolute', '');
        $group[] =& $mform->createElement('periodduration', 'startdaterelative', '', null, null);
        $mform->addGroup($group, 'startdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('startdateabsolute', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('startdateabsolute', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->hideIf('startdaterelative', 'startdatetype', 'noteq', constants::DATE_RELATIVE_TO_ALLOCATION_DATE);
        $mform->disabledIf('startdaterelative', 'startdatetype', 'noteq',
            constants::DATE_RELATIVE_TO_ALLOCATION_DATE);
        $mform->addHelpButton('startdateformgroup', 'startdate', 'tool_certification');

        // Due date.
        $afterstartdatestr = get_string('afterstartdate', 'tool_certification');
        $duedatestr = get_string('duedate', 'tool_certification');
        $group = [];
        $group[] =& $mform->createElement('periodduration', 'duedaterelative', '', null, null);
        $group[] =& $mform->createElement('html', html_writer::tag('span', $afterstartdatestr));
        $group[] =& $mform->createElement('hidden', 'duedatetype', constants::DATE_AFTER_START_DATE);
        $mform->addGroup($group, 'duedateformgroup', $duedatestr, ' ', false);
        $mform->addHelpButton('duedateformgroup', 'duedate', 'tool_certification');
        $mform->setType('duedatetype', PARAM_INT);
        $mform->setDefault('duedatetype', constants::DATE_AFTER_START_DATE);

        // Expiry date.
        $neverstr = get_string('never', 'tool_certification');
        $aftercompletionstr = get_string('aftercompletion', 'tool_certification');
        $afterduedatestr = get_string('afterduedate', 'tool_certification');
        $expirydatestr = get_string('expirydate', 'tool_certification');
        $choices = [
            constants::DATE_NEVER => $neverstr,
            constants::DATE_ABSOLUTE => $selectdatestr,
            constants::DATE_AFTER_COMPLETION => $aftercompletionstr,
            constants::DATE_AFTER_ALLOCATION_DATE => $afterallocdatestr,
            constants::DATE_AFTER_DUE_DATE => $afterduedatestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'expirydatetype', '', $choices);
        $group[] =& $mform->createElement('date_selector', 'expirydateabsolute', '');
        $group[] =& $mform->createElement('periodduration', 'expirydaterelative', '', null, null);
        $mform->addGroup($group, 'expirydateformgroup', $expirydatestr, ' ', false);
        $mform->hideIf('expirydateabsolute', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('expirydateabsolute', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->addHelpButton('expirydateformgroup', 'expirydate', 'tool_certification');
        $params = [constants::DATE_ABSOLUTE, constants::DATE_NEVER];
        $mform->hideIf('expirydaterelative', 'expirydatetype', 'in', $params);
        $mform->disabledIf('expirydaterelative', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);

        $this->add_action_buttons(false);
    }

    /**
     * Require access.
     */
    public function require_access(): void {
        $certificationid = (int)$this->_ajaxformdata['id'];
        if (0 === $certificationid) {
            permission::require_can_create(context_system::instance());
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
    public function set_data_for_modal() {
        // Ajaxformdata comes from attributes.
        if (!empty($this->_ajaxformdata['id']) && 0 !== (int)$this->_ajaxformdata['id']) {
            $certification = new certification($this->_ajaxformdata['id']);
            // We check that user belongs to same tenant as the certification.
            permission::require_check_belongs_same_tenant($certification);
            $certificationdata = $certification->to_record();
            $certificationdata->certification_tags = \core_tag_tag::get_item_tags_array(
                'tool_certification', 'tool_certification', $certificationdata->id);
        } else if (!empty($this->_ajaxformdata['duplicatecertification']) &&
                    0 !== (int)$this->_ajaxformdata['duplicatecertification']) {
            $certification = new certification($this->_ajaxformdata['duplicatecertification']);
            $certificationdata = $certification->to_record();
            $tags = core_tag_tag::get_item_tags_array('tool_certification', 'tool_certification', $certificationdata->id);
            $certificationdata->certification_tags = $tags;
            $certificationdata->duplicatecertification = $certificationdata->id;
            $certificationdata->id = null;
            $certificationdata->program = null;
        } else {
            $certification = new certification();
            $certificationdata = $certification->to_record();
            $certificationdata->program = null;
        }
        $this->set_data($certificationdata);
    }
}
