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
 * Class for column definition.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

use tool_reportbuilder\helper;
use tool_reportbuilder\local\helpers\aggregation;
use tool_reportbuilder\local\helpers\format;

defined('MOODLE_INTERNAL') || die();

/**
 * Class report_column
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_column {
    /** @var string $name */
    protected $name;
    /** @var \lang_string $visiblename */
    protected $visiblename;
    /** @var string $entity */
    protected $entity;
    /** @var string $defaultcolumnorder */
    protected $defaultcolumnorder;
    /** @var array $join */
    protected $joins = [];
    /** @var array */
    protected $fields = [];
    /** @var array  Custom fields for each type of aggregation*/
    protected $fieldsaggre = [];
    /** @var string SQL for group by */
    protected $groupbysql = '';
    /** @var array $params  */
    protected $params = [];
    /** @var bool $available  */
    protected $available = true;
    /** @var bool $default */
    protected $default = false;
    /** @var bool $defaultsortenabled */
    protected $defaultsortenabled = false;
    /** @var array $callbacks Functions or static class methods. */
    protected $callbacks = [];
    /** @var array $callbacks Functions or static class methods for each aggregation method. */
    protected $callbacksaggre = [];
    /** @var int $defaultsortorder */
    protected $defaultsortorder = null;
    /** @var int $defaultsortdirection */
    protected $defaultsortdirection = SORT_ASC;
    /** @var bool $issortable */
    protected $issortable = false;
    /** @var array $attributes */
    protected $attributes = [];
    /** @var array $disabledaggregations */
    protected $disabledaggregations = [];
    /**
     * String indicating the data type of this column when retrieved from the database.
     * Valid options are the same as allowed DB fields types and null.
     *
     * 'null' (default if parameter is not specified)

     * DB_TYPE_NUMBER
     * DB_TYPE_TEXT
     * DB_TYPE_DATETIME
     * DB_TYPE_TIMESTAMP
     * DB_TYPE_BOOLEAN
     * @var string $type The data type of column
     */
    protected $type = null;

    /**
     * Constructor for the report_column
     *
     * Each column must have a name that is unique inside the entity (for internal indexing only, not used in the UI).
     *
     * For better readability use chainable methods, for example:
     *
     * $report->add_column(
     *    (new report_column('name', new lang_string('name'), 'user'))
     *    ->add_join('left join {table} t on t.id = p.tableid')
     *    ->add_field('t.name')
     *    ->set_is_default(true)
     *    ->add_callback([format::class, 'format_string']));
     *
     * @param string $name - name unique inside the entity (for internal indexing only, not used in the UI)
     * @param \lang_string $visiblename - the name and default title of the column
     * @param string $entity - name of the entity that is used for groupping the columns. See also report_base::add_entity()
     */
    public function __construct(string $name, ?\lang_string $visiblename, string $entity) {
        $this->name = $name;
        $this->visiblename = $visiblename;
        $this->entity = $entity;
    }

    /**
     * Get the column name.
     *
     * @return mixed
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get the visible name.
     *
     * @return string
     */
    public function get_visiblename() : string {
        return (string)$this->visiblename;
    }

    /**
     * Set another visiblename instead of the default.
     *
     * @param \lang_string|null $visiblename
     * @return report_column
     */
    public function set_visiblename(?\lang_string $visiblename) {
        $this->visiblename = $visiblename;
        return $this;
    }

    /**
     * Get entity.
     *
     * @return mixed
     */
    public function get_entity() {
        return $this->entity;
    }

    /**
     * If the column is default display it in this order
     *
     * @return int|null
     */
    public function get_default_column_order() : ?int {
        return $this->defaultcolumnorder;
    }

    /**
     * Get joins.
     *
     * @return array
     */
    public function get_joins() : array {
        return $this->joins;
    }

    /**
     * Set join.
     *
     * @param string $join
     * @return report_column
     */
    public function add_join(string $join) : report_column {
        $this->joins[] = strtolower(trim($join));
        return $this;
    }

    /**
     * Set join.
     *
     * @deprecated use add_join
     * @param string $join
     * @return report_column
     */
    public function set_join(?string $join) : report_column {
        if ($join) {
            $this->add_join($join);
        }
        return $this;
    }

    /**
     * Get sql.
     * @param string $aggr The aggregation active.
     * @param int $dbtype Type of field.
     * @param int $columnkey index of the column in the table (to generate unique fields prefixes)
     * @return mixed
     */
    public function get_fields_sql(?string $aggr, ?int $dbtype, int $columnkey) {
        return join(', ', array_map(function($f) use ($aggr, $dbtype) {
            $field = aggregation::get_sql($aggr, $f[0], $dbtype);
            return "$field AS {$f[1]}";
        }, $this->get_fields($aggr, $columnkey)));
    }

    /**
     * Returns SQL for sorting
     *
     * @param int $direction Sort direction SORT_ASC/SORT_DESC
     * @param string $aggr If the column has some aggregation applied
     * @param int $columnkey index of the column in the table (to generate unique fields prefixes)
     * @return string
     */
    public function get_sort_sql(int $direction, ?string $aggr, int $columnkey) {
        global $DB;
        $shortentext = ($this->type == constants::DB_TYPE_LONGTEXT && $DB->get_dbfamily() === 'oracle');
        $sortsql = array_map(function($f) use ($direction, $aggr, $shortentext) {
            global $DB;
            $field = $shortentext ? $DB->sql_compare_text($f[1]) : $f[1];
            return $field . ' ' . ($direction == SORT_ASC ? 'ASC' : 'DESC');
        }, $this->get_fields($aggr, $columnkey));
        return join(', ', $sortsql);
    }

    /**
     * Get the columns fields and their aliases
     *
     * Example:
     *  $aggre=='' : ['shortname' => ['c.shortname', 'c1_shortname'], 'fullname' => ['c.fullname', 'c1_fullname']]
     *  $aggre=='count' : ['shortname' => ['c.id', 'c1_shortname']]
     *  $aggre=='groupconcat' : ['shortname' => ['CONCAT(c.shortname, \' \', c.fullname)', 'c1_shortname']]
     *
     * If aggregation is specified - always return
     *
     * @param null|string $aggre
     * @param int $columnkey index of the column in the table (to generate unique fields prefixes)
     * @return array|mixed
     */
    private function get_fields(?string $aggre, int $columnkey) {
        global $DB;
        $firstkey = key($this->fields);
        if ($aggre === 'unique' && !isset($this->fieldsaggre[$aggre])) {
            $fields = $this->fields;
        } else if ($aggre) {
            // If aggregation is used, only one field is allowed (unless it's 'unique').
            $sql = isset($this->fieldsaggre[$aggre]) ? $this->fieldsaggre[$aggre] : $this->fields[$firstkey];
            $fields = [$firstkey => $sql];
        } else {
            $fields = $this->fields;
        }
        if ($aggre === 'unique' && $this->type == constants::DB_TYPE_LONGTEXT &&
                $DB->get_dbfamily() === 'oracle' && empty($this->groupbysql)) {
            // Oracle can not perform "SELECT longtext FROM ... GROUP BY longtext"
            // We need to convert it to char.
            $fields[$firstkey] = $DB->sql_compare_text($fields[$firstkey], 4000);
        }
        foreach ($fields as $alias => $sql) {
            $fields[$alias] = [$sql, substr("c{$columnkey}_$alias", 0, 30)];
        }

        // If the field has parameters add a suffix for this columnkey (so that one column can be added multiple times).
        if ($this->params) {
            foreach ($fields as $idx => $field) {
                $f = $field[0];
                foreach (array_keys($this->params) as $param) {
                    $fields[$idx][0] = preg_replace('/:' . preg_quote($param, '\b/') . '/',
                        ':' . $param . '__' . $columnkey,
                        $fields[$idx][0]);
                }
            }
        }
        return $fields;
    }

    /**
     * Get SQL named parameters
     *
     * @param int $columnkey index of the column in the table
     * @return array
     */
    public function get_params($columnkey) : array {
        $params = $this->params;
        // Add a suffix for this columnkey (so that one column can be added multiple times).
        foreach ($this->params as $key => $value) {
            $params[$key . '__' . $columnkey] = $value;
        }
        return $params;
    }

    /**
     * Adds a field to be queried from the database that is necessary for this column
     *
     * Multiple fields can be needed for one column, this method may be called several times. Field aliases
     * must be unique inside one column, but there will be no conflicts if the same aliases are used in other columns
     * in the same report.
     *
     * Chainable
     *
     * @param string $sql SQL query, this may be a simple "tablealias.fieldname" or a complex sub-query that returns only one field
     * @param string $alias Alias for
     * @param array $params
     * @return report_column
     */
    public function add_field(string $sql, ?string $alias = null, array $params = []) : report_column {
        $sql = trim($sql);
        if (preg_match('/ \w+$/', $sql) && !$alias) {
            // SQL ends with a space and a word - this looks like an alias was passed as part of the field.
            throw new \coding_exception('Column alias must be passed as a separate argument: '. $sql);
        }
        if (!strlen($alias) && preg_match('/^\w+\\.(\w+)$/', $sql, $matches)) {
            // Simple query "tablename.fieldname", assume alias = fieldname.
            $alias = $matches[1];
        }
        if (!strlen($alias) && preg_match('/^(\w+)$/', $sql)) {
            // Simple query "fieldname", assume alias = fieldname.
            $alias = $sql;
        }
        if (!strlen($alias)) {
            throw new \coding_exception('Complex columns must have an alias');
        }
        $this->fields[$alias] = $sql;
        $this->params += $params;
        return $this;
    }

    /**
     * Add a list of comma-separated fields
     *
     * Chainable
     *
     * @param string $sql
     * @return report_column
     */
    public function add_fields(string $sql) : report_column {
        $chunks = preg_split('/\s*,\s*/', trim($sql));
        foreach ($chunks as $chunk) {
            $ch2 = preg_split('/\s+/', $chunk);
            if (count($ch2) == 2 || (count($ch2) == 3 && strtolower($ch2[1]) === 'as')) {
                // Query "fieldname as alias" (or "fieldname alias"), we can extract field name and alias.
                $sql = reset($ch2);
                $alias = array_pop($ch2);
                $this->add_field($sql, $alias);
            } else {
                $this->add_field($chunk);
            }
        }
        return $this;
    }

    /**
     * Adds a column callback. There can be multiple callbacks, they will be applied one after another.
     *
     * The value of the first field is passed as the first argument to the callback.
     * All fields values are passed as the stdClass object as the first argument to the callback.
     *
     * Chainable
     *
     * @param callable $callable function that takes arguments ($value, \stdClass $row, $additionalarguments)
     * @param mixed $additionalarguments will be passed as a third parameter to the callback
     * @return report_column
     */
    public function add_callback(callable $callable, $additionalarguments = null) : report_column {
        $this->callbacks[] = [$callable, $additionalarguments];
        return $this;
    }

    /**
     * Adds a column callback. There can be multiple callbacks, they will be applied one after another.
     *
     * The value of the first field is passed as the first argument to the callback.
     * All fields values are passed as the stdClass object as the first argument to the callback.
     *
     * Chainable
     *
     * @param string $aggregation Aggregation type
     * @param callable $callable function that takes arguments ($value, \stdClass $row, $additionalarguments)
     * @param mixed $additionalarguments will be passed as a third parameter to the callback
     * @return report_column
     */
    public function add_aggregation_callback(string $aggregation, callable $callable, $additionalarguments = null) : report_column {
        if (!aggregation::is_valid($aggregation)) {
            throw new \coding_exception("Invalid aggregation '$aggregation'.");
        }

        $this->callbacksaggre[$aggregation][] = [$callable, $additionalarguments];
        return $this;
    }

    /**
     * Get the callbacks definition.
     *
     * @param string $aggre Aggregation name
     * @return array
     */
    public function get_callbacks(string $aggre = null) : array {
        // First check if a specific aggregation callback is set.
        if ($aggre && array_key_exists($aggre, $this->callbacksaggre) && !empty($this->callbacksaggre[$aggre])) {
            return $this->callbacksaggre[$aggre];
        }
        // For aggregations 'unique', 'min' and 'max' use the same callback as for non-aggregated field,
        // otherwise return "no callback".
        if (!$aggre || $aggre === 'unique' || $aggre === 'min' || $aggre === 'max' || $aggre === 'sum') {
            return $this->callbacks;
        }
        // If callback for 'percent' is not specified, use default.
        if ($aggre === 'percent') {
            return [[[format::class, 'percent'], []]];
        }
        // If groupconcat callback is not specified and this is a boolean field - do some magic.
        if ($this->callbacks &&
                ($this->get_type() == constants::DB_TYPE_BOOLEAN) &&
                ($aggre === 'groupconcat' || $aggre === 'groupconcatdistinct')) {
            return [[[self::class, 'apply_callbacks_to_list'], $this->callbacks]];
        }
        return [];
    }

    /**
     * Splits the value by separator, applies callbacks and joins back
     *
     * @param mixed $value result of the 'groupconcat' or 'groupconcatdistinct' aggregation
     * @param \stdClass $obj
     * @param callable[] $callbacks callbacks that the original column had
     * @return null|string
     */
    public static function apply_callbacks_to_list($value, \stdClass $obj, array $callbacks) {
        if (!strlen($value)) {
            return $value;
        }
        $separator = helper::get_list_separator();
        $values = preg_split('/' . preg_quote($separator, '/') . '/', $value);
        foreach ($values as $idx => $v) {
            foreach ($callbacks as $callback) {
                $v = call_user_func_array($callback[0], [$v, $obj, $callback[1]]);
            }
            $values[$idx] = $v;
        }
        return join($separator, $values);
    }

    /**
     * Get the identifier for this filter that is unique for the datasource
     *
     * @return mixed
     */
    public function get_unique_identifier() : string {
        return $this->get_entity() . ':' . $this->get_name();
    }

    /**
     * Get if is a default column.
     *
     * @return bool
     */
    public function is_default() : bool {
        return $this->default;
    }

    /**
     * Indicate that this column should be a default column for the report
     *
     * System reports must have default columns
     *
     * @param bool $default
     * @param int|null $defaultcolumnorder
     * @return report_column
     */
    public function set_is_default(bool $default, ?int $defaultcolumnorder = null) : report_column {
        $this->default = $default;
        if ($defaultcolumnorder !== null) {
            $this->defaultcolumnorder = $defaultcolumnorder;
        }
        return $this;
    }

    /**
     * Is it possible to sort by this column
     *
     * @param string $aggre Aggregation type
     * @return bool
     */
    public function get_is_sortable($aggre = '') : bool {
        if ($aggre) {
            return aggregation::is_sortable($aggre, $this->issortable);
        }
        return $this->issortable;
    }

    /**
     * If the column is sortable, should it be sorted by it by default
     *
     * @return bool
     */
    public function get_default_sortenabled() {
        return $this->defaultsortenabled;
    }

    /**
     * If the column is sortable and sort is enabled on this column by default what is the order of sorting
     * @return int
     */
    public function get_default_sortorder() {
        return $this->defaultsortorder;
    }

    /**
     * If the column is sortable and should be sorted by default, sort with this sort direction
     *
     * @return int SORT_ASC or SORT_DESC
     */
    public function get_default_sortdirection() {
        return $this->defaultsortdirection;
    }

    /**
     * Sets the column as sortable and whether the sorting is enabled by default
     *
     * @param bool $issortable whether the column is sortable. "TEXT" type columns (multiline text) can not be sortable.
     *     Some fields such as 'phone' can not be sortable since it does not make sense
     * @param bool $defaultsortenabled If the column is sortable, should it be sorted by default
     * @param int|null $defaultsortorder If the column is sorted by default what should be the order or sorting
     *     (for example "ORDER BY firstname, lastname", sortorder of 'firstname' is 0 and of 'lastname' is 1)
     * @param int|null $defaultsortdirection The default sort direction - SORT_ASC or SORT_DESC
     * @return report_column
     */
    public function set_is_sortable(bool $issortable, bool $defaultsortenabled = false, ?int $defaultsortorder = null,
                                    ?int $defaultsortdirection = SORT_ASC) : report_column {
        $this->issortable = $issortable;
        $this->defaultsortenabled = $defaultsortenabled;
        $this->defaultsortorder = $defaultsortorder;
        $this->defaultsortdirection = ($defaultsortdirection == SORT_DESC) ? SORT_DESC : SORT_ASC;
        return $this;
    }

    /**
     * Enable sorting on a column, by default disabled.
     *
     * @deprecated use set_is_sortable
     *
     * @param bool $sortenabled
     * @return report_column
     */
    public function set_is_sort_enabled(bool $sortenabled) : report_column {
        // TODO SP-422 create PR to other plugins that use this method and then remove this function from here.
        $this->set_is_sortable($sortenabled, $sortenabled);
        return $this;
    }

    /**
     * Extracts fields values from the SQL results row
     *
     * @param \stdClass $row
     * @param null|string $aggre
     * @param int $columnkey index of the column in the table (to generate unique fields prefixes)
     * @return array
     */
    public function get_fields_values(\stdClass $row, ?string $aggre, int $columnkey) : array {
        $sqlfields = $this->get_fields($aggre, $columnkey);
        $fields = [];
        foreach ($sqlfields as $alias => $field) {
            $fields[$alias] = $row->{$field[1]};
        }
        return $fields;
    }

    /**
     * Get is column is available to the current user or not
     *
     * @return bool
     */
    public function get_is_available() : bool {
        return $this->available;
    }

    /**
     * When the column is conditionally available based on current user's permissions
     *
     * Note that the column will still be displayed to the report creator.
     * Usually not recommended to use, valid exception is a "Tenant" column.
     *
     * @param bool $available
     * @return report_column
     */
    public function set_is_available(bool $available) : report_column {
        $this->available = $available;
        return $this;
    }

    /**
     * Set the type of the column. Needed for the aggregation functions.
     *
     * @param null|string $dbtype
     * @return report_column
     * @throws \coding_exception
     */
    public function set_type(?string $dbtype) : report_column {
        if (!in_array($dbtype, [
            null,
            constants::DB_TYPE_NUMBER,
            constants::DB_TYPE_TEXT,
            constants::DB_TYPE_DATETIME,
            constants::DB_TYPE_TIMESTAMP,
            constants::DB_TYPE_BOOLEAN,
            constants::DB_TYPE_LONGTEXT,
        ] )) {
            throw new \coding_exception('Not allowed DB field type');
        }
        $this->type = $dbtype;
        return $this;
    }

    /**
     * Get the type of column.
     *
     * @return null|string
     */
    public function get_type() : ?string {
        return $this->type;
    }

    /**
     * Get all fields alias.
     *
     * @param int $columnkey index of the column in the table (to generate unique fields prefixes)
     * @return string
     */
    public function get_fields_for_group_by(int $columnkey) : string {
        global $DB;
        if (!empty($this->groupbysql)) {
            return $this->groupbysql;
        }

        $usealias = !in_array($DB->get_dbfamily(), ['mssql', 'oracle']);
        $rv = [];
        foreach ($this->get_fields('unique', $columnkey) as $key => $f) {
            $rv[$key] = $usealias ? $f[1] : $f[0];
        }
        return join(', ', $rv);
    }

    /**
     * Adds a field to be queried from the database when used in aggregation if different from the default value
     *
     * Only one field (or function) can be used for one aggregation method. By default the first field from the
     * column fields list is used.
     *
     * Chainable
     *
     * @param string $aggregation Aggregation type
     * @param string $sql SQL query, this may be a simple "tablealias.fieldname" or a complex sub-query that returns only one field
     * @return report_column
     * @throws \coding_exception
     */
    public function add_aggregation_fields(string $aggregation, string $sql) : report_column {
        if (!aggregation::is_valid($aggregation)) {
            throw new \coding_exception("Invalid aggregation '$aggregation'.");
        }

        // TODO: check if a column has multiple values and create an aggregation for this.
        $sql = trim($sql);
        $this->fieldsaggre[$aggregation] = $sql;

        return $this;
    }

    /**
     * Allows to set a simplified expression if this column is groupped (used in MsSQL and Oracle only)
     *
     * For example:
     *   $column
     *     ->add_field('some_complicated_function_of(a.id, a.name)', 'fld')
     *     ->set_groupby_sql('a.id, a.name');
     *
     * @param string $sql
     * @return report_column
     */
    public function set_groupby_sql(string $sql) : report_column {
        $this->groupbysql = $sql;
        return $this;
    }

    /**
     * Add column attributes (data-, class, etc.) that will be included in HTML when column is displayed
     *
     * @param array $attributes
     * @return report_column
     */
    public function add_attributes(array $attributes) : report_column {
        $this->attributes = $attributes + $this->attributes;
        return $this;
    }

    /**
     * Returns the column HTML attributes
     */
    public function get_attributes() {
        return $this->attributes;
    }

    /**
     * Helps to validate the report.
     */
    public function validate() {
        // If field has parameters, the groupby must be specified.
        if ($this->params && empty($this->groupbysql)) {
            throw new \coding_exception('Column ' . $this->get_unique_identifier() . ' must define groupby sql');
        }
    }

    /**
     * Disable aggregation type for this column.
     *
     * Normally allowed aggregation types are determined by the column data type (see
     * is_compatible method in aggregation_base). This method allows to disable any
     * of allowed aggregation types for this column.
     *
     * @param string $aggregation Aggregation type to disable.
     * @return report_column
     */
    public function disable_aggregation(string $aggregation) : report_column {
        if (!aggregation::is_valid($aggregation)) {
            throw new \coding_exception("Invalid aggregation '$aggregation'.");
        }
        if (!in_array($aggregation, $this->disabledaggregations)) {
            $this->disabledaggregations[] = $aggregation;
        }
        return $this;
    }

    /**
     * Return aggregation types disabled for this column.
     *
     * @return array
     */
    public function get_disabled_aggregations() {
        return $this->disabledaggregations;
    }
}