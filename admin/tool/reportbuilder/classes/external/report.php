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
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\external;


use tool_reportbuilder\event\report_viewed;
use tool_reportbuilder\helper;
use tool_reportbuilder\output\report_view;
use required_capability_exception;
use tool_reportbuilder\permission;
use tool_reportbuilder\reportbuilder;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * Class report
 *
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_reportbuilder
 */
class report extends \external_api {

    /**
     * Parameters description for get_reportbuilder service.
     *
     * @return \external_function_parameters
     */
    public static function get_reportbuilder_parameters() {
        return new \external_function_parameters(
            array(
                'reportid'  => new \external_value(PARAM_INT, 'The report id to add the filter'),
                'editon' => new \external_value(PARAM_BOOL, 'If the edition is on.', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Main function for get_reportbuilder service.
     *
     * @param int $reportid
     * @param int $editon
     *
     * @return array|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_reportbuilder(int $reportid, int $editon) {
        global $PAGE, $OUTPUT;

        $PAGE->set_context(\context_system::instance());
        // Hack alert: Set a default URL to stop the annoying debug.
        $PAGE->set_url('/');
        // Hack alert: Forcing bootstrap_renderer to initiate moodle page.
        $OUTPUT->header();

        $PAGE->start_collecting_javascript_requirements();

        $report = new report_view($reportid, $editon);
        $output = $PAGE->get_renderer('tool_reportbuilder');
        $context = $report->export_for_template($output);
        $context->ispreview = $editon ? false : true;
        $context->tabheading = get_string('tabletab', 'tool_reportbuilder');

        if (!$editon) {
            // Trigger report viewed event on preview mode.
            $report = new reportbuilder($reportid);
            $event = report_viewed::create_from_object($report, 'preview');
            $event->trigger();
        }

        $context->javascript = $PAGE->requires->get_end_code();

        return $context;
    }

    /**
     * Parameters description for get_reportbuilder returns.
     *
     * @return \external_single_structure
     */
    public static function get_reportbuilder_returns() {
        // TODO SP-398: complete the return definition.
        return null;
    }

    /**
     * Parameters for delete report
     *
     * @return \external_function_parameters
     */
    public static function delete_report_parameters() {
        return new \external_function_parameters(
                array('reportid' => new \external_value(PARAM_INT, 'The report id for delete.', VALUE_REQUIRED))
        );
    }

    /**
     * Delete report function
     *
     * @param int $reportid The ID of the report to be deleted.
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function delete_report(int $reportid) {
        permission::require_can_delete($reportid);
        return ['result' => helper::delete_report($reportid)];
    }

    /**
     * Return for delete report
     */
    public static function delete_report_returns() {
        return new \external_single_structure(
                ['result' => new \external_value(PARAM_BOOL, '', VALUE_REQUIRED)]
        );
    }
}
