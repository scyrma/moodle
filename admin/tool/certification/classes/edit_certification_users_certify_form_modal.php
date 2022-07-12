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
 * Class tool_certification\edit_certification_users_certify_form_modal
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use coding_exception;
use context;
use context_system;
use core_form\dynamic_form;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class edit_certification_users_certify_form_modal
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_certification_users_certify_form_modal extends dynamic_form {
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

        $mform->addElement('hidden', 'certificationuserid');
        $mform->setType('certificationuserid', PARAM_INT);

        $markcompletedstr = get_string('markcertificationcompletednotice', 'tool_certification');
        $mform->addElement('static', '', '', $markcompletedstr);

        $neverstr = get_string('never', 'tool_certification');
        $defaultstr = get_string('default');
        $expirydatestr = get_string('certifyexpirydate', 'tool_certification');
        $selectdatestr = get_string('selectdate', 'tool_certification');

        $certification = $this->get_certification_user()->get_certification();
        $defaultdates = api::get_default_certification_dates($certification);

        // Expiry date.
        $choices = [
            constants::DATE_NONE => $defaultdates->expirydate . ' (' . $defaultstr . ')',
            constants::DATE_NEVER => $neverstr,
            constants::DATE_ABSOLUTE => $selectdatestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'expirydatetype', '', $choices);
        $group[] =& $mform->createElement('date_time_selector', 'expirydateabsolute', '');
        $mform->addGroup($group, 'expirydateformgroup', $expirydatestr, ' ', false);
        $mform->hideIf('expirydateabsolute', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->disabledIf('expirydateabsolute', 'expirydatetype', 'noteq', constants::DATE_ABSOLUTE);
        $mform->addHelpButton('expirydateformgroup', 'certifyexpirydate', 'tool_certification');
        $mform->setType('expirydatetype', PARAM_INT);
        $mform->setDefault('expirydatetype', constants::DATE_NONE);
    }

    /**
     * Require capabilities.
     */
    public function check_access_for_dynamic_submission(): void {
        $certificationuser = $this->get_certification_user();
        $certified = api::is_user_certified($certificationuser->get('userid'), $certificationuser->get('certificationid'));
        permission::require_can_certify_user_deep($certificationuser, $certified);
    }

    /**
     * Set Data for the certify user modal form.
     */
    public function set_data_for_dynamic_submission(): void {
        $certificationuser = $this->get_certification_user();

        $formdata = [
            'certificationuserid' => $certificationuser->get('id'),
        ];
        $this->set_data($formdata);
    }

    /**
     * Process data submitted by the certify user form.
     *
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        global $USER;

        $data = $this->get_data();
        $certificationuser = $this->get_certification_user();
        $certification = $certificationuser->get_certification();

        switch ($data->expirydatetype) {
            case constants::DATE_NONE:
                $expirydate = null;
                break;
            case constants::DATE_NEVER:
                // Override user expiry date.
                $expirydate = 0;
                break;
            case constants::DATE_ABSOLUTE:
                // Override user expiry date.
                $expirydate = $data->expirydateabsolute;
                break;
            default:
                throw new coding_exception('unexpected certification expiry date type');
                break;
        }

        api::set_user_as_certified($certificationuser->get('userid'), $certification->get('id'), $expirydate, 0, $USER->id);
        // TODO WP-2986 - if the nextstartdate is in the past - start the next certification round immediately but without
        // resetting the program. Display a notice that program will not be reset. See issue for more details.
    }

    /**
     * Perform some extra moodle validation
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws coding_exception
     */
    public function validation($data, $files): array {
        $errors = [];
        if (isset($data['timecertified']) && $data['timecertified'] > time()) {
            $errors['timecertified'] = get_string('errorinvalidtimecertified', 'tool_certification');
        }
        // We check that a manual selected date cannot be in the past.
        if ((int)$data['expirydatetype'] === constants::DATE_ABSOLUTE && (int)$data['expirydateabsolute'] < time()) {
            $errors['expirydateformgroup'] = get_string('errorinvalidpastexpirydate', 'tool_certification');
        }

        return $errors;
    }

    /**
     * Returns context where this form is used
     *
     * @return context
     */
    public function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'id' => $this->get_certification_user()->get('id'),
        ]);
    }
}
