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
 * Class containing all services for the report.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\external;

use tool_reportbuilder\event\report_updated;
use tool_reportbuilder\manager;
use required_capability_exception;
use tool_reportbuilder\output\reportbuilder_column_exporter;
use tool_reportbuilder\permission;
use tool_reportbuilder\reportbuilder_column;
use tool_reportbuilder\local\helpers\columns as columns_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * Class columns
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * @package   tool_reportbuilder
 */
class columns extends \external_api {

    /**
     * Parameters description for get_columns service.
     *
     * @return \external_function_parameters
     */
    public static function get_columns_parameters() {
        return new \external_function_parameters(
            array(
                'reportid' => new \external_value(PARAM_INT, 'The report id to get columns')
            )
        );
    }

    /**
     * Mark the method as deprecated
     *
     * @return bool
     */
    public static function get_columns_is_deprecated() {
        return true;
    }

    /**
     * Return all the columns of the report (active/not active) with the status (already added or not)
     *
     * @deprecated this external function is deprecated
     *
     * @param int $reportid
     * @return array
     */
    public static function get_columns(int $reportid) {
        global $PAGE;

        $params = self::validate_parameters(self::get_columns_parameters(),
            [
                'reportid' => $reportid
            ]
        );

        $context = \context_system::instance();
        self::validate_context($context);

        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);

        $columnsinuse = columns_helper::get_active_columns($report);
        $related = ['reportuniqid' => $report->get_report_uniqid(), 'context' => \context_system::instance()];
        $output = $PAGE->get_renderer('tool_reportbuilder');
        $columnsdata = [];
        $columnscount = [];
        foreach ($columnsinuse as $column) {
            $columnkey = $column->get_unique_identifier();
            isset($columnscount[$columnkey]) ? $columnscount[$columnkey]++ : $columnscount[$columnkey] = 0;
            $columnexporter = new reportbuilder_column_exporter($column, $related +
                [
                    'reportcolumn' => $report->get_column($column->get_unique_identifier()),
                    'columncount' => $columnscount[$columnkey]
                ]
            );
            $columnsdata[] = $columnexporter->export($output);
        }

        $columns = $report->get_columns_select($columnsdata);

        return array(
            'availablecolumns' => $columns
        );
    }

    /**
     * Parameters description for get_columns returns.
     *
     * @return \external_single_structure
     */
    public static function get_columns_returns() {
        return new \external_single_structure(
            array(
                'availablecolumns' => new \external_multiple_structure(
                    new \external_single_structure([
                            'optiongroup' => new \external_single_structure([
                                'key' => new \external_value(PARAM_RAW, 'The key of the groups columns'),
                                'text' => new \external_value(PARAM_RAW, 'The visible name of the groups columns'),
                                'values' => new \external_multiple_structure(
                                    new \external_single_structure(
                                        array(
                                            'value' => new \external_value(PARAM_TEXT, 'Filter key'),
                                            'visiblename' => new \external_value(PARAM_TEXT, 'Filter visible name')
                                        )
                                    )
                                )
                            ])
                        ]
                    )
                )
            )
        );
    }

    /**
     * Parameters description for reorder_columns service.
     *
     * @return \external_function_parameters
     */
    public static function reorder_columns_parameters() {
        return new \external_function_parameters([
            'reportid' => new \external_value(PARAM_INT, 'The ID of the report', VALUE_REQUIRED),
            'columnsinorder' => new \external_value(PARAM_RAW, 'The columns in order by ID'),
        ]);
    }

    /**
     * Reorder columns
     *
     * @param int $reportid The id of the report to reorder the columns
     * @param string $columnsinorder The columns in order by id
     * @return bool
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public static function reorder_columns(int $reportid, string $columnsinorder) {
        $params = self::validate_parameters(self::reorder_columns_parameters(),
            [
                'reportid' => $reportid,
                'columnsinorder' => $columnsinorder
            ]
        );

        $context = \context_system::instance();
        self::validate_context($context);

        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);

        $order = 1;
        $columnsids = json_decode($params['columnsinorder']);
        foreach ($columnsids as $columnid) {
            $persistent = new reportbuilder_column($columnid);
            $persistent->set('columnorder', $order);
            $persistent->update();
            $order++;
        }

        // Trigger report updated event.
        $event = report_updated::create_from_object($report->get_persistent());
        $event->trigger();

        return true;
    }

    /**
     * Return parameters for reorder_columns service.
     *
     * @return \external_value
     */
    public static function reorder_columns_returns() {
        return new \external_value(PARAM_BOOL, 'success');
    }
}