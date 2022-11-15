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
 * Class user_fields
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\entities;

use core_user\fields;
use tool_organisation\organisation;
use tool_organisation\tool_reportbuilder\filter\audience_select;
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
use tool_tenant\tenancy;
use tool_tenant\tool_reportbuilder\filter\user_tenant_filter;
use tool_wp\db;
use lang_string;

/**
 * Typical fields from the user table that can be added
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user extends entity_base {

    /** @var array cached fields */
    protected $userprofilefields = null;
    /** @var bool Add columns and filters/conditions for user tenants */
    protected $hastenantcolumns = false;

    /**
     * Which entity class from core reportbuilder should this entity be migrated to
     *
     * @return string name of the class extending {@see \core_reportbuilder\local\entities\entity_base}
     */
    public function convert_get_entity_class(): string {
        return \core_reportbuilder\local\entities\user::class;
    }

    /**
     * The column name from the corresponding core reportbuilder entity
     *
     * Entities can override if some columns were renamed in the new core_reportbuilder entities
     *
     * @param report_column $oldcolumn
     * @param \core_reportbuilder\datasource $newsource
     * @return string
     */
    public function convert_get_column_name(report_column $oldcolumn, \core_reportbuilder\datasource $newsource): string {
        foreach ($this->get_user_profile_fields() as $profilefield) {
            if ('profilefield_'.$profilefield->fieldid === $oldcolumn->get_name()) {
                return strtolower('profilefield_' . $profilefield->field->shortname);
            }
        }
        return $oldcolumn->get_name();
    }

    /**
     * Allows to map custom/profile fields during conversion to core reportbuilder
     *
     * This function is used only for filters and conditions and is not used for column names
     *
     * @param string $oldfieldname the current column/filter/condition name
     * @return string the identifier of the corresponding column/filter/condition in the converted datasource entity
     */
    protected function convert_get_generic_field_name(string $oldfieldname): string {
        foreach ($this->get_user_profile_fields() as $profilefield) {
            $oldname = $this->resolve_filter_name('profile_field_'.$profilefield->field->shortname);
            if ($oldname === $oldfieldname) {
                return strtolower('profilefield_' . $profilefield->field->shortname);
            }
        }
        return $oldfieldname;
    }

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['user' => 'u', 'tool_tenant_user' => 'uttu', 'tool_tenant' => 'utt'];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'user';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityuser', 'tool_reportbuilder');
    }

    /**
     * Allow to add column and filters to show the user tenant. Disabled by default
     *
     * @param bool $value
     * @return $this
     */
    public function set_allow_tenant_columns(bool $value): self {
        $this->hastenantcolumns = $value;
        return $this;
    }

    /**
     * Executed when entity is added to the datasource or system report
     */
    public function add_to_report() {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $conditions = $this->get_conditions_or_filters(true);
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        $filters = $this->get_conditions_or_filters(false);
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }
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
        if ($returnsql) {
            return fields::for_name()->get_sql((string) $tableprefix, false, '', '', false)->selects;
        } else {
            return fields::get_name_fields();
        }
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
            'phone1' => new lang_string('phone1'),
            'phone2' => new lang_string('phone2'),
            'address' => new lang_string('address'),
            'lastaccess' => new lang_string('lastaccess'),
            'suspended' => new lang_string('suspended'),
            'confirmed' => new lang_string('confirmed', 'admin'),
            'username' => new lang_string('username'),
            'timecreated' => new lang_string('timecreated', 'tool_reportbuilder'),
        );

        return array_filter($fields, [$this, 'should_include_field'], ARRAY_FILTER_USE_KEY);
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
    protected function get_all_columns() : array {
        $columns = [];

        // Column "fullname".
        $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        $usertablealias = $this->get_table_alias('user');
        list($sql, $params) = \tool_reportbuilder\db::sql_fullname($usertablealias, $viewfullnames);
        $columns[] = (new report_column(
            'fullname',
            new lang_string('fullname'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field($sql, 'fullname', $params)
            ->set_is_sortable(true)
            ->add_aggregation_fields('count', $usertablealias . '.id')
            ->set_groupby_sql(\tool_reportbuilder\db::sql_fullname($usertablealias, $viewfullnames, true));

        // Columns picture, fullname with picture, fullname with picture and link, fullname with link.
        $fullnamefields = [
            'picture' => new lang_string('userpicture', 'tool_reportbuilder'),
            'fullnamewithlink' => new lang_string('fullnamewithlink', 'tool_reportbuilder'),
            'fullnamewithpicture' => new lang_string('fullnamewithpicture', 'tool_reportbuilder'),
            'fullnamewithpicturelink' => new lang_string('fullnamewithpicturelink', 'tool_reportbuilder'),
        ];
        foreach ($fullnamefields as $fieldname => $fielddisplayname) {
            $haspicture = preg_match('/picture/', $fieldname);
            $hasfullname = preg_match('/fullname/', $fieldname);
            $groupby = "{$usertablealias}.id".
                ($haspicture ? ",{$usertablealias}.picture,{$usertablealias}.email" : "") .
                ($hasfullname ? "," . \tool_reportbuilder\db::sql_fullname($usertablealias, $viewfullnames, true) : "");
            $str = '<span>' . ($hasfullname ? '{{name}}' : '') .
                '</span data-user="{{id}},' . ($haspicture ? '{{picture}},{{email}}' : ',') . '">';
            $sqlname = '';
            $paramsname = [];
            if ($hasfullname) {
                [$sqlname, $paramsname] = \tool_reportbuilder\db::sql_fullname($usertablealias, $viewfullnames);
                $sqlname = \tool_reportbuilder\db::remove_oracle_hack($sqlname);
            }
            list($sql, $params) = \tool_reportbuilder\db::sql_string_with_placeholders($str,
                ['{{id}}' => $usertablealias . '.id',
                    '{{picture}}' => $usertablealias . '.picture',
                    '{{email}}' => $usertablealias . '.email',
                    '{{name}}' => $sqlname]);
            $params += $paramsname;

            $columns[] = (new report_column(
                $fieldname,
                $fielddisplayname,
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->add_field($sql, $fieldname, $params)
                ->set_is_sortable($fieldname !== 'picture')
                ->add_aggregation_fields('count', $usertablealias . '.id')
                ->add_callback([$this, 'fullname_replace_all'], $fieldname)
                ->add_aggregation_callback('groupconcat', [$this, 'fullname_replace_all'], $fieldname)
                ->add_aggregation_callback('groupconcatdistinct', [$this, 'fullname_replace_all'], $fieldname)
                ->set_groupby_sql($groupby);
        }

        // Add all other user fields.
        $userfields = $this->get_user_fields();
        $extrauserfields = fields::for_identity(\context_system::instance(), false)->get_required_fields();

        foreach ($userfields as $userkey => $userfield) {
            $column = (new report_column(
                $userkey,
                $userfield,
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->add_field($usertablealias . '.' . $userkey)
                ->set_type($this->get_type($userkey))
                ->set_is_sortable($this->is_sortable($userkey));

            if ($this->get_type($userkey) == constants::DB_TYPE_DATETIME) {
                $column->add_callback([format::class, 'userdate']);
            } else if ($userkey === 'country') {
                $column->add_callback([format::class, 'country']);
            } else if ($this->get_type($userkey) == constants::DB_TYPE_BOOLEAN) {
                $column->add_callback([format::class, 'checkbox_as_text']);
            } else if (array_search($userkey, $extrauserfields) !== false) {
                $column->add_callback('s');
            }

            if ($userkey === 'country') {
                $column
                    ->add_aggregation_callback('groupconcat', [format::class, 'countries_list'])
                    ->add_aggregation_callback('groupconcatdistinct', [format::class, 'countries_list']);
            }
            $columns[] = $column;
        }

        $columns = array_merge($columns, $this->add_user_custom_fields());

        if ($this->hastenantcolumns) {
            $columns[] = (new report_column(
                'tenant',
                new lang_string('tenant', 'tool_tenant'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->add_join($this->get_tenant_user_join())
                ->add_join($this->get_tenant_join())
                ->add_field($this->get_table_alias('tool_tenant').'.name', 'tenant')
                ->set_is_sortable(true)
                ->add_callback([format::class, 'format_string']);
        }

        return $columns;
    }

    /**
     * Formats a fullname or a list of comma-separated names to add pictures and/or links
     *
     * @param string $value
     * @param \stdClass $row
     * @param string $type one of: fullnamewithpicture, fullnamewithpicturelink, fullnamewithlink, picture
     * @return null|string|string[]
     */
    public static function fullname_replace_all($value, $row, $type) {
        return preg_replace_callback('#<span>([^<]*?)</span data-user="(\d*),(\d*),([^"]*?)">#',
            function($matches) use ($type) {
                return self::fullname_replace_one($type, $matches[1], $matches[2], $matches[3], $matches[4]);
            }, $value);
    }

    /**
     * Formats a fullname to add an picture and/or a link
     *
     * @param string $type one of: fullnamewithpicture, fullnamewithpicturelink, fullnamewithlink, picture
     * @param string $name
     * @param int $id
     * @param int $picture
     * @param string $email
     * @return string
     */
    protected static function fullname_replace_one($type, $name, $id, $picture, $email) {
        global $OUTPUT;
        if ($type === 'fullnamewithpicture' || $type === 'fullnamewithpicturelink' || $type === 'picture') {
            $user = (object)array_fill_keys(self::get_all_user_name_fields(), '');
            $user->id = (int)$id;
            $user->picture = (int)$picture;
            $user->email = $email;
            $user->imagealt = '';
            $name = $OUTPUT->user_picture($user, ['link' => false, 'alttext' => false]) . $name;
        }
        if ($type === 'fullnamewithpicturelink' || $type === 'fullnamewithlink') {
            $url = new \moodle_url('/user/profile.php', ['id' => $id]);
            return \html_writer::link($url, $name);
        }
        return $name;
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
            case 'suspended':
            case 'hascurrentjobs':
                return constants::DB_TYPE_BOOLEAN;
                break;
            case 'lastaccess':
            case 'timecreated':
                return constants::DB_TYPE_DATETIME;
                break;
            case 'description':
                return constants::DB_TYPE_LONGTEXT;
                break;
            default:
                return constants::DB_TYPE_TEXT;
                break;
        }
    }

    /**
     * Returns the name of the column that corresponds to the given identity field
     *
     * @param string $identityfieldname either field from the table 'user' or a shortname of a user profile field,
     *     in which case it starts with 'profile_field_'
     * @return string
     */
    public function resolve_column_name(string $identityfieldname): string {
        if (preg_match("/^profile_field_(.*)$/", $identityfieldname, $matches)) {
            foreach ($this->get_user_profile_fields() as $field) {
                if ($field->field->shortname === $matches[1]) {
                    return 'profilefield_' . $field->fieldid;
                }
            }
        }
        return $identityfieldname;
    }

    /**
     * Add the user profile fields
     */
    private function add_user_custom_fields(): array {
        global $DB;
        $profilefields = $this->get_user_profile_fields();
        $columns = [];
        foreach ($profilefields as $profilefield) {

            // If is not set to 'Visible to everyone' don't show it.
            if ((int)$profilefield->field->visible !== 2) {
                continue;
            }

            $alias = db::generate_alias();

            $column = (new report_column(
                $this->resolve_column_name('profile_field_'.$profilefield->field->shortname),
                new lang_string('customfieldcolumn', 'tool_reportbuilder',
                    format_string($profilefield->field->name, true, ['escape' => false])),
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
     * Retrieves the list of available user profile fields (wrapper for core method with caching)
     *
     * @return \profile_field_base[]
     */
    public function get_user_profile_fields() {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');

        if ($this->userprofilefields === null) {
            $this->userprofilefields = profile_get_user_fields_with_data(0);
        }
        return $this->userprofilefields;
    }

    /**
     * Returns the name of the filter/condition that corresponds to the given identity field
     *
     * @param string $identityfieldname either field from the table 'user' or a shortname of a user profile field,
     *     in which case it starts with 'profile_field_'
     * @return string
     */
    public function resolve_filter_name(string $identityfieldname): string {
        if (preg_match("/^profile_field_(.*)$/", $identityfieldname, $matches)) {
            foreach ($this->get_user_profile_fields() as $field) {
                if ($field->field->shortname === $matches[1]) {
                    return 'profilefield_' . $field->field->shortname . '_' . $field->fieldid;
                }
            }
        }
        return $identityfieldname;
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
        global $DB;
        $profilefields = $this->get_user_profile_fields();
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
                $this->resolve_filter_name('profile_field_'.$profilefield->field->shortname),
                new lang_string('customfieldcolumn', 'tool_reportbuilder',
                    format_string($profilefield->field->name, true, ['escape' => false])),
                $this->get_entity_name(),
                $field
            );

            // If dropdown load options in conditions form.
            if ($profilefield->field->datatype === 'menu') {
                $filter->set_options($profilefield->options);
            }

            $filter->add_joins($this->get_joins());
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
            case 'text':
            case 'textarea':
                return constants::DB_TYPE_LONGTEXT;
                break;
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
     * SQL join to add for the tenant column and tenant filter
     *
     * @return string
     */
    protected function get_tenant_user_join() {
        $ttu = $this->get_table_alias('tool_tenant_user');
        return "LEFT JOIN {tool_tenant_user} $ttu ON {$ttu}.userid = ".$this->get_table_alias('user').".id";
    }

    /**
     * SQL join to add for the tenant column (after adding get_tenant_user_join())
     *
     * @return string
     */
    protected function get_tenant_join() {
        $ttu = $this->get_table_alias('tool_tenant_user');
        $tt = $this->get_table_alias('tool_tenant');
        $u = $this->get_table_alias('user');
        $defaulttenantid = tenancy::get_default_tenant_id();
        return "LEFT JOIN {tool_tenant} $tt ON {$tt}.id = COALESCE({$ttu}.tenantid, $defaulttenantid) " .
            // When there is no record in {tool_tenant_user} for an existing user it means that user belongs
            // to the default tenant. At the same time when there is a left join with {user} table and there is no user,
            // check that user.id is not null to make sure we return null in the tenant column and not the "Default tenant".
            "AND {$u}.id IS NOT NULL";
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
            $optionscallback = [static::class, 'get_options_for_' . $field];
            if (is_callable($optionscallback)) {
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
                $this->get_table_alias('user') . '.' . $field
            ))
                ->add_joins($this->get_joins());
            if (is_callable($optionscallback)) {
                $filter->set_options_callback($optionscallback);
            }
            $conditions[] = $filter;
        }

        // Add fullname condition and filter. Filter is default, condition is not.
        $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        [$sqlname, $paramsname] = \tool_reportbuilder\db::sql_fullname($this->get_table_alias('user'), $viewfullnames);
        $fullnamefilter = (new report_filter(
            text::class,
            'fullname',
            new lang_string('fullname'),
            $this->get_entity_name(),
            $sqlname,
            $paramsname
        ))
            ->add_joins($this->get_joins());
        $conditions[] = $fullnamefilter;

        // Add has current jobs condition and filter.
        $currentjobsfilter = (new report_filter(
            checkbox::class,
            'hascurrentjobs',
            new lang_string('hascurrentjobs', 'tool_reportbuilder'),
            $this->get_entity_name(),
            \tool_organisation\helper::get_has_current_jobs_sql()
        ))
            ->add_joins($this->get_joins());
        $conditions[] = $currentjobsfilter;

        // Authentication filter.
        $authenticationfilter = (new report_filter(
            select::class,
            'auth',
            new lang_string('authmethod', 'tool_reportbuilder'),
            $this->get_entity_name(),
            $this->get_table_alias('user') . '.auth'
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(function () {
                $auths = \core_component::get_plugin_list('auth');
                $enabled = get_string('pluginenabled', 'core_plugin');
                $disabled = get_string('plugindisabled', 'core_plugin');
                $authoptions = array($enabled => array(), $disabled => array());
                foreach ($auths as $auth => $unused) {
                    if (is_enabled_auth($auth)) {
                        $authoptions[$enabled][$auth] = get_string('pluginname', "auth_{$auth}");
                    } else {
                        $authoptions[$disabled][$auth] = get_string('pluginname', "auth_{$auth}");
                    }
                }
                return $authoptions;
            });
        $conditions[] = $authenticationfilter;
        // Add user profile fields filters.
        $customfilters = $this->get_user_custom_filters($iscondition);

        // User job filters.
        $conditions = array_merge($conditions, $this->get_job_filters($iscondition), $customfilters);

        if ($iscondition) {
            // Audience view condition (i.e. which users can current user view in the report).
            $conditions[] = (new report_filter(
                audience_select::class,
                'audience',
                new lang_string('audienceselect', 'tool_organisation'),
                $this->get_entity_name(),
                $this->get_table_alias('user')
            ))->add_joins($this->get_joins());
        }

        // User tenant condition.
        if ($this->hastenantcolumns) {
            $ttu = $this->get_table_alias('tool_tenant_user');
            $conditions[]  = (new report_filter(
                user_tenant_filter::class,
                'tenant',
                new lang_string('tenant', 'tool_tenant'),
                $this->get_entity_name(),
                "{$ttu}.tenantid"
            ))
                ->add_joins($this->get_joins())
                ->add_join($this->get_tenant_user_join());
        }

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
            ->add_joins($this->get_joins())
            ->set_options_callback(function() {
                return organisation::get_all_positions_menu(
                    ['' => get_string('anyposition', 'tool_organisation')]);
            });

        $conditions[] = (new report_filter(
            department_select::class,
            'jobdepartment',
            new lang_string('hasjobdepartment', 'tool_organisation'),
            $this->get_entity_name(),
            'u.id'
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(function() {
                return organisation::get_all_departments_menu(
                    ['' => get_string('anydepartment', 'tool_organisation')]);
            });

        return $conditions;
    }

    /**
     * List of options for the field country
     *
     * @return array
     */
    public static function get_options_for_country() {
        $countries = array_map(function($name) {
            return shorten_text($name);
        }, get_string_manager()->get_list_of_countries());
        return ['' => ''] + $countries;
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
        return \tool_reportbuilder\db::sql_fullname($usertablealias, $override, $ascsv);
    }
}
