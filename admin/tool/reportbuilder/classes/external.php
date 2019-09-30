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
 * Class containing the external API functions functions for the Report Builder tool.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use tool_reportbuilder\event\report_updated;
use tool_reportbuilder\local\filter\report_filtering;
use tool_reportbuilder\local\helpers\columns as columns_helper;
use tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_reportbuilder\output\reportbuilder_column_exporter;
use tool_reportbuilder\output\reportbuilder_filter_exporter;
use tool_reportbuilder\local\report\reportbuilder_filter;

/**
 * Class external.
 *
 * The external API for the Report Builder tool.
 *
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Returns description of method add_report_column.
     *
     * @return external_value
     */
    public static function add_report_column_returns() {
        return new external_value(PARAM_BOOL, 'Result of the operation');
    }

    /**
     * Returns description of method remove_report_column.
     *
     * @return external_value
     */
    public static function remove_report_column_returns() {
        return new external_value(PARAM_BOOL, 'Result of the operation');
    }

    /**
     * Delete report filter parameters.
     *
     * @return external_function_parameters
     */
    public static function delete_report_filter_parameters() {
        return new external_function_parameters(
            array(
                'reportid' => new external_value(PARAM_INT, 'The report id to add the filter'),
                'filterid' => new external_value(PARAM_RAW, 'The filter id to delete')
            )
        );
    }

    /**
     * Delete a filter from a report
     *
     * @param int $reportid
     * @param int $filterid
     *
     * @return array
     * @throws \Exception
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     */
    public static function delete_report_filter($reportid, $filterid) {
        global $PAGE;
        $params = self::validate_parameters(self::delete_report_filter_parameters(),
            [
                'reportid' => $reportid,
                'filterid' => $filterid,
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);
        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);

        $filterpersistent = new reportbuilder_filter($params['filterid'], null);

        if ((int)$filterpersistent->get('reportid') !== (int)$params['reportid']) {
            throw new \Exception('The filter report id does not match the report id');
        }

        $filterpersistent->delete();

        $activefilters = filters_helper::get_active_filters($report->get_id());
        $reportfilters = $report->get_filters();
        $filtersinuse = filters_helper::get_filters_with_data($activefilters, $reportfilters);

        $availablefilters = $report->get_filters_select($filtersinuse);

        $output = $PAGE->get_renderer('tool_reportbuilder');
        return array(
            'filtersinuse'     => $filtersinuse,
            'availablefilters' => $availablefilters,
            'nofiltersurl' => $output->image_url('no-filters', 'tool_reportbuilder')->out(),
            'hasfiltersselected' => count($filtersinuse) ? true : false,
            'hasavailablefilters' => $availablefilters ? true : false,
        );
    }

    /**
     * Returns description of method delete_report_filter.
     *
     * @return \external_single_structure
     */
    public static function delete_report_filter_returns() {
        return new \external_single_structure(array(
            'filtersinuse'     => new \external_multiple_structure(
                reportbuilder_filter_exporter::get_read_structure()
            ),
            'availablefilters' => new \external_multiple_structure(
                new \external_single_structure([
                        'optiongroup' => new \external_single_structure([
                            'text'   => new external_value(PARAM_RAW, 'The filter in order by ID'),
                            'values' => new \external_multiple_structure(
                                new \external_single_structure(
                                    array(
                                        'value'       => new \external_value(PARAM_TEXT, 'Filter key'),
                                        'visiblename' => new external_value(PARAM_TEXT, 'Filter visible name')
                                    )
                                )
                            )
                        ])
                    ]
                )
            ),
            'nofiltersurl' => new external_value(PARAM_URL, 'URL of the no filter icon'),
            'hasfiltersselected' => new external_value(PARAM_BOOL, 'If the report have filters selected'),
            'hasavailablefilters' => new external_value(PARAM_BOOL, 'If have selectable filters')
        ));
    }

    /**
     * Describes the parameters for reorder_report_filters.
     *
     * @return external_function_parameters
     */
    public static function reorder_report_filters_parameters() {
        return new external_function_parameters(
            array(
                'reportid'       => new external_value(PARAM_INT, 'The report id to add the filter'),
                'filtersinorder' => new external_value(PARAM_RAW, 'The filter in order by ID')
            )
        );
    }

    /**
     * Reorder the report filters.
     *
     * @param int $reportid
     * @param int $filtersid
     *
     * @return array
     * @throws \Exception
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     */
    public static function reorder_report_filters($reportid, $filtersid) {
        global $PAGE;
        $params = self::validate_parameters(self::reorder_report_filters_parameters(),
            [
                'reportid'       => $reportid,
                'filtersinorder' => $filtersid,
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);
        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);

        $filtersorders = json_decode($params['filtersinorder']);

        foreach ($filtersorders as $key => $value) {
            $filterpersistent = new reportbuilder_filter($value, null);
            if ((int)$filterpersistent->get('reportid') !== (int)$params['reportid']) {
                throw new \Exception('The filter does not match the report id');
            }
            $filterpersistent->set('sortorder', $key);
            $filterpersistent->update();
        }

        $activefilters = filters_helper::get_active_filters($report->get_id());
        $reportfilters = $report->get_filters();
        $filtersdata = filters_helper::get_filters_with_data($activefilters, $reportfilters);

        // Trigger report updated event.
        $event = report_updated::create_from_object($report->get_persistent());
        $event->trigger();

        return array(
            'filtersinuse' => $filtersdata,
            'hasfiltersselected' => true
        );
    }

    /**
     * Returns description of method  reorder_report_filters
     *
     * @return external_function_parameters|null
     */
    public static function reorder_report_filters_returns() {
        return new external_function_parameters(
            array(
                'filtersinuse' => new \external_multiple_structure(
                    reportbuilder_filter_exporter::get_read_structure()
                ),
                'hasfiltersselected' => new external_value(PARAM_BOOL, 'The report has filters'),
            )
        );
    }

    /**
     * Add a column to the report.
     *
     * @param int $reportid
     * @param string $columnkey
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \restricted_context_exception
     */
    public static function add_report_column(int $reportid, string $columnkey) {
        $params = self::validate_parameters(self::add_report_column_parameters(),
            [
                'reportid'  => $reportid,
                'columnkey' => $columnkey,
            ]
        );

        self::validate_context(context_system::instance());

        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);
        columns_helper::add_column_from_key($report, $params['columnkey']);

        return true;
    }

    /**
     * Describes the parameters for add_report_column.
     *
     * @return external_function_parameters
     */
    public static function add_report_column_parameters() {
        return new external_function_parameters(
            array(
                'reportid'  => new external_value(PARAM_INT, 'The report id to add the column'),
                'columnkey' => new external_value(PARAM_RAW, 'The column key to add')
            )
        );
    }

    /**
     * Remove a column from a report.
     *
     * @param int $reportid
     * @param int $columnid
     *
     * @return bool
     * @throws \Exception
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     */
    public static function remove_report_column($reportid, $columnid) {
        $params = self::validate_parameters(self::remove_report_column_parameters(),
            [
                'reportid' => $reportid,
                'columnid' => $columnid
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);

        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);

        columns_helper::remove_column($report, $params['columnid']);

        return true;
    }

    /**
     * Describes the parameters for remove_report_column.
     *
     * @return external_function_parameters
     */
    public static function remove_report_column_parameters() {
        return new external_function_parameters(
            array(
                'reportid' => new external_value(PARAM_INT, 'The report id to remove the column'),
                'columnid' => new external_value(PARAM_RAW, 'The column id to remove')
            )
        );
    }

    /**
     * Describes the parameters for get_report_columns.
     *
     * @return external_function_parameters
     */
    public static function get_report_sortable_columns_parameters() {
        return new external_function_parameters(
            array(
                'reportid'  => new external_value(PARAM_INT, 'The report id to get the columns')
            )
        );
    }

    /**
     * Get the active report columns.
     *
     * @param int    $reportid
     *
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     */
    public static function get_report_sortable_columns($reportid) {
        global $PAGE;
        $params = self::validate_parameters(self::get_report_sortable_columns_parameters(),
            [
                'reportid' => $reportid,
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);

        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);
        $output = $PAGE->get_renderer('tool_reportbuilder');

        list($columnsdata, $columnssorting) = columns_helper::export($report, $output);

        $nocolumnsurl = $output->image_url('no-sortable', 'tool_reportbuilder')->out();
        $hascolumns = count($columnssorting) ? true : false;

        return array(
            'columnsinuse' => $columnssorting,
            'nocolumnsurl' => $nocolumnsurl,
            'hassortablecolumns' => $hascolumns
        );
    }

    /**
     * Return definition for get_report_columns service.
     *
     * @return external_function_parameters
     */
    public static function get_report_sortable_columns_returns() {
        return new external_function_parameters(
            array (
                'columnsinuse'  => new \external_multiple_structure(
                    reportbuilder_column_exporter::get_read_structure()
                ),
                'nocolumnsurl' => new external_value(PARAM_URL, 'Url of the no columns image'),
                'hassortablecolumns' => new external_value(PARAM_BOOL, 'If has sortable columns')
            )
        );
    }

    /**
     * Enable/disable a column sorting parameters.
     *
     * @return external_function_parameters
     */
    public static function toggle_report_sorting_column_parameters() {
        return new external_function_parameters(
            array(
                'reportid' => new external_value(PARAM_INT, 'The report id'),
                'columnid' => new external_value(PARAM_RAW, 'The column id to toggle')
            )
        );
    }
    /**
     * Enable/disable a column sorting.
     *
     * @param int $reportid
     * @param int $columnid
     *
     * @return array
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \restricted_context_exception
     */
    public static function toggle_report_sorting_column($reportid, $columnid) {
        $params = self::validate_parameters(self::toggle_report_sorting_column_parameters(),
            [
                'reportid' => $reportid,
                'columnid' => $columnid
            ]
        );

        self::validate_context(context_system::instance());

        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);

        $persistent = new reportbuilder_column($params['columnid']);
        if ((int)$persistent->get('reportid') != $params['reportid']) {
            throw new \Exception('The column does not match the report id');
        }
        $currentvalue = $persistent->get('sortenabled');
        $newvalue = ($currentvalue == 1) ? 0 : 1;
        $persistent->set('sortenabled', $newvalue);
        $persistent->update();

        // Trigger report updated event.
        $event = report_updated::create_from_object($report->get_persistent());
        $event->trigger();

        return [
            'newcolumnstate' => $newvalue
        ];
    }

    /**
     * Enable/disable a column sorting return.
     *
     * @return \external_single_structure
     */
    public static function toggle_report_sorting_column_returns() {
        return new \external_single_structure([
            'newcolumnstate' => new external_value(PARAM_INT, 'The new column visibility status', VALUE_REQUIRED)
        ]);
    }

    /**
     * Describes the parameters for toggle_column_sorting_direction.
     */
    public static function toggle_column_sorting_direction_parameters() {
        return new external_function_parameters(
            array(
                'reportid' => new external_value(PARAM_INT, 'The report id'),
                'columnid' => new external_value(PARAM_RAW, 'The column id to toggle')
            )
        );
    }

    /**
     * Toggle the direction of the column (asc/desc)
     *
     * @param int $reportid
     * @param int $columnid
     *
     * @return array
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \restricted_context_exception
     */
    public static function toggle_column_sorting_direction($reportid, $columnid) {
        $params = self::validate_parameters(self::toggle_column_sorting_direction_parameters(),
            [
                'reportid' => $reportid,
                'columnid' => $columnid
            ]
        );

        self::validate_context(context_system::instance());

        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);

        $persistent = new reportbuilder_column($params['columnid']);
        if ((int)$persistent->get('reportid') != $params['reportid']) {
            throw new \Exception('The column does not match the report id');
        }
        $currentvalue = $persistent->get('sortdirection');
        $newvalue = $currentvalue == SORT_ASC ? SORT_DESC : SORT_ASC;
        $persistent->set('sortdirection', $newvalue);
        $persistent->update();

        // Trigger report updated event.
        $event = report_updated::create_from_object($report->get_persistent());
        $event->trigger();

        return [
            'newcolumnstate' => $newvalue
        ];
    }

    /**
     * Returns description of method toggle_column_sorting_direction.
     *
     * @return \external_single_structure
     */
    public static function toggle_column_sorting_direction_returns() {
        return new \external_single_structure([
            'newcolumnstate' => new external_value(PARAM_INT, 'The new column order status', VALUE_REQUIRED)
        ]);
    }

    /**
     * Describes the parameters for reorder_sortable_column webservice.
     *
     * @return external_function_parameters
     */
    public static function reorder_sortable_column_parameters() {
        return new external_function_parameters(
            array(
                'reportid'       => new external_value(PARAM_INT, 'The report id to add the filter'),
                'columnsinorder' => new external_value(PARAM_RAW, 'The fields in order by column id.')
            )
        );
    }

    /**
     * Reorder the report columnns.
     *
     * @param int $reportid
     * @param int $columnsid
     *
     * @throws \Exception
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     */
    public static function reorder_sortable_column($reportid, $columnsid) {
        global $PAGE;
        $params = self::validate_parameters(self::reorder_sortable_column_parameters(),
            [
                'reportid'       => $reportid,
                'columnsinorder' => $columnsid,
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);

        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);

        $columnsinorder = json_decode($params['columnsinorder']);

        foreach ($columnsinorder as $key => $value) {
            $columnapersistent = new reportbuilder_column($value, null);
            if ((int)$columnapersistent->get('reportid') !== (int)$params['reportid']) {
                throw new \Exception('The filter does not match the report id');
            }
            $columnapersistent->set('sortorder', $key);
            $columnapersistent->save();
        }
    }

    /**
     * Returns description of method reorder_sortable_column.
     *
     * @return external_function_parameters
     */
    public static function reorder_sortable_column_returns() {
        return null;
    }
}