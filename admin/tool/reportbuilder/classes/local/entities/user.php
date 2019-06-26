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
 * Class user_fields
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\entities;

use tool_organisation\organisation;
use tool_organisation\tool_reportbuilder\filter\department_select;
use tool_organisation\tool_reportbuilder\filter\position_select;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use tool_wp\db;
use lang_string;

defined('MOODLE_INTERNAL') || die();

/**
 * Typical fields from the user table that can be added
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user extends entity_base {

    /** @var string  */
    protected $userjoin = '';
    /** @var string  */
    protected $usertablealias = 'u';
    /** @var array  */
    protected $excludefields = [];

    /**
     * user_fields constructor.
     *
     * @param string $userjoin join with the user table or '' if the join is already added
     * @param string $usertablealias alias for the {user} table used in the join above
     * @param array $excludefields list of user fields to exclude
     */
    public function __construct(string $userjoin, string $usertablealias = 'u', array $excludefields = []) {
        $this->userjoin = $userjoin;
        $this->usertablealias = $usertablealias;
        $this->excludefields = array_combine($excludefields, $excludefields);
    }

    /**
     * Entity name
     * @return string
     */
    public function get_entity_name(): string {
        return 'user';
    }

    /**
     * Entity title
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('entityuser', 'tool_reportbuilder');
    }

    /**
     * Returns user fields necessary for fullname() function
     *
     * @param bool $returnsql
     * @param null|string $tableprefix
     * @return array|string
     */
    public static function get_all_user_name_fields(bool $returnsql = false, ?string $tableprefix = null) {
        // TODO SP-422 return only fields used in fullname() with the correct order.
        return get_all_user_name_fields($returnsql, $tableprefix, null, null, true);
    }

    /**
     * User fields.
     *
     * @return array
     * @throws \coding_exception
     */
    protected function get_user_fields(): array {
        $fields = array(
            'firstname' => new lang_string('firstname'),
            'lastname' => new lang_string('lastname'),
            'email' => new lang_string('email'),
            'city' => new lang_string('city'),
            'country' => new lang_string('country'),
            'firstnamephonetic' => new lang_string('firstnamephonetic'),
            'lastnamephonetic' => new lang_string('lastnamephonetic'),
            'middlename' => new lang_string('middlename'),
            'alternatename' => new lang_string('alternatename'),
            'idnumber' => new lang_string('idnumber'),
            'institution' => new lang_string('institution'),
            'department' => new lang_string('profiledepartment', 'tool_reportbuilder'),
            'phone1' => new lang_string('phone'),
            'address' => new lang_string('address'),
            'lastaccess' => new lang_string('lastaccess'),
            'confirmed' => new lang_string('userconfirmed', 'tool_reportbuilder'),
            'username' => new lang_string('username'),
        );
        return array_diff_key($fields, $this->excludefields);
    }

    /**
     * Is field sortable
     *
     * @param string $fieldname
     * @return bool
     */
    protected function is_sortable(string $fieldname) : bool {
        return !in_array($fieldname, ['email', 'phone1', 'address']);
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns() : array {
        $columns = [];

        // Column "fullname".
        $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        list($sql, $params) = self::sql_fullname($this->usertablealias, $viewfullnames);
        $columns[] = (new report_column(
            'fullname',
            new \lang_string('fullname'),
            $this->get_entity_name()
        ))
            ->add_field($sql, 'fullname', $params)
            ->set_is_sortable(true)
            ->add_aggregation_fields('count', $this->usertablealias . '.id')
            ->set_groupby_sql(self::sql_fullname($this->usertablealias, $viewfullnames, true));

        $userfields = $this->get_user_fields();

        foreach ($userfields as $userkey => $userfield) {
            $columns[] = (new report_column(
                $userkey,
                $userfield,
                $this->get_entity_name()
            ))
                ->add_join($this->userjoin)
                ->add_field($this->usertablealias . '.' . $userkey)
                ->set_type($this->get_type($userkey))
                ->add_callback([$this, 'format'], $userkey)
                ->add_aggregation_callback('groupconcat', [$this, 'format_aggregation'], $userkey)
                ->add_aggregation_callback('groupconcatdistinct', [$this, 'format_aggregation'], $userkey)
                ->set_is_sortable($this->is_sortable($userkey));
        }

        $columns = array_merge($columns, $this->add_user_custom_fields());

        return $columns;
    }

    /**
     * Get the type of the column.
     *
     * @param string $userkey
     * @return null
     */
    protected function get_type(string $userkey) {
        switch ($userkey) {
            case 'confirmed':
                return constants::DB_TYPE_BOOLEAN;
                break;
            case 'lastaccess':
                return constants::DB_TYPE_DATETIME;
                break;
            case 'description':
                return constants::DB_TYPE_LONGTEXT;
                break;
            default:
                return null;
                break;
        }
    }

    /**
     * Add the user profile fields
     */
    private function add_user_custom_fields(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/user/profile/lib.php');
        $profilefields = profile_get_user_fields_with_data(0);
        $columns = [];
        foreach ($profilefields as $profilefield) {

            // If is not set to 'Visible to everyone' don't show it.
            if ((int)$profilefield->field->visible !== 2) {
                continue;
            }

            $alias = db::generate_alias();
            $a = ['field' => format_string($profilefield->field->shortname, true, ['escape' => false]),
                'category' => format_string($profilefield->get_category_name(), true, ['escape' => false])];
            $column = (new report_column(
                'profilefield_' . $profilefield->fieldid,
                new lang_string('coursecustomfieldname', 'tool_reportbuilder', $a),
                $this->get_entity_name()
            ))
                ->add_join("left join {user_info_data} $alias on $alias.userid = u.id AND $alias.fieldid = " .
                    $profilefield->fieldid)
                ->add_field($alias . '.data')
                ->add_callback([$this, 'format_profile_field'], $profilefield);
            // TODO add aggregation callbacks for groupconcat and groupconcatdistinct.

            // Aggregation for groupconcat.
            $column->add_aggregation_fields('groupconcat', "$alias.data");

            // Aggregation for groupconcatdistinct.
            $column->add_aggregation_fields('groupconcatdistinct', "$alias.data");

            // Aggregation for count.
            $column->add_aggregation_fields('count', "$alias.data");

            // Aggregation for countdtistinct.
            $column->add_aggregation_fields('countdistinct', "$alias.data");

            // Aggregation for sum.
            $column->add_aggregation_fields('sum', "$alias.data");

            // Aggregation for avg.
            $column->add_aggregation_fields('avg', "$alias.data");

            // Aggregation for max.
            $column->add_aggregation_fields('max', "$alias.data");

            // Aggregation for min.
            $column->add_aggregation_fields('min', "$alias.data");

            // Aggregation for percent.
            $castfield = $DB->sql_cast_char2int("$alias.data");
            $column->add_aggregation_fields('percent', $castfield);

            // Aggregation for unique.
            $column->add_aggregation_fields('unique', "$alias.data");

            $column->set_type(self::get_custom_fields_type($profilefield->field->datatype));

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Get custom user profile fields filters.
     *
     * @param bool $iscondition
     * @return array
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function get_user_custom_filters(bool $iscondition): array {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/user/profile/lib.php');
        $profilefields = profile_get_user_fields_with_data(0);
        $columns = [];
        foreach ($profilefields as $profilefield) {

            // If is not set to 'Visible to everyone' don't show it.
            if ((int)$profilefield->field->visible !== 2) {
                continue;
            }

            $d = db::generate_alias();
            $field = $d . '.data';

            switch ($profilefield->field->datatype) {
                case 'checkbox':
                    $classname = checkbox::class;
                    $field = $DB->sql_cast_char2int($field);
                    break;
                case 'datetime':
                    $classname = $iscondition ? date_condition::class : date_filter::class;
                    $field = $DB->sql_cast_char2int($field);
                    break;
                case 'menu':
                    $classname = select::class;
                    break;
                case 'text':
                case 'textarea':
                default:
                    $classname = text::class;
                    break;
            }

            $filter = new report_filter(
                $classname,
                'profilefield_' . $profilefield->field->shortname . '_' . $profilefield->fieldid,
                new lang_string('customfieldcolumn', 'tool_reportbuilder', $profilefield->field->shortname),
                $this->get_entity_name(),
                $field
            );

            // If dropdown load options in conditions form.
            if ($profilefield->field->datatype === 'menu') {
                $filter->set_options($profilefield->options);
            }

            $filter->add_join("LEFT JOIN {user_info_data} $d ON $d.userid = u.id AND $d.fieldid = $profilefield->fieldid");

            $columns[] = $filter;
        }
        return $columns;
    }

    /**
     * Get user profile field type for report.
     *
     * @param string $type
     * @return int
     */
    private static function get_custom_fields_type(string $type): int {
        switch ($type) {
            case 'checkbox':
                return constants::DB_TYPE_BOOLEAN;
                break;
            case 'datetime':
                return constants::DB_TYPE_DATETIME;
                break;
            case 'menu':
                return constants::DB_TYPE_TEXT;
                break;
            case 'textarea':
                return constants::DB_TYPE_LONGTEXT;
                break;
            case 'text':
            default:
                return constants::DB_TYPE_TEXT;
                break;
        }
    }

    /**
     * Formatter for a profile field
     *
     * @param mixed $value
     * @param \stdClass $row
     * @param \profile_field_base $field
     * @return string
     */
    public static function format_profile_field($value, $row, \profile_field_base $field): string {
        // TODO we may need to override display_data() for some known types in case of downloading (i.e. checkbox).
        $field->data = $value;
        return $field->display_data();
    }

    /**
     * Returns all available conditions on user fields
     *
     * @param bool $iscondition true if this is condition, false if this is a filter
     * @return report_filter[]
     */
    protected function get_conditions_or_filters(bool $iscondition): array {
        $conditions = [];

        // User fields filters.
        $fields = $this->get_user_fields();
        foreach ($fields as $field => $name) {
            $options = $this->get_options_for($field);
            if ($options !== null) {
                $classname = select::class;
            } else if ((int)$this->get_type($field) === constants::DB_TYPE_BOOLEAN) {
                $classname = checkbox::class;
            } else if ((int)$this->get_type($field) === constants::DB_TYPE_DATETIME) {
                $classname = $iscondition ? date_condition::class : date_filter::class;
            } else {
                $classname = text::class;
            }

            $filter = (new report_filter(
                $classname,
                $field,
                $name,
                $this->get_entity_name(),
                $this->usertablealias . '.' . $field
            ))
                ->add_join($this->userjoin);
            if ($options !== null) {
                $filter->set_options($options);
            }
            $conditions[] = $filter;
        }

        // Add user profile fields filters.
        $customfilters = $this->get_user_custom_filters($iscondition);

        // User job filters.
        $conditions = array_merge($conditions, $this->get_job_filters($iscondition), $customfilters);

        return $conditions;
    }

    /**
     * Filters/conditions for user jobs
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_job_filters(bool $iscondition): array {
        $conditions[] = (new report_filter(
            position_select::class,
            'jobposition',
            new lang_string('hasjobposition', 'tool_organisation'),
            $this->get_entity_name(),
            'u.id'
        ))
            ->add_join($this->userjoin)
            ->set_options(organisation::get_all_positions_menu(
                ['' => get_string('anyposition', 'tool_organisation')]));

        $conditions[] = (new report_filter(
            department_select::class,
            'jobdepartment',
            new lang_string('hasjobdepartment', 'tool_organisation'),
            $this->get_entity_name(),
            'u.id'
        ))
            ->add_join($this->userjoin)
            ->set_options(organisation::get_all_departments_menu(
                ['' => get_string('anydepartment', 'tool_organisation')]));

        return $conditions;
    }

    /**
     * Gets list of options if the field filter/condition should be displayed as select
     *
     * @param string $fieldname
     * @return null|array
     */
    protected function get_options_for($fieldname) {
        if ($fieldname === 'country') {
            $countries = array_map(function($name) {
                return shorten_text($name);
            }, get_string_manager()->get_list_of_countries());
            return ['' => ''] + $countries;
        }

        return null;
    }

    /**
     * Returns all available conditions on user fields
     *
     * @return report_filter[]
     */
    public function get_conditions() : array {
        return $this->get_conditions_or_filters(true);
    }

    /**
     * Returns all available filters on user fields
     *
     * @return report_filter[]
     */
    public function get_filters() : array {
        return $this->get_conditions_or_filters(false);
    }

    /**
     * Formats the user field for display
     *
     * @param mixed $value
     * @param \stdClass $row
     * @param string $fieldname
     * @return string
     */
    public function format($value, \stdClass $row, string $fieldname) {
        try {
            $type = \core_user::get_property_type($fieldname);
        } catch (\Exception $e) {
            $type = PARAM_NOTAGS;
        }

        $value = clean_param($value, $type);

        if ($fieldname === 'lastaccess') {
            return format::userdate($value, $row);
        } else if ($fieldname === 'country') {
            return format::country($value, $row);
        } else if ($fieldname === 'confirmed') {
            return format::checkbox_as_text($value);
        } else {
            return $value;
        }
    }

    /**
     * Formats the user field for display
     *
     * @param string $value Current field value
     * @param \stdClass $row Complete row
     * @param string $fieldname Current fieldname
     * @return string
     * @throws \coding_exception
     */
    public function format_aggregation(string $value, \stdClass $row, string $fieldname) {
        try {
            $type = \core_user::get_property_type($fieldname);
        } catch (\Exception $e) {
            $type = PARAM_NOTAGS;
        }

        if ($fieldname === 'country') {
            $namedcountries = [];
            $separator = get_string('listsep', 'langconfig');
            $countries = explode ($separator, $value);
            foreach ($countries as $country) {
                $value = clean_param($country, $type);
                $namedcountries[] = format::country($value, $row);
            }
            return implode(', ', array_filter($namedcountries));
        }

        $value = clean_param($value, $type);
        return $value;
    }

    /**
     * Correct implementation of sql_fullname
     *
     * @param string $usertablealias
     * @param bool $override
     * @param bool $ascsv return as CSV string with only necessary fields, no other characters, no concatenation
     *        (to be used in group by)
     * @return array|string
     */
    public static function sql_fullname($usertablealias = 'u', bool $override = false, bool $ascsv = false) {
        global $DB;
        $user = (object)[];
        $usernames = array_keys(self::get_all_user_name_fields());
        foreach ($usernames as $idx => $field) {
            $user->$field = '|||<<' . $idx . '>>|||';
        }
        $name = fullname($user, $override);
        $parts = preg_split('/\|\|\|/', $name);
        $params = [];
        $elements = [];
        $prefix = strlen($usertablealias) ? $usertablealias . '.' : '';
        foreach ($parts as $part) {
            if (!strlen($part)) {
                continue;
            }
            if (preg_match('/^<<(\d+)>>$/', $part, $matches)) {
                $elements[] = $prefix . $usernames[$matches[1]];
            } else if (!$ascsv) {
                $paramname = db::generate_param_name();
                $params[$paramname] = $part;
                $elements[] = ':' . $paramname;
            }
        }
        if ($ascsv) {
            return join(', ', $elements);
        }
        $sql = call_user_func_array([$DB, 'sql_concat'], $elements);
        return [$sql, $params];
    }
}
