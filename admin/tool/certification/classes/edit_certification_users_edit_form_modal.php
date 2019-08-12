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
 * Class tool_certification\edit_certification_users_edit_form_modal
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;

use coding_exception;
use core_text;
use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class edit_certification_users_edit_form_modal
 *
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_certification_users_edit_form_modal extends \tool_wp\modal_form {
    /** @var certification_user */
    protected $certificationuser;

    /**
     * Current certification_user
     *
     * @return certification_user
     */
    protected function get_certification_user(): certification_user {
        if (!$this->certificationuser) {
            $certificationuserid = $this->_ajaxformdata['certificationuserid'];
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

        // Loads default startdate, duedate and expirydate.
        $defaultdates = api::get_default_certification_dates($certification);

        $startdatestr = get_string('startdate', 'tool_certification');
        $duedatestr = get_string('duedate', 'tool_certification');
        $dateabsolutestr = get_string('selectdate', 'tool_certification');
        $defaultstr = get_string('default', 'tool_certification');
        $statusstr = get_string('status', 'tool_certification');
        $suspendedstr = get_string('suspended', 'tool_certification');

        $mform->addElement('hidden', 'certificationuserid');
        $mform->setType('certificationuserid', PARAM_INT);

        $options = [
            constants::STATUS_OVERRIDE_DEFAULT => $defaultstr,
            constants::STATUS_OVERRIDE_SUSPENDED => $suspendedstr,
        ];
        $mform->addElement('select', 'status', $statusstr, $options);
        $mform->setDefault('status', constants::STATUS_OVERRIDE_DEFAULT);
        $mform->addHelpButton('status', 'userstatus', 'tool_certification');

        // Start date.
        $options = [
            constants::DATE_NONE => $defaultdates->startdate . ' (' . core_text::strtolower($defaultstr) . ')',
            constants::DATE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'startdatetype', '', $options);
        $group[] =& $mform->createElement('date_selector', 'startdate', '');
        $mform->addGroup($group, 'userstartdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('startdate', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('startdate', 'startdatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->setDefault('startdatetype', constants::DATE_NONE);
        $mform->addHelpButton('userstartdateformgroup', 'userstartdate', 'tool_certification');

        // Due date.
        $options = [
            constants::DATE_NONE => $defaultdates->duedate . ' (' . core_text::strtolower($defaultstr) . ')',
            constants::DATE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'duedatetype', '', $options);
        $group[] =& $mform->createElement('date_selector', 'duedate', '');
        $mform->addGroup($group, 'userduedateformgroup', $duedatestr, ' ', false);
        $mform->hideIf('duedate', 'duedatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('duedate', 'duedatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->setDefault('duedatetype', constants::DATE_NONE);
        $mform->addHelpButton('userduedateformgroup', 'userduedate', 'tool_certification');

        // If user is certified allow to set manually an expiry date.
        $iscertified = api::is_user_certified($userid, $certification->get('id'));
        if ($iscertified) {
            $neverstr = get_string('never', 'tool_certification');
            $expirydatestr = get_string('certifyexpirydate', 'tool_certification');
            $selectdatestr = get_string('selectdate', 'tool_certification');

            // Expiry date.
            $choices = [
                constants::DATE_NONE => $defaultdates->expirydate . ' (' . core_text::strtolower($defaultstr) . ')',
                constants::DATE_NEVER => $neverstr,
                constants::DATE_ABSOLUTE => $selectdatestr,
            ];
            $group = [];
            $group[] =& $mform->createElement('select', 'expirydatetype', '', $choices);
            $group[] =& $mform->createElement('date_selector', 'expirydate', '');
            $mform->addGroup($group, 'expirydateformgroup', $expirydatestr, ' ', false);
            $mform->hideIf('expirydate', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->disabledIf('expirydate', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
            $mform->addHelpButton('expirydateformgroup', 'certifyexpirydate', 'tool_certification');
            $mform->setType('expirydatetype', PARAM_INT);
            $mform->setDefault('expirydatetype', constants::DATE_NONE);
        }

        $this->add_action_buttons();
    }

    /**
     * Require capabilities.
     */
    public function require_access(): void {
        permission::require_can_edit_user_allocation($this->get_certification_user());
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_modal() {
        $certificationuser = $this->get_certification_user();
        $userid = $certificationuser->get('userid');
        $certificationid = $certificationuser->get('certificationid');

        // Calculate expirydatetype (never, absolute or none).
        $expirydate = (int) $certificationuser->get('expirydate');
        $expirydatelocked = (int) $certificationuser->get('expirydatelocked');
        $iscertified = api::is_user_certified($userid, $certificationid);
        $expirydatetype = 0;
        if ($iscertified) {
            $expirydatetype = ($expirydate === 0 ? constants::DATE_NEVER : constants::DATE_ABSOLUTE);
            if ($expirydatelocked === constants::DATE_UNLOCKED) {
                $expirydatetype = constants::DATE_NONE;
            }
        }

        $formdata = [
            'certificationuserid' => $certificationuser->get('id'),
            'status' => $certificationuser->get('status'),
            'startdatetype' => $certificationuser->get('startdatelocked'),
            'startdate' => $certificationuser->get('startdate'),
            'duedatetype' => $certificationuser->get('duedatelocked'),
            'duedate' => $certificationuser->get('duedate'),
            'expirydatetype' => $expirydatetype,
            'expirydate' => $expirydate,
        ];
        $this->set_data($formdata);
    }

    /**
     * Process data submited by the form.
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
        $certificationuser = $this->get_certification_user();

        $data->duedatelocked = $data->duedatetype;
        $data->startdatelocked = $data->startdatetype;

        if (isset($data->expirydatetype)) {
            switch ($data->expirydatetype) {
                case constants::DATE_NONE:
                    // Recalculate user dates.
                    $certificationuser->set('expirydatelocked', constants::DATE_UNLOCKED);
                    $certificationuser->update();
                    $certification = $certificationuser->get_certification();
                    api::recalculate_certification_user_dates($certification, $certificationuser);

                    // We get user calculated default date.
                    $data->expirydate = $certificationuser->get('expirydate');
                    break;
                case constants::DATE_NEVER:
                    $data->expirydate = 0;
                    break;
                case constants::DATE_ABSOLUTE:
                    // Expiry date is already in absolute format.
                    break;
                default:
                    throw new coding_exception('unexpected certification expiry date type');
                    break;
            }
        }

        api::update_certification_user_dates_and_status($certificationuser, $data);
    }
}
