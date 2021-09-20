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
 * Class tool_certification\edit_certification_users_edit_form_modal
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use coding_exception;
use core_form\dynamic_form;
use core_text;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use lang_string;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class edit_certification_users_edit_form_modal
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_certification_users_edit_form_modal extends dynamic_form {
    /** @var certification_user */
    protected $certificationuser;

    /**
     * Current certification_user
     *
     * @return certification_user
     */
    protected function get_certification_user(): certification_user {
        if (!$this->certificationuser) {
            $certificationuserid = $this->optional_param('certificationuserid', 0, PARAM_INT);
            $this->certificationuser = new certification_user($certificationuserid);
        }
        return $this->certificationuser;
    }

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {
        $mform = $this->_form;

        $certificationuser = $this->get_certification_user();
        $certification = $certificationuser->get_certification();
        $userid = $certificationuser->get('userid');
        $currentprogramid = $certificationuser->get('currentprogramid');
        $iscertified = (bool)api::get_last_completion_record($userid, $certification->get('id'));
        // Loads default startdate, duedate and expirydate.
        $defaultdates = api::get_default_certification_dates($certification);
        $programisdifferent = api::is_recertification_program_different($certification, $certificationuser);

        // Basic details section.
        $mform->addElement('header', 'basichdr', get_string('general'));
        $mform->setExpanded('basichdr');

        $mform->addElement('hidden', 'certificationuserid');
        $mform->setType('certificationuserid', PARAM_INT);

        $defaultstr = get_string('default', 'tool_certification');
        $statusstr = get_string('status', 'tool_certification');
        $certstatusstr = get_string('certificationstatus', 'tool_certification');
        $suspendedstr = get_string('suspended', 'tool_certification');
        $startdatestr = new lang_string('startdate', 'tool_certification');
        $startdatenextstr = new lang_string('recertificationstartdate', 'tool_certification');
        $dateabsolutestr = new lang_string('selectdate', 'tool_certification');
        $duedatestr = new lang_string('duedate', 'tool_certification');
        $graceperiodendsstr = new lang_string('recertgraceperiodends', 'tool_certification');
        $neverstr = new lang_string('never', 'tool_certification');
        $expirydatestr = new lang_string('certifyexpirydate', 'tool_certification');
        $selectdatestr = new lang_string('selectdate', 'tool_certification');

        $options = [
            constants::STATUS_OVERRIDE_DEFAULT => $defaultstr,
            constants::STATUS_OVERRIDE_SUSPENDED => $suspendedstr,
        ];
        $mform->addElement('select', 'status', $statusstr, $options);
        $mform->setDefault('status', constants::STATUS_OVERRIDE_DEFAULT);
        $mform->addHelpButton('status', 'userstatus', 'tool_certification');

        // TODO: Cleaner code for this visual hack.
        $statuses = api::get_user_allocation_status($certificationuser->get('certificationid'), $userid);
        $htmlstatuses = '';
        foreach ($statuses as $status) {
            $htmlstatuses .= \html_writer::span($status['statusstr'], 'tool_certification_'.$status['status']);
        }
        $html = \html_writer::div($htmlstatuses, 'form-control-static');

        $mform->addElement('static', 'certificationstatus', $certstatusstr, $html);
        $mform->addHelpButton('certificationstatus', 'certificationstatus', 'tool_certification');

        // Expiry date.
        if ($iscertified) {
            $choices = [
                constants::DATE_NONE => $defaultdates->expirydate . ' (' . core_text::strtolower($defaultstr) . ')',
                constants::DATE_NEVER => $neverstr,
                constants::DATE_ABSOLUTE => $selectdatestr,
            ];
            $group = [];
            $group[] =& $mform->createElement('select', 'expirydatetype', '', $choices, ['style' => 'max-width: 300px']);
            $group[] =& $mform->createElement('date_time_selector', 'expirydate', '');
            $mform->addGroup($group, 'expirydateformgroup', $expirydatestr, ' ', false);
            $mform->hideIf('expirydate', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->disabledIf('expirydate', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->addHelpButton('expirydateformgroup', 'certifyexpirydate', 'tool_certification');
            $mform->setType('expirydatetype', PARAM_INT);
            $mform->setDefault('expirydatetype', constants::DATE_NONE);
        }

        if ($iscertified && (int)$certification->get('requirerecertification') === 1) {
            // Rcertification details section.
            $mform->addElement('header', 'recertificationhdr', get_string('recertification', 'tool_certification'));
            $mform->setExpanded('recertificationhdr');
        }

        if (!$iscertified) {
            $options = [
                constants::DATE_NONE => $defaultdates->startdate . ' (' . core_text::strtolower($defaultstr) . ')',
                constants::DATE_ABSOLUTE => $dateabsolutestr,
            ];
            $group = [];
            $group[] =& $mform->createElement('select', 'startdatetype', '', $options);
            $group[] =& $mform->createElement('date_time_selector', 'startdate', '');
            $mform->addGroup($group, 'userstartdateformgroup', $startdatestr, ' ', false);
            $mform->hideIf('startdate', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->disabledIf('startdate', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->setDefault('startdatetype', constants::DATE_NONE);
            $mform->addHelpButton('userstartdateformgroup', 'userstartdate', 'tool_certification');

        } else if ($iscertified && !$currentprogramid && (int)$certification->get('requirerecertification') === 1) {
            $group = [];
            $group[] =& $mform->createElement('date_time_selector', 'startdate', '');
            $mform->addGroup($group, 'userstartdateformgroup', $startdatenextstr, ' ', false);
            $mform->hideIf('startdate', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->disabledIf('startdate', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->setDefault('startdatetype', constants::DATE_NONE);
            $mform->addHelpButton('userstartdateformgroup', 'userstartdate', 'tool_certification');

        } else if ((int)$certification->get('requirerecertification') === 1) {
            $mform->addElement('date_time_selector', 'startdate', $startdatenextstr)->freeze();
            $mform->addHelpButton('startdate', 'userstartdate', 'tool_certification');
        }

        // Due date.
        if (!$iscertified) {
            $options = [
                constants::DATE_NONE => $defaultdates->duedate . ' (' . core_text::strtolower($defaultstr) . ')',
                constants::DATE_ABSOLUTE => $dateabsolutestr,
            ];
            $group = [];
            $group[] =& $mform->createElement('select', 'duedatetype', '', $options);
            $group[] =& $mform->createElement('date_time_selector', 'duedate', '');
            $mform->addGroup($group, 'userduedateformgroup', $duedatestr, ' ', false);
            $mform->hideIf('duedate', 'duedatetype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->disabledIf('duedate', 'duedatetype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->setDefault('duedatetype', constants::DATE_NONE);
            $mform->addHelpButton('userduedateformgroup', 'userduedate', 'tool_certification');

        } else if ($iscertified && (int)$certification->get('requirerecertification') === 1) {
            // Due date is locked to be equal to expiry date.
            $mform->addElement('date_time_selector', 'duedate', $duedatestr)->freeze();
            $mform->addHelpButton('duedate', 'userduedate', 'tool_certification');
        }

        // Grace period.
        if ($programisdifferent && $iscertified && (int)$certification->get('requirerecertification') === 1) {
            $options = [
                constants::DATE_NONE => $defaultdates->graceperiod . ' (' . core_text::strtolower($defaultstr) . ')',
                constants::DATE_ABSOLUTE => $dateabsolutestr,
            ];
            $group = [];
            $group[] =& $mform->createElement('select', 'graceperiodendstype', '', $options);
            $group[] =& $mform->createElement('date_time_selector', 'graceperiodends', '');
            $mform->addGroup($group, 'graceperiodendsformgroup', $graceperiodendsstr, ' ', false);
            $mform->hideIf('graceperiodends', 'graceperiodendstype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->disabledIf('graceperiodends', 'graceperiodendstype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->setDefault('graceperiodendstype', constants::DATE_NONE);
            $mform->addHelpButton('graceperiodendsformgroup', 'recertgraceperiod', 'tool_certification');
        }

        // Program dropdown.
        if ($iscertified && $currentprogramid && (int)$certification->get('requirerecertification') === 1) {
            // Re-certification program.
            $program = new program($currentprogramid);
            $choices = [$currentprogramid => format_string($program->get('fullname'))];
            // If user does not have current recertification program set in certifictaion settings give the choice to change it.
            if ((int)$currentprogramid !== (int)$certification->get('recertificationprogram')
                && (int)$certification->get('recertdifferentprogram') === 1) {
                $recertprogram = new program((int)$certification->get('recertificationprogram'));
                $choices[(int)$certification->get('recertificationprogram')] = format_string($recertprogram->get('fullname'));
            }
            if ((int)$currentprogramid !== (int)$certification->get('program')
                && (int)$certification->get('recertdifferentprogram') === 0) {
                $recertprogram = new program((int)$certification->get('program'));
                $choices[(int)$certification->get('program')] = format_string($recertprogram->get('fullname'));
            }

            $currentprogramstr = get_string('currentprogram', 'tool_certification');
            $group = [];
            $group[] =& $mform->createElement('select', 'currentprogram', '', $choices);
            $mform->addGroup($group, 'currentprogramgroup', $currentprogramstr, ' ', false);
            $mform->addHelpButton('currentprogramgroup', 'currentprogram', 'tool_certification');

            // Reset additional courses (checkbox, hidden unless "current program" was changed).
            // Explanation - reset the courses that are part of the new program that were not part of the old program.
            $mform->addElement('checkbox', 'resetprogramcourses', '',
                get_string('resetadditionalcourses', 'tool_certification'), ['disabled' => 'disabled']);
            $mform->setDefault('resetprogramcourses', 0);
            $mform->addHelpButton('resetprogramcourses', 'resetadditionalcourses', 'tool_certification');
            $mform->hideIf('resetprogramcourses', 'currentprogram', 'eq', $currentprogramid);
            $mform->disabledIf('resetprogramcourses', 'currentprogram', 'eq', $currentprogramid);
        }
    }

    /**
     * Require capabilities.
     */
    public function check_access_for_dynamic_submission(): void {
        permission::require_can_edit_user_allocation($this->get_certification_user());
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_dynamic_submission(): void {
        $certificationuser = $this->get_certification_user();
        $userid = $certificationuser->get('userid');
        $certificationid = $certificationuser->get('certificationid');
        /** @var program_user $programuser */
        $programuser = program_user::get_record(['userid' => $userid, 'certificationid' => $certificationid]);
        $startdate = $programuser->get('startdate');

        // If user is certified we need expirydate and expirydatetype.
        $iscertified = (bool)api::get_last_completion_record($userid, $certificationid);
        $expirydate = 0;
        $expirydatetype = 0;
        $graceperiodends = 0;
        $graceperiodendslocked = 0;
        $duedate = $programuser->get('duedate');
        if ($iscertified) {
            $startdate = $certificationuser->get('nextstartdate');
            $lastcompletion = api::get_last_completion_record($userid, $certificationid);
            // When user has been certified at least once the due date on the form should always be locked to be the equal as the
            // expiry date.
            $expirydate = $duedate = (int)$lastcompletion->get('expirydate');
            $expirydatetype = ($expirydate === 0 ? constants::DATE_NEVER : constants::DATE_ABSOLUTE);
            $graceperiodends = $certificationuser->get('graceperiodends');
            $graceperiodendslocked = $certificationuser->get('graceperiodendslocked');
        }

        $formdata = [
            'certificationuserid' => $certificationuser->get('id'),
            'status' => $certificationuser->get('status'),
            'startdatetype' => $programuser->get('startdatelocked'),
            'startdate' => $startdate,
            'duedatetype' => $programuser->get('duedatelocked'),
            'duedate' => $duedate,
            'expirydatetype' => $expirydatetype,
            'expirydate' => $expirydate,
            'graceperiodends' => $graceperiodends,
            'graceperiodendstype' => $graceperiodendslocked
        ];
        $this->set_data($formdata);
    }

    /**
     * Process data submited by the form.
     *
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $certificationuser = $this->get_certification_user();
        $certification = $certificationuser->get_certification();
        $userid = $certificationuser->get('userid');
        // Is certified or certified and expired.
        $iscertified = (bool)api::get_last_completion_record($userid, $certification->get('id'));

        if (!$iscertified) {
            // User is not certified.
            $data->duedatelocked = $data->duedatetype;
            $data->startdatelocked = $data->startdatetype;
        } else {
            // Certified and recertification in progress.
            $data->expirydate = self::get_expiry_date($data, $certificationuser);
            $data->graceperiodendstype = $data->graceperiodendstype ?? 0;
            $data->graceperiodendslocked = $data->graceperiodendstype;
        }

        api::update_certification_user_dates_and_status($certificationuser, $data);
    }

    /**
     * Gets absolute expiry date
     *
     * @param \stdClass $data
     * @param certification_user $certificationuser
     * @return int
     * @throws coding_exception
     */
    private static function get_expiry_date(\stdClass $data, certification_user $certificationuser): int {
        $certification = $certificationuser->get_certification();
        $userid = $certificationuser->get('userid');
        $certificationid = $certificationuser->get('certificationid');

        switch ($data->expirydatetype) {
            case constants::DATE_NONE:
                $userallocdate = (int) $certificationuser->get('timecreated');
                $programuser = program_user::get_record(['certificationid' => $certificationid, 'userid' => $userid]);
                $userduedate = $programuser->get('duedate');
                return api::recalculate_user_expiry_date($certification, $certificationuser, $userallocdate, $userduedate);
                break;
            case constants::DATE_NEVER:
                return 0;
                break;
            case constants::DATE_ABSOLUTE:
                // Expiry date is already in absolute format.
                return $data->expirydate;
                break;
            default:
                throw new coding_exception('unexpected certification expiry date type');
                break;
        }
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
            'id' => $this->get_certification_user()->get('id'),
        ]);
    }
}
