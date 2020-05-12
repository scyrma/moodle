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
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule\api;
use tool_reportbuilder\local\helpers\relative_dates;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for user_profile_field condition
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_profile_field extends \tool_dynamicrule\condition_sql {

    /**
     * @var array Fields on user table that are filterable by this condition.
     */
    private $defaultfields = ['lastname', 'firstname', 'username', 'email', 'city', 'idnumber', 'country'];

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

        $upfields = $this->get_profile_fields_info();

        $listfields = [0 => get_string('select', 'tool_dynamicrule')];
        foreach ($upfields as $f) {
            $listfields[$f->shortname] = $f->name;
        }

        $fieldstr = get_string('field', 'tool_dynamicrule');
        $group = [];
        $params = ['class' => 'userprofilefieldselector'];
        $group[] = $mform->createElement('select', 'userprofilefield', $fieldstr, $listfields, $params);

        foreach ($upfields as $field) {
            switch ($field->datatype) {
                case 'text':
                    $this->add_text_field($mform, $group, $field);
                    break;
                case 'datetime':
                    $this->add_date_field($mform, $group, $field);
                    break;
                case 'menu':
                    $this->add_menu_field($mform, $group, $field);
                    break;
                case 'checkbox':
                    $this->add_checkbox_field($mform, $group, $field);
                    break;
            }
        }

        $mform->addGroup($group, 'userprofilefieldgroup', '', ' ', false);
    }

    /**
     * Adds text field form elements
     *
     * @param \MoodleQuickForm $mform
     * @param array $group
     * @param \stdClass $field
     * @throws \coding_exception
     */
    public function add_text_field(\MoodleQuickForm $mform, array &$group, \stdClass $field): void {
        $shortname = $field->shortname;
        $elements = [];
        $elements[] = $mform->createElement('select', $shortname . '_op', null, $this->get_text_operators());
        $elements[] = $mform->createElement('text', $shortname . '_value', null);
        $mform->setType($shortname . '_value', PARAM_RAW);
        $mform->hideIf($shortname . '_value', $shortname . '_op', 'in', '5|6');

        $group[] = $mform->createElement('group', $shortname, '', $elements, '', false);
        $mform->hideIf($shortname, 'userprofilefield', 'neq', $shortname);
    }

    /**
     * Adds date field form elements
     *
     * @param \MoodleQuickForm $mform
     * @param array $group
     * @param \stdClass $field
     * @throws \coding_exception
     */
    public function add_date_field(\MoodleQuickForm $mform, array &$group, \stdClass $field): void {
        $shortname = $field->shortname;
        $elements = [];
        $elements[] = $mform->createElement('select', $shortname . '_op', null, $this->get_date_operators());
        $elements[] = $mform->createElement('text', $shortname . '_value', null, ['size' => 3]);
        $elements[] = $mform->createElement('select', $shortname . '_op2', null, $this->get_time_operators());
        $mform->setType($shortname . '_value', PARAM_RAW);
        $mform->setType($shortname . '_op', PARAM_INT);
        $mform->setDefault($shortname . '_op', 0);
        $mform->setType($shortname . '_op2', PARAM_INT);
        $mform->setDefault($shortname . '_op2', 1);
        $mform->hideIf($shortname . '_value', $shortname . '_op', 'in', '0|1|2|3|4|7|8|9');
        $mform->hideIf($shortname . '_op2', $shortname . '_op', 'in', '0|1|2|3|4|5|6');

        $group[] = $mform->createElement('group', $shortname, '', $elements, '', false);
        $mform->hideIf($shortname, 'userprofilefield', 'neq', $shortname);
    }

    /**
     * Adds menu field form elements
     *
     * @param \MoodleQuickForm $mform
     * @param array $group
     * @param \stdClass $field
     */
    public function add_menu_field(\MoodleQuickForm $mform, array &$group, \stdClass $field): void {
        $elements = [];
        $elements[] = $mform->createElement('select', $field->shortname . '_op', $field->name, (array) $field->param1);
        $group[] = $mform->createElement('group', $field->shortname, '', $elements, '', false);
        $mform->hideIf($field->shortname, 'userprofilefield', 'neq', $field->shortname);
    }

    /**
     * Adds checkbox field form elements
     *
     * @param \MoodleQuickForm $mform
     * @param array $group
     * @param \stdClass $field
     */
    public function add_checkbox_field(\MoodleQuickForm $mform, array &$group, \stdClass $field): void {
        $elements = [];
        $elements[] = $mform->createElement('advcheckbox', $field->shortname . '_op', $field->name, '');
        $group[] = $mform->createElement('group', $field->shortname, '', $elements, '', false);
        $mform->hideIf($field->shortname, 'userprofilefield', 'neq', $field->shortname);
    }

    /**
     * Returns an array of text comparison operators
     *
     * @return array of comparison operators
     * @throws \coding_exception
     */
    public function get_text_operators() : array {
        return [
            0 => get_string('contains', 'filters'),
            1 => get_string('doesnotcontain', 'filters'),
            2 => get_string('isequalto', 'filters'),
            3 => get_string('startswith', 'filters'),
            4 => get_string('endswith', 'filters'),
            5 => get_string('isempty', 'filters'),
            6 => get_string('isnotempty', 'tool_reportbuilder')
        ];
    }

    /**
     * Returns an array of date comparison operators
     *
     * @return array of comparison operators
     * @throws \coding_exception
     */
    public function get_date_operators() : array {
        return [
            0 => get_string('dateanyvalue', 'tool_reportbuilder'),
            1 => get_string('dateisnotempty', 'tool_reportbuilder'),
            2 => get_string('dateisempty', 'tool_reportbuilder'),
            3 => get_string('dateinthepast', 'tool_reportbuilder'),
            4 => get_string('dateinthefuture', 'tool_reportbuilder'),
            5 => get_string('datelast', 'tool_reportbuilder'),
            6 => get_string('datenext', 'tool_reportbuilder'),
            7 => get_string('datecurrent', 'tool_reportbuilder'),
            8 => get_string('dateprevious', 'tool_reportbuilder'),
            9 => get_string('dateupcoming', 'tool_reportbuilder')
        ];
    }

    /**
     * Returns an array of time select options
     *
     * @return array of select options
     * @throws \coding_exception
     */
    public function get_time_operators() : array {
        return [
            1 => get_string('day'),
            2 => get_string('week'),
            3 => get_string('month'),
            4 => get_string('quarter', 'tool_reportbuilder'),
            5 => get_string('year')
        ];
    }

    /**
     * Retrieves config data
     *
     * @param \stdClass $data
     * @return array
     */
    public static function retrieve_configdata($data) {
        $data = parent::retrieve_configdata($data);

        $filtered = [];
        $filtered['instanceclass'] = $data['instanceclass'];
        $filtered['userprofilefield'] = $data['userprofilefield'];

        foreach ($data as $key => $value) {
            if (strpos($key, $data['userprofilefield'] . '_') === 0) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Validates the configform of the condition
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];

        $fields = $this->get_profile_fields_info();
        if (empty($data['userprofilefield']) || !isset($data['userprofilefield']) || !isset($fields[$data['userprofilefield']])) {
            $errors['userprofilefieldgroup'] = get_string('errorinvaliduserprofilefield', 'tool_dynamicrule');
        }

        $fieldvalue = $data['userprofilefield'] . '_value';
        $fieldop = $data['userprofilefield'] . '_op';
        $datatype = $fields[$data['userprofilefield']]->datatype ?? '';
        if ((empty($data[$fieldvalue]) &&
            (($datatype == 'text' && $data[$fieldop] != 5 && $data[$fieldop] != 6) ||
            ($datatype == 'datetime'&& ($data[$fieldop] == 5 || $data[$fieldop] == 6)))) ||
            (!empty($data[$fieldvalue]) && $datatype == 'datetime' && !is_numeric($data[$fieldvalue]))) {
            $errors['userprofilefieldgroup'] = get_string('errorinvaliduserprofilefieldvalue', 'tool_dynamicrule');
        }

        return $errors;
    }

    /**
     * Returns array with all user profile fields shortname, name, datatype and param1.
     *
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function get_profile_fields_info(): array {
        global $DB;
        $countries = get_string_manager()->get_list_of_countries(true);
        $res = [];
        $res['lastname'] = (object)['shortname' => 'lastname', 'name' => get_string('lastname'), 'datatype' => 'text'];
        $res['firstname'] = (object)['shortname' => 'firstname', 'name' => get_string('firstname'), 'datatype' => 'text'];
        $res['username'] = (object)['shortname' => 'username', 'name' => get_string('username'), 'datatype' => 'text'];
        $res['email'] = (object)['shortname' => 'email', 'name' => get_string('email'), 'datatype' => 'text'];
        $res['city'] = (object)['shortname' => 'city', 'name' => get_string('city'), 'datatype' => 'text'];
        $res['idnumber'] = (object)['shortname' => 'idnumber', 'name' => get_string('idnumber'), 'datatype' => 'text'];
        $country = get_string('country');
        $res['country'] = (object)['shortname' => 'country', 'name' => $country, 'datatype' => 'menu', 'param1' => $countries];
        $sql = "SELECT f.shortname, f.name, f.datatype, f.param1
                  FROM {user_info_field} f
                  JOIN {user_info_category} c
                    ON c.id = f.categoryid
                    WHERE f.datatype <> 'textarea'
              ORDER BY c.sortorder, f.sortorder";
        $res1 = $DB->get_records_sql($sql);
        // We need to create array from params1 for menu fields.
        foreach ($res1 as $field) {
            if ($field->datatype == 'menu') {
                $fields = explode("\n", $field->param1);
                $field->param1 = array_combine($fields, $fields);
            }
        }

        $res = $res + $res1;
        return $res;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {

        $field = $this->get_userprofilefield();
        $upfields = $this->get_profile_fields_info();
        $where = '';
        $params = [];

        if (in_array($field, $this->defaultfields)) {
            $join = '';
            $datatype = $upfields[$this->get_configdata()['userprofilefield']]->datatype;

            if ($datatype == 'text') {
                [$where, $params] = $this->get_text_sql('u', $field);
            } else if ($datatype == 'menu') {
                [$where, $params] = $this->get_menu_checkbox_sql('u', $field);
            }

        } else {
            $ud = api::generate_alias();
            $uf = api::generate_alias();
            $userprofilefield = api::generate_param_name();

            $join = "JOIN {user_info_data} $ud
                       ON ({$ud}.userid = u.id)
                     JOIN {user_info_field} $uf
                       ON ({$uf}.id = {$ud}.fieldid)";

            $where = "{$uf}.shortname = :{$userprofilefield}";
            $params = [$userprofilefield => $field];

            $datatype = $upfields[$this->get_configdata()['userprofilefield']]->datatype;
            if ($datatype == 'datetime') {
                [$where1, $params1] = $this->get_datetime_sql($ud);
            } else if ($datatype == 'text') {
                [$where1, $params1] = $this->get_text_sql($ud, 'data');
            } else if ($datatype == 'menu' || $datatype == 'checkbox') {
                [$where1, $params1] = $this->get_menu_checkbox_sql($ud, 'data', $field);
            }

            if (isset($where1)) {
                $where .= ' AND ' . $where1;
            }
            if (isset($params1)) {
                $params = $params + $params1;
            }
        }
        return [$join, $where, $params];
    }

    /**
     * Generates the datetime sql
     *
     * @param string $alias
     * @return array
     * @throws \coding_exception
     */
    public function get_datetime_sql(string $alias) : array {
        global $DB;
        $value = $this->get_userprofilefield_value();
        $fieldshortname = $this->get_configdata()['userprofilefield'];
        $operator = $this->get_configdata()[$fieldshortname . '_op'];
        $operator2 = $this->get_configdata()[$fieldshortname . '_op2'];
        $param = api::generate_param_name();

        if (is_null($operator)) {
            // Configuration is invalid.
            return ['', []];
        }
        if (($operator == 5 || $operator == 6) && !(int)$value) {
            // Configuration is invalid.
            return ['', []];
        }
        if (($operator == 7 || $operator == 8 || $operator == 9) && !(int)$operator2) {
            // Configuration is invalid.
            return ['', []];
        }

        switch($operator) {
            case 0:
                $res = "1=1";
                $params = [];
                break;
            case 1: // Is not empty.
                $res = "$alias.data IS NOT NULL AND " . $DB->sql_compare_text("$alias.data") . " <> " .
                    $DB->sql_compare_text(":$param");
                $params[$param] = 0;
                break;
            case 2: // Is empty.
                $res = "$alias.data IS NULL OR " . $DB->sql_compare_text("$alias.data") . " = " .
                    $DB->sql_compare_text(":$param");
                $params[$param] = 0;
                break;
            case 3: // In the past.
                $param2 = api::generate_param_name();
                $res = $DB->sql_compare_text("$alias.data") . " <= " . $DB->sql_compare_text(":$param") .
                    " AND " . $DB->sql_compare_text("$alias.data") . " > " . $DB->sql_compare_text(":$param2");
                $params[$param] = time();
                $params[$param2] = 0;
                break;
            case 4: // In the future.
                $res = $DB->sql_compare_text("$alias.data") . " >= " . $DB->sql_compare_text(":$param");
                $params[$param] = time();
                break;
            case 5: // Last X days.
                $param2 = api::generate_param_name();
                $res = $DB->sql_compare_text("$alias.data") . " >= " . $DB->sql_compare_text(":$param") .
                    " AND " . $DB->sql_compare_text("$alias.data") . " <= " . $DB->sql_compare_text(":$param2");
                $params[$param] = time() - DAYSECS * ((int)$value);
                $params[$param2] = time();
                break;
            case 6: // Next X days.
                $param2 = api::generate_param_name();
                $res = $DB->sql_compare_text("$alias.data") . " >= " . $DB->sql_compare_text(":$param") .
                    " AND " . $DB->sql_compare_text("$alias.data") . " <= " . $DB->sql_compare_text(":$param2");
                $params[$param] = time();
                $params[$param2] = time() + DAYSECS * ((int)$value);
                break;
            case 7: // Current [day/week/month/quarter/year/financial year].
                $param2 = api::generate_param_name();
                $res = $DB->sql_compare_text("$alias.data") . " >= " . $DB->sql_compare_text(":$param") .
                    " AND " . $DB->sql_compare_text("$alias.data") . " <= " . $DB->sql_compare_text(":$param2");
                $period = $this->get_time_operators()[$operator2];
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('current', $period);
                return [$res, $params];
                break;
            case 8: // Previous [day/week/month/quarter/year/financial year].
                $param2 = api::generate_param_name();
                $res = $DB->sql_compare_text("$alias.data") . " >= " . $DB->sql_compare_text(":$param") .
                    " AND " . $DB->sql_compare_text("$alias.data") . " <= " . $DB->sql_compare_text(":$param2");
                $period = $this->get_time_operators()[$operator2];
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('previous', $period);
                return [$res, $params];
                break;
            case 9: // Upcoming [day/week/month/quarter/year/financial year].
                $param2 = api::generate_param_name();
                $res = $DB->sql_compare_text("$alias.data") . " >= " . $DB->sql_compare_text(":$param") .
                    " AND " . $DB->sql_compare_text("$alias.data") . " <= " . $DB->sql_compare_text(":$param2");
                $period = $this->get_time_operators()[$operator2];
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('upcoming', $period);
                return [$res, $params];
                break;
            default:
                // Configuration is invalid.
                return ['', []];
        }
        return [$res, $params];
    }

    /**
     * Generates the text sql
     *
     * @param string $alias
     * @param string $field
     * @return array
     */
    public function get_text_sql(string $alias, string $field) : array {
        global $DB;
        $name = api::generate_param_name();

        if (!$alias) {
            return ['', []];
        }

        $value = $this->get_userprofilefield_value();
        $fieldshortname = $this->get_configdata()['userprofilefield'];
        $operator = $this->get_configdata()[$fieldshortname . '_op'];

        if (is_null($operator) || ('' . $value === '' AND ($operator != 5 && $operator != 6))) {
            // Configuration is invalid.
            return ['', []];
        }

        switch ($operator) {
            case 0: // Contains.
                $res = $DB->sql_like("$alias.$field", ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value%";
                break;
            case 1: // Does not contain.
                $res = $DB->sql_like("$alias.$field", ":$name", false, false, true);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value%";
                break;
            case 2: // Equal to.
                $res = $DB->sql_equal("$alias.$field", ":$name", false, false);
                $params[$name] = "$value";
                break;
            case 3: // Starts with.
                $res = $DB->sql_like("$alias.$field", ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "$value%";
                break;
            case 4: // Ends with.
                $res = $DB->sql_like("$alias.$field", ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value";
                break;
            case 5: // Empty.
                $res = $DB->sql_compare_text("$alias.$field") . " = " . $DB->sql_compare_text(":$name");
                $params[$name] = '';
                break;
            case 6: // Not Empty.
                $res = $DB->sql_compare_text("$alias.$field") . " != " . $DB->sql_compare_text(":$name");
                $params[$name] = '';
                break;
            default:
                // Configuration is invalid.
                return ['', []];
        }
        // Configuration is invalid.
        return [$res, $params];
    }

    /**
     * Generates the menu sql
     *
     * @param string $alias
     * @param string $field
     * @param string $field2
     * @return array
     */
    public function get_menu_checkbox_sql(string $alias, string $field, string $field2 = null) : array {
        global $DB;
        $userprofilevalue = api::generate_param_name();
        if (isset($field2)) {
            $operator = $this->get_configdata()[$field2 . '_op'];
        } else {
            $operator = $this->get_configdata()[$field . '_op'];
        }
        $res = $DB->sql_compare_text("$alias.$field") . " = " . $DB->sql_compare_text(":$userprofilevalue");
        $params = [$userprofilevalue => $operator];
        return [$res, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $a = new \stdClass();
        $upfields = $this->get_profile_fields_info();
        $datatype = $upfields[$this->get_configdata()['userprofilefield']]->datatype;
        $field = $this->get_configdata()['userprofilefield'];
        // We default to 2 (EQUAL TO) in case there is an existing old rule that doesn't have _op set.
        $opint = $this->get_configdata()[$field . '_op'] ?? 2;

        $str = 'conditionuserprofilefielddescription';
        $a->fieldvalue = '';
        if ($datatype == 'datetime' && ($opint === 5 || $opint === 6)) {
            $a->fieldvalue = $this->get_userprofilefield_op($datatype) . ' ' . $this->get_userprofilefield_value();
        } else if ($datatype == 'datetime' && $opint >= 7) {
            $a->fieldvalue = $this->get_userprofilefield_op($datatype) . ' ' . $this->get_userprofilefield_op2($datatype);
        } else if ($datatype == 'datetime') {
            $a->fieldvalue = $this->get_userprofilefield_op($datatype);
        } else if ($datatype == 'text' && $opint > 4) {
            $str = 'conditionuserprofilefielddescriptiontext';
            $a->fieldvalue = $this->get_userprofilefield_op($datatype);
        } else if ($datatype == 'text') {
            $str = 'conditionuserprofilefielddescriptiontext';
            $a->fieldvalue = $this->get_userprofilefield_op($datatype) . ' ' . $this->get_userprofilefield_value();
        } else if ($datatype == 'menu') {
            if ($field == 'country') {
                $countries = get_string_manager()->get_list_of_countries(true);
                $a->fieldvalue = $countries[$opint];
            } else {
                // Custom user profile fields.
                $a->fieldvalue = $upfields[$field]->param1[$opint];
            }
        } else if ($datatype == 'checkbox') {
            if ($opint == 1) {
                $a->fieldvalue = get_string('yes');
            } else {
                $a->fieldvalue = get_string('no');
            }
        }

        $a->fieldname = $this->get_userprofilefieldname();
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
     * Returns value of op
     *
     * @param string $datatype
     * @return mixed|string|null
     * @throws \coding_exception
     */
    public function get_userprofilefield_op(string $datatype) {
        $data = $this->get_configdata();
        $field = $data['userprofilefield'];
        // We default to 2 (EQUAL TO) in case there is an existing old rule that doesn't have _op set.
        $fieldop = $data[$field . '_op'] ?? 2;

        if (!isset($fieldop)) {
            return null;
        }
        if ($datatype == 'text') {
            return $this->get_text_operators()[$fieldop];
        }
        if ($datatype == 'datetime') {
            return $this->get_date_operators()[$fieldop];
        }
        return null;
    }

    /**
     * Returns value of op2
     *
     * @param string $datatype
     * @return mixed|null
     * @throws \coding_exception
     */
    public function get_userprofilefield_op2(string $datatype) {
        $data = $this->get_configdata();
        $field = $data['userprofilefield'];
        $fieldop2 = $data[$field . '_op2'];

        if (!isset($fieldop2)) {
            return null;
        }

        if ($datatype == 'datetime') {
            return $this->get_time_operators()[$fieldop2];
        }
    }

    /**
     * Return the configured user profile field value.
     *
     * @return string|null The value configure to match against
     */
    public function get_userprofilefield_value(): ?string {
        $data = $this->get_configdata();
        if (empty($data) || !isset($data['userprofilefield'])) {
            return null;
        }
        $field = $data['userprofilefield'];

        if (!isset($data[$field . '_value'])) {
            return null;
        }
        $fieldvalue = $data[$field . '_value'];

        if (!isset($fieldvalue)) {
            return null;
        }

        return $fieldvalue;
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
