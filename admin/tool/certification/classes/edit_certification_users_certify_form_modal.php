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
 * Class tool_certification\edit_certification_users_certify_form_modal
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;

use coding_exception;
use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class edit_certification_users_certify_form_modal
 *
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_certification_users_certify_form_modal extends \tool_wp\modal_form {
    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {
        $mform = $this->_form;
        $certificationid = $this->_ajaxformdata['id'];
        $userid = $this->_ajaxformdata['userid'];

        $mform->addElement('hidden', 'id', $certificationid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid', $userid);
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'certificationuserid', $userid);
        $mform->setType('certificationuserid', PARAM_INT);

        $markcompletedstr = get_string('markcertificationcompletednotice', 'tool_certification');
        $mform->addElement('static', '', '', $markcompletedstr);

        $neverstr = get_string('never', 'tool_certification');
        $defaultstr = get_string('default');
        $expirydatestr = get_string('certifyexpirydate', 'tool_certification');
        $selectdatestr = get_string('selectdate', 'tool_certification');

        $certification = new certification($certificationid);
        $defaultdates = api::get_default_certification_dates($certification);

        // Expiry date.
        $choices = [
            constants::DATE_NONE => $defaultdates->expirydate . ' (' . $defaultstr . ')',
            constants::DATE_NEVER => $neverstr,
            constants::DATE_ABSOLUTE => $selectdatestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'expirydatetype', '', $choices);
        $group[] =& $mform->createElement('date_selector', 'expirydateabsolute', '');
        $mform->addGroup($group, 'expirydateformgroup', $expirydatestr, ' ', false);
        $mform->hideIf('expirydateabsolute', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('expirydateabsolute', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->addHelpButton('expirydateformgroup', 'certifyexpirydate', 'tool_certification');
        $mform->setType('expirydatetype', PARAM_INT);
        $mform->setDefault('expirydatetype', constants::DATE_NONE);

        // Suspend program allocation.
        $leaveuserallocatedstr = get_string('markcertifyleaveusernotice', 'tool_certification');
        $suspendprogallocstr = get_string('markcertifysuspendnotice', 'tool_certification');

        $mform->addElement('radio', 'suspendprogallocation', '', $leaveuserallocatedstr, 0);
        $mform->addElement('radio', 'suspendprogallocation', '', $suspendprogallocstr, 1);
        $mform->setType('suspendprogallocation', PARAM_INT);
        $mform->setDefault('suspendprogallocation', 1);
    }

    /**
     * Require capabilities.
     */
    public function require_access(): void {
        $certification = new certification($this->_ajaxformdata['id']);
        permission::require_can_edit_details($certification, context_system::instance());
    }

    /**
     * Set Data for the certify user modal form.
     */
    public function set_data_for_modal() {
        $certificationuserid = $this->_ajaxformdata['certificationuserid'];
        $userid = $this->_ajaxformdata['userid'];
        $certificationid = $this->_ajaxformdata['id'];
        $certificationuser = new certification_user($certificationuserid);

        if (constants::ALLOCATION_MANUAL !== (int) $certificationuser->get('allocationtype')) {
            throw new \moodle_exception('allocationnoteditable', 'tool_certification');
        }
        $formdata = [
            'userid' => $userid,
            'id' => $certificationid,
            'certificationuserid' => $certificationuserid,
        ];
        $this->set_data($formdata);
    }

    /**
     * Process data submited by the certify user form.
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
        $certification = new certification($data->id);
        $certificationuser = new certification_user($data->certificationuserid);

        switch ($data->expirydatetype) {
            case constants::DATE_NONE:
                // Recalculate user dates.
                $certificationuser->set('expirydatelocked', constants::DATE_UNLOCKED);
                $certificationuser->update();
                api::recalculate_certification_user_dates($certification, $certificationuser);

                // We get user calculated default date.
                $expirydate = $certificationuser->get('expirydate');

                // If type is after completion we set now as the completion time.
                $expyrydatetype = (int) $certification->get('expirydatetype');
                if ($expyrydatetype === constants::DATE_AFTER_COMPLETION) {
                    $expirydaterelative = $certification->get('expirydate' . 'relative');
                    $expirydate = strtotime('+' . $expirydaterelative);
                }

                $certificationuser->set('expirydatelocked', constants::DATE_UNLOCKED);
                break;
            case constants::DATE_NEVER:
                // Override user expiry date.
                $certificationuser->set('expirydatelocked', constants::DATE_LOCKED);
                $expirydate = 0;
                break;
            case constants::DATE_ABSOLUTE:
                // Override user expiry date.
                $certificationuser->set('expirydatelocked', constants::DATE_LOCKED);
                $expirydate = $data->expirydateabsolute;
                break;
            default:
                throw new coding_exception('unexpected certification expiry date type');
                break;
        }

        $certificationuser->set('expirydate', $expirydate);
        $certificationuser->update();

        api::set_user_as_certified($data->userid, $data->id, (bool) $data->suspendprogallocation, $expirydate);
    }
}
