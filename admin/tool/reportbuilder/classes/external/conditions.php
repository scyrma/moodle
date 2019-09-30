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
 * Class containing all services for the conditions.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use tool_reportbuilder\local\filter\report_conditions;
use tool_reportbuilder\local\models\reportbuilder_conditions;
use tool_reportbuilder\manager;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\permission;

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * Class external.
 *
 * The external API for the Report Builder tool.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conditions extends external_api {
    /**
     * Parameters description for add report condition service.
     *
     * @return external_function_parameters
     */
    public static function add_report_condition_parameters() {
        return new external_function_parameters(
            array(
                'reportid'  => new external_value(PARAM_INT, 'The report id to add the filter'),
                'conditionkey' => new external_value(PARAM_RAW, 'The condition key to add')
            )
        );
    }

    /**
     * Add a new filter to the report.
     *
     * @param int $reportid
     * @param int $conditionkey
     *
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     * @throws \moodle_exception
     */
    public static function add_report_condition($reportid, $conditionkey) {
        global $PAGE;
        $params = self::validate_parameters(self::add_report_condition_parameters(),
            [
                'reportid'  => $reportid,
                'conditionkey' => $conditionkey,
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);
        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);

        conditions_helper::add_condition($reportid, $conditionkey);

        $output = $PAGE->get_renderer('tool_reportbuilder');
        return self::conditions_return($params['reportid'], $output);
    }

    /**
     * Service returns.
     *
     * @return \external_single_structure
     */
    public static function add_report_condition_returns() {
        return self::conditions_return_parameters();
    }

    /**
     * Parameter description for delete_condition_parameters.
     *
     * @return external_function_parameters
     */
    public static function delete_condition_parameters() {
        return new external_function_parameters(
            array(
                'conditionid' => new external_value(PARAM_INT, 'The condition id to removed')
            )
        );
    }

    /**
     * Parameter description for delete_condition.
     *
     * @param int $conditionid
     *
     * @return array
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     * @throws \core\invalid_persistent_exception
     */
    public static function delete_condition($conditionid) {
        global $PAGE;
        $params = self::validate_parameters(self::delete_condition_parameters(), ['conditionid' => $conditionid]);

        $context = context_system::instance();
        self::validate_context($context);

        $condition = new reportbuilder_conditions($params['conditionid']);

        $report = manager::get_report($condition->get('reportid'));
        permission::require_can_edit($report);

        conditions_helper::remove_condition($condition->get('id'));

        $output = $PAGE->get_renderer('tool_reportbuilder');
        return self::conditions_return($report->get_id(), $output);
    }

    /**
     * Parameter description for delete_condition_returns.
     *
     * @return null
     */
    public static function delete_condition_returns() {
        return self::conditions_return_parameters();
    }

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
     * @return mixed
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \restricted_context_exception
     */
    public static function reset_all(int $reportid): array {
        global $PAGE;
        $params = self::validate_parameters(self::reset_all_parameters(),
            [
                'reportid'  => $reportid
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);
        $report = manager::get_report($params['reportid']);
        permission::require_can_edit($report);

        $conditionhelper = new conditions_helper($report);
        $conditionhelper->reset_all();

        $output = $PAGE->get_renderer('tool_reportbuilder');
        return self::conditions_return($params['reportid'], $output);
    }

    /**
     * Return parameters definition for reset_all service.
     *
     * @return \external_single_structure
     */
    public static function reset_all_returns() {
        return self::conditions_return_parameters();
    }


    /**
     * Parameters definition to reset_filter service.
     *
     * @return external_function_parameters
     */
    public static function reset_condition_parameters() {
        return new external_function_parameters(
            array(
                'reportid'  => new external_value(PARAM_INT, 'The report id to add the filter'),
                'conditionid' => new external_value(PARAM_INT, 'The condition id to reset')
            )
        );
    }

    /**
     * Reset the given filter for the current user.
     *
     * @param int $reportid The report to reset the filter.
     * @param int $conditionid The id of the condition to reset
     * @return array
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \restricted_context_exception
     */
    public static function reset_condition(int $reportid, int $conditionid) {
        global $PAGE;
        // TODO reportid parameter is not needed.
        $params = self::validate_parameters(self::reset_condition_parameters(),
            [
                'reportid'  => $reportid,
                'conditionid'  => $conditionid
            ]
        );

        $context = context_system::instance();
        self::validate_context($context);
        $persistent = new reportbuilder_conditions($params['conditionid']);
        $reportid = $persistent->get('reportid');
        $report = manager::get_report($reportid);
        permission::require_can_edit($report);

        $condition = new conditions_helper($report);
        $condition->reset($params['conditionid']);

        $output = $PAGE->get_renderer('tool_reportbuilder');
        return self::conditions_return($reportid, $output);
    }

    /**
     * Return parameters definition for reset_filter service.
     *
     * @return \external_single_structure
     */
    public static function reset_condition_returns() {
        return self::conditions_return_parameters();
    }

    /**
     * Common return in all conditions services.
     *
     * @param int $reportid Report ID
     * @param \renderer_base $output
     * @return array
     * @throws \coding_exception
     */
    private static function conditions_return(int $reportid, \renderer_base $output): array {
        global $PAGE, $OUTPUT;

        // Hack alert: Set a default URL to stop the annoying debug.
        $PAGE->set_url('/');
        // Hack alert: Forcing bootstrap_renderer to initiate moodle page.
        $OUTPUT->header();

        $PAGE->start_collecting_javascript_requirements();

        $source = manager::get_report($reportid);
        $activeconditions = conditions_helper::get_active_conditions($source->get_id());
        $conditions = $source->get_conditions();
        $conditionsinuse = conditions_helper::get_conditions($activeconditions, $conditions, $output);
        $conditions = new report_conditions($source, true, $conditionsinuse);
        $availableconditions = $source->get_conditions_select($conditionsinuse);
        $conditionsform = $conditions->display_active();

        $jsfooter = $PAGE->requires->get_end_code();

        return array(
            'conditionsform'     => $conditionsform,
            'availableconditions' => $availableconditions,
            'hasconditionsselected' => !empty($activeconditions) ? true : false,
            'noconditionsurl' => $output->image_url('no-filters', 'tool_reportbuilder')->out(),
            'hasavailableconditions' => $availableconditions ? true : false,
            'javascript' => $jsfooter
        );
    }

    /**
     * Return parameters definition.
     *
     * @return \external_single_structure
     * TODO: make an exporter
     */
    private static function conditions_return_parameters() {
        return new \external_single_structure(array(
            'conditionsform' => new \external_value(PARAM_RAW, 'Conditions form'),
            'availableconditions' => new \external_multiple_structure(
                new \external_single_structure([
                        'optiongroup' => new \external_single_structure([
                            'text'   => new external_value(PARAM_RAW, 'The filter in order by ID'),
                            'values' => new \external_multiple_structure(
                                new \external_single_structure(
                                    array(
                                        'value'       => new \external_value(PARAM_TEXT, 'Condition key'),
                                        'visiblename' => new external_value(PARAM_TEXT, 'Condition visible name')
                                    )
                                )
                            )
                        ])
                    ]
                )
            ),
            'hasconditionsselected' => new external_value(PARAM_BOOL, 'Has filters selected'),
            'noconditionsurl' => new external_value(PARAM_URL, 'URL of the no filter icon'),
            'hasavailableconditions' => new \external_value(PARAM_BOOL,
                'If have available conditions in order to show the select'),
            'javascript' => new \external_value(PARAM_RAW, 'Conditions javascript code'),
        ));
    }
}
