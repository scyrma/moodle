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
 * This file contains the backend class for notification outcome.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use core\message\message;
use tool_dynamicrule\api;
use tool_organisation\organisation;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for notification outcome
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class notification extends \tool_dynamicrule\outcome_base {

    /** @var array */
    private $conditionsplaceholders;
    /** @var array */
    private $configdata;
    /** @var message */
    private $eventdata;

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
        $mform->setType('body', PARAM_RAW);

        $helper = new \tool_dynamicrule\output\outcome_notification_placeholders($this);
        $helpercontent = $OUTPUT->render_from_template('tool_dynamicrule/outcome_notification_placeholders',
            $helper->export_for_template($OUTPUT));
        $mform->addElement('static', 'placeholdersdesc', '', $helpercontent);
        $mform->addHelpButton('placeholdersdesc', 'placeholdersdesc', 'tool_dynamicrule');

        $elements = [
            $mform->createElement('advcheckbox', 'matching', get_string('sendtomatching', 'tool_dynamicrule')),
            $mform->createElement('advcheckbox', 'dptlead', get_string('sendtodptlead', 'tool_dynamicrule')),
            $mform->createElement('advcheckbox', 'manager', get_string('sendtomanager', 'tool_dynamicrule')),
        ];
        $mform->addGroup($elements, 'sendto', get_string('sendto', 'tool_dynamicrule'),
            \html_writer::div('', 'w-100 mt-2'));
        $mform->addRule('sendto', get_string('required'), 'callback', function ($data) {
            if (empty($data['matching']) && empty($data['dptlead']) && empty($data['manager'])) {
                return false;
            }
            return true;
        }, 'server', false, false);
        $mform->_defaultValues['sendto']['matching'] = 1;
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
     * Helper function called before outcome is applied to user.
     */
    public function setup_for_applying(): void {
        $userfrom = \core_user::get_noreply_user();
        $userfrom->maildisplay = true;
        $allplaceholders = $this->get_message_placeholders();
        $this->conditionsplaceholders = array_diff($allplaceholders,
            array_keys($this->get_available_user_placeholders()));
        $this->configdata = $this->get_configdata();
        // Create a message object.
        $this->eventdata = new message();
        $this->eventdata->courseid          = SITEID;
        $this->eventdata->component         = 'tool_dynamicrule';
        $this->eventdata->name              = 'notificationoutcome';
        $this->eventdata->userfrom          = $userfrom;
        $this->eventdata->notification      = 1;
        $this->eventdata->fullmessageformat = FORMAT_HTML;
    }

    /**
     * Apply this outcome to a given user.
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $conditionsdata = $this->get_data_from_conditions($this->conditionsplaceholders, [$user]);

        $placeholdersdata = $this->get_user_data_for_placeholders($user) + $this->get_site_data_for_placeholders() +
            $conditionsdata[$user->id];
        $usermessage = $this->get_body($placeholdersdata);
        $usersubject = $this->get_subject($placeholdersdata);
        $eventdata = $this->eventdata;
        $eventdata->fullmessagehtml   = $usermessage;
        $eventdata->fullmessage       = format_text_email($usermessage, FORMAT_HTML);
        $eventdata->subject           = $usersubject;
        $eventdata->smallmessage      = $usersubject;

        $usersto = [];
        if (!empty($this->configdata['sendto']['matching'])) {
            // We need full user record to have all mail preferences.
            $usersto[] = $user;
        }
        if (!empty($this->configdata['sendto']['dptlead'])) {
            $dptleaders = organisation::get_user_direct_dptleads($user->id);
            $usersto = array_merge($usersto, $dptleaders);
        }
        if (!empty($this->configdata['sendto']['manager'])) {
            $managers = organisation::get_user_direct_managers($user->id);
            $usersto = array_merge($usersto, $managers);
        }
        foreach ($usersto as $userto) {
            $eventdata->userto = $userto;
            message_send($eventdata);
        }
    }

    /**
     * Returns the list of placeholders available for user data
     *
     * @return array
     */
    public function get_available_user_placeholders(): array {
        return [
            'userfirstname' => \core_user\fields::get_display_name('firstname'),
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
            'userfirstname' => $user->firstname,
            'userfullname' => fullname($user),
        ];
    }

    /**
     * Returns the list of placeholders available for site data
     *
     * @return array
     */
    public function get_available_site_placeholders(): array {
        return [
            'sitefullname' => new \lang_string('fullsitename', 'moodle'),
            'siteshortname' => new \lang_string('siteshortname', 'tool_dynamicrule'),
            'sitelink' => new \lang_string('sitelink', 'tool_dynamicrule'),
        ];
    }

    /**
     * Returns the values for the site placeholders
     *
     * @return array
     */
    private function get_site_data_for_placeholders() {
        global $SITE;
        $contextid = \context_system::instance()->id;
        return [
            'sitefullname' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
            'siteshortname' => format_string($SITE->shortname, true, ['context' => $contextid, 'escape' => false]),
            'sitelink' => (new \moodle_url('/'))->out(false),
        ];
    }

    /**
     * Finds all placeholders used in a message
     *
     * @return array
     */
    private function get_message_placeholders() {
        // Extract placeholders from raw 'body' and 'subject' (before we apply format_text and format_string).
        $message = $this->get_configdata()['body']['text'] . $this->get_configdata()['subject'];
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
     * @param array $placeholders placeholders to replace
     * @return string
     */
    private function get_subject(array $placeholders = []): string {
        $options = ['context' => \context_system::instance(), 'escape' => false];
        return format_string($this->replace_placeholders($this->get_configdata()['subject'], $placeholders), true, $options);
    }

    /**
     * Return body formatted.
     *
     * @param array $placeholders placeholders to replace
     * @return string
     */
    private function get_body(array $placeholders = []): string {
        return format_text($this->replace_placeholders($this->get_configdata()['body']['text'], $placeholders),
            $this->get_configdata()['body']['format'], ['context' => \context_system::instance()]);
    }

    /**
     * Check if configuration is valid.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        // TODO check if any of placeholders has become invalid (WP-1684).
        return true;
    }

    /**
     * If the current user is able to add this outcome.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return has_capability('moodle/site:sendmessage', \context_system::instance());
    }

    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return has_capability('moodle/site:sendmessage', \context_system::instance());
    }

    /**
     * Renders the html form.
     *
     * Add extra JS for placeholders.
     */
    public static function before_form_render(): void {
        global $PAGE;
        $PAGE->requires->js_call_amd('tool_wp/copy_to_clipboard', 'init');
    }


    /**
     * Modifies configdata to settings we are expecting to see.
     *
     * @return array decoded configdata
     */
    public function get_configdata() {
        $data = parent::get_configdata();
        if (empty($data['sendto'])) {
            $data['sendto']['matching'] = 1;
        }
        return $data;
    }
}
