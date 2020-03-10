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
 * Module edit form
 *
 * Copyright (C) 2007-2011 Catalyst IT (http://www.catalyst.net.nz)
 * Copyright (C) 2011-2013 Totara LMS (http://www.totaralms.com)
 * Copyright (C) 2014 onwards Catalyst IT (http://www.catalyst-eu.net)
 *
 * @package    mod_appointment
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @author     Stacey Walker <stacey@catalyst-eu.net>
 * @author     Alastair Munro <alastair.munro@totaralms.com>
 * @author     Aaron Barnes <aaron.barnes@totaralms.com>
 * @author     Francois Marier <francois@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/appointment/lib.php');

/**
 * Class mod_appointment_mod_form
 *
 * @package    mod_appointment
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_appointment_mod_form extends moodleform_mod {

    /**
     * Form definition
     */
    public function definition() {
        global $CFG;

        $mform =& $this->_form;

        // General.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('text', 'thirdparty', get_string('thirdpartyemailaddress', 'appointment'), array('size' => '64'));
        $mform->setType('thirdparty', PARAM_NOTAGS);
        $mform->addHelpButton('thirdparty', 'thirdpartyemailaddress', 'appointment');

        $mform->addElement('checkbox', 'thirdpartywaitlist', get_string('thirdpartywaitlist', 'appointment'));
        $mform->addHelpButton('thirdpartywaitlist', 'thirdpartywaitlist', 'appointment');

        if (has_capability('mod/appointment:configurecancellation', $this->context)) {
            $mform->addElement('advcheckbox', 'allowcancellationsdefault',
                get_string('allowcancellationsdefault', 'appointment'));
            $mform->setDefault('allowcancellationsdefault', 1);
            $mform->addHelpButton('allowcancellationsdefault', 'allowcancellationsdefault', 'appointment');
        }

        $mform->addElement('header', 'calendaroptions', get_string('calendaroptions', 'appointment'));

        $calendaroptions = array(
            MOD_APPOINTMENT_CAL_NONE => get_string('none'),
            MOD_APPOINTMENT_CAL_COURSE => get_string('course'),
            MOD_APPOINTMENT_CAL_SITE => get_string('site')
        );
        $mform->addElement('select', 'showoncalendar', get_string('showoncalendar', 'appointment'), $calendaroptions);
        $mform->setDefault('showoncalendar', MOD_APPOINTMENT_CAL_COURSE);
        $mform->addHelpButton('showoncalendar', 'showoncalendar', 'appointment');

        $mform->addElement('advcheckbox', 'usercalentry', get_string('usercalentry', 'appointment'));
        $mform->setDefault('usercalentry', true);
        $mform->addHelpButton('usercalentry', 'usercalentry', 'appointment');

        $mform->addElement('text', 'shortname', get_string('shortname'), array('size' => 32, 'maxlength' => 32));
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addHelpButton('shortname', 'shortname', 'appointment');
        $mform->addRule('shortname', null, 'maxlength', 32);

        $features = new stdClass;
        $features->groups = false;
        $features->groupings = false;
        $features->groupmembersonly = false;
        $features->outcomes = false;
        $features->gradecat = false;
        $features->idnumber = true;
        $this->standard_coursemodule_elements($features);

        $this->add_action_buttons();
    }

    /**
     * Preprocess data
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {

        // Fix manager emails.
        if (empty($defaultvalues['confirmationinstrmngr'])) {
            $defaultvalues['confirmationinstrmngr'] = null;
        } else {
            $defaultvalues['emailmanagerconfirmation'] = 1;
        }

        if (empty($defaultvalues['reminderinstrmngr'])) {
            $defaultvalues['reminderinstrmngr'] = null;
        } else {
            $defaultvalues['emailmanagerreminder'] = 1;
        }

        if (empty($defaultvalues['cancellationinstrmngr'])) {
            $defaultvalues['cancellationinstrmngr'] = null;
        } else {
            $defaultvalues['emailmanagercancellation'] = 1;
        }

        $defaultvalues['confirmationsubject'] = get_string('setting:defaultconfirmationsubjectdefault', 'mod_appointment');
        $defaultvalues['confirmationmessage'] = get_string('setting:defaultconfirmationmessagedefault', 'mod_appointment');
        $defaultvalues['remindersubject'] = get_string('setting:defaultremindersubjectdefault', 'mod_appointment');
        $defaultvalues['remindermessage'] = get_string('setting:defaultremindermessagedefault', 'mod_appointment');
        $defaultvalues['reminderperiod'] = 2;
        $defaultvalues['waitlistedsubject'] = get_string('setting:defaultwaitlistedsubjectdefault', 'mod_appointment');
        $defaultvalues['waitlistedmessage'] = get_string('setting:defaultwaitlistedmessagedefault', 'mod_appointment');
        $defaultvalues['cancellationsubject'] = get_string('setting:defaultcancellationsubjectdefault', 'mod_appointment');
        $defaultvalues['cancellationmessage'] = get_string('setting:defaultcancellationmessagedefault', 'mod_appointment');
    }
}
