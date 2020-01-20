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
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\external;

use moodle_url;
use tool_reportbuilder\event\report_viewed;
use tool_reportbuilder\manager;
use tool_reportbuilder\output\report_view;
use tool_reportbuilder\output\reportbuilder_column_exporter;
use tool_reportbuilder\output\reportbuilder_filter_exporter;
use tool_reportbuilder\permission;
use tool_reportbuilder\reportbuilder;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * Class report
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report extends \external_api {

    /**
     * Parameters description for get_reportbuilder service.
     *
     * @return \external_function_parameters
     */
    public static function get_reportbuilder_parameters() {
        return new \external_function_parameters([
            'reportid'  => new \external_value(PARAM_INT, 'The ID of the report to return', VALUE_REQUIRED),
            'editon' => new \external_value(PARAM_BOOL, 'Whether editing mode is enabled', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Main function for get_reportbuilder service.
     *
     * @param int $reportid
     * @param bool $editon
     *
     * @return array|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_reportbuilder(int $reportid, bool $editon) {
        global $PAGE, $OUTPUT;

        $params = self::validate_parameters(self::get_reportbuilder_parameters(), [
            'reportid' => $reportid,
            'editon' => $editon,
        ]);

        $syscontext = \context_system::instance();
        self::validate_context($syscontext);

        $report = manager::get_report($params['reportid']);

        // Hack alert: Set current URL to the view page so that report export always works.
        $PAGE->set_url(new moodle_url('/admin/tool/reportbuilder/view.php', ['id' => $report->get_id()]));

        // Hack alert: Forcing bootstrap_renderer to initiate moodle page.
        $OUTPUT->header();

        $PAGE->start_collecting_javascript_requirements();

        if ($params['editon']) {
            permission::require_can_edit($report);
        } else {
            permission::require_can_view($report);
        }

        $reportview = new report_view($report->get_id(), $params['editon']);

        $output = $PAGE->get_renderer('tool_reportbuilder');
        $context = $reportview->export_for_template($output);
        $context->ispreview = !$params['editon'];
        $context->tabheading = get_string('tabletab', 'tool_reportbuilder');

        if ($context->ispreview) {
            // Trigger report viewed event on preview mode.
            $event = report_viewed::create_from_object($report->get_persistent(), 'preview');
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
        $optiongroup = new \external_multiple_structure(
            new \external_single_structure([
                    'optiongroup' => new \external_single_structure([
                        'key' => new \external_value(PARAM_RAW, 'The key of the groups columns', VALUE_OPTIONAL, ''),
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
        );

        $helpreturn = new \external_single_structure([
            'heading' => new \external_value(PARAM_RAW),
            'text' => new \external_value(PARAM_RAW),
            'alt' => new \external_value(PARAM_RAW),
            'linktext' => new \external_value(PARAM_RAW),
            'title' => new \external_value(PARAM_RAW),
            'url' => new \external_value(PARAM_URL),
            'ltr' => new \external_value(PARAM_RAW),
            'icon' => new \external_single_structure(
                array(
                    'attributes' => new \external_multiple_structure(
                        new \external_single_structure(
                            array(
                                'name' => new \external_value(PARAM_TEXT),
                                'value' => new \external_value(PARAM_TEXT)
                            )
                        )
                    ),
                    'extraclasses' => new \external_value(PARAM_TEXT)
                )
            )
        ]);
        return new \external_single_structure(
            array(
                'name' => new \external_value(PARAM_RAW),
                'idnumber' => new \external_value(PARAM_ALPHANUM),
                'shortname' => new \external_value(PARAM_RAW),
                'source' => new \external_value(PARAM_RAW),
                'type' => new \external_value(PARAM_INT),
                'id' => new \external_value(PARAM_INT),
                'reportid' => new \external_value(PARAM_INT),
                'columnsinuse' => new \external_multiple_structure(reportbuilder_column_exporter::get_read_structure()),
                'sortablecolumns' => new \external_multiple_structure(reportbuilder_column_exporter::get_read_structure()),
                'availablecolumns' => $optiongroup,
                'availablefilters' => $optiongroup,
                'availableconditions' => $optiongroup,
                'table' => new \external_value(PARAM_RAW),
                'filtersform' => new \external_value(PARAM_RAW),
                'filtersinuse'     => new \external_multiple_structure(
                    reportbuilder_filter_exporter::get_read_structure()
                ),
                'conditionsform' => new \external_value(PARAM_RAW),
                'ispreview' => new \external_value(PARAM_BOOL),
                'hasavailablefilters' => new \external_value(PARAM_BOOL),
                'hassortablecolumns' => new \external_value(PARAM_BOOL),
                'hasavailableconditions' => new \external_value(PARAM_BOOL),
                'hasfiltersselected' => new \external_value(PARAM_BOOL),
                'hasconditionsselected' => new \external_value(PARAM_BOOL),
                'hascolumns' => new \external_value(PARAM_BOOL),
                'hassidebar' => new \external_value(PARAM_BOOL),
                'editon' => new \external_value(PARAM_BOOL),
                'canaddfilters' => new \external_value(PARAM_BOOL),
                'tabheading' => new \external_value(PARAM_RAW),
                'javascript' => new \external_value(PARAM_RAW),
                'nofiltersurl' => new \external_value(PARAM_URL),
                'nocolumnsurl' => new \external_value(PARAM_URL),
                'noconditionsurl' => new \external_value(PARAM_URL),
                'conditionshelp' => $helpreturn,
                'filtershelp' => $helpreturn,
                'sortingshelp' => $helpreturn,
                'parameters' => new \external_value(PARAM_RAW),
            )
        );
    }

    /**
     * Parameters for delete report
     *
     * @return \external_function_parameters
     */
    public static function delete_report_parameters() {
        return new \external_function_parameters([
            'reportid' => new \external_value(PARAM_INT, 'The ID of the report to delete', VALUE_REQUIRED),
        ]);
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
        $params = self::validate_parameters(self::delete_report_parameters(), [
            'reportid' => $reportid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $persistent = new reportbuilder($params['reportid']);

        try {
            $report = manager::get_report_from_persistent($persistent);
            permission::require_can_delete($report);
        } catch (\moodle_exception $exception) {
            // Report source no longer exists, allow someone who can manage reports to delete this one.
            permission::require_can_manage_reports($persistent->get('tenantid'));
        }

        return ['result' => $persistent->delete()];
    }

    /**
     * Return for delete report
     *
     * @return \external_single_structure
     */
    public static function delete_report_returns() {
        return new \external_single_structure([
            'result' => new \external_value(PARAM_BOOL, 'Whether the report deletion succeeded'),
        ]);
    }
}