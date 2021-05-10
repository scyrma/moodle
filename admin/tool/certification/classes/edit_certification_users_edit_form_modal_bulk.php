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
 * Class tool_certification\edit_certification_users_edit_form_modal_bulk
 *
 * @package    tool_certification
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use lang_string;
use tool_program\persistent\program_user;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class edit_certification_users_edit_form_modal_bulk
 *
 * @package    tool_certification
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_certification_users_edit_form_modal_bulk extends \tool_wp\modal_form {

    /** @var int Date will not be modified */
    protected const DATE_DONT_CHANGE = -1;

    /** @var certification */
    protected $certification;

    /**
     * Current certification
     *
     * @return certification
     */
    protected function get_certification(): certification {
        if (!$this->certification) {
            $this->certification = new certification($this->_ajaxformdata['certificationid']);
        }
        return $this->certification;
    }

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {
        $mform = $this->_form;
        $certification = $this->get_certification();

        $mform->addElement('hidden', 'certificationid', $certification->get('id'));
        $mform->setType('certificationid', PARAM_INT);

        $mform->addElement('hidden', 'certificationuserids', $this->_ajaxformdata['certificationuserids']);
        $mform->setType('certificationuserids', PARAM_RAW);

        $dontchangestr = get_string('dontchange', 'tool_certification');
        $activestr = get_string('active', 'tool_certification');
        $statusstr = get_string('status', 'tool_certification');
        $suspendedstr = get_string('suspended', 'tool_certification');
        $startdatestr = new lang_string('startdate', 'tool_certification');
        $dateabsolutestr = new lang_string('selectdate', 'tool_certification');
        $duedatestr = new lang_string('duedate', 'tool_certification');

        $options = [
            self::DATE_DONT_CHANGE => $dontchangestr,
            constants::STATUS_OVERRIDE_DEFAULT => $activestr,
            constants::STATUS_OVERRIDE_SUSPENDED => $suspendedstr,
        ];
        $mform->addElement('select', 'status', $statusstr, $options);
        $mform->setDefault('status', self::DATE_DONT_CHANGE);
        $mform->addHelpButton('status', 'userstatus', 'tool_certification');

        $options = [
            self::DATE_DONT_CHANGE => $dontchangestr,
            constants::DATE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'startdatetype', '', $options);
        $group[] =& $mform->createElement('date_time_selector', 'startdate', '');
        $mform->addGroup($group, 'userstartdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('startdate', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('startdate', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->setDefault('startdatetype', self::DATE_DONT_CHANGE);
        $mform->addHelpButton('userstartdateformgroup', 'userstartdate', 'tool_certification');

        // Due date.
        $options = [
            self::DATE_DONT_CHANGE => $dontchangestr,
            constants::DATE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'duedatetype', '', $options);
        $group[] =& $mform->createElement('date_time_selector', 'duedate', '');
        $mform->addGroup($group, 'userduedateformgroup', $duedatestr, ' ', false);
        $mform->hideIf('duedate', 'duedatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('duedate', 'duedatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->setDefault('duedatetype', self::DATE_DONT_CHANGE);
        $mform->addHelpButton('userduedateformgroup', 'userduedate', 'tool_certification');
    }

    /**
     * Require capabilities.
     */
    public function require_access(): void {
        permission::require_can_view_allocated_users($this->get_certification());
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_modal() {
        $formdata = [
            'certificationid' => $this->get_certification()->get('id'),
            'certificationuserids' => serialize($this->_ajaxformdata['certificationuserids']),
        ];
        $this->set_data($formdata);
    }

    /**
     * Process data submited by the form.
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
        $certificationuserids = unserialize($data->certificationuserids);
        $successcount = 0;
        $skippedcount = 0;

        foreach ($certificationuserids as $certificationuserid) {
            $formdata = $data;
            $certificationuser = new certification_user($certificationuserid);
            $certificationid = $certificationuser->get('certificationid');
            $userid = $certificationuser->get('userid');

            // Do not change status or dates for users who are certified or certified and expired.
            $iscertified = (bool)api::get_last_completion_record($userid, $certificationid);
            if ($iscertified || !permission::can_edit_user_allocation($certificationuser)) {
                $skippedcount++;
                continue;
            }

            if ($formdata->status == self::DATE_DONT_CHANGE) {
                $formdata->status = $certificationuser->get('status');
            }

            // Dates are stored in the program user record.
            $programuser = program_user::get_record(['certificationid' => $certificationid, 'userid' => $userid]);

            if ($formdata->startdatetype == self::DATE_DONT_CHANGE) {
                $formdata->startdate = $programuser->get('startdate');
                $formdata->startdatelocked = $programuser->get('startdatelocked');
            } else {
                $formdata->startdatelocked = $formdata->startdatetype;
            }

            if ($formdata->duedatetype == self::DATE_DONT_CHANGE) {
                $formdata->duedate = $programuser->get('startdate');
                $formdata->duedatelocked = $programuser->get('startdatelocked');
            } else {
                $formdata->duedatelocked = $formdata->duedatetype;
            }

            api::update_certification_user_dates_and_status($certificationuser, $formdata);
            $successcount++;
        }

        return ['successcount' => $successcount, 'skippedcount' => $skippedcount];
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

        // Custom start date for this user cannot be set in the past.
        if (isset($data['startdatetype']) && (int)$data['startdatetype'] === constants::DATE_ABSOLUTE
            && (int)$data['startdate'] < time()) {
            $errors['userstartdateformgroup'] = get_string('errorinvalidpaststartdate', 'tool_certification');
        }

        return $errors;
    }
}
