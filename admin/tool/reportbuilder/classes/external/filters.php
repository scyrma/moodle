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
 * Class containing all services related to filters.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use tool_reportbuilder\manager;
use tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_reportbuilder\output\reportbuilder_filter_exporter;
use tool_reportbuilder\permission;

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * Class external.
 *
 * The external API for the filters services.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filters extends external_api {

    /**
     * Parameters definition for reset_all service.
     *
     * @return external_function_parameters
     */
    public static function reset_all_parameters() {
        return new external_function_parameters(
            array(
                'reportid'  => new external_value(PARAM_INT, 'The report id to add the filter')
            )
        );
    }

    /**
     * Reset all filters for the given report and the current user.
     *
     * @param int $reportid
     * @return bool
     * @throws \coding_exception
     * @throws \invalid_parameter_exception
     */
    public static function reset_all(int $reportid) : string {
        $params = self::validate_parameters(self::reset_all_parameters(),
            [
                'reportid'  => $reportid
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);

        $report = manager::get_report($params['reportid']);
        if (!permission::is_system_report($report)) {
            permission::require_can_view($report);
        }

        $filterhelper = new filters_helper($report->get_id());
        return $filterhelper->reset_all();
    }

    /**
     * Return parameters definition for reset_all service.
     *
     * @return external_value
     */
    public static function reset_all_returns() {
        return new external_value(PARAM_BOOL, 'Whether the filters were reset');
    }


    /**
     * Parameters definition to reset_filter service.
     *
     * @return external_function_parameters
     */
    public static function reset_filter_parameters() {
        return new external_function_parameters(
            array(
                'reportid'  => new external_value(PARAM_INT, 'The report id to add the filter'),
                'filterid' => new external_value(PARAM_INT, 'The filter to set')
            )
        );
    }

    /**
     * Reset the given filter for the current user.
     *
     * @param int $reportid The report to reset the filter.
     * @param int $filterid The filter to be reset.
     * @return bool
     * @throws \coding_exception
     * @throws \invalid_parameter_exception
     */
    public static function reset_filter(int $reportid, int $filterid) {
        $params = self::validate_parameters(self::reset_filter_parameters(),
            [
                'reportid'  => $reportid,
                'filterid'  => $filterid
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);

        $report = manager::get_report($params['reportid']);
        if (!permission::is_system_report($report)) {
            permission::require_can_view($report);
        }

        $filterhelper = new filters_helper($report->get_id());
        return $filterhelper->reset_filter($params['filterid']);
    }

    /**
     * Return parameters definition for reset_filter service.
     *
     * @return external_value
     */
    public static function reset_filter_returns() {
        return new external_value(PARAM_BOOL, 'Whether the filter was reset');
    }

    /**
     * Describes the parameters for add_report_filter webservice.
     *
     * @return external_function_parameters
     */
    public static function add_filter_parameters() {
        return new external_function_parameters(
            array(
                'reportid'  => new external_value(PARAM_INT, 'The report id to add the filter'),
                'filterkey' => new external_value(PARAM_RAW, 'The filter key to add')
            )
        );
    }

    /**
     * Add a new filter to the report.
     *
     * @param int $reportid Id of the report to add the filter.
     * @param string $filterkey The key of the filter to add.
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \restricted_context_exception
     */
    public static function add_filter(int $reportid, string $filterkey) : array {
        $params = self::validate_parameters(self::add_filter_parameters(),
            [
                'reportid'  => $reportid,
                'filterkey' => $filterkey,
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);

        $report = manager::get_report($params['reportid']);
        if (!permission::is_system_report($report)) {
            permission::require_can_view($report);
        }

        $filtersdefinitions = $report->get_filters();
        $filter = $filtersdefinitions[$params['filterkey']]; // TODO SP-422 throw exception if not found.

        filters_helper::add_filter($report->get_id(), $filter);

        $activefilters = filters_helper::get_active_filters($report->get_id());
        $reportfilters = $report->get_filters();
        $filtersinuse = filters_helper::get_filters_with_data($activefilters, $reportfilters);

        return [
            'filtersinuse'     => $filtersinuse,
            'availablefilters' => $report->get_filters_select($filtersinuse),
            'hasfiltersselected' => !empty($filtersinuse) ? true : false,
        ];
    }

    /**
     * Returns description of method add_report_filter.
     *
     * @return \external_single_structure
     */
    public static function add_filter_returns() {
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
            'hasfiltersselected' => new external_value(PARAM_BOOL, 'Has filters selected')
        ));
    }
}