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
 * Plugin callbacks.
 *
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

use \tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_tenant\tenancy;

/**
 * Fragment to update a report basic info.
 *
 * @param array $args List of named arguments for the fragment loader.
 *
 * @return string
 * @throws \core\invalid_persistent_exception
 * @throws coding_exception
 */
function tool_reportbuilder_output_fragment_submit_basicinfo_form($args) {

    $formdata = [];
    if (!empty($args['jsonformdata'])) {
        $serialiseddata = json_decode($args['jsonformdata']);
        parse_str($serialiseddata, $formdata);
    }

    $mform = new \tool_reportbuilder\form\detail(null, [],
        'post', '', array('class' => 'details'), true, $formdata);

    if ($mform->get_data() && $mform->is_validated()) {
        $formdata['id'] = $mform->process();
        return  \tool_reportbuilder\manager::get_detail_form($formdata['id']);
    }

    return $mform->render();
}

/**
 * Get icon mapping for font-awesome.
 *
 * @package tool_reportbuilder
 */
function tool_reportbuilder_get_fontawesome_icon_map() {
    return [
        'tool_reportbuilder:edit' => 'fa-cog text-muted',
        'tool_reportbuilder:switch_minus' => 'fa-switch_minus text-muted',
        'tool_reportbuilder:cancel' => 'fa-times text-muted',
        'tool_reportbuilder:move' => 'fa-arrows text-muted',
    ];
}

/**
 * Plugin inplace editable implementation
 *
 * Note that the only place that requires cleaning of the new value is the "report name" - the rest are either defined as
 * PARAM_RAW in the persistent (column/filter/condition heading) or handled internally (aggregation type)
 *
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 * @return \core\output\inplace_editable|bool
 */
function tool_reportbuilder_inplace_editable($itemtype, $itemid, $newvalue) {
    external_api::validate_context(context_system::instance());

    if ($itemtype === 'reportname') {
        $report = \tool_reportbuilder\manager::get_report($itemid); // Todo: move to helper.
        \tool_reportbuilder\permission::require_can_edit($report);

        // Clean new value according to persistent field type.
        $newvalue = clean_param($newvalue, PARAM_TEXT);
        \tool_reportbuilder\manager::update_report((object)['id' => $itemid, 'name' => $newvalue]);

        $reportbuilder = new \tool_reportbuilder\reportbuilder($itemid);
        return \tool_reportbuilder\manager::get_name_inplace_editable($reportbuilder->get('name'), $reportbuilder->get('id'),
            true, true);
    }

    if ($itemtype === 'columnname') {
        $columnpersistent = new \tool_reportbuilder\reportbuilder_column($itemid, null); // Todo: move to helper.
        $report = \tool_reportbuilder\manager::get_report($columnpersistent->get('reportid'));
        \tool_reportbuilder\permission::require_can_edit($report);
        $columnpersistent->set('heading', $newvalue);
        $columnpersistent->save();
        $newvalue = $columnpersistent->get('heading');
        $reportcolumn = $report->get_columns()[$columnpersistent->get_unique_identifier()];
        $formatedheader = \tool_reportbuilder\local\helpers\columns::get_formatted_header($reportcolumn, $newvalue);
        return \tool_reportbuilder\local\helpers\columns::get_header_inplace_editable($formatedheader, $newvalue, $itemid);
    }

    if ($itemtype === 'filtername') {
        $columnpersistent = new \tool_reportbuilder\local\report\reportbuilder_filter($itemid, null); // Todo: move to helper.
        $report = \tool_reportbuilder\manager::get_report($columnpersistent->get('reportid'));
        \tool_reportbuilder\permission::require_can_edit($report);
        $columnpersistent->set('heading', $newvalue);
        $columnpersistent->save();
        $newvalue = $columnpersistent->get('heading');
        $reportfilter = $report->get_filters()[$columnpersistent->get_unique_identifier()];
        return \tool_reportbuilder\local\helpers\filters::get_header_inplace_editable($reportfilter, $newvalue, $itemid);
    }

    if ($itemtype === 'condition') {
        $columnpersistent = new \tool_reportbuilder\local\models\reportbuilder_conditions($itemid, null); // Todo: move to helper.
        $report = \tool_reportbuilder\manager::get_report($columnpersistent->get('reportid'));
        \tool_reportbuilder\permission::require_can_edit($report);
        $columnpersistent->set('heading', $newvalue);
        $columnpersistent->save();
        $newvalue = $columnpersistent->get('heading');
        $reportcondition = $report->get_conditions()[$columnpersistent->get_unique_identifier()];
        return tool_reportbuilder\local\helpers\conditions::get_header_inplace_editable($reportcondition, $newvalue, $itemid);
    }

    if ($itemtype === 'aggregation') {
        $columnpersistent = new \tool_reportbuilder\reportbuilder_column($itemid, null);
        $report = \tool_reportbuilder\manager::get_report($columnpersistent->get('reportid'));
        \tool_reportbuilder\permission::require_can_edit($report);
        $column = \tool_reportbuilder\local\helpers\aggregation::set_aggregation($columnpersistent->get('id'), $newvalue);
        $formatedheader = \tool_reportbuilder\local\helpers\columns::get_formatted_header($column, $newvalue);
        return \tool_reportbuilder\local\helpers\aggregation::get_aggregation_inplace_editable($newvalue, $itemid,
            $formatedheader, $column->get_type(), $column->get_disabled_aggregations());
    }

    return false;
}

