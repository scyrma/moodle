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
 * This file contains the backend class for badge outcome.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\tool_dynamicrule\outcome;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/badgeslib.php');

/**
 * The backend class for notification outcome
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badge extends \tool_dynamicrule\outcome_base {

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomebadge', 'tool_dynamicrule');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        // Badges selector.
        $options = [
            'ajax' => 'tool_dynamicrule/form_badge_selector',
            'multiple' => false,
            'class' => 'select_badge',
        ];
        $selected = $this->get_selected();
        $mform->addElement('autocomplete', 'badge', get_string('selectbadge', 'tool_dynamicrule'), $selected, $options);
        $mform->addHelpButton('badge', 'selectbadge', 'tool_dynamicrule');
        $mform->addRule('badge', get_string('required'), 'required', null, 'client');

        // Manage badges link.
        $manageprogurl = new \moodle_url('/badges/index.php?type=1');
        $manageprogramsstr = get_string('managebadges', 'tool_dynamicrule');
        $html = \html_writer::tag('a', $manageprogramsstr, ['href' => $manageprogurl]);
        $mform->addElement('static', 'managebadge', '', $html);
    }

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];
        return $errors;
    }

    /**
     * Apply this outcome on a given list of users
     *
     * @param array $users The users objects to apply the outcome to
     */
    public function apply_to_users(array $users) {
        global $DB;
        if ($DB->record_exists('badge', ['id' => $this->get_badgeid()])) {
            $badge = new \badge($this->get_badgeid());
            foreach ($users as $user) {
                // Check if user has already this badge and status is not archived.
                // After MDL-65065 is fixed we can use is_valid to check status.
                if (4 !== (int) $badge->status && !$badge->is_issued($user->id)) {
                    $badge->issue($user->id, !empty($this->get_configdata()['nobake']));
                }
            }
        }
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('outcomebadgedescription', 'tool_dynamicrule', $this->get_badge_name());
    }

    /**
     * Return subject formatted.
     *
     * @return string
     */
    private function get_badge_name(): string {
        // TODO SP-381 cache.
        global $DB;
        $cid = (int)$this->get_badgeid();
        if ($cid) {
            $c = $DB->get_record_sql("SELECT * FROM {badge} WHERE id=?", [$cid]);
            if ($c) {
                $options = ['context' => \context_system::instance(), 'escape' => false];
                return format_string($c->name, true, $options);
            }
        }
        return '';
    }

    /**
     * Return configured badge id.
     *
     * @return int
     */
    public function get_badgeid() {
        if (isset($this->get_configdata()['badge'])) {
            $b = $this->get_configdata()['badge'];
        } else {
            $b = null;
        }
        return $b;
    }

    /**
     * Check if badge is not empty.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return !empty($this->get_configdata()['badge']);
    }

    /**
     * Return id and name of selected badges.
     *
     * @return array
     */
    private function get_selected(): array {
        if ($this->get_badgeid()) {
            $selected = [$this->get_badgeid() => $this->get_badge_name()];
        } else {
            $selected = [];
        }
        return $selected;
    }
}
