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
 * Messages form
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      Daniel Neis Araujo <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace mod_appointment\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Class mod_appointment\form\messages
 *
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class messages extends \moodleform {

    /**
     * Form definition
     */
    public function definition() {

        $mform =& $this->_form;

        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->setType('cmid', PARAM_INT);

        // Confirmation message.
        $mform->addElement('header', 'confirmation', get_string('confirmationmessage', 'appointment'));
        $mform->addHelpButton('confirmation', 'confirmationmessage', 'appointment');

        $mform->addElement('text', 'confirmationsubject', get_string('email:subject', 'appointment'), array('size' => '55'));
        $mform->setType('confirmationsubject', PARAM_TEXT);
        $mform->setDefault('confirmationsubject', get_string('setting:defaultconfirmationsubjectdefault', 'appointment'));

        $mform->addElement('textarea', 'confirmationmessage', get_string('email:message', 'appointment'),
            'wrap="virtual" rows="15" cols="70"');
        $mform->setDefault('confirmationmessage', get_string('setting:defaultconfirmationmessagedefault', 'appointment'));

        // Reminder message.
        $mform->addElement('header', 'reminder', get_string('remindermessage', 'appointment'));
        $mform->addHelpButton('reminder', 'remindermessage', 'appointment');

        $mform->addElement('text', 'remindersubject', get_string('email:subject', 'appointment'), array('size' => '55'));
        $mform->setType('remindersubject', PARAM_TEXT);
        $mform->setDefault('remindersubject', get_string('setting:defaultremindersubjectdefault', 'appointment'));

        $mform->addElement('textarea', 'remindermessage', get_string('email:message', 'appointment'),
            'wrap="virtual" rows="15" cols="70"');
        $mform->setDefault('remindermessage', get_string('setting:defaultremindermessagedefault', 'appointment'));

        $reminderperiod = array();
        for ($i = 1; $i <= 20; $i += 1) {
            $reminderperiod[$i] = $i;
        }
        $mform->addElement('select', 'reminderperiod', get_string('reminderperiod', 'appointment'), $reminderperiod);
        $mform->setDefault('reminderperiod', 2);
        $mform->addHelpButton('reminderperiod', 'reminderperiod', 'appointment');

        // Waitlisted message.
        $mform->addElement('header', 'waitlisted', get_string('waitlistedmessage', 'appointment'));
        $mform->addHelpButton('waitlisted', 'waitlistedmessage', 'appointment');

        $mform->addElement('text', 'waitlistedsubject', get_string('email:subject', 'appointment'), array('size' => '55'));
        $mform->setType('waitlistedsubject', PARAM_TEXT);
        $mform->setDefault('waitlistedsubject', get_string('setting:defaultwaitlistedsubjectdefault', 'appointment'));

        $mform->addElement('textarea', 'waitlistedmessage', get_string('email:message', 'appointment'),
            'wrap="virtual" rows="15" cols="70"');
        $mform->setDefault('waitlistedmessage', get_string('setting:defaultwaitlistedmessagedefault', 'appointment'));

        // Cancellation message.
        $mform->addElement('header', 'cancellation', get_string('cancellationmessage', 'appointment'));
        $mform->addHelpButton('cancellation', 'cancellationmessage', 'appointment');

        $mform->addElement('text', 'cancellationsubject', get_string('email:subject', 'appointment'), array('size' => '55'));
        $mform->setType('cancellationsubject', PARAM_TEXT);
        $mform->setDefault('cancellationsubject', get_string('setting:defaultcancellationsubjectdefault', 'appointment'));

        $mform->addElement('textarea', 'cancellationmessage', get_string('email:message', 'appointment'),
            'wrap="virtual" rows="15" cols="70"');
        $mform->setDefault('cancellationmessage', get_string('setting:defaultcancellationmessagedefault', 'appointment'));

        $this->add_action_buttons();
    }

    /**
     * Process data from submitted form.
     *
     * @param \stdClass $data
     */
    public function process(\stdClass $data) {
        global $DB;

        $cm = get_coursemodule_from_id('appointment', $data->cmid);
        unset($data->cmid);
        $data->id = $cm->instance;

        $DB->update_record('appointment', $data);
    }
}
