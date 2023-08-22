<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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
 */

namespace mod_appointment\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Class mod_appointment\form\messages
 *
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messages extends \moodleform {

    /**
     * Form definition
     */
    public function definition() {

        $mform =& $this->_form;

        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->setType('cmid', PARAM_INT);

        $defaults = self::get_defaults();

        // Confirmation message.
        $mform->addElement('header', 'confirmation', get_string('confirmationmessage', 'appointment'));
        $mform->addHelpButton('confirmation', 'confirmationmessage', 'appointment');

        $mform->addElement('text', 'confirmationsubject', get_string('email:subject', 'appointment'), array('size' => '55'));
        $mform->setType('confirmationsubject', PARAM_TEXT);
        $mform->setDefault('confirmationsubject', $defaults['confirmationsubject']);

        $mform->addElement('editor', 'confirmationmessage_editor', get_string('email:message', 'appointment'),
            self::editor_options());
        $mform->setDefault('confirmationmessage_editor', ['text' => $defaults['confirmationmessage'], 'format' => FORMAT_HTML]);
        $mform->setType('confirmationmessage_editor', PARAM_RAW);

        // Session update message.
        $mform->addElement('header', 'update', get_string('updatemessage', 'appointment'));
        $mform->addHelpButton('update', 'updatemessage', 'appointment');

        $mform->addElement('text', 'updatesubject', get_string('email:subject', 'appointment'), array('size' => '55'));
        $mform->setType('updatesubject', PARAM_TEXT);
        $mform->setDefault('updatesubject', $defaults['updatesubject']);

        $mform->addElement('editor', 'updatemessage_editor', get_string('email:message', 'appointment'),
            self::editor_options());
        $mform->setDefault('updatemessage_editor', ['text' => $defaults['updatemessage'], 'format' => FORMAT_HTML]);
        $mform->setType('updatemessage_editor', PARAM_RAW);

        // Reminder message.
        $mform->addElement('header', 'reminder', get_string('remindermessage', 'appointment'));
        $mform->addHelpButton('reminder', 'remindermessage', 'appointment');

        $mform->addElement('text', 'remindersubject', get_string('email:subject', 'appointment'), array('size' => '55'));
        $mform->setType('remindersubject', PARAM_TEXT);
        $mform->setDefault('remindersubject', $defaults['remindersubject']);

        $mform->addElement('editor', 'remindermessage_editor', get_string('email:message', 'appointment'), self::editor_options());
        $mform->setDefault('remindermessage_editor', ['text' => $defaults['remindermessage'], 'format' => FORMAT_HTML]);
        $mform->setType('remindermessage_editor', PARAM_RAW);

        $reminderperiod = array();
        for ($i = 1; $i <= 20; $i += 1) {
            $reminderperiod[$i] = $i;
        }
        $mform->addElement('select', 'reminderperiod', get_string('reminderperiod', 'appointment'), $reminderperiod);
        $mform->setDefault('reminderperiod', $defaults['reminderperiod']);
        $mform->addHelpButton('reminderperiod', 'reminderperiod', 'appointment');

        // Waitlisted message.
        $mform->addElement('header', 'waitlisted', get_string('waitlistedmessage', 'appointment'));
        $mform->addHelpButton('waitlisted', 'waitlistedmessage', 'appointment');

        $mform->addElement('text', 'waitlistedsubject', get_string('email:subject', 'appointment'), array('size' => '55'));
        $mform->setType('waitlistedsubject', PARAM_TEXT);
        $mform->setDefault('waitlistedsubject', $defaults['waitlistedsubject']);

        $mform->addElement('editor', 'waitlistedmessage_editor', get_string('email:message', 'appointment'),
            self::editor_options());
        $mform->setDefault('waitlistedmessage_editor', ['text' => $defaults['waitlistedmessage'], 'format' => FORMAT_HTML]);
        $mform->setType('waitlistedmessage_editor', PARAM_RAW);

        // Cancellation message.
        $mform->addElement('header', 'cancellation', get_string('cancellationmessage', 'appointment'));
        $mform->addHelpButton('cancellation', 'cancellationmessage', 'appointment');

        $mform->addElement('text', 'cancellationsubject', get_string('email:subject', 'appointment'), array('size' => '55'));
        $mform->setType('cancellationsubject', PARAM_TEXT);
        $mform->setDefault('cancellationsubject', $defaults['cancellationsubject']);

        $mform->addElement('editor', 'cancellationmessage_editor', get_string('email:message', 'appointment'),
            self::editor_options());
        $mform->setDefault('cancellationmessage_editor', ['text' => $defaults['cancellationmessage'], 'format' => FORMAT_HTML]);
        $mform->setType('cancellationmessage_editor', PARAM_RAW);

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
        $context = \context_module::instance($data->cmid);
        unset($data->cmid);
        $data->id = $cm->instance;

        if (isset($data->confirmationmessage_editor)) {
            $data = file_postupdate_standard_editor($data, 'confirmationmessage', self::editor_options(),
                $context, 'appointment', 'notifications', $data->id);
        }
        if (isset($data->updatemessage_editor)) {
            $data = file_postupdate_standard_editor($data, 'updatemessage', self::editor_options(),
                $context, 'appointment', 'notifications', $data->id);
        }
        if (isset($data->remindermessage_editor)) {
            $data = file_postupdate_standard_editor($data, 'remindermessage', self::editor_options(),
                $context, 'appointment', 'notifications', $data->id);
        }
        if (isset($data->waitlistedmessage_editor)) {
            $data = file_postupdate_standard_editor($data, 'waitlistedmessage', self::editor_options(),
                $context, 'appointment', 'notifications', $data->id);
        }
        if (isset($data->cancellationmessage_editor)) {
            $data = file_postupdate_standard_editor($data, 'cancellationmessage', self::editor_options(),
                $context, 'appointment', 'notifications', $data->id);
        }

        $DB->update_record('appointment', $data);
    }

    /**
     * Options for atto editor.
     *
     * @return array
     */
    public static function editor_options(): array {
        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => \context_system::instance(),
            'noclean' => true,
            'accepted_types' => '*',
        ];
    }

    /**
     * Get form default data.
     *
     * This is also used on appointment instance creation {@see appointment_add_instance}
     *
     * @return array default data
     */
    public static function get_defaults(): array {
        return [
            'confirmationsubject' => get_string('setting:defaultconfirmationsubjectdefault', 'appointment'),
            'confirmationmessage' => get_string('setting:defaultconfirmationmessagedefault', 'appointment'),
            'confirmationmessageformat' => FORMAT_HTML,
            'updatesubject' => get_string('setting:defaultupdatesubjectdefault', 'appointment'),
            'updatemessage' => get_string('setting:defaultupdatemessagedefault', 'appointment'),
            'updatemessageformat' => FORMAT_HTML,
            'remindersubject'     => get_string('setting:defaultremindersubjectdefault', 'appointment'),
            'remindermessage'     => get_string('setting:defaultremindermessagedefault', 'appointment'),
            'remindermessageformat' => FORMAT_HTML,
            'reminderperiod'      => 2,
            'waitlistedsubject'   => get_string('setting:defaultwaitlistedsubjectdefault', 'appointment'),
            'waitlistedmessage'   => get_string('setting:defaultwaitlistedmessagedefault', 'appointment'),
            'waitlistedmessageformat' => FORMAT_HTML,
            'cancellationsubject' => get_string('setting:defaultcancellationsubjectdefault', 'appointment'),
            'cancellationmessage' => get_string('setting:defaultcancellationmessagedefault', 'appointment'),
            'cancellationmessageformat' => FORMAT_HTML,
        ];
    }
}
