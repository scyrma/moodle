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
 * Class tool_reportbuilder_report
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use tool_reportbuilder\output\report_dataformat_export_format;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_table extends \table_sql {

    /** @var report_base $report */
    protected $report;
    /** @var array $originalheaders The columns original header name with the entity */
    protected $originalheaders;
    /** @var report_column[] $sourcecolums */
    protected $sourcecolums = array();
    /** @var bool $isuserview Whether the current user is viewing the report (as opposed to editing it)*/
    protected $isuserview;
    /** @var report_action[] The action for each row. */
    protected $actions = array();
    /** @var callable */
    protected $rowclasscallback = null;
    /** @var callable $rowcallback */
    protected $rowcallback = null;
    /** @var \renderable[] */
    protected $editableheaders = [];
    /** @var \renderable[] */
    protected $aggregationheaders = [];
    /** @var array $columnsaggregation */
    protected $columnsaggregation = [];
    /** @var array $columnattributes additional attributes for each column (data- , classes, etc) */
    protected $columnattributes = [];
    /** @var array */
    protected $sqlparts = ['fields' => [], 'maintable' => '', 'joins' => [], 'where' => [], 'params' => [], 'sort' => []];

    /**
     * Contructor
     *
     * @param report_base $report
     */
    public function __construct(report_base $report) {
        $this->report = $report;

        parent::__construct($report->get_report_uniqid());
    }

    /**
     * Whether or not report data should be included in the table while in editing mode
     *
     * @return bool
     */
    private function display_table_data_during_editing(): bool {
        global $CFG;

        return !empty($CFG->tool_reportbuilder_liveediting ?? true);
    }

    /**
     * Render the table.
     *
     * @param int  $pagesize
     * @param int  $page
     * @param bool $editon User is able to edit this report and editing mode is on
     * @return string
     */
    final public function render(int $pagesize, int $page, bool $editon) {
        $this->isuserview = !$editon;

        ob_start();

        $this->is_persistent(true);
        $this->setup();
        $this->currpage = $page;

        // To aid performance while editing a report, we should observe the "live editing" config value.
        if ($this->isuserview || $this->display_table_data_during_editing()) {
            $this->query_db($pagesize, false);
            $this->build_table();
            $this->close_recordset();
        }

        // Edit page needs see the table even doesn't have any content.
        if (
            !$this->isuserview
            && $this->totalrows === 0
        ) {
            if (!$this->started_output) {
                $this->start_output();
            }
        }
        $this->finish_output();
        $this->show_query();

        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    /**
     * Override parent method so that we can set our own exportclass
     *
     * @param null $exportclass
     * @return \table_default_export_format_parent|report_dataformat_export_format|null
     */
    public function export_class_instance($exportclass = null) {
        if (!empty($this->download)) {
            $this->exportclass = new report_dataformat_export_format($this, $this->download);
            if (!$this->exportclass->document_started()) {
                $this->exportclass->start_document($this->filename, $this->sheettitle);
            }
        }

        return $this->exportclass;
    }

    /**
     * Show the current query and params.
     */
    private function show_query() {
        if (!$this->isuserview || debugging()) {
            $query = preg_replace("/\t/", "&nbsp;&nbsp;&nbsp;&nbsp;", $this->get_sql_query());
            $params = '';
            foreach ($this->get_sql_params() as $keyparam => $param) {
                $params .= '<p><code>' . $keyparam . '</code> => \'<code>' . s($param) . '</code>\'</p>';
            }
            echo "<details>
              <summary>" . get_string('debugsqlquery', 'tool_reportbuilder') . "</summary>
              <p><code>".str_replace("\n", "<br>\n", $query)."</code></p>
              <p>" . get_string('debugsqlparams', 'tool_reportbuilder') . "</p>
              <p>$params</p>
            </details>";
        }
    }

    /**
     * Query the db. Store results in the table object for use by build_table.
     *
     * @param int  $pagesize
     * @param bool $useinitialsbar Not used in report builder!
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;
        if (!$this->is_downloading()) {
            $this->pagesize($pagesize, $this->count_records());

            $this->rawdata = $DB->get_recordset_sql($this->get_sql_query(), $this->get_sql_params(),
                $this->get_page_start(), $this->get_page_size());
        } else {
            $this->rawdata = $DB->get_recordset_sql($this->get_sql_query(), $this->get_sql_params());
        }
    }

    /**
     * Return count of table records
     *
     * @return int
     */
    public function count_records(): int {
        global $DB;

        return $DB->count_records_sql($this->get_sql_count_query(), $this->get_sql_count_params());
    }

    /**
     * Group by string.
     *
     * @return string
     */
    private function get_groupby() {
        if (empty(array_filter(array_unique($this->columnsaggregation)))) {
            return '';
        }
        $columnsneedgroupby = [];
        foreach ($this->columns as $column => $columnkey) {
            $aggr = isset($this->columnsaggregation[$columnkey]) ? $this->columnsaggregation[$columnkey] : '';
            if ($aggr == 'unique' || empty($aggr)) {
                $fieldalias = $this->sourcecolums[$columnkey]->get_fields_for_group_by($columnkey);
                $columnsneedgroupby[$fieldalias] = $fieldalias;
            }
        }
        if ($columnsneedgroupby) {
            return "GROUP BY\n\t" . implode(",\n\t", $columnsneedgroupby) . "\n";
        }
        return '';
    }

    /**
     * Prints report headers.
     *
     * @see \table_sql::print_headers()
     *
     * @return void
     */
    public function print_headers() {
        global $OUTPUT;

        echo \html_writer::start_tag('thead');
        echo \html_writer::start_tag('tr');

        $col = 0;

        // If we are viewing the report (non-editing), print simple headers otherwise render editing template.
        if ($this->isuserview) {
            $sortpreferences = $this->report->get_sort_preferences();

            $primarysortcolumn = '';
            $primarysortorder  = SORT_ASC;
            if (!empty($sortpreferences) && reset($sortpreferences)) {
                $primarysortcolumn = key($sortpreferences);
                $primarysortorder  = current($sortpreferences);
            }

            foreach ($this->headers as $headerid => $title) {
                $class = isset($this->columnattributes[$col]['class']) ? ' ' . $this->columnattributes[$col]['class'] : '';
                $attributes = array(
                        'class' => "header c$col" . $class,
                        'scope' => 'col') + $this->columnattributes[$col];

                // If column supports sorting, create links for doing so.
                $aggregation = $this->columnsaggregation[$col] ?? '';
                if (!empty($title) && array_key_exists($col, $this->sourcecolums) &&
                        $this->sourcecolums[$col]->get_is_sortable($aggregation)) {

                    $columnname = array_search($col, $this->columns);
                    $title = $this->sort_link($title, $columnname, $columnname === $primarysortcolumn, $primarysortorder);
                }

                $content = \html_writer::tag('th', $title, $attributes);
                $col++;
                echo $content;
            }
        } else {
            foreach ($this->headers as $headerid => $title) {
                $class = isset($this->columnattributes[$col]['class']) ? ' ' . $this->columnattributes[$col]['class'] : '';
                $attributes = array(
                        'class' => "header c$col border-right border-left" . $class,
                        'scope' => 'col') + $this->columnattributes[$col];
                if (!array_key_exists($headerid, $this->originalheaders)) {
                    $content = \html_writer::tag('th', $title, $attributes);
                    $col++;
                    echo $content;
                    continue;
                }
                $context = new \stdClass();
                $context->originalname = $this->originalheaders[$headerid];
                $context->inplacename = $OUTPUT->render($this->editableheaders[$headerid]);
                $context->name = $title;
                $context->columnid = $headerid;
                $context->movetitle = get_string('movecolumn', 'tool_reportbuilder', $title);
                $context->aggregation = $OUTPUT->render($this->aggregationheaders[$headerid]);
                $content = \html_writer::tag('th',
                    $OUTPUT->render_from_template('tool_reportbuilder/table_header_cell', $context),
                    $attributes + array('data-title' => $title)
                );
                echo $content;
                $col++;
            }
        }

        echo \html_writer::end_tag('tr');
        echo \html_writer::end_tag('thead');
    }

    /**
     * Original headers.
     *
     * @param array $headers numerical keyed array of original displayed string titles
     * for each column.
     */
    public function define_originalheaders($headers) {
        $this->originalheaders = $headers;
    }

    /**
     * Editable headers.
     *
     * @param \renderable[] $headers numerical keyed array of editable name for each column
     */
    public function define_editableheaders($headers) {
        $this->editableheaders = $headers;
    }

    /**
     * Aggregation headers.
     *
     * @param \renderable[] $headers numerical keyed array of aggregations options for each column
     */
    public function define_aggregationheaders($headers) {
        $this->aggregationheaders = $headers;
    }

    /**
     * Original columns definition.
     *
     * @param report_column[] $columns
     */
    public function define_sourcecolumns($columns) {
        $this->sourcecolums = $columns;
    }

    /**
     * Set the actions (icons).
     *
     * @param report_action[] $actions
     */
    public function define_actions(array $actions = array()) {
        $this->actions = $actions;
    }

    /**
     * Format row if a callbacks has been defined.
     *
     * @param array|object $row
     *
     * @return array
     */
    public function format_row($row) {
        if (is_array($row)) {
            $row = (object)$row;
        }

        // Execute row callback.
        if (!empty($this->rowcallback)) {
            call_user_func_array($this->rowcallback, [$row]);
        }

        $formattedrow = array();
        // If at least one fields has aggregation, all other fields are displayed with the 'unique' aggregation.
        $defaultaggregation = array_filter($this->columnsaggregation) ? 'unique' : '';
        foreach ($this->columns as $column => $columnkey) {
            if ($column === 'actions') {
                $formattedrow[$column] = $this->get_actions_column_value($row);
                continue;
            }

            $sourcecolumn = $this->sourcecolums[$columnkey];
            if (!$sourcecolumn->get_is_available()) {
                // The column in not available to the current user, however it may still be shown to the
                // report creator. Display empty.
                $formattedrow[$column] = '';
                continue;
            }

            $aggregation = $this->columnsaggregation[$columnkey] ?: $defaultaggregation;
            $callbacks = $sourcecolumn->get_callbacks($aggregation);
            $values = $sourcecolumn->get_fields_values($row, $aggregation, $columnkey);
            $formattedrow[$column] = reset($values);
            if ($callbacks) {
                $obj = (object)$values;
                foreach ($callbacks as $callback) {
                    $formattedrow[$column] = call_user_func_array($callback[0],
                        [$formattedrow[$column], $obj, $callback[1]]);
                }
            }
        }
        return $formattedrow;
    }

    /**
     * Get the actions icons with the id of the row.
     *
     * @param \stdClass $row
     *
     * @return string
     */
    private function get_actions_column_value(\stdClass $row) {
        $actions = $this->actions;
        $icons = '';
        foreach ($actions as $action) {
            $icons .= $action->get_icon($row);
        }

        return $icons;
    }

    /**
     * Allows to set a callback to execute before each row is formatted.
     *
     * @param callable $rowcallback
     */
    public function set_row_callback(callable $rowcallback) {
        $this->rowcallback = $rowcallback;
    }

    /**
     * Allows to set a callback to calculate CSS classes added to the row
     * @param callable $callback function that take stdClass $row as argument
     */
    public function set_row_class_callback(callable $callback) {
        $this->rowclasscallback = $callback;
    }

    /**
     * Get any extra classes names to add to this row in the HTML.
     * @param \stdClass $row data for this row.
     * @return string added to the class="" attribute of the tr.
     */
    public function get_row_class($row) {
        if (!empty($this->rowclasscallback)) {
            return call_user_func($this->rowclasscallback, $row);
        }
        return '';
    }

    /**
     * Get the sql sort statement.
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_sql_sort() {
        // Get heading sort only in userview.
        if ($this->isuserview) {
            $sort = parent::get_sql_sort();
        } else {
            $sort = '';
        }
        ksort($this->sqlparts['sort']);
        $defaultsort = [];
        foreach ($this->sqlparts['sort'] as $asort) {
            // Avoid same column multiple sorting.
            $asortcolumn = explode(' ', $asort[0])[0];
            if (strpos($sort, $asortcolumn) === false) {
                $defaultsort = array_merge($defaultsort, $asort);
            }
        }
        if ($defaultsort) {
            $sort .= strlen($sort) ? ",\n\t" : '';
            $sort .= join(",\n\t", $defaultsort);
        }
        return $sort;
    }

    /**
     * This function is not part of the public api.
     */
    public function print_nothing_to_display() {
        // Render button to allow user to reset table preferences.
        echo $this->render_reset_button();

        $this->print_initials_bar();
        echo \html_writer::div(get_string('nothingtodisplay'), 'p-5 text-center text-gray080 bg-gray020');
    }

    /**
     * This function is not part of the public api.
     */
    public function start_html() : void {
        // Render button to allow user to reset table preferences.
        if ($this->isuserview) {
            echo $this->render_reset_button();
        }

        $this->wrap_html_start();
        // Start of main data table.

        echo \html_writer::start_tag('div', array('class' => 'no-overflow'));
        echo \html_writer::start_tag('table', $this->attributes);

    }

    /**
     * This function is not part of the public api.
     */
    public function finish_html() : void {
        global $OUTPUT;
        if (!$this->started_output) {
            // No data has been added to the table.
            $this->print_nothing_to_display();

        } else {
            // Print empty rows to fill the table to the current pagesize.
            // This is done so the header aria-controls attributes do not point to
            // non existant elements.
            $emptyrow = array_fill(0, count($this->columns), '');
            while ($this->currentrow < $this->pagesize) {
                $this->print_row($emptyrow, 'emptyrow');
            }

            echo \html_writer::end_tag('tbody');
            echo \html_writer::end_tag('table');
            echo \html_writer::end_tag('div');
            $this->wrap_html_finish();

            $pagination = '';
            if ($this->use_pages) {
                $pagingbar = new \paging_bar($this->totalrows, $this->currpage, $this->pagesize, $this->baseurl);
                $pagingbar->pagevar = $this->request[TABLE_VAR_PAGE];
                $pagination = $OUTPUT->render($pagingbar);
            }

            $downloadbuttons = $this->download_buttons();

            $context = [];
            $context['pagination'] = $pagination;
            $context['downloadbuttons'] = $downloadbuttons;
            $context['showingall'] = $this->pagesize == 5000;
            $context['defaultpagesize'] = $this->report->get_default_pagesize();
            $context['totalrows'] = $this->totalrows;
            echo $OUTPUT->render_from_template('tool_reportbuilder/report_table_footer', $context);

            // Let user know if admin has disabled live report data during editing.
            if (!$this->isuserview && !$this->display_table_data_during_editing()) {
                echo \html_writer::div(get_string('reportliveeditingdisabled', 'tool_reportbuilder'),
                    'p-5 text-center text-gray080 bg-gray020');
            }
        }
    }

    /**
     * Defined the columns aggregation.
     * @param array $columnsaggr The aggregation for the column.
     */
    public function define_columns_aggregate($columnsaggr) {
        $this->columnsaggregation = $columnsaggr;
    }

    /**
     * Defined the columns attributes.
     * @param array $columnsattributes
     */
    public function define_columns_attributes($columnsattributes) {
        $this->columnattributes = $columnsattributes;
    }

    /**
     * Generate html code for the passed row.
     *
     * @param array $row Row data.
     * @param string $classname classes to add.
     *
     * @return string $html html code for the row passed.
     */
    public function get_row_html($row, $classname = '') {
        // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
        static $suppress_lastrow = null;

        $rowclasses = array();

        if ($classname) {
            $rowclasses[] = $classname;
        }

        $rowid = $this->uniqueid . '_r' . $this->currentrow;
        $html = '';

        $html .= \html_writer::start_tag('tr', array('class' => implode(' ', $rowclasses), 'id' => $rowid));

        // If we have a separator, print it.
        if ($row === null) {
            $colcount = count($this->columns);
            $html .= \html_writer::tag('td', \html_writer::tag('div', '',
                array('class' => 'tabledivider')), array('colspan' => $colcount));

        } else {
            foreach ($row as $index => $data) {
                $column = array_search($index, $this->columns);
                $class = isset($this->columnattributes[$index]['class']) ? ' ' . $this->columnattributes[$index]['class'] : '';
                $attributes = [
                    'class' => "cell c{$index}" . $class,
                    'id' => "{$rowid}_c{$index}",
                ] + $this->columnattributes[$index];

                $celltype = 'td';
                if ($this->headercolumn && $column == $this->headercolumn) {
                    $celltype = 'th';
                    $attributes['scope'] = 'row';
                }

                $content = $data;

                $html .= \html_writer::tag($celltype, $content, $attributes);
            }
        }

        $html .= \html_writer::end_tag('tr');

        $this->currentrow++;
        return $html;
    }

    /**
     * Set sql. This is a function from the parent class that should not be used
     *
     * @param string $fields
     * @param string $from
     * @param string $where
     * @param array $params
     */
    public function set_sql($fields, $from, $where, array $params = array()) {
        debugging('Function set_sql() is not supported, SQL is generated for each report', DEBUG_DEVELOPER);
    }

    /**
     * Add a field to the SQL query
     *
     * @param string $field
     */
    public function add_field_to_sql(string $field) {
        $this->sqlparts['fields'][] = str_replace("\n", "\n\t\t", $field);
    }

    /**
     * Add one of the "WHERE" clauses to the SQL query
     *
     * @param string $where
     */
    public function add_where_to_sql(string $where) {
        if (strlen('' . $where)) {
            $this->sqlparts['where'][] = str_replace("\n", "\n\t\t", $where);
        }
    }

    /**
     * Add multiple "WHERE" clauses to the SQL query (do not include the word "where")
     *
     * @param array $wheres
     */
    public function add_wheres_to_sql(array $wheres) {
        foreach ($wheres as $where) {
            $this->add_where_to_sql($where);
        }
    }

    /**
     * Set the SQL for the main table (will be used in the "FROM" clause before adding joins, do not include "from" word)
     *
     * @param string $maintable
     */
    public function set_maintable_in_sql(string $maintable) {
        $this->sqlparts['maintable'] = $maintable;
    }

    /**
     * Add a "JOIN" clause to the SQL. Duplicate joins will be removed. Must include the actual word "join"
     *
     * @param string $join
     */
    public function add_join_to_sql(string $join) {
        $join = trim($join);
        foreach ($this->sqlparts['joins'] as $e) {
            if (strtolower($e) === strtolower($join)) {
                return;
            }
        }
        $this->sqlparts['joins'][] = $join;
    }

    /**
     * Add multiple "JOIN" clauses to the SQL. Duplicate joins will be removed. Each must include the actual word "join"
     *
     * @param array $joins
     */
    public function add_joins_to_sql(array $joins) {
        foreach ($joins as $join) {
            $this->add_join_to_sql($join);
        }
    }

    /**
     * Add parameters to the SQL
     *
     * @param array $params
     */
    public function add_params_to_sql(array $params) {
        $this->sqlparts['params'] = array_merge($this->sqlparts['params'], $params);
    }

    /**
     * Add default sort to the SQL
     *
     * @param int $sortorder priority of this sort
     * @param string $sortsql SQL clause that will be used in the "ORDER BY" clause, for example "u.firstname ASC"
     */
    public function add_sort_to_sql(int $sortorder, string $sortsql) {
        $this->sqlparts['sort'] += [$sortorder => []];
        $this->sqlparts['sort'][$sortorder][] = $sortsql;
    }

    /**
     * Final SQL query
     *
     * @return string
     */
    protected function get_sql_query(): string {
        $sql = "SELECT\n\t" .
            implode(",\n\t", $this->sqlparts['fields']) . "\n";

        $sql .= "FROM\n\t" . $this->sqlparts['maintable'] . "\n";

        if ($joins = $this->sqlparts['joins']) {
            $sql .= "\t" . implode("\n\t", $this->sqlparts['joins']) . "\n";
        }

        if ($where = join("\n\tAND ", $this->sqlparts['where'])) {
            $sql .= "WHERE\n\t{$where}\n";
        }

        $sql .= $this->get_groupby();

        // TODO: implement "HAVING" when add custom filters and conditions for aggregation.

        if ($sort = $this->get_sql_sort()) {
            $sql .= "ORDER BY\n\t$sort";
        }

        return $sql;
    }

    /**
     * Parameters for the final SQL query
     *
     * @return array
     */
    protected function get_sql_params(): array {
        return $this->sqlparts['params'];
    }

    /**
     * Parameters for the "count" SQL query
     *
     * @return array
     */
    protected function get_sql_count_params(): array {
        return $this->sqlparts['params'];
    }

    /**
     * SQL query for counting the number of records
     *
     * @return string
     */
    protected function get_sql_count_query(): string {
        $fields = implode(', ', $this->sqlparts['fields']);
        $from = $this->sqlparts['maintable'] . ' ' . implode(' ', $this->sqlparts['joins']);

        $sql = 'SELECT COUNT(1) FROM (SELECT ' . $fields . ' FROM ' . $from;

        if ($where = join(' AND ', $this->sqlparts['where'])) {
            $sql .= ' WHERE '. $where;
        }

        return $sql . ' ' . $this->get_groupby() . ') counttable';
    }

    /**
     * Generate the HTML for the report builder sort link.
     *
     * @param string $text
     * @param string $column
     * @param bool $isprimary
     * @param int $order
     * @return string
     */
    protected function sort_link($text, $column, $isprimary, $order) {
        $linktext = $text . get_accesshide(get_string('sortby') . ' ' . $text . ' ' .
            $this->sort_order_name($isprimary, $order));

        $linkattributes = [
            'role' => 'button',
            'tabindex' => 0,
            'data-action' => 'sort-table',
            'data-sortcolumn' => $column,
            'data-sortorder' => $order
        ];

        return \html_writer::link('#', $linktext, $linkattributes) . ' ' .
            $this->sort_icon($isprimary, $order);
    }

    /**
     * Generate the HTML for the report builder reset button.
     *
     * @return string
     */
    protected function render_reset_button() {
        $attributes = [
            'class' => 'd-none',
            'data-tableid' => $this->uniqueid,
            'data-region' => 'reset-button',
            'data-isactive' => $this->can_be_reset()
        ];
        return \html_writer::tag('a', '', $attributes);
    }

    /**
     * Override parent method for hiding/showing columns as user preferences
     *
     * We don't support this and due to core changes this can cause warnings to be displayed, see: MDL-69336
     */
    protected function set_hide_show_preferences(): void {
        return;
    }

    /**
     * Get the html for the download buttons
     */
    public function download_buttons() {
        global $OUTPUT;

        if ($this->is_downloadable() && !$this->is_downloading()) {
            return $OUTPUT->download_dataformat_selector(
                get_string('downloadas', 'table'),
                new \moodle_url('/admin/tool/reportbuilder/view.php'),
                'download',
                [
                    'id' => $this->report->get_id(),
                    'parameters' => json_encode($this->report->get_parameters()),
                ]
            );
        } else {
            return '';
        }
    }
}
