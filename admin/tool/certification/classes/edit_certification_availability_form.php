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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class tool_certification\edit_certification_availability_form
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot. '/admin/tool/wp/periodduration.php');

use stdClass;
use tool_program\persistent\program;
use tool_wp\modal_form;
use html_writer;

/**
 * File for class edit_certification_availability_form
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_certification_availability_form extends modal_form {
    /** @var certification */
    protected $certification;

    /**
     * Current certification
     *
     * @return certification
     */
    protected function get_certification(): certification {
        if (!$this->certification) {
            $this->certification = new certification((int)$this->_ajaxformdata['id']);
        }
        return $this->certification;
    }

    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $certification = $this->get_certification();
        $caneditdetails = permission::can_edit_details($certification);

        $startdatestr = get_string('startdate', 'tool_certification');
        $enddatestr = get_string('enddate', 'tool_certification');
        $notsetstr = get_string('notset', 'tool_certification');

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // Program section.
        $mform->addElement('header', 'programhdr', get_string('program', 'tool_certification'));
        $mform->setExpanded('programhdr', true);

        // Select program.
        $params = $this->get_program();
        $selectprogramstr = get_string('selectprogram', 'tool_certification');
        if ($this->certification->get('requirerecertification') && $this->certification->get('recertdifferentprogram')) {
            $excluderecertprogram = $this->certification->get('recertificationprogram');
        }

        $options = array(
            'ajax' => 'tool_program/form_potential_program_selector',
            'multiple' => false,
            'class' => 'select_program_field',
            'data-exclude' => $excluderecertprogram ?? ''
        );
        $programselector = $mform->addElement('autocomplete', 'program', $selectprogramstr, $params, $options);
        $mform->addRule('program', get_string('missingprogram', 'tool_certification'), 'required', null, 'client');
        $mform->addHelpButton('program', 'selectprogram', 'tool_certification');
        $mform->setType('programname', PARAM_RAW);

        if ($caneditdetails) {
            // Program warning.
            $warningstr = get_string('programchangewarning', 'tool_certification');
            $html = html_writer::tag('div', $warningstr, ['class' => 'alert alert-warning']);
            $mform->addElement('static', 'programchangewarning', '', $html);
        }

        $choices = [
            -1 => get_string('autocreategroupsasinprogram', 'tool_certification'),
            \tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT =>
                get_string('autocreategroupscertification', 'tool_certification'),
        ];
        $creategroupsstr = get_string('autocreategroups', 'tool_certification');
        $autocreategroups = $mform->addElement('select', 'autocreategroups', $creategroupsstr, $choices);
        $mform->addHelpButton('autocreategroups', 'autocreategroups', 'tool_certification');

        // This setting is currently hardcoded. In the future we may implement a site-wide setting (available to admin only)
        // that would allow program managers to uncheck this setting for individual program.
        $warningstr = get_string('separatetenantsingroupswarning', 'tool_certification');
        $mform->addElement('static', 'separatetenantsingroupswarning', '', html_writer::span($warningstr));

        // Certification dates section.
        $mform->addElement('header', 'schedule', get_string('schedule', 'tool_certification'));
        $mform->setExpanded('schedule');

        // Start date.
        $selectdatestr = get_string('selectdate', 'tool_certification');
        $allocationdatestr = get_string('allocationdate', 'tool_certification');
        $afterallocdatestr = get_string('afterallocationdate', 'tool_certification');
        $choices = [
            constants::DATE_USER_ALLOCATION_DATE => $allocationdatestr,
            constants::DATE_ABSOLUTE => $selectdatestr,
            constants::DATE_RELATIVE_TO_ALLOCATION_DATE => $afterallocdatestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'startdatetype', '', $choices);
        $group[] =& $mform->createElement('date_time_selector', 'startdateabsolute', '');
        $group[] =& $mform->createElement('periodduration', 'startdaterelative', '', null, null);
        $startdategroup = $mform->addGroup($group, 'startdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('startdateabsolute', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('startdateabsolute', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->hideIf('startdaterelative', 'startdatetype', 'noteq', constants::DATE_RELATIVE_TO_ALLOCATION_DATE);
        $mform->disabledIf('startdaterelative', 'startdatetype', 'noteq',
            constants::DATE_RELATIVE_TO_ALLOCATION_DATE);
        $mform->addHelpButton('startdateformgroup', 'startdate', 'tool_certification');

        // Due date.
        $neverstr = get_string('never', 'tool_certification');
        $afterstartdatestr = get_string('afterstartdate', 'tool_certification');
        $duedatestr = get_string('duedate', 'tool_certification');
        $choices = [
            constants::DATE_NEVER => $neverstr,
            constants::DATE_AFTER_START_DATE => $afterstartdatestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'duedatetype', '', $choices);
        $group[] =& $mform->createElement('periodduration', 'duedaterelative', '', null, null);
        $duedategroup = $mform->addGroup($group, 'duedateformgroup', $duedatestr, ' ', false);
        $mform->hideIf('duedaterelative', 'duedatetype', 'noteq', constants::DATE_AFTER_START_DATE);
        $mform->addHelpButton('duedateformgroup', 'duedate', 'tool_certification');
        $mform->setType('duedatetype', PARAM_INT);
        $mform->setDefault('duedatetype', constants::DATE_AFTER_START_DATE);

        // Expiry date.
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
        $group[] =& $mform->createElement('date_time_selector', 'expirydateabsolute', '');
        $group[] =& $mform->createElement('periodduration', 'expirydaterelative', '', null, null);
        $expirydategroup = $mform->addGroup($group, 'expirydateformgroup', $expirydatestr, ' ', false);
        $mform->hideIf('expirydateabsolute', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('expirydateabsolute', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->addHelpButton('expirydateformgroup', 'expirydate', 'tool_certification');
        $params = [constants::DATE_ABSOLUTE, constants::DATE_NEVER];
        $mform->hideIf('expirydaterelative', 'expirydatetype', 'in', $params);
        $mform->disabledIf('expirydaterelative', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);

        // Availability section.
        $mform->addElement('header', 'availability', get_string('allocationwindow', 'tool_program'));
        $mform->setExpanded('availability');

        // Allocation window start date.
        $allocstartdateopts = [
            constants::DATE_NONE => $notsetstr,
            constants::DATE_ABSOLUTE => $selectdatestr,
        ];
        $group = [];
        $elementstartdatetype = $mform->createElement('select', 'allocationstartdatetype', '', $allocstartdateopts,
            ['class' => 'calendar-fix-selector-width']);
        $group[] =& $elementstartdatetype;
        $elementstartdateabsolute = $mform->createElement('date_time_selector', 'allocationstartdateabsolute', '');
        $group[] =& $elementstartdateabsolute;
        $mform->addGroup($group, 'allocationstartdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('allocationstartdateabsolute', 'allocationstartdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('allocationstartdateabsolute', 'allocationstartdatetype', 'noteq',
            constants::DATE_ABSOLUTE);
        $mform->addHelpButton('allocationstartdateformgroup', 'allocationwindowstartdate', 'tool_certification');

        // Allocation window end date.
        $allocenddateopts = [
            constants::DATE_NONE => $notsetstr,
            constants::DATE_ABSOLUTE => $selectdatestr,
        ];
        $group = [];
        $elementenddatetype = $mform->createElement('select', 'allocationenddatetype', '', $allocenddateopts,
            ['class' => 'calendar-fix-selector-width']);
        $group[] =& $elementenddatetype;
        $elementenddateabsolute = $mform->createElement('date_time_selector', 'allocationenddateabsolute', '');
        $group[] =& $elementenddateabsolute;
        $mform->addGroup($group, 'allocationenddateformgroup', $enddatestr, ' ', false);
        $mform->hideIf('allocationenddateabsolute', 'allocationenddatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('allocationenddateabsolute', 'allocationenddatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->addHelpButton('allocationenddateformgroup', 'allocationwindowenddate', 'tool_certification');

        // If user has no edit permission disable form elements.
        if (!$caneditdetails) {
            $programselector->freeze();
            $autocreategroups->freeze();
            $startdategroup->freeze();
            $duedategroup->freeze();
            $expirydategroup->freeze();
            $elementenddatetype->freeze();
            $elementenddateabsolute->freeze();
            $elementstartdatetype->freeze();
            $elementstartdateabsolute->freeze();
        } else {
            $this->add_action_buttons(false);
        }
    }

    /**
     * Require access.
     */
    public function require_access(): void {
        permission::require_can_view_details($this->get_certification());
    }

    /**
     * Process data.
     * @param stdClass $data
     * @return bool
     */
    public function process(stdClass $data): bool {
        if (permission::can_edit_details($this->get_certification())) {
            return api::update_certification_calendar($data);
        }
        return false;
    }

    /**
     * Sets data for form.
     */
    public function set_data_for_modal(): void {
        $certificationdata = $this->get_certification()->to_record();
        $this->set_data($certificationdata);
    }

    /**
     * Returns associated program ID and fullname
     *
     * @return array
     * @throws \coding_exception
     */
    private function get_program(): array {
        $certification = $this->get_certification();
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
     */
    public function validation($data, $files): array {
        $errors = [];

        // Absolute expiry date has to be higher than relative due date if start date is set as absolute.
        if ((int)$data['startdatetype'] === constants::DATE_ABSOLUTE
        && (int)$data['expirydatetype'] === constants::DATE_ABSOLUTE) {
            $duedateabsolute = strtotime('+' . $data['duedaterelative'], (int)$data['startdateabsolute']);
            if ($duedateabsolute > (int)$data['expirydateabsolute']) {
                $errors['expirydateformgroup'] = get_string('errorexpirydatepreviousduedate', 'tool_certification');
            }
        }

        return $errors;
    }
}
