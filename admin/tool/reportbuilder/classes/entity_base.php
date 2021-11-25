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
 * Class entity_base
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Entity is a typical collection of columns/filters/conditions that can be re-used in the reports
 *
 * For example, user fields, course fields, etc.
 *
 * To create a new entity extend this class and override all abstract methods. No other methods
 * need to be overridden.
 *
 * To use the entity in the datasource or system report call:
 *
 * $this->add_entity((new entityclassname())
 *   ->set_entity_name('user')
 *   ->set_entity_title(new \lang_string('user'))
 *   ->set_table_alias('user', 'u')
 *   ->add_join('LEFT JOIN {user} u ON u.id=p.userid')
 *   ->add_joins([])
 *   ->set_exclude_fields(['address'])
 *   ->set_include_only_fields(['fullname*'])
 * );
 *
 * Some entities may add more methods. Remember to call all of these methods BEFORE adding an entity,
 * otherwise they will have no effect.
 *
 * All calls are optional and only needed if default entity attributes need to be changed.
 * For example, name and title should be overridden if there are several entities added to the report.
 * Joins should be added if they are not included in the main query. In this case joins will be added to the report
 * only if columns or filters/conditions from this entity are selected.
 * Exclude/include-only fields can be specified if needed.
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class entity_base {
    /** @var \lang_string  */
    private $entitytitle = null;
    /** @var string  */
    private $entityname = null;
    /** @var array */
    private $tablealiases = [];
    /** @var null|array */
    private $excludefields = null;
    /** @var null|array */
    private $includeonlyfields = null;
    /** @var array */
    private $joins = [];
    /** @var array */
    private $columns = [];
    /** @var array */
    private $filters = [];
    /** @var array */
    private $conditions = [];

    /**
     * Database tables that this entity uses and their default aliases
     *
     * Must be overridden by the entity to list all database tables that it expects to be present in the main
     * SQL or in JOINs added to this entity.
     *
     * The array keys will usually be the same as database table names however they don't have to be.
     * The array values are the aliases that the respective columns have in the query.
     *
     * @return array
     */
    abstract protected function get_default_table_aliases(): array;

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return \lang_string
     */
    abstract protected function get_default_entity_title(): \lang_string;

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * This name must be unique in the datasource/system report. The datasource can call set_entity_name() to
     * override it (for example, when several instances of the same entity are added to the same datasource).
     *
     * @return string
     */
    abstract protected function get_default_entity_name(): string;

    /**
     * Executed when entity is added to the datasource or system report
     *
     * This is where entity defines all its columns, filters and conditions by calling:
     * - add_column()
     * - add_filter()
     * - add_condition()
     * - add_condition_and_filter()
     */
    abstract public function add_to_report();

    /**
     * Name used for this entity, used only internally to build unique keys
     * @return string
     */
    public function get_entity_name() : string {
        if ($this->entityname === null) {
            return $this->get_default_entity_name();
        }
        return $this->entityname;
    }

    /**
     * Name for this entity as displayed to the user in the group names for columns, filters and conditions
     * @return \lang_string
     */
    public function get_entity_title() : \lang_string {
        if ($this->entitytitle === null) {
            return $this->get_default_entity_title();
        }
        return $this->entitytitle;
    }

    /**
     * Set machine-readable entity name
     *
     * @param string $name
     * @return entity_base
     */
    public function set_entity_name(string $name): self {
        if ($name !== clean_param($name, PARAM_ALPHANUMEXT)) {
            $this->debugging('Entity name should only use alphanumeric character, underscore and/or dash');
        }
        $this->entityname = $name;
        return $this;
    }

    /**
     * Set entity title that is displayed as a header for the columns/filters/conditions
     *
     * @param \lang_string $title
     * @return entity_base
     */
    public function set_entity_title(\lang_string $title): self {
        $this->entitytitle = $title;
        return $this;
    }

    /**
     * Allows to override the default aliases for the db tables used in the queries
     *
     * All tables must be defined either in the datasource query or in the joins (see add_join() and add_joins())
     *
     * @param string $tablename
     * @param string $alias
     * @return entity_base
     */
    public function set_table_alias(string $tablename, string $alias): self {
        if (!array_key_exists($tablename, $this->get_default_table_aliases())) {
            $this->debugging('Table ' . s($tablename) . ' is not valid');
        }
        $this->tablealiases[$tablename] = $alias;
        return $this;
    }

    /**
     * Returns an alias used in the queries for a given table
     *
     * @param string $tablename
     * @return mixed|string
     */
    public function get_table_alias(string $tablename) {
        if (array_key_exists($tablename, $this->tablealiases)) {
            return $this->tablealiases[$tablename];
        }
        if (!array_key_exists($tablename, $this->get_default_table_aliases())) {
            $this->debugging('Table ' . s($tablename) . ' is not valid');
            return $tablename;
        }
        return $this->get_default_table_aliases()[$tablename];
    }

    /**
     * JOIN clause necessary for columns and filters in this entity
     *
     * @param string $join
     * @return entity_base
     */
    public function add_join(string $join): self {
        if ($join) {
            $this->joins[] = $join;
        }
        return $this;
    }

    /**
     * JOIN clauses necessary for columns and filters in this entity
     *
     * @param array $joins
     * @return entity_base
     */
    public function add_joins(array $joins): self {
        foreach ($joins as $join) {
            $this->add_join($join);
        }
        return $this;
    }

    /**
     * Get JOINs used in this entity
     *
     * @return array
     */
    protected function get_joins() {
        return $this->joins;
    }

    /**
     * List the fields that should be excluded, respective columns and filters will be excluded
     *
     * @param array $excludefields list of vaues or simple patterns (only * is allowed) to match a field against
     * @return entity_base
     */
    public function set_exclude_fields(array $excludefields): self {
        if (empty($excludefields)) {
            return $this;
        }
        if ($this->includeonlyfields !== null) {
            $this->debugging('Either excludefields or includeonlyfields can be set but not both');
            $this->includeonlyfields = null;
        }
        $this->excludefields = $excludefields;
        return $this;
    }

    /**
     * List the only fields that should be included, only columns and filters related to this fields will be returned
     *
     * @param array $includeonlyfields list of vaues or simple patterns (only * is allowed) to match a field against
     * @return entity_base
     */
    public function set_include_only_fields(array $includeonlyfields): self {
        if ($this->excludefields !== null) {
            $this->debugging('Either excludefields or includeonlyfields can be set but not both');
            $this->excludefields = null;
        }
        $this->includeonlyfields = $includeonlyfields;
        return $this;
    }

    /**
     * Field matches a simple pattern
     *
     * @param string $fieldname
     * @param string $pattern a string that may contain *
     * @return bool|false|int
     */
    private function field_matches(string $fieldname, string $pattern) {
        if ($fieldname === $pattern) {
            return true;
        }
        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace('*', '.*', $pattern) . '$/';
            return preg_match($regex, $fieldname);
        }
        return false;
    }

    /**
     * Checks if a field should be included
     *
     * @param string $fieldname
     * @return bool
     */
    protected function should_include_field(string $fieldname): bool {
        if ($this->excludefields) {
            foreach ($this->excludefields as $pattern) {
                if ($this->field_matches($fieldname, $pattern)) {
                    return false;
                }
            }
        }
        if ($this->includeonlyfields) {
            foreach ($this->includeonlyfields as $pattern) {
                if ($this->field_matches($fieldname, $pattern)) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Add a column
     *
     * @param report_column $column
     * @param null|string $fieldname name of the field to match against excluded/included fields (if different from column name)
     * @return entity_base
     */
    protected function add_column(report_column $column, ?string $fieldname = null): self {
        $fieldname = $fieldname ?: $column->get_name();
        if ($this->should_include_field($fieldname)) {
            $this->columns[] = $column;
        }
        return $this;
    }

    /**
     * Add a condition
     *
     * @param report_filter $condition
     * @param null|string $fieldname name of the field to match against excluded/included fields
     *        (if different from condition name)
     * @return entity_base
     */
    protected function add_condition(report_filter $condition, ?string $fieldname = null): self {
        $fieldname = $fieldname ?: $condition->get_name();
        if ($this->should_include_field($fieldname)) {
            $this->conditions[] = $condition;
        }
        return $this;
    }

    /**
     * Add a filter
     *
     * @param report_filter $filter
     * @param null|string $fieldname name of the field to match against excluded/included fields (if different from filter name)
     * @return entity_base
     */
    protected function add_filter(report_filter $filter, ?string $fieldname = null): self {
        $fieldname = $fieldname ?: $filter->get_name();
        if ($this->should_include_field($fieldname)) {
            $this->filters[] = $filter;
        }
        return $this;
    }

    /**
     * Add an object that can be used as both filter and condition
     *
     * @param report_filter $condition
     * @param null|string $fieldname name of the field to match against excluded/included fields (if different from filter name)
     * @return entity_base
     */
    protected function add_condition_and_filter(report_filter $condition, ?string $fieldname = null): self {
        $fieldname = $fieldname ?: $condition->get_name();
        if ($this->should_include_field($fieldname)) {
            $this->conditions[] = $condition;
            $this->filters[] = clone $condition;
        }
        return $this;
    }

    /**
     * Returns list of all columns that need to be added to the datasource
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        return $this->columns;
    }

    /**
     * Returns list of all conditions that need to be added to the datasource
     *
     * @return report_filter[]
     */
    public function get_conditions() : array {
        return $this->conditions;
    }

    /**
     * Returns list of all filters that need to be added to the datasource
     *
     * @return report_filter[]
     */
    public function get_filters() : array {
        return $this->filters;
    }

    /**
     * Debugging messages are suppressed in AJAX requests. Instead throw an exception
     *
     * @param string $msg
     * @throws \coding_exception
     */
    protected function debugging(string $msg) {
        global $CFG;
        if ($CFG->debugdeveloper && defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            throw new \coding_exception($msg);
        } else {
            debugging($msg, DEBUG_DEVELOPER);
        }
    }
}
