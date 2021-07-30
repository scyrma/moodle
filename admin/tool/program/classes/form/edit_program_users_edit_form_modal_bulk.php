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
 * Form to edit program users in bulk.
 *
 * @package   tool_program
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\form;

use core_form\dynamic_form;
use stdClass;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_user;

defined('MOODLE_INTERNAL') || die();

/**
 * Class edit_program_users_edit_form_modal_bulk
 *
 * @package   tool_program
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_program_users_edit_form_modal_bulk extends dynamic_form {
    /** @var int Date has been overriden and set to a specific date */
    protected const DATE_OVERRIDE_ABSOLUTE = 1;
    /** @var int Date has been overriden and set to never (not set) */
    protected const DATE_OVERRIDE_NEVER = 2;
    /** @var int Date will not be modified */
    protected const DATE_DONT_CHANGE = -1;

    /** @var program */
    protected $program;

    /**
     * Current program
     *
     * @return program
     */
    protected function get_program(): program {
        if (!$this->program) {
            $this->program = new program($this->optional_param('programid', 0, PARAM_INT));
        }
        return $this->program;
    }

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;
        $program = $this->get_program();

        $dontchangestr = get_string('dontchange', 'tool_program');
        $startdatestr = get_string('startdate', 'tool_program');
        $duedatestr = get_string('duedate', 'tool_program');
        $enddatestr = get_string('enddate', 'tool_program');
        $dateabsolutestr = get_string('datetypeabsolute', 'tool_program');
        $activestr = get_string('active', 'tool_program');
        $statusstr = get_string('status', 'tool_program');
        $suspendedstr = get_string('suspended', 'tool_program');
        $neverstr = get_string('never', 'tool_program');

        $mform->addElement('hidden', 'programid', $program->get('id'));
        $mform->setType('programid', PARAM_INT);

        $mform->addElement('hidden', 'programuserids', $this->_ajaxformdata['programuserids']);
        $mform->setType('programuserids', PARAM_RAW);

        $choices = [
            self::DATE_DONT_CHANGE => $dontchangestr,
            constants::STATUS_OVERRIDE_DEFAULT => $activestr,
            constants::STATUS_OVERRIDE_SUSPENDED => $suspendedstr,
        ];
        $mform->addElement('select', 'status', $statusstr, $choices);
        $mform->setDefault('status', self::DATE_DONT_CHANGE);
        $mform->addHelpButton('status', 'status', 'tool_program');

        // Start date.
        $availabilitystartdateoptions = [
            self::DATE_DONT_CHANGE => $dontchangestr,
            self::DATE_OVERRIDE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'startdatetype', '', $availabilitystartdateoptions);
        $group[] =& $mform->createElement('date_time_selector', 'startdate', '');
        $mform->addGroup($group, 'userstartdateformgroup', $startdatestr, ' ', false);
        $mform->hideIf('startdate', 'startdatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->disabledIf('startdate', 'startdatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->setDefault('startdatetype', self::DATE_DONT_CHANGE);
        $mform->addHelpButton('userstartdateformgroup', 'userstartdate', 'tool_program');

        // Due date.
        $availabilityduedateoptions = [
            self::DATE_DONT_CHANGE => $dontchangestr,
            self::DATE_OVERRIDE_NEVER => $neverstr,
            self::DATE_OVERRIDE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'duedatetype', '', $availabilityduedateoptions);
        $group[] =& $mform->createElement('date_time_selector', 'duedate', '');
        $mform->addGroup($group, 'userduedateformgroup', $duedatestr, ' ', false);
        $mform->hideIf('duedate', 'duedatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->disabledIf('duedate', 'duedatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->setDefault('duedatetype', self::DATE_DONT_CHANGE);
        $mform->addHelpButton('userduedateformgroup', 'userduedate', 'tool_program');

        // End date.
        $availabilityenddateoptions = [
            self::DATE_DONT_CHANGE => $dontchangestr,
            self::DATE_OVERRIDE_NEVER => $neverstr,
            self::DATE_OVERRIDE_ABSOLUTE => $dateabsolutestr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'enddatetype', '', $availabilityenddateoptions);
        $group[] =& $mform->createElement('date_time_selector', 'enddate', '');
        $mform->addGroup($group, 'userenddateformgroup', $enddatestr, ' ', false);
        $mform->hideIf('enddate', 'enddatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->disabledIf('enddate', 'enddatetype', 'noteq', self::DATE_OVERRIDE_ABSOLUTE);
        $mform->setDefault('enddatetype', self::DATE_DONT_CHANGE);
        $mform->addHelpButton('userenddateformgroup', 'userenddate', 'tool_program');
    }

    /**
     * Require capabilities.
     */
    public function check_access_for_dynamic_submission(): void {
        permission::require_can_view_allocated_users($this->get_program());
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_dynamic_submission(): void {
        $formdata = [
            'programid' => $this->get_program()->get('id'),
            'programuserids' => serialize($this->_ajaxformdata['programuserids']),
        ];
        $this->set_data($formdata);
    }

    /**
     * Process data submited by the form.
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $programuserids = unserialize($data->programuserids);
        $successcount = 0;
        $skippedcount = 0;

        foreach ($programuserids as $programuserid) {
            $formdata = $data;
            $programuser = new program_user($programuserid);
            if (!$programuser || !permission::can_edit_user_allocation($programuser)) {
                $skippedcount++;
                continue;
            }

            if ($formdata->status == self::DATE_DONT_CHANGE) {
                $formdata->status = $programuser->get('status');
            }

            if ($formdata->startdatetype == self::DATE_DONT_CHANGE) {
                $formdata->startdate = $programuser->get('startdate');
                $formdata->startdatelocked = $programuser->get('startdatelocked');
            } else {
                $formdata->startdatelocked = $formdata->startdatetype;
            }

            switch ($formdata->duedatetype) {
                case self::DATE_DONT_CHANGE:
                    $formdata->duedate = $programuser->get('duedate');
                    $formdata->duedatelocked = $programuser->get('duedatelocked');
                    break;
                case self::DATE_OVERRIDE_NEVER:
                    $formdata->duedate = constants::DATE_NONE;
                    $formdata->duedatelocked = constants::DATE_LOCKED;
                    break;
                case self::DATE_OVERRIDE_ABSOLUTE:
                    $formdata->duedatelocked = constants::DATE_LOCKED;
                    break;
                default:
                    throw new \coding_exception('unexpected program due date type');
                    break;
            }

            switch ($formdata->enddatetype) {
                case self::DATE_DONT_CHANGE:
                    $formdata->enddate = $programuser->get('enddate');
                    $formdata->enddatelocked = $programuser->get('enddatelocked');
                    break;
                case self::DATE_OVERRIDE_NEVER:
                    $formdata->enddate = constants::DATE_NONE;
                    $formdata->enddatelocked = constants::DATE_LOCKED;
                    break;
                case self::DATE_OVERRIDE_ABSOLUTE:
                    $formdata->enddatelocked = constants::DATE_LOCKED;
                    break;
                default:
                    throw new \coding_exception('unexpected program end date type');
                    break;
            }

            api::update_program_user_dates_and_status($programuser, $formdata);
            $successcount++;
        }
        return ['successcount' => $successcount, 'skippedcount' => $skippedcount];
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
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'id' => $id,
        ]);
    }
}
