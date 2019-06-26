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
 * This file contains the backend class for notification outcome.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for notification outcome
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification extends \tool_dynamicrule\outcome_base {

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomenotification', 'tool_dynamicrule');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        global $OUTPUT;
        $mform->addElement('text', 'subject', get_string('subject', 'tool_dynamicrule'));
        $mform->addRule('subject', null, 'required', null, 'client');
        $mform->setType('subject', PARAM_TEXT);

        $mform->addElement('editor', 'body', get_string('body', 'tool_dynamicrule'), null, ['autosave' => false]);
        $mform->addRule('body', null, 'required', null, 'client');
        $mform->setType('body', PARAM_CLEANHTML);

        $helper = new \tool_dynamicrule\output\outcome_notification_placeholders($this);
        $helpercontent = $OUTPUT->render_from_template('tool_dynamicrule/outcome_notification_placeholders',
            $helper->export_for_template($OUTPUT));
        $mform->addElement('static', 'placeholdersdesc', '', $helpercontent);
        $mform->addHelpButton('placeholdersdesc', 'placeholdersdesc', 'tool_dynamicrule');

        $mform->addElement('checkbox', 'notifymanagers', get_string('notifymanagers', 'tool_dynamicrule'));
        $mform->setType('notifyowner', PARAM_INT);
    }

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];
        if (empty($data['subject'])) {
            $errors['subject'] = get_string('errorinvalidsubject', 'tool_dynamicrule');
        }
        if (empty($data['body'])) {
            $errors['body'] = get_string('errorinvalidbody', 'tool_dynamicrule');
        }
        return $errors;
    }

    /**
     * Apply this outcome on a given list of users
     *
     * @param array $users The users objects to apply the outcome to
     */
    public function apply_to_users(array $users) {
        global $DB;

        $userfrom = \core_user::get_noreply_user();
        $userfrom->maildisplay = true;

        $subject = $this->get_subject();
        $message = $this->get_body();

        $allplaceholders = $this->get_message_placeholders($message . $subject);
        $conditionsplaceholders = array_diff($allplaceholders,
            array_keys($this->get_available_user_placeholders()));
        $conditionsdata = $this->get_data_from_conditions($conditionsplaceholders, $users);

        // Create a message object.
        $eventdata = new \core\message\message();
        $eventdata->courseid          = SITEID;
        $eventdata->component         = 'tool_dynamicrule';
        $eventdata->name              = 'notificationoutcome';
        $eventdata->userfrom          = $userfrom;
        $eventdata->notification      = 1;
        $eventdata->fullmessageformat = FORMAT_PLAIN;

        // Re-fetch the users (users objects are incomplete).
        list($usersql, $userparams) = $DB->get_in_or_equal(array_column($users, 'id'));
        $userfields = get_all_user_name_fields(true);
        $sql = "SELECT u.id, u.username, u.email, {$userfields}, auth, suspended, deleted, emailstop
                  FROM {user} u
                 WHERE u.id {$usersql}";
        $users = $DB->get_records_sql($sql, $userparams);

        foreach ($users as $userto) {
            $placeholdersdata = $this->get_user_data_for_placeholders($userto) + $conditionsdata[$userto->id];
            $usermessage = $this->replace_placeholders($message, $placeholdersdata);
            $usersubject = $this->replace_placeholders($subject, $placeholdersdata);
            $eventdata->userto            = $userto;
            $eventdata->fullmessagehtml   = $usermessage; // TODO SP-450 apply filters and clean html. Use editor.
            $eventdata->fullmessage       = format_text_email($usermessage, FORMAT_HTML);
            $eventdata->subject           = $usersubject;
            $eventdata->smallmessage      = $usersubject;
            message_send($eventdata);
        }
    }

    /**
     * Returns the list of placeholders available for user data
     *
     * @return array
     */
    public function get_available_user_placeholders() {
        return [
            'userfullname' => new \lang_string('fullname'),
        ];
    }

    /**
     * Returns the values for the user placeholders
     *
     * @param \stdClass $user
     * @return array
     */
    private function get_user_data_for_placeholders(\stdClass $user) {
        return [
            'userfullname' => fullname($user),
        ];
    }

    /**
     * Finds all placeholders used in a message
     *
     * @param string $message
     * @return array
     */
    private function get_message_placeholders(string $message) {
        $placeholders = [];
        if (preg_match_all('/\\{\\{(\w*)\\}\\}/', $message, $res)) {
            $placeholders = array_unique($res[1]);
        }
        return $placeholders;
    }

    /**
     * Replace placeholders used in a message
     *
     * @param string $message
     * @param array $values
     * @return null|string|string[]
     */
    private function replace_placeholders(string $message, array $values) {
        foreach ($values as $key => $value) {
            $message = preg_replace('/' . preg_quote('{{' . $key . '}}', '/') . '/',
                $value, $message);
        }
        // TODO SP-450 leave the remaining placeholders as is or replace them with empty string?
        return $message;
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('outcomenotificationdescription', 'tool_dynamicrule', $this->get_subject());
    }

    /**
     * Return subject formatted.
     *
     * @return string
     */
    private function get_subject(): string {
        $options = ['context' => \context_system::instance(), 'escape' => false];
        return format_string($this->get_configdata()['subject'], true, $options);
    }

    /**
     * Return body formatted.
     *
     * @return string
     */
    private function get_body(): string {
        return format_string($this->get_configdata()['body'], false, ['context' => \context_system::instance()]);
    }

    /**
     * Check if subject and body are not empty.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return !empty($this->get_configdata()['body']) && !empty($this->get_configdata()['subject']);
    }
}