/**
 * Fragment to get the filters form.
 *
 * @param array $args Fragment arguments
 * @return string
 */
function tool_reportbuilder_output_fragment_filters_form(array $args) : string {
    $parameters = json_decode($args['parameters'], true);
    $report = \tool_reportbuilder\manager::get_report($args['reportid'], $parameters ?: []);
    if (!\tool_reportbuilder\permission::is_system_report($report)) {
        \tool_reportbuilder\permission::require_can_view($report);
    }

    $activefilters = $report->get_active_filters();
    $filters = $report->get_filters();
    $filtersinuse = filters_helper::get_filters_with_data($activefilters, $filters);
    $filtering = new \tool_reportbuilder\local\filter\report_filtering($report, false, $filtersinuse);

    return $filtering->display_active();
}

/**
 * Fragment to get the report table
 *
 * @param array $args Fragment arguments
 * @return string
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function tool_reportbuilder_output_fragment_report_table(array $args) : string {
    global $PAGE;
    $url = new \moodle_url('/admin/tool/reportbuilder/view.php', ['id' => $args['reportid']]);
    $PAGE->set_url($url);
    $parameters = json_decode($args['parameters'], true);
    $report = \tool_reportbuilder\manager::get_report($args['reportid'], $parameters ?: [], $args['page']);
    $output = $PAGE->get_renderer('tool_reportbuilder');
    $content = $report->export($output, $args['editon'], true);
    return $content->table;
}

/**
 * Callback for the tool_wp_potential_users_selector web service.
 *
 * @param string $area
 * @param int $itemid
 * @return array
 */
function tool_reportbuilder_potential_users_selector(string $area, int $itemid) {
    if (!in_array($area, ['usercreator', 'usersmanually'])) {
        return null;
    }

    // Item ID will refer to the schedule if we are editing an existing one, otherwise zero.
    if ($itemid) {
        $schedule = \tool_reportbuilder\local\helpers\schedules::get_schedule($itemid);
        \tool_reportbuilder\permission::require_can_edit_schedule($schedule);
    } else {
        \tool_reportbuilder\permission::require_can_create_schedule();
    }

    return ['', tenancy::get_users_subquery(false, false, 'u.id', 0, true), []];
}

/**
 * Callback for tool_wp, return list of role shortnames this component defines.
 */
function tool_reportbuilder_workplace_roles() {
    return ['tool_reportbuilder_manager'];
}
