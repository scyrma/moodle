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
 * Class for export the report context.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\output;

use tool_reportbuilder\constants;
use tool_reportbuilder\local\filter\report_conditions;
use tool_reportbuilder\local\filter\report_filtering;
use tool_reportbuilder\local\helpers\columns as columns_helper;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_reportbuilder\local\helpers\aggregation as aggregation_helper;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_table;
use tool_reportbuilder\reportbuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Class report_exporter
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_exporter extends \core\external\persistent_exporter {

    /** @var reportbuilder The persistent object we will export. */
    protected $persistent = null;

    /** @var report_table */
    protected $table;

    /** @var array */
    protected $columnsdata;

    /** @var array */
    protected $filtersinuse;

    /** @var array */
    protected $conditionsinuse;

    /** @var array */
    protected $columnssorting;

    /** @var report_filtering */
    protected $filtering;

    /** @var report_conditions */
    protected $conditions;

    /**
     * report_exporter constructor.
     *
     * @param \core\persistent $persistent
     * @param array $related
     */
    public function __construct(\core\persistent $persistent, array $related = array()) {
        parent::__construct($persistent, $related);

        // Make sure user can never turn editing on if he does not have permission to edit.
        /** @var report_base $source */
        $source = $this->related['source'];
        $this->related['editon'] = $this->related['editon'] && permission::can_edit($source);
    }

    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class() {
        return \tool_reportbuilder\reportbuilder::class;
    }

    /**
     * Defined the related data to export.
     *
     * @return array
     */
    protected static function define_related() {
        return [
            'source' => report_base::class,
            'page' => 'int',
            'editon' => 'bool',
            'tableonly' => 'bool'
        ];
    }

    /**
     * Return the list of additional properties.

     * @return array
     */
    protected static function define_other_properties() {
        return [
            'columnsinuse' => [
                'type' => reportbuilder_column_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => true
            ],
            'sortablecolumns' => [
                'type' => reportbuilder_column_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => true
            ],
            'availablecolumns' => [
                'type' => reportbuilder_column_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => true
            ],
            'table' => [
                'type' => PARAM_RAW
            ],
            'filtersform' => [
                'type' => PARAM_RAW,
                'optional' => true
            ],
            'conditionsform' => [
                'type' => PARAM_RAW,
                'optional' => true
            ],
            'availablefilters' => [
                'type' => reportbuilder_filter_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => true
            ],
            'hasavailablefilters' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'hassortablecolumns' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'availableconditions' => [
                'type' => reportbuilder_condition_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => true
            ],
            'hasavailableconditions' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'filtersinuse' => [
                'type' => reportbuilder_filter_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => true
            ],
            'hasfiltersselected' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'hasconditionsselected' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'nofiltersurl' => [
                'type' => PARAM_URL,
                'optional' => true
            ],
            'nocolumnsurl' => [
                'type' => PARAM_URL,
                'optional' => true
            ],
            'noconditionsurl' => [
                'type' => PARAM_URL,
                'optional' => true
            ],
            'hascolumns' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'hassidebar' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'editon' => [
                'type' => PARAM_BOOL,
                'optional' => true,
                'default' => false
            ],
            'canaddfilters' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'conditionshelp' => [
                'type' => PARAM_RAW,
                'optional' => true
            ],
            'filtershelp' => [
                'type' => PARAM_RAW,
                'optional' => true
            ],
            'sortingshelp' => [
                'type' => PARAM_RAW,
                'optional' => true
            ],
            'reportid' => [
                'type' => PARAM_INT,
                'optional' => true
            ]
        ];
    }

    /**
     * Parameters for format_string() for name
     *
     * @return array
     * @throws \dml_exception
     */
    protected function get_format_parameters_for_name() {
        return ['context' => \context_system::instance(), 'escape' => false];
    }

    /**
     * Prepares conditions, filters and the report table
     *
     * @param \renderer_base $output
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function prepare_report(\renderer_base $output) {
        /** @var report_base $source */
        $source = $this->related['source'];
        $editon = $this->related['editon'];
        $extrajoins = $source->get_joins();

        $extrasql = $sql = $source->get_main_filter_field();
        $extraparams = $source->get_main_filter_value();

        $activefilters = filters_helper::get_active_filters($source->get_id());
        $filters = $source->get_filters();
        $this->filtersinuse = $this->get_filters($activefilters, $filters);
        $this->filtering = new report_filtering($source, $editon, $this->filtersinuse);

        if (!$editon) {
            list($extrasql, $extraparams, $extrajoins) = $this->filtering->get_sql_filter($sql, $extraparams, $extrajoins);
        }

        $activeconditions = conditions_helper::get_active_conditions($source->get_id());
        $conditionslist = $source->get_conditions();
        $this->conditionsinuse = conditions_helper::get_conditions($activeconditions, $conditionslist, $output);
        $this->conditions = new report_conditions($source, $editon, $this->conditionsinuse);
        list($extrasql, $extraparams, $extrajoins) = $this->conditions->get_sql_filter($extrasql, $extraparams, $extrajoins);

        list($this->columnsdata, $this->columnssorting) = columns_helper::export($source, $output);

        $this->table = $this->prepare_table($this->columnsdata, $extrajoins, $extrasql, $extraparams);
    }

    /**
     * Get the additional values to inject while exporting.
     * @param \renderer_base $output
     *
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function get_other_values(\renderer_base $output) {
        /** @var report_base $source */
        $source = $this->related['source'];
        $editon = $this->related['editon'];

        $this->prepare_report($output);

        if ($this->table) {
            $tableoutput = $this->table->render($source->get_pagesize(), false, $this->related['page'], $this->related['editon']);
        } else {
            $nocolumnsurl = $output->image_url('no-columns', 'tool_reportbuilder')->out();
            $tableoutput = $output->render_from_template(
                'tool_reportbuilder/no-columns',
                ['nocolumnsurl' => $nocolumnsurl]);
        }

        if (!empty($this->related['tableonly'])) {
            // For fragment we only need to return table. In this case do not evaluate other properties
            // and do not render filter/conditions forms because they add javascript to the page.
            return ['table' => $tableoutput];
        }

        $nosortablecolumnsurl = $output->image_url('no-sortable', 'tool_reportbuilder')->out();
        $nofiltersurl = $output->image_url('no-filters', 'tool_reportbuilder')->out();

        $context = \context_system::instance();
        $canedit = has_capability('tool/reportbuilder:edit', $context);

        // Helps.
        $conditionshelp = new \help_icon('conditionshelp', 'tool_reportbuilder');
        $filtershelp = new \help_icon('filtershelp', 'tool_reportbuilder');
        $sortingshelp = new \help_icon('sortingshelp', 'tool_reportbuilder');
        $availablefilters = $editon ? $source->get_filters_select($this->filtersinuse) : '';
        $availableconditions = $source->get_conditions_select($this->conditionsinuse);
        $availablecolumns = $source->get_columns_select($this->columnsdata);
        // TODO SP-422 do not evaluate available filters/conditions/columns, help strings, etc unless editing.

        return [
            'columnsinuse' => $this->columnsdata,
            'sortablecolumns' => $this->columnssorting ? array_values($this->columnssorting) : '',
            'availablecolumns' => $availablecolumns,
            'table' => $tableoutput,
            'filtersform' => !$editon ? $this->filtering->display_active() : '',
            'conditionsform' => $editon ? $this->conditions->display_active() : '',
            'availablefilters' => $availablefilters,
            'hasavailablefilters' => $availablefilters ? true : false,
            'hassortablecolumns' => $this->columnssorting ? true : false,
            'availableconditions' => $availableconditions,
            'hasavailableconditions' => $availableconditions ? true : false,
            'filtersinuse' => array_values($this->filtersinuse),
            'hasfiltersselected' => $this->filtersinuse ? true : false,
            'hasconditionsselected' => $this->conditionsinuse ? true : false,
            'nofiltersurl' => $nofiltersurl,
            'nocolumnsurl' => $nosortablecolumnsurl,
            'noconditionsurl' => $nofiltersurl,
            'hascolumns' => true && $editon,
            'hassidebar' => true,
            'editon' => $editon,
            'canaddfilters' => ($editon && $canedit) ? true : false,
            'conditionshelp' => $conditionshelp->export_for_template($output),
            'filtershelp' => $filtershelp->export_for_template($output),
            'sortingshelp' => $sortingshelp->export_for_template($output),
            'reportid' => $source->get_id()
        ];
    }

    /**
     * Get the filters exported.
     *
     * @param array $filtersinuse Current filters in use
     * @param \tool_reportbuilder\report_filter[] $filters All filters definition
     * @return array
     * @throws \coding_exception
     */
    private function get_filters(array $filtersinuse, $filters) : array {
        return filters_helper::get_filters_with_data($filtersinuse, $filters);
    }

    /**
     * HTML attributes for the column
     * @param report_column $column
     * @param \stdClass $columndata
     * @return array
     */
    protected function get_column_attributes(report_column $column, \stdClass $columndata) {
        $attributes = $column->get_attributes();
        $attributes['data-source'] = $column->get_unique_identifier();
        $type = $column->get_type();
        $aggr = $columndata->aggregate;
        if ($aggr) {
            $attributes['data-aggregation'] = $aggr;
        }
        if ($aggr === 'groupconcat' || $aggr === 'groupconcatdistinct') {
            $type = 'text';
        } else if ($aggr === 'count' || $aggr === 'countdistinct' || $aggr === 'percent' || $type == constants::DB_TYPE_NUMBER) {
            $type = 'numeric';
        } else if ($type == constants::DB_TYPE_BOOLEAN) {
            $type = 'boolean';
        } else if ($type == constants::DB_TYPE_DATETIME || $type == constants::DB_TYPE_TIMESTAMP) {
            $type = 'date';
        } else {
            $type = 'text';
        }
        $attributes['data-type'] = $type;
        return $attributes;
    }

    /**
     * Prepares the report table.
     *
     * @param array $columnsdata the columns of the report after export
     * @param array $extrajoins
     * @param string $extrasql
     * @param array $extraparams
     *
     * @return report_table|null
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function prepare_table($columnsdata, $extrajoins, $extrasql, array $extraparams): ?report_table {
        global $PAGE;

        /** @var report_base $source */
        $source = $this->related['source'];

        if (!$columnsdata) {
            return null;
        }

        $tableuniqid = $this->persistent->get('idnumber');
        $table = new \tool_reportbuilder\report_table($tableuniqid);
        $table->is_downloading(optional_param('download', 0, PARAM_ALPHA), str_replace(' ', '_', $source->get_reportname()));

        $fields = [];
        $columns = [];
        $columnsaggre = [];
        $sourcecolumns = [];
        $headers = [];
        $originalheaders = [];
        $editableheaders = [];
        $aggregationheaders = [];
        $params = [];
        $joins = [];
        $sort = [];
        $columnsattributes = [];

        // If at least one field has aggregation set, we will need to add GROUP BY.
        $hasaggregation = false;
        foreach ($columnsdata as $columndata) {
            $hasaggregation = $hasaggregation || !empty($columndata->aggregate);
        }

        $columnsdefinition = $source->get_columns();
        // Add fields that are necessary for the columns display.
        foreach ($columnsdata as $columndata) {
            if ($columndata->hidden || !array_key_exists($columndata->key, $columnsdefinition)) {
                continue;
            }
            $actualaggregation = $columndata->aggregate ?: ($hasaggregation ? 'unique' : '');

            $column = $columnsdefinition[$columndata->key];

            if ($column->get_is_available() || $this->related['editon']) {
                $columnkey = count($columns);
                $fields[] = $column->get_fields_sql($actualaggregation, $column->get_type(), $columnkey);
                $columns[] = $column->get_unique_identifier();
                $columnsaggre[] = $columndata->aggregate;
                $columnsattributes[] = $this->get_column_attributes($column, $columndata);
                $sourcecolumns[$column->get_unique_identifier()] = $column;
                $headers[$columndata->id] = $columndata->formattedheading;
                if ($this->related['editon']) {
                    $originalheaders[$columndata->id] = columns_helper::get_original_header($source, $column);
                    $editableheaders[$columndata->id] = columns_helper::get_header_inplace_editable($columndata->formattedheading,
                        $columndata->heading, $columndata->id);
                    $aggregationheaders[$columndata->id] = aggregation_helper::get_aggregation_inplace_editable(
                        $columndata->aggregate,
                        $columndata->id,
                        $columndata->formattedheading,
                        $column->get_type(),
                        $column->get_disabled_aggregations()
                    );
                }
                foreach ($column->get_joins() as $columnjoin) {
                    $joins[$columnjoin] = $columnjoin;
                }
                if ($columndata->sortenabled && $column->get_is_sortable($columndata->aggregate)) {
                    $sort[$columndata->sortorder] = $column->get_sort_sql(
                        ($columndata->sortdirection == SORT_DESC) ? SORT_DESC : SORT_ASC,
                        $columndata->aggregate,
                        $columnkey
                    );
                }
                $params = array_merge($params, $column->get_params($columnkey));
            }
        }

        // Add fields that are necessary for the actions.
        if ($source instanceof \tool_reportbuilder\system_report) {
            if ($sql = $source->get_base_fields()) {
                $fields[] = $sql;
            }
        }

        $extraparams = array_merge($extraparams, $params);

        $finaljoins = array_merge($extrajoins, $joins);

        // Clear the repeated joins.
        // TODO do better after MVP.
        array_change_key_case($finaljoins, CASE_LOWER);
        $finaljoins = array_map('strtolower', $finaljoins);
        $finaljoins = array_unique($finaljoins);

        $table->set_sql(
            implode(',', $fields),
            "{" . $source->get_main_table() . "} " . $source->get_main_table_alias() . " " . implode(" ", $finaljoins),
            $extrasql,
            $extraparams
        );

        $table->sortable(true);

        if ($source->has_actions()) {
            // TODO SP-422 for system reports only?
            $columns[] = 'actions';
            $headers[0] = $source->get_show_actions_header() ? get_string('actions', 'tool_reportbuilder') : '';
            $columnsattributes[] = ['data-source' => 'actions'];
        }

        $table->define_actions($source->get_actions());
        $table->define_columns($columns);
        $table->define_columns_aggregate($columnsaggre);
        $table->define_columns_attributes($columnsattributes);
        $table->define_headers($headers);
        $table->define_sourcecolumns($sourcecolumns);
        $table->define_editableheaders($editableheaders);
        $table->define_originalheaders($originalheaders);
        $table->define_aggregationheaders($aggregationheaders);
        $table->define_sort($sort);
        $table->define_baseurl($PAGE->url->out(false));
        $table->pageable(false);
        $table->collapsible(true);
        $table->is_persistent(false);
        $table->column_suppress('id');
        $table->is_downloadable($this->related['editon'] ? false : $source->is_downloadable());
        $extraclass = $this->related['editon'] ? ' table-bordered' : '';

        $tableclasses = [
            'ui-droppable',
            'report-table',
            $extraclass
        ];
        $table->set_attribute('class', '');
        $extrattributes = $source->get_attributes();
        foreach ($extrattributes as $keyattribute => $extrattribute) {
            $table->set_attribute($keyattribute, $extrattribute);
        }
        $table->set_attribute('data-region', 'report-table');
        $currentclasses = explode(" ", $table->attributes['class']);
        $table->set_attribute('class', implode(' ', $currentclasses + $tableclasses));

        if ($source instanceof \tool_reportbuilder\system_report) {
            $table->set_row_callback([$source, 'row_callback']);
            $table->set_row_class_callback([$source, 'get_row_class']);
        }

        return $table;
    }

    /**
     * Get the report table
     *
     * @return report_table
     */
    public function get_table() {
        return $this->table;
    }
}