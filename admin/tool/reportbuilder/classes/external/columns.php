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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

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
use tool_reportbuilder\permission;
use tool_reportbuilder\reportbuilder_column;

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


    /**
     * Describes the parameters for sort_table_by_heading webservice.
     *
     * @return \external_function_parameters
     */
    public static function sort_table_by_heading_parameters() {
        return new \external_function_parameters(
            [
                'reportid'          => new \external_value(PARAM_INT, 'The ID of the report'),
                'sortcolumn'        => new \external_value(PARAM_TEXT, 'The name of the column'),
                'sortorder'         => new \external_value(PARAM_INT, 'The order type')
            ]
        );
    }

    /**
     * Sort report table by heading.
     *
     * @param int $reportid
     * @param string $sortcolumn
     * @param int $sortorder
     */
    public static function sort_table_by_heading($reportid, $sortcolumn, $sortorder) {
        $params = self::validate_parameters(self::sort_table_by_heading_parameters(),
            [
                'reportid' => $reportid,
                'sortcolumn' => $sortcolumn,
                'sortorder' => $sortorder
            ]
        );

        $context = \context_system::instance();
        self::validate_context($context);

        $report = manager::get_report($params['reportid']);
        if (!permission::is_system_report($report)) {
            permission::require_can_view($report);
        }

        $sortpreferences = $report->get_sort_preferences();

        // If key exists switch direction and remove it first.
        if (array_key_exists($params['sortcolumn'], $sortpreferences)) {
            $sortorder = $sortpreferences[$params['sortcolumn']] == SORT_ASC ? SORT_DESC : SORT_ASC;
            unset($sortpreferences[$params['sortcolumn']]);
        } else {
            $sortorder = $params['sortorder'];
        }

        // Add the new key and make sure that no more than 2 are present into the array.
        $sortpreferences = array_slice(
            array_merge([$params['sortcolumn'] => $sortorder], $sortpreferences),
            0,
            2
        );

        $report->set_sort_preferences($sortpreferences);
    }

    /**
     * Returns description of method sort_table_by_headings.
     *
     * @return null
     */
    public static function sort_table_by_heading_returns() {
        return null;
    }
}
