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
 * This file contains the backend class for user_profile_field condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use core_plugin_manager;
use core_user;
use tool_dynamicrule\api;
use tool_dynamicrule\rule;
use tool_reportbuilder\local\helpers\relative_dates;

/**
 * The backend class for user_profile_field condition
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_profile_field extends \tool_dynamicrule\condition_sql {

    /** @var int Text contains value */
    public const TEXT_CONTAINS = 0;
    /** @var int Text does not contain value */
    public const TEXT_DOES_NOT_CONTAIN = 1;
    /** @var int Text is equal to value */
    public const TEXT_IS_EQUAL_TO = 2;
    /** @var int Text starts with value */
    public const TEXT_STARTS_WITH = 3;
    /** @var int Text ends with value */
    public const TEXT_ENDS_WITH = 4;
    /** @var int Text is empty */
    public const TEXT_IS_EMPTY = 5;
    /** @var int Text is not empty */
    public const TEXT_IS_NOT_EMPTY = 6;
    /** @var int Text is not equal to value */
    public const TEXT_IS_NOT_EQUAL_TO = 7;

    /** @var int Date is any value */
    public const DATE_ANY_VALUE = 0;
    /** @var int Date is not empty */
    public const DATE_IS_NOT_EMPTY = 1;
    /** @var int Date is empty */
    public const DATE_IS_EMPTY = 2;
    /** @var int Date is in the past */
    public const DATE_IS_IN_THE_PAST = 3;
    /** @var int Date is in the future */
    public const DATE_IS_IN_THE_FUTURE = 4;
    /** @var int Date last x days */
    public const DATE_LAST_X_DAYS = 5;
    /** @var int Date next x days */
    public const DATE_NEXT_X_DAYS = 6;
    /** @var int Date current [day/week/month/quarter/year/financial year] */
    public const DATE_CURRENT = 7;
    /** @var int Date previous [day/week/month/quarter/year/financial year] */
    public const DATE_PREVIOUS = 8;
    /** @var int Date upcoming [day/week/month/quarter/year/financial year] */
    public const DATE_UPCOMING = 9;

    /** @var int op2 value Day */
    public const TIME_DAY = 1;
    /** @var int op2 value Week */
    public const TIME_WEEK = 2;
    /** @var int op2 value Month */
    public const TIME_MONTH = 3;
    /** @var int op2 value Quarter */
    public const TIME_QUARTER = 4;
    /** @var int op2 value Year */
    public const TIME_YEAR = 5;

    /**
     * @var array Fields on user table that are filterable by this condition.
     */
    private static $defaultfields = ['lastname', 'firstname', 'username', 'email', 'city', 'idnumber', 'institution',
        'country', 'auth'];

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
     */
    public function add_text_field(\MoodleQuickForm $mform, array &$group, \stdClass $field): void {
        $shortname = $field->shortname;
        $elements = [];
        $elements[] = $mform->createElement('select', $shortname . '_op', null, $this->get_text_operators());
        $elements[] = $mform->createElement('text', $shortname . '_value', null);

        // Field should define 'paramtype' property, otherwise be cautious and define PARAM_TEXT.
        $mform->setType($shortname . '_value', $field->paramtype ?? PARAM_TEXT);
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
        $mform->setDefault($shortname . '_op', self::DATE_ANY_VALUE);
        $mform->setType($shortname . '_op2', PARAM_INT);
        $mform->setDefault($shortname . '_op2', self::TIME_DAY);
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
        // If a multi-dimensional array is passed, we need to use a different element type.
        $options = (array) $field->param1;
        $selectelement = (count($options) == count($options, COUNT_RECURSIVE) ? 'select' : 'selectgroups');

        $elements = [];
        $elements[] = $mform->createElement($selectelement, $field->shortname . '_op', $field->name, $options);
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
            self::TEXT_CONTAINS => get_string('contains', 'filters'),
            self::TEXT_DOES_NOT_CONTAIN => get_string('doesnotcontain', 'filters'),
            self::TEXT_IS_EQUAL_TO => get_string('isequalto', 'filters'),
            self::TEXT_IS_NOT_EQUAL_TO => get_string('isnotequalto', 'filters'),
            self::TEXT_STARTS_WITH => get_string('startswith', 'filters'),
            self::TEXT_ENDS_WITH => get_string('endswith', 'filters'),
            self::TEXT_IS_EMPTY => get_string('isempty', 'filters'),
            self::TEXT_IS_NOT_EMPTY => get_string('isnotempty', 'tool_reportbuilder')
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
            self::DATE_ANY_VALUE => get_string('dateanyvalue', 'tool_reportbuilder'),
            self::DATE_IS_NOT_EMPTY => get_string('dateisnotempty', 'tool_reportbuilder'),
            self::DATE_IS_EMPTY => get_string('dateisempty', 'tool_reportbuilder'),
            self::DATE_IS_IN_THE_PAST => get_string('dateinthepast', 'tool_reportbuilder'),
            self::DATE_IS_IN_THE_FUTURE => get_string('dateinthefuture', 'tool_reportbuilder'),
            self::DATE_LAST_X_DAYS => get_string('datelast', 'tool_reportbuilder'),
            self::DATE_NEXT_X_DAYS => get_string('datenext', 'tool_reportbuilder'),
            self::DATE_CURRENT => get_string('datecurrent', 'tool_reportbuilder'),
            self::DATE_PREVIOUS => get_string('dateprevious', 'tool_reportbuilder'),
            self::DATE_UPCOMING => get_string('dateupcoming', 'tool_reportbuilder')
        ];
    }

    /**
     * Returns an array of time select options
     *
     * TODO: define the strings consistently instead of re-using from core (mixed casing in English lang pack)
     *
     * @return array of select options
     */
    public function get_time_operators() : array {
        return [
            self::TIME_DAY => get_string('day'),
            self::TIME_WEEK => get_string('week'),
            self::TIME_MONTH => get_string('month'),
            self::TIME_QUARTER => get_string('quarter', 'tool_reportbuilder'),
            self::TIME_YEAR => get_string('year')
        ];
    }

    /**
     * Return a mapping of relative time period constants used internally, to those expected by the {@see relative_dates} helper
     *
     * @param int $timeperiod One of the relative time period constants
     * @return string
     */
    protected function get_time_period_name(int $timeperiod): string {
        $mapping = [
            self::TIME_DAY     => 'day',
            self::TIME_WEEK    => 'week',
            self::TIME_MONTH   => 'month',
            self::TIME_QUARTER => 'quarter',
            self::TIME_YEAR    => 'year',
        ];

        return $mapping[$timeperiod] ?? (string) $timeperiod;
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
            (($datatype == 'text' && $data[$fieldop] != self::TEXT_IS_EMPTY && $data[$fieldop] != self::TEXT_IS_NOT_EMPTY) ||
            ($datatype == 'datetime' &&
                ($data[$fieldop] == self::DATE_LAST_X_DAYS || $data[$fieldop] == self::DATE_NEXT_X_DAYS)))) ||
            (!empty($data[$fieldvalue]) && $datatype == 'datetime' && !is_numeric($data[$fieldvalue]))) {
            $errors['userprofilefieldgroup'] = get_string('errorinvaliduserprofilefieldvalue', 'tool_dynamicrule');
        }

        return $errors;
    }

    /**
     * Returns array with all user profile fields shortname, name, datatype and param1, plus type for text fields
     *
     * @param bool $checkcapability Whether to check viewing capability for custom fields.
     * @return \stdClass[]
     */
    private function get_profile_fields_info(bool $checkcapability = true): array {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');

        $res = [];
        $res['lastname'] = (object)['shortname' => 'lastname', 'name' => get_string('lastname'), 'datatype' => 'text',
            'paramtype' => core_user::get_property_type('lastname')];
        $res['firstname'] = (object)['shortname' => 'firstname', 'name' => get_string('firstname'), 'datatype' => 'text',
            'paramtype' => core_user::get_property_type('firstname')];
        $res['username'] = (object)['shortname' => 'username', 'name' => get_string('username'), 'datatype' => 'text',
            'paramtype' => core_user::get_property_type('username')];
        $res['email'] = (object)['shortname' => 'email', 'name' => get_string('email'), 'datatype' => 'text',
            'paramtype' => core_user::get_property_type('email')];
        $res['city'] = (object)['shortname' => 'city', 'name' => get_string('city'), 'datatype' => 'text',
            'paramtype' => core_user::get_property_type('city')];
        $res['idnumber'] = (object)['shortname' => 'idnumber', 'name' => get_string('idnumber'), 'datatype' => 'text',
            'paramtype' => core_user::get_property_type('idnumber')];
        $res['institution'] = (object)['shortname' => 'institution', 'name' => get_string('institution'), 'datatype' => 'text',
            'paramtype' => core_user::get_property_type('institution')];
        $res['country'] = (object)['shortname' => 'country', 'name' => get_string('country'), 'datatype' => 'menu',
            'param1' => get_string_manager()->get_list_of_countries(true)];

        // Use a group select to distinguish between enabled/disabled authentication plugins.
        $authstrs = get_strings(['pluginenabled', 'plugindisabled'], 'core_plugin');
        $authpluginselect = [
            $authstrs->pluginenabled => [],
            $authstrs->plugindisabled => [],
        ];

        $authplugins = core_plugin_manager::instance()->get_plugins_of_type('auth');
        foreach ($authplugins as $authplugin) {
            $authplugingroup = is_enabled_auth($authplugin->name)
                ? $authstrs->pluginenabled
                : $authstrs->plugindisabled;

            $authpluginselect[$authplugingroup][$authplugin->name] = $authplugin->displayname;
        }
        $res['auth'] = (object)['shortname' => 'auth', 'name' => get_string('type_auth', 'plugin'), 'datatype' => 'menu',
            'param1' => $authpluginselect];

        $onlyvisible = $checkcapability && !has_capability('moodle/user:viewalldetails', \context_system::instance());
        $pfields = array_filter(profile_get_user_fields_with_data(0), function($f) use ($onlyvisible) {
            return $f->field->datatype !== 'textarea'
                && (!$onlyvisible || $f->is_visible());
        });

        foreach ($pfields as $pfield) {
            $field = (object)array_intersect_key((array)$pfield->field,
                ['shortname' => 1, 'name' => 1, 'datatype' => 1, 'param1' => 1]);
            if ($field->datatype == 'menu') {
                $fields = explode("\n", $field->param1);
                $field->param1 = array_combine($fields, $fields);
            } else if ($field->datatype == 'text') {
                // Match type defined in the class for custom 'text' profile fields.
                $field->paramtype = PARAM_TEXT;
            }
            // TODO WP-2758 the actual array index has to be "profile_field_{$field->shortname}" but it requires
            // other changes and also upgrade script.
            $res[$field->shortname] = $field;
        }

        return $res;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        global $DB;
        $field = $this->get_userprofilefield();
        $upfields = $this->get_profile_fields_info(false);
        $datatype = $upfields[$this->get_configdata()['userprofilefield']]->datatype;

        // Default to "not matched".
        $where = '1=0';
        $join = '';
        $params = [];

        if (in_array($field, self::$defaultfields)) {
            // One of default fields.
            if ($datatype == 'text') {
                [$where, $params] = $this->get_text_sql('u', $field);
            } else if ($datatype == 'menu') {
                [$where, $params] = $this->get_menu_checkbox_sql('u', $field);
            }
        } else {
            // One of custom fields.
            $ud = api::generate_alias();
            $uif = api::generate_alias();
            $uid = api::generate_alias();
            $fields = "{$uid}.data, {$uid}.userid";
            if ($datatype == 'datetime') {
                [$where, $params] = $this->get_datetime_sql($ud);
                $fields .= ", (CASE WHEN {$uif}.datatype = 'datetime'
                    THEN " . $DB->sql_cast_char2int("{$uid}.data", true) . " ELSE 0 END) datetime";
            } else if ($datatype == 'text') {
                [$where, $params] = $this->get_text_sql($ud, 'data');
            } else if ($datatype == 'menu' || $datatype == 'checkbox') {
                [$where, $params] = $this->get_menu_checkbox_sql($ud, 'data', $field);
            }
            if (!empty($where)) {
                $userprofilefield = api::generate_param_name();
                // Get all users either they are custom profile field assigned or not,then match with current operator.
                $join = "LEFT JOIN (SELECT $fields
                                 FROM {user_info_data} $uid
                                 JOIN {user_info_field} $uif
                                   ON ({$uif}.id = {$uid}.fieldid AND {$uif}.shortname = :{$userprofilefield})) $ud
                           ON ({$ud}.userid = u.id)";
                $params[$userprofilefield] = $field;
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
        $value = $this->get_userprofilefield_value();
        $fieldshortname = $this->get_configdata()['userprofilefield'];
        $operator = $this->get_configdata()[$fieldshortname . '_op'];
        $operator2 = $this->get_configdata()[$fieldshortname . '_op2'];
        $param = api::generate_param_name();
        $param2 = api::generate_param_name();

        if (is_null($operator)) {
            // Configuration is invalid.
            return ['', []];
        }
        if (($operator == self::DATE_LAST_X_DAYS || $operator == self::DATE_NEXT_X_DAYS) && !(int)$value) {
            // Configuration is invalid.
            return ['', []];
        }
        if (($operator == self::DATE_CURRENT || $operator == self::DATE_PREVIOUS || $operator == self::DATE_UPCOMING)
            && !(int)$operator2) {
            // Configuration is invalid.
            return ['', []];
        }

        switch($operator) {
            case self::DATE_ANY_VALUE:
                $res = "1=1";
                $params = [];
                break;
            case self::DATE_IS_NOT_EMPTY:
                $res = "{$alias}.datetime  <> :$param";
                $params[$param] = 0;
                break;
            case self::DATE_IS_EMPTY:
                $res = "{$alias}.datetime = :$param OR {$alias}.datetime IS NULL";
                $params[$param] = 0;
                break;
            case self::DATE_IS_IN_THE_PAST:
                $res = "{$alias}.datetime <= :$param AND {$alias}.datetime > :$param2";
                $params[$param] = time();
                $params[$param2] = 0;
                break;
            case self::DATE_IS_IN_THE_FUTURE:
                $res = "{$alias}.datetime >= :$param";
                $params[$param] = time();
                break;
            case self::DATE_LAST_X_DAYS:
                $res = "{$alias}.datetime >= :$param AND {$alias}.datetime <= :$param2";
                $params[$param] = time() - DAYSECS * ((int)$value);
                $params[$param2] = time();
                break;
            case self::DATE_NEXT_X_DAYS:
                $res = "{$alias}.datetime >= :$param AND {$alias}.datetime <= :$param2";
                $params[$param] = time();
                $params[$param2] = time() + DAYSECS * ((int)$value);
                break;
            case self::DATE_CURRENT: // Current [day/week/month/quarter/year/financial year].
                $res = "{$alias}.datetime >= :$param AND {$alias}.datetime <= :$param2";
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('current',
                    $this->get_time_period_name($operator2));
                return [$res, $params];
                break;
            case self::DATE_PREVIOUS: // Previous [day/week/month/quarter/year/financial year].
                $res = "{$alias}.datetime >= :$param AND {$alias}.datetime <= :$param2";
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('previous',
                    $this->get_time_period_name($operator2));
                return [$res, $params];
                break;
            case self::DATE_UPCOMING: // Upcoming [day/week/month/quarter/year/financial year].
                $res = "{$alias}.datetime >= :$param AND {$alias}.datetime <= :$param2";
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('upcoming',
                    $this->get_time_period_name($operator2));
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
        $operator = $this->get_configdata()[$fieldshortname . '_op'] ?? self::TEXT_IS_EQUAL_TO;

        if ($value === '' && $operator != self::TEXT_IS_EMPTY && $operator != self::TEXT_IS_NOT_EMPTY) {
            // Configuration is invalid.
            return ['', []];
        }

        switch ($operator) {
            case self::TEXT_CONTAINS:
                $res = $DB->sql_like("$alias.$field", ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value%";
                break;
            case self::TEXT_DOES_NOT_CONTAIN:
                $res = $DB->sql_like("$alias.$field", ":$name", false, false, true);
                // Include users with profile field not equal and unassigned.
                $res .= " OR {$alias}.{$field} IS NULL";
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value%";
                break;
            case self::TEXT_IS_EQUAL_TO:
                $res = $DB->sql_equal($DB->sql_compare_text("{$alias}.{$field}"), ":$name", false, false);
                $params[$name] = $value;
                break;
            case self::TEXT_IS_NOT_EQUAL_TO:
                $res = $DB->sql_equal($DB->sql_compare_text("{$alias}.{$field}"), ":$name", false, false, true);
                $res .= " OR {$alias}.{$field} IS NULL";
                $params[$name] = $value;
                break;
            case self::TEXT_STARTS_WITH:
                $res = $DB->sql_like("$alias.$field", ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "$value%";
                break;
            case self::TEXT_ENDS_WITH:
                $res = $DB->sql_like("$alias.$field", ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value";
                break;
            case self::TEXT_IS_EMPTY:
                $res = $DB->sql_compare_text("$alias.$field") . " = " . $DB->sql_compare_text(":$name");
                // Include users with profile field text empty and unassigned.
                $res .= " OR {$alias}.{$field} IS NULL";
                $params[$name] = '';
                break;
            case self::TEXT_IS_NOT_EMPTY:
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
        if (!isset($this->get_configdata()['userprofilefield']) ||
            !isset($upfields[$this->get_configdata()['userprofilefield']])) {
            return '';
        }

        $field = $this->get_configdata()['userprofilefield'];
        $datatype = $upfields[$field]->datatype;

        // We default to 2 (EQUAL TO) in case there is an existing old rule that doesn't have _op set.
        $opint = $this->get_configdata()[$field . '_op'] ?? self::TEXT_IS_EQUAL_TO;

        $str = 'conditionuserprofilefielddescription';
        $a->fieldvalue = '';
        if ($datatype == 'datetime' && ($opint === self::DATE_LAST_X_DAYS || $opint === self::DATE_NEXT_X_DAYS)) {
            $a->fieldvalue = $this->get_userprofilefield_op($datatype) . ' ' . $this->get_userprofilefield_value();
        } else if ($datatype == 'datetime' && in_array($opint, [self::DATE_CURRENT, self::DATE_PREVIOUS, self::DATE_UPCOMING])) {
            $a->fieldvalue = $this->get_userprofilefield_op($datatype) . ' ' . $this->get_userprofilefield_op2($datatype);
        } else if ($datatype == 'datetime') {
            $a->fieldvalue = $this->get_userprofilefield_op($datatype);
        } else if ($datatype == 'text' && in_array($opint, [self::TEXT_IS_EMPTY, self::TEXT_IS_NOT_EMPTY])) {
            $str = 'conditionuserprofilefielddescriptiontext';
            $a->fieldvalue = $this->get_userprofilefield_op($datatype);
        } else if ($datatype == 'text') {
            $str = 'conditionuserprofilefielddescriptiontext';
            $a->fieldvalue = $this->get_userprofilefield_op($datatype) . ' ' . s($this->get_userprofilefield_value());
        } else if ($datatype == 'menu') {
            if ($field == 'country') {
                $countries = get_string_manager()->get_list_of_countries(true);
                $a->fieldvalue = $countries[$opint];
            } else if ($field == 'auth') {
                $authplugins = core_plugin_manager::instance()->get_plugins_of_type('auth');
                $a->fieldvalue = $authplugins[$opint]->displayname;
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
        $fieldop = $data[$field . '_op'] ?? self::TEXT_IS_EQUAL_TO;

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
            if (isset($data['userprofilefieldvalue'])) {
                return $data['userprofilefieldvalue'];
            }
            return null;
        }
        return $data[$field . '_value'];
    }

    /**
     * Return the formatted user profile field name.
     *
     * @return string
     */
    public function get_userprofilefieldname(): string {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');

        $field = $this->get_userprofilefield();
        if (in_array($field, self::$defaultfields)) {
            $str = $this->get_profile_fields_info()[$field]->name;
        } else {
            $fieldobj = profile_get_custom_field_data_by_shortname($field);
            $str = format_string($fieldobj->name, true, ['context' => \context_system::instance(), 'escape' => false]);
        }
        return $str;
    }

    /**
     * Check if user profile field still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');

        $field = $this->get_userprofilefield();
        return in_array($field, self::$defaultfields) ||
            ($field && profile_get_custom_field_data_by_shortname($field));
    }

    /**
     * Event subscription.
     *
     * @return string|false eventname or false if no subscription.
     */
    public function get_event_subscription() {
        return '\core\event\user_updated';
    }

    /**
     * If the current user is able to add this condition.
     *
     * We need to check if any of user profile fields we use is visible to user. If none - user can't add.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');

        if (\tool_tenant\permission::can_browse_users() || \tool_tenant\permission::can_browse_users_anywhere()) {
            if (has_capability('moodle/user:viewalldetails', \context_system::instance())) {
                return true;
            }

            // User does not have capability but at least one field is visible.
            foreach (profile_get_user_fields_with_data(0) as $f) {
                if ($f->is_visible() && $f->field->datatype !== 'textarea') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');

        if (\tool_tenant\permission::can_browse_users() || \tool_tenant\permission::can_browse_all_users()) {
            if (has_capability('moodle/user:viewalldetails', \context_system::instance())) {
                return true;
            }

            if (!in_array($configdata['userprofilefield'], self::$defaultfields)) {
                // If no capability to viewalldetails,
                // check if selected field is visible for this user.
                foreach (profile_get_user_fields_with_data(0) as $f) {
                    if ($f->get_shortname() === $configdata['userprofilefield'] && $f->is_visible()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Which rule types this condition supports.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return rule::TYPE_NORMAL + rule::TYPE_SHARED;
    }
}
