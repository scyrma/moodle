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
 * Class tool_certification\edit_certification_availability_form
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use context_system;
use stdClass;
use tool_wp\modal_form;

/**
 * File for class edit_certification_availability_form
 *
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_certification_availability_form extends modal_form {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $certificationid = (int)$this->_ajaxformdata['id'];

        $certification = new certification($certificationid);
        $caneditdetails = permission::can_edit_details($certification, context_system::instance());

        $startdatestr = get_string('startdate', 'tool_certification');
        $enddatestr = get_string('enddate', 'tool_certification');
        $notsetstr = get_string('notset', 'tool_certification');
        $selectdatestr = get_string('selectdate', 'tool_certification');

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

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
        $elementstartdateabsolute = $mform->createElement('date_selector', 'allocationstartdateabsolute', '');
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
        $elementenddateabsolute = $mform->createElement('date_selector', 'allocationenddateabsolute', '');
        $group[] =& $elementenddateabsolute;
        $mform->addGroup($group, 'allocationenddateformgroup', $enddatestr, ' ', false);
        $mform->hideIf('allocationenddateabsolute', 'allocationenddatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('allocationenddateabsolute', 'allocationenddatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->addHelpButton('allocationenddateformgroup', 'allocationwindowenddate', 'tool_certification');

        // If user has no edit permission disable form elements.
        if (!$caneditdetails) {
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
        $certification = new certification($this->_ajaxformdata['id']);
        permission::require_can_edit_details($certification, context_system::instance());
    }

    /**
     * Process data.
     * @param stdClass $data
     * @return bool
     */
    public function process(stdClass $data): bool {
        return api::update_certification_calendar($data);
    }

    /**
     * Sets data for form.
     */
    public function set_data_for_modal(): void {
        if (!empty($this->_ajaxformdata['id'])) {
            $certification = new certification($this->_ajaxformdata['id']);
        } else {
            $certification = new certification();
        }
        $certificationdata = $certification->to_record();
        $this->set_data($certificationdata);
    }
}
