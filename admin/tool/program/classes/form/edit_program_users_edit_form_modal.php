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
 * Form to edit program users.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\form;

use context_system;
use core_text;
use stdClass;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use coding_exception;
use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class edit_program_users_edit_form_modal
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_program_users_edit_form_modal extends modal_form {
    /** @var int Date is the current calculated date */
    protected const DATE_DEFAULT = 0;
    /** @var int Date has been overriden and set to a specific date */
    protected const DATE_OVERRIDE_ABSOLUTE = 1;
    /** @var int Date has been overriden and set to never (not set) */
    protected const DATE_OVERRIDE_NEVER = 2;

    /** @var program_user */
    protected $programuser;

    /**
     * Current program user
     *
     * @return program_user
     */
    protected function get_program_user(): program_user {
        if (!$this->programuser) {
            $this->programuser = new program_user($this->_ajaxformdata['programuserid']);
        }
        return $this->programuser;
    }

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;
        $programuserid = $this->get_program_user()->get('id');

        $startdatestr = get_string('startdate', 'tool_program');
        $duedatestr = get_string('duedate', 'tool_program');
        $enddatestr = get_string('enddate', 'tool_program');
        $dateabsolutestr = get_string('datetypeabsolute', 'tool_program');
        $defaultstr = get_string('default', 'tool_program');
        $statusstr = get_string('status', 'tool_program');
        $suspendedstr = get_string('suspended', 'tool_program');
        $neverstr = get_string('never', 'tool_program');

        $program = $this->get_program_user()->get_program();
        $dates = api::get_default_program_dates($program);

        $mform->addElement('hidden', 'programuserid', $programuserid);
        $mform->setType('programuserid', PARAM_INT);

        $choices = [
            constants::STATUS_OVERRIDE_DEFAULT => $defaultstr,
            constants::STATUS_OVERRIDE_SUSPENDED => $suspendedstr,
        ];
        $mform->addElement('select', 'status', $statusstr, $choices);
        $mform->setDefault('status', constants::STATUS_OVERRIDE_DEFAULT);
        $mform->addHelpButton('status', 'status', 'tool_program');

        // Start date.
        $availabilitystartdateoptions = [
            self::DATE_DEFAULT => $dates->startdate . ' (' . core_text::strtolower($defaultstr) . ')',
            self::DATE_OVERRIDE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'startdatetype', '', $availabilitystartdateoptions);
        $group[] =& $mform->createElement('date_selector', 'startdate', '');
        $mform->addGroup($group, 'userstartdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('startdate', 'startdatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->disabledIf('startdate', 'startdatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->setDefault('startdatetype', constants::DATE_UNLOCKED);
        $mform->addHelpButton('userstartdateformgroup', 'userstartdate', 'tool_program');

        // Due date.
        $availabilityduedateoptions = [
            self::DATE_DEFAULT => $dates->duedate . ' (' . core_text::strtolower($defaultstr) . ')',
            self::DATE_OVERRIDE_NEVER => $neverstr,
            self::DATE_OVERRIDE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'duedatetype', '', $availabilityduedateoptions);
        $group[] =& $mform->createElement('date_selector', 'duedate', '');
        $mform->addGroup($group, 'userduedateformgroup', $duedatestr, ' ', false);
        $mform->hideIf('duedate', 'duedatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->disabledIf('duedate', 'duedatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->setDefault('duedatetype', constants::DATE_UNLOCKED);
        $mform->addHelpButton('userduedateformgroup', 'userduedate', 'tool_program');

        // End date.
        $availabilityenddateoptions = [
            self::DATE_DEFAULT => $dates->enddate . ' (' . core_text::strtolower($defaultstr) . ')',
            self::DATE_OVERRIDE_NEVER => $neverstr,
            self::DATE_OVERRIDE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'enddatetype', '', $availabilityenddateoptions);
        $group[] =& $mform->createElement('date_selector', 'enddate', '');
        $mform->addGroup($group, 'userenddateformgroup', $enddatestr, ' ', false);
        $mform->hideIf('enddate', 'enddatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->disabledIf('enddate', 'enddatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->setDefault('enddatetype', constants::DATE_UNLOCKED);
        $mform->addHelpButton('userenddateformgroup', 'userenddate', 'tool_program');

        $this->add_action_buttons();
    }

    /**
     * Require capabilities.
     */
    public function require_access(): void {
        permission::require_can_edit_user_allocation($this->get_program_user());
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_modal(): void {
        $programuser = $this->get_program_user();
        $userid = $programuser->get('userid');

        $duedatetype = self::calculate_type($programuser, 'duedate');
        $enddatetype = self::calculate_type($programuser, 'enddate');

        $formdata = [
            'programuserid' => $programuser->get('id'),
            'userid' => $userid,
            'status' => $programuser->get('status'),
            'startdate' => $programuser->get('startdate'),
            'startdatetype' => $programuser->get('startdatelocked'),
            'duedate' => $programuser->get('duedate'),
            'duedatetype' => $duedatetype,
            'enddate' => $programuser->get('enddate'),
            'enddatetype' => $enddatetype,
        ];
        $this->set_data($formdata);
    }

    /**
     * Process data submited by the form.
     *
     * @param stdClass $data
     */
    public function process(stdClass $data): void {
        $programuser = new program_user($data->programuserid);
        $program = $programuser->get_program();

        $data->startdatelocked = $data->startdatetype;

        switch ($data->duedatetype) {
            case self::DATE_DEFAULT:
                // Recalculate user dates.
                $programuser->set('duedatelocked', constants::DATE_UNLOCKED);
                $programuser->update();
                api::recalculate_program_user_dates($program, $programuser);
                // We get user calculated default date.
                $data->duedate = $programuser->get('duedate');
                $data->duedatelocked = constants::DATE_UNLOCKED;
                break;
            case self::DATE_OVERRIDE_NEVER:
                $data->duedate = constants::DATE_NONE;
                $data->duedatelocked = constants::DATE_LOCKED;
                break;
            case self::DATE_OVERRIDE_ABSOLUTE:
                $data->duedatelocked = constants::DATE_LOCKED;
                break;
            default:
                throw new coding_exception('unexpected program due date type');
                break;
        }

        switch ($data->enddatetype) {
            case self::DATE_DEFAULT:
                // Recalculate user dates.
                $programuser->set('enddatelocked', constants::DATE_UNLOCKED);
                $programuser->update();
                api::recalculate_program_user_dates($program, $programuser);
                // We get user calculated default date.
                $data->enddate = $programuser->get('enddate');
                $data->enddatelocked = constants::DATE_UNLOCKED;
                break;
            case self::DATE_OVERRIDE_NEVER:
                $data->enddate = constants::DATE_NONE;
                $data->enddatelocked = constants::DATE_LOCKED;
                break;
            case self::DATE_OVERRIDE_ABSOLUTE:
                $data->enddatelocked = constants::DATE_LOCKED;
                break;
            default:
                throw new coding_exception('unexpected program end date type');
                break;
        }

        api::update_program_user_dates_and_status($programuser, $data);
    }

    /**
     * Calculates the date type.
     *
     * @param program_user $programuser
     * @param string $datestring For example duedate or enddate
     * @return int date type
     * @throws coding_exception
     */
    private static function calculate_type(program_user $programuser, string $datestring): int {
        $date = (int) $programuser->get($datestring);
        $datelocked = (int) $programuser->get($datestring . 'locked');
        $datetype = self::DATE_DEFAULT;
        if ($datelocked === constants::DATE_LOCKED) {
            $datetype = $date === 0 ? self::DATE_OVERRIDE_NEVER : self::DATE_OVERRIDE_ABSOLUTE;
        }
        return $datetype;
    }
}
