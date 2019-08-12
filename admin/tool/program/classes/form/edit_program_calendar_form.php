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
 * Form to edit program calendar.
 *
 * @package   tool_program
 * @copyright 2018 Mitxel Moriana
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/../../../wp/periodduration.php');

use context_system;
use stdClass;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_tenant\tenancy;
use tool_wp\modal_form;

/**
 * Class edit_program_calendar_form
 *
 * @package tool_program
 * @copyright 2018 Mitxel Moriana
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_program_calendar_form extends modal_form {

    /** @var program current program */
    protected $program = null;

    /**
     * Current program
     *
     * @return program
     */
    protected function get_program() : program {
        if (!$this->program) {
            $this->program = new program(!empty($this->_ajaxformdata['id']) ? $this->_ajaxformdata['id'] : 0);
        }
        return $this->program;
    }

    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $startdatestr = get_string('startdate', 'tool_program');
        $duedatestr = get_string('duedate', 'tool_program');
        $enddatestr = get_string('enddate', 'tool_program');
        $datenonestr = get_string('datetypenone', 'tool_program');
        $dateabsolutestr = get_string('datetypeabsolute', 'tool_program');
        $afteruserallocationdatestr = get_string('afteruserallocationdate', 'tool_program');
        $afterstartdatestr = get_string('afterstartdate', 'tool_program');
        $beforeenddatestr = get_string('beforeenddate', 'tool_program');
        $afterduedatestr = get_string('afterduedate', 'tool_program');
        $afterallocationwindowstartsstr = get_string('afterallocationwindowstarts', 'tool_program');

        $program = $this->get_program();
        $caneditdetails = permission::can_edit_details($program);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // Availability section.
        $mform->addElement('header', 'availability', get_string('availability', 'tool_program'));
        $mform->setExpanded('availability');

        // Start date.
        $availabilitystartdateoptions = [
            constants::DATE_NONE => $datenonestr,
            constants::DATE_ABSOLUTE => $dateabsolutestr,
            constants::DATE_AFTER_USER_ALLOCATION => $afteruserallocationdatestr,
        ];
        $group = [];
        $elementstartdatetype = $mform->createElement('select', 'startdatetype', '', $availabilitystartdateoptions);
        $group[] =& $elementstartdatetype;
        $elementstartdateabsolute = $mform->createElement('date_selector', 'startdateabsolute', '');
        $group[] =& $elementstartdateabsolute;
        $elementstartdaterelative = $mform->createElement('periodduration', 'startdaterelative', '');
        $group[] =& $elementstartdaterelative;
        $mform->addGroup($group, 'startdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('startdateabsolute', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('startdateabsolute', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->hideIf('startdaterelative', 'startdatetype', 'noteq', constants::DATE_AFTER_USER_ALLOCATION);
        $mform->disabledIf('startdaterelative', 'startdatetype', 'noteq', constants::DATE_AFTER_USER_ALLOCATION);
        $mform->addHelpButton('startdateformgroup', 'startdate', 'tool_program');

        // Due date.
        $availabilityduedateoptions = [
            constants::DATE_NONE => $datenonestr,
            constants::DATE_ABSOLUTE => $dateabsolutestr,
            constants::DATE_AFTER_START => $afterstartdatestr,
            constants::DATE_AFTER_USER_ALLOCATION => $afteruserallocationdatestr,
            constants::DATE_BEFORE_END => $beforeenddatestr,
        ];
        $group = [];
        $elementduedatetype = $mform->createElement('select', 'duedatetype', '', $availabilityduedateoptions);
        $group[] =& $elementduedatetype;
        $elementduedateabsolute = $mform->createElement('date_selector', 'duedateabsolute', '');
        $group[] =& $elementduedateabsolute;
        $elementduedaterelative = $mform->createElement('periodduration', 'duedaterelative', '');
        $group[] =& $elementduedaterelative;
        $mform->addGroup($group, 'duedateformgroup', $duedatestr, ' ', false);
        $mform->hideIf('duedateabsolute', 'duedatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('duedateabsolute', 'duedatetype', 'noteq', constants::DATE_ABSOLUTE);
        $nonrelativeoptions = [
            constants::DATE_NONE,
            constants::DATE_ABSOLUTE,
        ];
        $mform->hideIf('duedaterelative', 'duedatetype', 'in', $nonrelativeoptions);
        $mform->disabledIf('duedaterelative', 'duedatetype', 'in', $nonrelativeoptions);
        $mform->addHelpButton('duedateformgroup', 'duedate', 'tool_program');

        // End date.
        $availabilityenddateoptions = [
            constants::DATE_NONE => $datenonestr,
            constants::DATE_ABSOLUTE => $dateabsolutestr,
            constants::DATE_AFTER_START => $afterstartdatestr,
            constants::DATE_AFTER_DUE => $afterduedatestr,
            constants::DATE_AFTER_USER_ALLOCATION => $afteruserallocationdatestr,
        ];
        $group = [];
        $elementenddatetype = $mform->createElement('select', 'enddatetype', '', $availabilityenddateoptions);
        $group[] =& $elementenddatetype;
        $elementenddateabsolute = $mform->createElement('date_selector', 'enddateabsolute', '');
        $group[] =& $elementenddateabsolute;
        $elementenddaterelative = $mform->createElement('periodduration', 'enddaterelative', '');
        $group[] =& $elementenddaterelative;
        $mform->addGroup($group, 'enddateformgroup', $enddatestr, ' ', false);
        $mform->hideIf('enddateabsolute', 'enddatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('enddateabsolute', 'enddatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->hideIf('enddaterelative', 'enddatetype', 'in', $nonrelativeoptions);
        $mform->disabledIf('enddaterelative', 'enddatetype', 'in', $nonrelativeoptions);
        $mform->addHelpButton('enddateformgroup', 'enddate', 'tool_program');

        // Allocation window section.
        $mform->addElement('header', 'allocationwindow', get_string('allocationwindow', 'tool_program'));
        $mform->setExpanded('allocationwindow');

        // Allocation window start date.
        $allocationstartdateoptions = [
            constants::DATE_NONE => $datenonestr,
            constants::DATE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $elementallocationstartdatetype = $mform->createElement('select', 'allocationstartdatetype', '',
            $allocationstartdateoptions, ['class' => 'calendar-fix-selector-width']);
        $group[] =& $elementallocationstartdatetype;
        $elementallocationstartdateabsolute = $mform->createElement('date_selector', 'allocationstartdateabsolute', '');
        $group[] =& $elementallocationstartdateabsolute;
        $mform->addGroup($group, 'allocationstartdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('allocationstartdateabsolute', 'allocationstartdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('allocationstartdateabsolute', 'allocationstartdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->addHelpButton('allocationstartdateformgroup', 'allocationstartdate', 'tool_program');

        // Allocation window end date.
        $allocationenddateoptions = [
            constants::DATE_NONE => $datenonestr,
            constants::DATE_ABSOLUTE => $dateabsolutestr,
            constants::DATE_AFTER_ALLOCATION_STARTS => $afterallocationwindowstartsstr,
        ];
        $group = [];
        $elementallocationenddatetype = $mform->createElement('select', 'allocationenddatetype', '', $allocationenddateoptions);
        $group[] =& $elementallocationenddatetype;
        $elementallocationenddateabsolute = $mform->createElement('date_selector', 'allocationenddateabsolute', '');
        $group[] =& $elementallocationenddateabsolute;
        $elementallocationenddaterelative = $mform->createElement('periodduration', 'allocationenddaterelative', '');
        $group[] =& $elementallocationenddaterelative;
        $mform->addGroup($group, 'allocationenddateformgroup', $enddatestr, ' ', false);
        $mform->hideIf('allocationenddateabsolute', 'allocationenddatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('allocationenddateabsolute', 'allocationenddatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->hideIf('allocationenddaterelative', 'allocationenddatetype', 'noteq',
            constants::DATE_AFTER_ALLOCATION_STARTS);
        $mform->disabledIf('allocationenddaterelative', 'allocationenddatetype', 'noteq',
            constants::DATE_AFTER_ALLOCATION_STARTS);
        $mform->addHelpButton('allocationenddateformgroup', 'allocationenddate', 'tool_program');

        $mform->setDisableShortforms();

        // If user has no edit permission disable form elements.
        if (!$caneditdetails) {
            $elementstartdatetype->freeze();
            $elementstartdateabsolute->freeze();
            $elementstartdaterelative->freeze();
            $elementduedatetype->freeze();
            $elementduedateabsolute->freeze();
            $elementduedaterelative->freeze();
            $elementenddatetype->freeze();
            $elementenddateabsolute->freeze();
            $elementenddaterelative->freeze();
            $elementallocationstartdatetype->freeze();
            $elementallocationstartdateabsolute->freeze();
            $elementallocationenddatetype->freeze();
            $elementallocationenddateabsolute->freeze();
            $elementallocationenddaterelative->freeze();
        } else {
            $this->add_action_buttons(false);
        }
    }

    /**
     * Require access.
     */
    public function require_access(): void {
        permission::require_can_view_details($this->get_program());
    }

    /**
     * Process data.
     *
     * @param stdClass $data
     * @return bool
     */
    public function process(stdClass $data): bool {
        if (permission::can_edit_details($this->get_program())) {
            return api::update_program_calendar($data);
        }
        return false;
    }

    /**
     * Sets data for form.
     */
    public function set_data_for_modal(): void {
        $programdata = $this->get_program()->to_record();
        $this->set_data($programdata);
    }
}
