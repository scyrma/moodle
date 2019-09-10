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
 * Class tool_reportbuilder_report
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_table extends \table_sql {

    /** @var $query */
    protected $query;
    /** @var $columnsdefinitions */
    protected $columnsdefinitions;
    /** @var array $originalheaders The columns original header name with the entity */
    protected $originalheaders;
    /** @var report_column[] $sourcecolums */
    protected $sourcecolums = array();
    /** @var array The columns to be sorted in order and the direction */
    protected $sortcolumns = array();
    /** @var bool $isuserview */
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

    /**
     * Render the table.
     *
     * @param int  $pagesize
     * @param int  $useinitialsbar
     * @param int  $page
     * @param bool $editon User is able to edit this report and editing mode is on
     * @return string
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public final function render(int $pagesize, int $useinitialsbar, int $page, bool $editon) {
        $this->isuserview = !$editon;

        $context = \context_system::instance();
        ob_start();

        $this->is_persistent(true);
        $this->setup();
        $this->currpage = $page;
        $this->query_db($pagesize, $useinitialsbar);
        $this->build_table();
        $this->close_recordset();
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
     * Show the current query and params.
     * TODO: do better...
     */
    private function show_query() {
        if (!$this->isuserview || debugging()) {
            $query = $this->query;
            $params = '';
            foreach ($this->sql->params as $keyparam => $param) {
                $params .= "<p>Parameter: <code>$keyparam</code> Value: <code>$param</code></p>";
            }
            echo "<details>
              <summary>Current query</summary>
              <p><code>$query</code></p>
              <p>Parameteres</p>
              <p>$params</p>
            </details>";
        }
    }
    /**
     * Query the db. Store results in the table object for use by build_table.
     *
     * @param int  $pagesize
     * @param bool $useinitialsbar
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;
        // TODO: split in other function.

        $groupby = $this->get_groupby();

        if (!$this->is_downloading()) {
            if ($this->countsql === null) {
                $this->countsql = 'SELECT COUNT(*) FROM (SELECT ' . $this->sql->fields . ' FROM ' . $this->sql->from;

                if ($this->sql->where) {
                    $this->countsql .= ' WHERE '. $this->sql->where;
                }

                $this->countsql .= $groupby . ') counttable';
                $this->countparams = $this->sql->params;
            }

            $grandtotal = $DB->count_records_sql($this->countsql, $this->countparams);
            if ($useinitialsbar && !$this->is_downloading()) {
                $this->initialbars($grandtotal > $pagesize);
            }

            list($wsql, $wparams) = $this->get_sql_where();
            if ($wsql) {
                $this->countsql .= ' AND '.$wsql;
                $this->countparams = array_merge($this->countparams, $wparams);

                $this->sql->where .= ' AND '.$wsql;
                $this->sql->params = array_merge($this->sql->params, $wparams);

                $total  = $DB->count_records_sql($this->countsql, $this->countparams);
            } else {
                $total = $grandtotal;
            }

            $this->pagesize($pagesize, $total);
        }

        $sort = $this->get_sql_sort();

        if ($sort) {
            $sort = "ORDER BY $sort";
        }

        $where = '';
        if ($this->sql->where) {
            $where = "WHERE {$this->sql->where}";
        }

        $having = ''; // TODO: implement when add custom filters and conditions for aggregation.

        $sql = "SELECT
            {$this->sql->fields}
            FROM {$this->sql->from}
            $where
            $groupby
            {$sort}";

        $this->query = $sql;

        if (!$this->is_downloading()) {
            $this->rawdata = $DB->get_recordset_sql($sql, $this->sql->params, $this->get_page_start(), $this->get_page_size());
        } else {
            $this->rawdata = $DB->get_recordset_sql($sql, $this->sql->params);
        }
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
        foreach ($this->columns as $columnkey => $column) {
            $aggr = isset($this->columnsaggregation[$columnkey]) ? $this->columnsaggregation[$columnkey] : '';
            if ($aggr == 'unique' || empty($aggr)) {
                $fieldalias = $this->sourcecolums[$column]->get_fields_for_group_by($columnkey);
                $columnsneedgroupby[$fieldalias] = $fieldalias;
            }
        }
        if ($columnsneedgroupby) {
            return ' GROUP BY ' . implode(',', $columnsneedgroupby);
        }
        return '';
    }

    /**
     * Custom headers.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function print_headers() {
        // TODO: move this code to template. Done on SP-183.
        global $PAGE, $OUTPUT;
        $renderer = $PAGE->get_renderer('core');
        echo \html_writer::start_tag('thead');
        echo \html_writer::start_tag('tr');

        $normalview = $this->isuserview;
        $col = 0;
        if ($normalview) {
            foreach ($this->headers as $headerid => $title) {
                $class = isset($this->columnattributes[$col]['class']) ? ' ' . $this->columnattributes[$col]['class'] : '';
                $attributes = array(
                        'class' => "header c$col" . $class,
                        'scope' => 'col') + $this->columnattributes[$col];
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
                    $renderer->render_from_template('tool_reportbuilder/table_header_cell', $context),
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
     * Define the sort.
     *
     * @param array $sort
     */
    public function define_sort(array $sort) : void {
        $this->sortcolumns = $sort;
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
        foreach ($this->columns as $columnkey => $column) {
            if ($column === 'actions') {
                $formattedrow[$columnkey] = $this->get_actions_column_value($row);
                continue;
            }

            $sourcecolumn = $this->sourcecolums[$column];
            if (!$sourcecolumn->get_is_available()) {
                // The column in not available to the current user, however it may still be shown to the
                // report creator. Display empty.
                $formattedrow[$columnkey] = '';
                continue;
            }

            $callbacks = $sourcecolumn->get_callbacks($this->columnsaggregation[$columnkey]);
            $values = $sourcecolumn->get_fields_values($row, $this->columnsaggregation[$columnkey], $columnkey);
            $formattedrow[$columnkey] = reset($values);
            if ($callbacks) {
                $obj = (object)$values;
                foreach ($callbacks as $callback) {
                    $formattedrow[$columnkey] = call_user_func_array($callback[0],
                        [$formattedrow[$columnkey], $obj, $callback[1]]);
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
        ksort($this->sortcolumns);
        return join(', ', $this->sortcolumns);
    }

    /**
     * This function is not part of the public api.
     */
    public function print_nothing_to_display() {
        // Render button to allow user to reset table preferences.
        echo $this->render_reset_button();

        $this->print_initials_bar();
        echo \html_writer::div(get_string('nothingtodisplay'), 'alert alert-info py-3');
    }

    /**
     * This function is not part of the public api.
     */
    public function start_html() : void {
        // Render button to allow user to reset table preferences.
        echo $this->render_reset_button();

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

            if ($this->use_pages) {
                $pagingbar = new \paging_bar($this->totalrows, $this->currpage, $this->pagesize, $this->baseurl);
                $pagingbar->pagevar = $this->request[TABLE_VAR_PAGE];
                $pagination = $OUTPUT->render($pagingbar);
            }

            $downloadbuttons = $this->download_buttons();

            $context = [];
            $context['pagination'] = $pagination;
            $context['downloadbuttons'] = $downloadbuttons;
            echo $OUTPUT->render_from_template('tool_reportbuilder/report_table_footer', $context);

        }
    }

    /**
     * Defined the columns.
     *
     * @param array $columns an array of identifying names for columns. If
     * columns are sorted then column names must correspond to a field in sql.
     */
    public function define_columns($columns) {
        $this->columns = array();
        $this->column_style = array(); // TODO: we use this? can be removed?
        $this->column_class = array(); // TODO: we use this? can be removed?
        $colnum = 0;

        foreach ($columns as $column) {
            $this->columns[$colnum]         = $column;
            $this->column_style[$colnum]    = array(); // TODO: we use this? can be removed?
            $this->column_class[$colnum]    = ''; // TODO: we use this? can be removed?
            $this->column_suppress[$colnum] = false; // TODO: we use this? can be removed?
            $colnum++;
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
        // @codingStandardsIgnoreStart
        static $suppress_lastrow = null;
        // @codingStandardsIgnoreEnd
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
            $colbyindex = $this->columns;
            foreach ($row as $index => $data) {
                $column = $colbyindex[$index];
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
}