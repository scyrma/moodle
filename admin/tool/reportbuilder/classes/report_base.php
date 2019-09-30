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
 * Class report_course_completion
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

use tool_organisation\organisation;
use tool_reportbuilder\local\helpers\columns as columns_helper;
use tool_reportbuilder\output\report_exporter;
use tool_reportbuilder\report_filter;
use tool_tenant\tenancy;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die;

/**
 * Class report_base, base class for all datasources and system reports.
 *
 * Do not extend directly, extend either class \tool_reportbuilder\datasource or
 * \tool_reportbuilder\system_report
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class report_base {
    /** @var int  */
    const DEFAULT_PAGESIZE = 10;

    /** @var reportbuilder $reportpersistent */
    private $reportpersistent;
    /** @var string $maintable */
    private $maintable;
    /** @var string $maintablealias */
    private $maintablealias;
    /** @var array $sqlfilters */
    private $sqlfilters = [];
    /** @var array $sqlparams */
    private $sqlparams = [];
    /** @var array $joins Joins definitions */
    private $joins = [];
    /** @var int $page */
    private $page;
    /** @var report_column[] */
    private $columns = array();
    /** @var report_filter[]*/
    private $filters = array();
    /** @var report_filter[]*/
    private $conditions = array();
    /** @var report_action[]*/
    private $actions = array();
    /** @var bool $showactionsheader Set if the actions header need to be showed or not.  */
    private $showactionsheader = true;
    /** @var bool $isdownloadable Set if the report is $downloadable */
    private $isdownloadable = true;
    /** @var array $parameters parameters of the report */
    private $parameters = [];
    /** @var \lang_string[] */
    private $entitytitles = [];
    /** @var array */
    private $attributes = [];

    /**
     * report_base constructor.
     *
     * @param reportbuilder $persistent
     * @param array $parameters additional parameters, simple keys with simple values
     * @param int $page
     *
     * @throws \coding_exception
     */
    public final function __construct(reportbuilder $persistent, array $parameters = [], int $page = 0) {
        $this->reportpersistent = $persistent;
        $this->page = $page;
        $this->parameters = $parameters;
        $this->initialise();
        $this->validate();
        columns_helper::fix_columns_default_columnorder($this->columns);
        columns_helper::fix_columns_default_sortorder($this->columns);
    }

    /**
     * Returns persistent class used when initialising this report
     *
     * @return reportbuilder
     */
    public final function get_persistent(): reportbuilder {
        return $this->reportpersistent;
    }
    /**
     * Initialise report
     *
     * Every report must override and specify which columns, conditions, filters, etc should be present in the report.
     *
     * To set the base query use:
     * - set_main_table()
     * - add_base_condition_simple()
     * - add_base_condition_sql()
     * - add_join()
     * - add_base_fields() (for system reports only)
     *
     * To add available columns, filters and conditions use:
     * - add_entity()
     * - add_column()
     * - add_condition()
     * - add_filter()
     *
     * To add actions that will be displayed in automatic 'Actions' column (for system reports only):
     * - add_action()
     * - set_show_actions_header()
     */
    protected abstract function initialise();

    /**
     * Helps to validate the report.
     */
    protected function validate() {
        if (empty($this->maintable)) {
            throw new \coding_exception('Report must define main table by calling $this->set_main_table()');
        }
        if (empty($this->columns)) {
            throw new \coding_exception('Report must define at least one column by calling $this->add_column()');
        }
        if ($this instanceof system_report) {
            $defaultcolumns = array_filter($this->columns, function (report_column $c) {
                return $c->is_default();
            });
            if (empty($defaultcolumns)) {
                throw new \coding_exception('System report must define at least one default column');
            }
        }

        // Will be great to see more validation here.
        foreach ($this->columns as $column) {
            $column->validate();
        }
    }

    /**
     * Get the report uniqid.
     *
     * @return mixed|string
     */
    public final function get_report_uniqid() {
        return $this->reportpersistent->get('idnumber');
    }

    /**
     * Export the report data for the template.
     *
     * @param \renderer_base $output
     * @param bool $editon
     * @param bool $tableonly
     *
     * @return \stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public final function export($output, $editon, bool $tableonly = false) {
        // TODO SP-422 consider moving this function outside of this class to keep it more lightweight.
        // Reduce related data. Maybe even move reportpersistent outside of this class.
        $exporter = new report_exporter($this->reportpersistent,
            [
                'source'  => $this,
                'page' => $this->page,
                'editon' => (bool)$editon,
                'tableonly' => $tableonly
            ]
        );
        $data = $exporter->export($output);
        $data->parameters = json_encode($this->parameters);

        return $data;
    }

    /**
     * Add entity to the report that will be used for groupping columns, filters and conditions
     *
     * @param string $entity
     * @param \lang_string $title
     */
    protected final function annotate_entity(string $entity, \lang_string $title) {
        if (!strlen($entity) || $entity !== clean_param($entity, PARAM_ALPHANUMEXT) || $entity !== strtolower($entity)) {
            throw new \coding_exception('Entity name must be specified and contain only lowercase letters, digits and _ symbol');
        }
        $this->entitytitles[$entity] = $title;
    }

    /**
     * Adds all the columns, filter and fields used in an entity (i.e. user, course, tool_program, etc.)
     *
     * @param entity_base|null $entity
     */
    protected final function add_entity(entity_base $entity) {
        $this->annotate_entity($entity->get_entity_name(), $entity->get_entity_title());

        foreach ($entity->get_columns() as $column) {
            $this->add_column($column);
        }

        foreach ($entity->get_filters() as $filter) {
            $this->add_filter($filter);
        }

        foreach ($entity->get_conditions() as $condition) {
            $this->add_condition($condition);
        }
    }

    /**
     * List of entities used in the report
     * @return \lang_string[]
     */
    public final function get_entities() : array {
        return $this->entitytitles;
    }

    /**
     * Get the columns select. Remove the columns in use
     *
     * @param array $columnsinuse The columns already added to the report
     * @return array
     * @throws \coding_exception
     */
    public final function get_columns_select(array $columnsinuse) : array {
        // TODO SP-422 move away from this class. Possibly create \tool_reportbuilder\local\helpers\columns ?

        $select = array();

        $columnsinusekeys = array_map(function($e) {
            return $e->key;
        }, $columnsinuse);

        foreach ($this->get_entities() as $entity => $entitytitle) {
            $values = array();
            foreach ($this->get_columns() as $column) {
                if ($column->get_entity() != $entity) {
                    continue;
                }
                $columnkey = $column->get_unique_identifier();
                $values[] = array(
                    'value'       => $columnkey,
                    'visiblename' => $column->get_visiblename(),
                );
            }

            if ($values) {
                $select[] = array(
                    'optiongroup' => array(
                        'text'   => $entitytitle->out(),
                        'key'    => $entity,
                        'values' => $values
                    )
                );
            }
        }

        return $select;
    }

    /**
     * Get page size
     *
     * @return int
     */
    public final function get_pagesize() : int {
        if (!empty($this->parameters['pagesize']) && is_int($this->parameters['pagesize'])) {
            return (int)$this->parameters['pagesize'];
        }
        return static::DEFAULT_PAGESIZE;
    }

    /**
     * Gets a report parameter (for system reports)
     *
     * Note that these parameters can be altered by user using HTML inspector,
     * cleaning and access validation may be necessary.
     *
     * @param string $parname the name of the page parameter we want
     * @param mixed  $default the default value to return if nothing is found
     * @param string $type expected type of parameter, for example PARAM_INT, PARAM_TEXT, etc .
     * @return mixed value of parameter (type depends on the $type attribute)
     */
    protected final function get_parameter($parname, $default, $type) {
        if (array_key_exists($parname, $this->parameters)) {
            return clean_param($this->parameters[$parname], $type);
        }
        return $default;
    }

    /**
     * Define simple (field==value) filters for the query (additional filters for the main table or filters for joined tables)
     *
     * These filters will always apply regardless of conditions and selected user filters
     *
     * @param string $fieldname name of the field with the table prefix
     * @param string|int|float $fieldvalue is the expected value
     */
    public final function add_base_condition_simple(string $fieldname, $fieldvalue) {
        if ($fieldvalue === null) {
            $this->add_base_condition_sql("$fieldname IS NULL");
        } else {
            $paramname = db::generate_param_name();
            $this->add_base_condition_sql("$fieldname = :$paramname", [$paramname => $fieldvalue]);
        }
    }

    /**
     * Define the SQL filter that always applies regardless of conditions and/or user filters
     *
     * @param string $sqlwhere Where clause
     * @param array $params named parameters for these queries (must be genrated with tool_wp\db::generate_param_name()
     */
    public final function add_base_condition_sql(string $sqlwhere, array $params = []) {
        $this->sqlfilters[] = $sqlwhere;
        db::validate_params($params);
        $this->sqlparams = $params + $this->sqlparams;
    }

    /**
     * Get the main table filter field.
     *
     * @return string where clause of the SQL query
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public final function get_main_filter_field() : string {
        // TODO SP-422 rename.
        return join(' AND ' , $this->sqlfilters);
    }


    /**
     * Get the main table filter value.
     *
     * @return array array of named parameters for the SQL query
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public final function get_main_filter_value() : array {
        // TODO SP-422 rename.
        return $this->sqlparams;
    }

    /**
     * Get the main table name.
     *
     * @return string
     */
    public function get_main_table() : string {
        return $this->maintable;
    }

    /**
     * Set the main table for SQL query
     *
     * @param string $tablename name of the db table without prefix
     * @param null $tablealias alias for the table
     * @param bool $addtenantfilter if 'tenantid' field is present in the main table, automatically add the tenant filter
     */
    public final function set_main_table($tablename, $tablealias = null, bool $addtenantfilter = true) {
        global $DB;
        $this->maintable = $tablename;
        $this->maintablealias = $tablealias;

        if ($addtenantfilter) {
            $columns = $DB->get_columns($this->get_main_table());
            if (array_key_exists('tenantid', $columns)) {
                $this->add_base_condition_simple($this->maintablealias . '.tenantid', tenancy::get_tenant_id());
            }
        }
    }

    /**
     * Get the alias for the main table.
     *
     * @return string
     */
    public final function get_main_table_alias() : string {
        return $this->maintablealias;
    }

    /**
     * Get the joins to build the query.
     *
     * @return array
     */
    public final function get_joins() {
        return $this->joins;
    }

    /**
     * Set the base join for the report that is added always regardless of selected conditions and user filters
     *
     * @param string $join a JOIN sql clauses
     * @param array $params additional named parameters for these join clauses, if needed
     *     (must be genrated with tool_wp\db::generate_param_name()
     */
    protected function add_base_join(string $join, array $params = []) {
        $cleanjoin = strtolower(trim($join));
        $this->joins[$cleanjoin] = $cleanjoin;
        db::validate_params($params);
        $this->sqlparams = $params + $this->sqlparams;
    }

    /**
     * Return the columns defined in the source.
     *
     * @return report_column[]
     */
    public final function get_columns() {
        return $this->columns;
    }

    /**
     * Retrieves a column definition by unique identifier key
     *
     * @param string $uniqueidentifier
     * @return null|report_column
     */
    public final function get_column(string $uniqueidentifier) : ?report_column {
        return array_key_exists($uniqueidentifier, $this->columns) ?
            $this->columns[$uniqueidentifier] : null;
    }

    /**
     * Get the report id.
     *
     * @return int
     * @throws \coding_exception
     */
    public function get_id() {
        return $this->reportpersistent->get('id');
    }

    /**
     * Return the filters defined in the source.
     *
     * @return report_filter[]
     */
    public final function get_filters() {
        return $this->filters;
    }

    /**
     * Get the filters for a select form control.
     *
     * @param array $filtersinuse
     *
     * @return array
     */
    public final function get_filters_select($filtersinuse) {
        // TODO SP-422 move to \tool_reportbuilder\local\helpers\filters.
        $select = array();

        $inuse = array_map(function($e) {
            return $e->key;
        }, $filtersinuse);

        foreach ($this->get_entities() as $entity => $entitytitle) {
            $values = array();
            foreach ($this->get_filters() as $filter) {
                if ($filter->get_entity() !== $entity || in_array($filter->get_unique_identifier(), $inuse)) {
                    continue;
                }
                $values[] = array(
                    'value'       => $filter->get_unique_identifier(),
                    'visiblename' => $filter->get_header()
                );
            }
            if ($values) {
                $select[] = array(
                    'optiongroup' => array(
                        'text'   => $entitytitle->out(),
                        'values' => $values
                    )
                );
            }
        }

        return $select;
    }

    /**
     * Get the conditions for a select form control.
     *
     * @param array $conditionsinuse

     * @return array
     */
    public final function get_conditions_select($conditionsinuse) {
        // TODO SP-422 move to \tool_reportbuilder\local\helpers\conditions .
        $select = array();

        $inuse = array_map(function($e) {
            return $e->key;
        }, $conditionsinuse);

        foreach ($this->get_entities() as $entity => $entitytitle) {
            $values = array();
            foreach ($this->get_conditions() as $filter) {
                if ($filter->get_entity() !== $entity || in_array($filter->get_unique_identifier(), $inuse)) {
                    continue;
                }
                $values[] = array(
                    'value'       => $filter->get_unique_identifier(),
                    'visiblename' => $filter->get_header()
                );
            }
            if ($values) {
                $select[] = array(
                    'optiongroup' => array(
                        'text'   => $entitytitle->out(),
                        'values' => $values
                    )
                );

            }
        }

        return $select;
    }

    /**
     * Get the report conditions definition.
     *
     * @return report_filter[]
     */
    public function get_conditions() {
        return $this->conditions;
    }

    /**
     * Adds an action to be displayed in the 'actions' column in each row (for system reports)
     *
     * @param report_action $action
     */
    public function add_action(report_action $action) {
        // TODO SP-422 only for system reports?
        $this->actions[] = $action;
    }

    /**
     * Visible name of the data source.
     *
     * @return string
     * @throws \moodle_exception
     */
    public static function get_name() {
        throw new \moodle_exception('errorneedtobeimplemented', 'tool_reportbuilder', __FUNCTION__);
    }

    /**
     * Add a column available in this report
     *
     * @param report_column $column
     * @return report_column
     */
    protected final function add_column(report_column $column) : report_column {
        if (!array_key_exists($column->get_entity(), $this->entitytitles)) {
            throw new \coding_exception("Entity ". $column->get_entity() .
                " must be annotated in this report before adding a column that uses it");
        }
        $name = $column->get_name();
        if (!strlen($name) || $name !== clean_param($name, PARAM_ALPHANUMEXT) || $name !== strtolower($name)) {
            throw new \coding_exception('Column name must be present and contain only lowercase letters, digits and _ symbol');
        }
        $index = $column->get_unique_identifier();
        if (array_key_exists($index, $this->columns)) {
            throw new \coding_exception("Column with the same name was already added to the report ($index)");
        }
        $this->columns[$index] = $column;
        return $column;
    }

    /**
     * Add a filter availabe in this report
     *
     * Conditions and filter share the same definition class but they can be different,
     * for example, filter for the date would use absolute dates selector where
     * condition for the date would usually use relative dates
     *
     * @param report_filter $filter
     */
    protected final function add_filter(report_filter $filter) {
        if (!array_key_exists($filter->get_entity(), $this->entitytitles)) {
            throw new \coding_exception("Entity ". $filter->get_entity() .
                " must be annotated in this report before adding a filter that uses it");
        }
        $index = $filter->get_unique_identifier();
        if (array_key_exists($index, $this->filters)) {
            throw new \coding_exception("Filter with the same name was already added to the report");
        }
        $this->filters[$index] = $filter;
    }

    /**
     * Add a condiion available in this report
     *
     * Conditions and filter share the same definition class but they can be different,
     * for example, filter for the date would use absolute dates selector where
     * condition for the date would usually use relative dates
     *
     * @param report_filter $filter
     */
    protected final function add_condition(report_filter $filter) {
        if (!array_key_exists($filter->get_entity(), $this->entitytitles)) {
            throw new \coding_exception("Entity ". $filter->get_entity() .
                " must be annotated in this report before adding a condition that uses it");
        }
        $index = $filter->get_unique_identifier();
        if (array_key_exists($index, $this->conditions)) {
            throw new \coding_exception("Condition with the same name was already added to the report");
        }
        $this->conditions[$index] = $filter;
    }

    /**
     * Get the actions defined for the report.
     *
     * @return report_action[]
     */
    public final function get_actions() {
        return $this->actions;
    }

    /**
     * Set if the header for the actions columns need to be showed.
     *
     * @param bool $status
     */
    public final function set_show_actions_header(bool $status) {
        // TODO SP-422 only for system reports?
        $this->showactionsheader = $status;
    }

    /**
     * Get the status for show or not, the actions header.
     *
     * @return bool
     */
    public final function get_show_actions_header() {
        // TODO SP-422 only for system reports?
        return $this->showactionsheader;
    }

    /**
     * Check if the report have actions defined.
     *
     * @return bool
     */
    public final function has_actions() : bool {
        // TODO SP-422 only for system reports?
        return !empty($this->actions);
    }

    /**
     * Set is the report can be downloaded.
     * @param bool $isdownloadable
     */
    public final function set_downloadable(bool $isdownloadable) {
        $this->isdownloadable = $isdownloadable;
    }

    /**
     * Get if the report can be downloaded.
     *
     * @return bool
     */
    public final function is_downloadable() : bool {
        return $this->isdownloadable;
    }

    /**
     * Get the report name.
     *
     * @return string
     * @throws \coding_exception
     */
    public final function get_reportname() : string {
        // TODO SP-422 this should be outside of this class. Plus for system reports it should be hardcoded.
        return $this->reportpersistent->get('name');
    }

    /**
     * Tenant this report belongs to
     *
     * @return int
     */
    public final function get_tenant_id(): int {
        return $this->reportpersistent->get('tenantid');
    }

    /**
     * Set attributes for the table.
     *
     * @param array $attributes
     */
    public function set_attributes(array $attributes) {
        $this->attributes = $attributes;
    }

    /**
     * Get attributes for the table.
     *
     * @return array
     */
    public function get_attributes() : array {
        return $this->attributes;
    }
}