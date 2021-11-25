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
 * Class report_course_completion
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use tool_reportbuilder\local\helpers\columns as columns_helper;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_reportbuilder\output\report_exporter;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;
use tool_wp\db;
use tool_reportbuilder\local\report\reportbuilder_filter;
use tool_reportbuilder\local\models\reportbuilder_conditions;

/**
 * Class report_base, base class for all datasources and system reports.
 *
 * Do not extend directly, extend either class \tool_reportbuilder\datasource or
 * \tool_reportbuilder\system_report
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class report_base {
    /** @var int  */

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
    /** @var bool $isdownloadable Set if the report is $downloadable */
    private $isdownloadable = true;
    /** @var array $parameters parameters of the report */
    private $parameters = [];
    /** @var \lang_string[] */
    private $entitytitles = [];
    /** @var array */
    private $attributes = [];
    /** @var reportbuilder_column[] list of active columns, calculated on first call to get_active_columns() */
    private $activecolumns = null;
    /** @var reportbuilder_filter[] list of active filters, calculated on first call to get_active_filters() */
    private $activefilters = null;
    /** @var reportbuilder_filter[] list of active conditions, calculated on first call to get_active_filters() */
    private $activeconditions = null;
    /** @var array when the report configuration was last reset */
    static private $configreset = [];
    /** @var int $defaultpagesize */
    private $defaultpagesize = 10;

    /**
     * report_base constructor.
     *
     * @param reportbuilder $persistent
     * @param array $parameters additional parameters, simple keys with simple values
     * @param int $page
     *
     * @throws \coding_exception
     */
    final public function __construct(reportbuilder $persistent, array $parameters = [], int $page = 0) {
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
    final public function get_persistent(): reportbuilder {
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
     * To add actions that will be displayed in automatic 'Actions' column:
     * - add_action()
     */
    abstract protected function initialise();

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
    final public function get_report_uniqid() {
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
    final public function export($output, $editon, bool $tableonly = false) {
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
        $data->parameters = json_encode($this->parameters, JSON_FORCE_OBJECT);
        $data->htmlid = \html_writer::random_id('rbsystem');

        return $data;
    }

    /**
     * Add entity to the report that will be used for groupping columns, filters and conditions
     *
     * @param string $entity
     * @param \lang_string $title
     */
    final protected function annotate_entity(string $entity, \lang_string $title) {
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
    final protected function add_entity(entity_base $entity) {
        $this->annotate_entity($entity->get_entity_name(), $entity->get_entity_title());
        $entity->add_to_report();

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
    final public function get_entities() : array {
        return $this->entitytitles;
    }

    /**
     * Get page size
     *
     * @return int
     */
    final public function get_pagesize() : int {
        if (!empty($this->parameters['pagesize']) && is_int($this->parameters['pagesize'])) {
            return (int)$this->parameters['pagesize'];
        }
        return $this->get_default_pagesize();
    }

    /**
     * Set default page size
     *
     * @param int $defaultpagesize
     * @return void
     */
    final public function set_default_pagesize(int $defaultpagesize): void {
        $this->defaultpagesize = $defaultpagesize;
    }

    /**
     * Get default page size
     *
     * @return int
     */
    final public function get_default_pagesize(): int {
        if (!empty($this->parameters['defaultpagesize']) && is_int($this->parameters['defaultpagesize'])) {
            return $this->parameters['defaultpagesize'];
        }
        return $this->defaultpagesize;
    }

    /**
     * Gets the report parameter (for system reports)
     *
     * @return array
     */
    final public function get_parameters(): array {
        return $this->parameters;
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
    final protected function get_parameter($parname, $default, $type) {
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
    final public function add_base_condition_simple(string $fieldname, $fieldvalue) {
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
     * @param bool $validateparams Some queries might add non wp parameters and validation could fail
     */
    final public function add_base_condition_sql(string $sqlwhere, array $params = [], bool $validateparams = true) {
        $this->sqlfilters[] = $sqlwhere;
        if ($validateparams) {
            db::validate_params($params);
        }
        $this->sqlparams = $params + $this->sqlparams;
    }

    /**
     * Get the main table filter field.
     *
     * @return string where clause of the SQL query
     * @throws \coding_exception
     * @throws \dml_exception
     */
    final public function get_main_filter_field() : string {
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
    final public function get_main_filter_value() : array {
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
    final public function set_main_table($tablename, $tablealias = null, bool $addtenantfilter = true) {
        global $DB;
        $this->maintable = $tablename;
        $this->maintablealias = $tablealias;

        if ($addtenantfilter) {
            $columns = $DB->get_columns($this->get_main_table());
            if (array_key_exists('tenantid', $columns)) {
                debugging('Parameter addtenantfilter is now deprecated and should be passed as false, you need to add
                tenant condition manually. Tenant condition now should include entities in subtenants', DEBUG_DEVELOPER);
                $this->add_base_condition_simple($this->maintablealias . '.tenantid', tenancy::get_tenant_id());
            }
        }
    }

    /**
     * Get the alias for the main table.
     *
     * @return string
     */
    final public function get_main_table_alias() : string {
        return $this->maintablealias;
    }

    /**
     * Get the joins to build the query.
     *
     * @return array
     */
    final public function get_joins() {
        return $this->joins;
    }

    /**
     * Set the base join for the report that is added always regardless of selected conditions and user filters
     *
     * @param string $join a JOIN sql clauses
     * @param array $params additional named parameters for these join clauses, if needed
     *     (must be genrated with tool_wp\db::generate_param_name()
     * @param bool $validateparams Some queries might add non wp parameters and validation could fail
     */
    protected function add_base_join(string $join, array $params = [], bool $validateparams = true) {
        $cleanjoin = trim($join);
        $this->joins[$cleanjoin] = $cleanjoin;
        if ($validateparams) {
            db::validate_params($params);
        }
        $this->sqlparams = $params + $this->sqlparams;
    }

    /**
     * Return the columns defined in the source.
     *
     * @return report_column[]
     */
    final public function get_columns() {
        return $this->columns;
    }

    /**
     * Retrieves a column definition by unique identifier key
     *
     * @param string $uniqueidentifier
     * @return null|report_column
     */
    final public function get_column(string $uniqueidentifier) : ?report_column {
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
    final public function get_filters() {
        return $this->filters;
    }

    /**
     * Retrieves a filter definition by unique identifier key
     *
     * @param string $uniqueidentifier
     * @return null|report_filter
     */
    final public function get_filter(string $uniqueidentifier) : ?report_filter {
        return array_key_exists($uniqueidentifier, $this->filters) ?
            $this->filters[$uniqueidentifier] : null;
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
     * Retrieves a condition definition by unique identifier key
     *
     * @param string $uniqueidentifier
     * @return null|report_filter
     */
    final public function get_condition(string $uniqueidentifier) : ?report_filter {
        return array_key_exists($uniqueidentifier, $this->conditions) ?
            $this->conditions[$uniqueidentifier] : null;
    }

    /**
     * Adds an action to be displayed in the 'actions' column in each row (for system reports)
     *
     * @param report_action $action
     */
    public function add_action(report_action $action) {
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
    final protected function add_column(report_column $column) : report_column {
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
    final protected function add_filter(report_filter $filter) {
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
    final protected function add_condition(report_filter $filter) {
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
    final public function get_actions() {
        return $this->actions;
    }

    /**
     * Check if the report have actions defined.
     *
     * @return bool
     */
    final public function has_actions() : bool {
        return !empty($this->actions);
    }

    /**
     * Set is the report can be downloaded.
     * @param bool $isdownloadable
     */
    final public function set_downloadable(bool $isdownloadable) {
        $this->isdownloadable = $isdownloadable;
    }

    /**
     * Get if the report can be downloaded.
     *
     * @return bool
     */
    final public function is_downloadable() : bool {
        return $this->isdownloadable;
    }

    /**
     * Get the report name.
     *
     * @return string
     * @throws \coding_exception
     */
    final public function get_reportname() : string {
        // TODO SP-422 this should be outside of this class. Plus for system reports it should be hardcoded.
        return $this->reportpersistent->get('name');
    }

    /**
     * Tenant this report belongs to
     *
     * @return int
     */
    final public function get_tenant_id(): int {
        return $this->reportpersistent->get('tenantid');
    }

    /**
     * Is the report shared with sub tenants
     *
     * @return bool
     */
    final public function is_shared(): bool {
        return (bool)$this->reportpersistent->get('shared');
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

    /**
     * Get the active columns of the report.
     *
     * @return reportbuilder_column[]
     */
    final public function get_active_columns(): array {
        self::$configreset += [$this->get_id() => 0];
        if ($this->activecolumns === null || $this->activecolumns['builttime'] < self::$configreset[$this->get_id()]) {
            $this->activecolumns = [
                'builttime' => microtime(true),
                'values' => columns_helper::get_active_columns($this)
            ];
        }
        return $this->activecolumns['values'];
    }

    /**
     * Get the active filters of the report.
     *
     * @return reportbuilder_filter[]
     */
    final public function get_active_filters(): array {
        self::$configreset += [$this->get_id() => 0];
        if ($this->activefilters === null || $this->activefilters['builttime'] < self::$configreset[$this->get_id()]) {
            $this->activefilters = [
                'builttime' => microtime(true),
                'values' => filters_helper::get_active_filters($this->get_id())
            ];
        }
        return $this->activefilters['values'];
    }

    /**
     * Get the active conditions of the report.
     *
     * @return reportbuilder_conditions[]
     */
    final public function get_active_conditions(): array {
        self::$configreset += [$this->get_id() => 0];
        if ($this->activeconditions === null || $this->activeconditions['builttime'] < self::$configreset[$this->get_id()]) {
            $this->activeconditions = [
                'builttime' => microtime(true),
                'values' => conditions_helper::get_active_conditions($this->get_id())
            ];
        }
        return $this->activeconditions['values'];
    }

    /**
     * Set report column sorting preferences
     *
     * @param array $preferences
     * @return bool
     */
    final public function set_sort_preferences(array $preferences): bool {
        $name = 'flextable_' . $this->get_report_uniqid();

        return set_user_preference($name, json_encode([
            'sortby' => $preferences,
            'collapse' => [],
        ]));
    }

    /**
     * Get report column sorting preferences
     *
     * @return array
     */
    final public function get_sort_preferences(): array {
        $name = 'flextable_' . $this->get_report_uniqid();
        $preferences = json_decode(get_user_preferences($name), true);

        return $preferences['sortby'] ?? [];
    }

    /**
     * Reset the cached list of active filters/columns/filters
     *
     * There are a lot of places where the list of report columns/filters/conditions may be modified
     * and there may be several instances of report_base. Use this static function to keep track
     * of the resets
     *
     * @param int $reportid
     */
    final public static function report_configuration_modified(int $reportid) {
        self::$configreset[$reportid] = microtime(true);
    }

    /**
     * Get the report availability. Sub-classes should override this method to declare themselves unavailable, for example if
     * they require classes that aren't present due to missing plugin
     *
     * @return bool
     */
    public static function is_available(): bool {
        return true;
    }
}
