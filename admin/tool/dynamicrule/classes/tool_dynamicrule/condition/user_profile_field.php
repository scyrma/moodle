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
 * This file contains the backend class for user_profile_field condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for user_profile_field condition
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_profile_field extends \tool_dynamicrule\condition_sql {

    /**
     * @var array Fields on user table that are filterable by this condition.
     */
    private $defaultfields = ['lastname', 'firstname', 'username', 'email', 'city', 'idnumber'];

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionuserprofilefield', 'tool_dynamicrule');
    }

    /**
     * Adds condition's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        $mform->addElement('select', 'userprofilefield', get_string('field', 'tool_dynamicrule'), $this->get_profile_fields());
        $mform->addRule('userprofilefield', null, 'required', null, 'client');
        $mform->setType('userprofilefield', PARAM_TEXT);

        $mform->addElement('text', 'userprofilefieldvalue', get_string('value', 'tool_dynamicrule'));
        $mform->setType('userprofilefieldvalue', PARAM_TEXT);
        $mform->addRule('userprofilefieldvalue', null, 'required', null, 'client');
    }

    /**
     * Validates the configform of the condition
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];
        $fields = $this->get_profile_fields();
        if (empty($data['userprofilefield']) || !isset($fields[$data['userprofilefield']])) {
            $errors['userprofilefield'] = get_string('errorinvaliduserprofilefield', 'tool_dynamicrule');
        }
        return $errors;
    }

    /**
     * Returns an array of custom profile fields
     *
     * @return array of profile fields
     */
    private function get_profile_fields(): array {
        global $DB;
        $res = [0 => get_string('select', 'tool_dynamicrule')];
        foreach ($this->defaultfields as $f) {
            $res[$f] = get_string($f);
        }
        // TODO: search by just "value" is not helpful for most of user field types.
        $sql = 'SELECT f.shortname, f.name
                  FROM {user_info_field} f
                  JOIN {user_info_category} c
                    ON c.id = f.categoryid
                 WHERE f.datatype = ?
              ORDER BY c.sortorder, f.sortorder';
        return $res + $DB->get_records_sql_menu($sql, ['text']);
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        global $DB;

        $field = $this->get_userprofilefield();
        $value = $this->get_userprofilefieldvalue();

        if (in_array($field, $this->defaultfields)) {
            $join = '';
            $userprofilevalue = api::generate_param_name();
            $where = "u.{$field} = :{$userprofilevalue}";
            $params = [$userprofilevalue => $value];
        } else {
            $ud = api::generate_alias();
            $uf = api::generate_alias();
            $userprofilefield = api::generate_param_name();
            $userprofilevalue = api::generate_param_name();

            $join = "JOIN {user_info_data} $ud
                       ON ({$ud}.userid = u.id)
                     JOIN {user_info_field} $uf
                       ON ({$uf}.id = {$ud}.fieldid)";

            $datafield = $DB->sql_compare_text("{$ud}.data", 255);
            $datacomp = $DB->sql_compare_text(":{$userprofilevalue}", 255);
            $comp = $DB->sql_equal($datafield, $datacomp);
            $where = "{$uf}.shortname = :{$userprofilefield} AND {$comp}";
            $params = [$userprofilefield => $field, $userprofilevalue => $value];
        }
        return [$join, $where, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $a = new \stdClass();
        $a->fieldvalue = $this->get_userprofilefieldvalue();
        $a->fieldname = $this->get_userprofilefieldname();
        $str = 'conditionuserprofilefielddescription';
        return get_string($str, 'tool_dynamicrule', $a);
    }

    /**
     * Return the configured user profile field identifier.
     *
     * @return string|null The id of the user profile field or the name of the field on user table
     */
    public function get_userprofilefield() {
        if (empty($this->get_configdata()['userprofilefield'])) {
            return null;
        }
        return $this->get_configdata()['userprofilefield'];
    }

    /**
     * Return the configured user profile field value.
     *
     * @return string|null The value configure to match against
     */
    public function get_userprofilefieldvalue() {
        if (!isset($this->get_configdata()['userprofilefieldvalue'])) {
            return null;
        }
        return $this->get_configdata()['userprofilefieldvalue'];
    }

    /**
     * Return the formatted user profile field name.
     *
     * @return string
     */
    public function get_userprofilefieldname(): string {
        global $DB;
        $field = $this->get_userprofilefield();
        if (in_array($field, $this->defaultfields)) {
            $str = get_string($field);
        } else {
            $fieldname = $DB->get_field('user_info_field', 'name', ['shortname' => $field]);
            $str = format_string($fieldname, true, ['context' => \context_system::instance(), 'escape' => false]);
        }
        return $str;
    }

    /**
     * Check if user profile field still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;

        $field = $this->get_userprofilefield();
        if (is_null($field)) {
            return false;
        } else if (empty($field) || in_array($field, $this->defaultfields)) {
            return true; // Empty means any field.
        } else {
            return $DB->record_exists('user_info_field', ['shortname' => $field]);
        }
    }

    /**
     * Event subscription.
     *
     * @return string|false eventname or false if no subscription.
     */
    public function get_event_subscription() {
        return '\core\event\user_updated';
    }
}
