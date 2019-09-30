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
 * Plugin callbacks.
 *
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use \tool_reportbuilder\local\helpers\filters as filters_helper;

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
 * Inplace editable for table headers.
 *
 * @package tool_reportbuilder
 *
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 *
 * @return \core\output\inplace_editable | false
 * @throws coding_exception
 * @throws dml_exception
 */
function tool_reportbuilder_inplace_editable($itemtype, $itemid, $newvalue) {
    external_api::validate_context(context_system::instance());

    if ($itemtype === 'reportname') {
        $report = \tool_reportbuilder\manager::get_report($itemid); // Todo: move to helper.
        \tool_reportbuilder\permission::require_can_edit($report);
        \tool_reportbuilder\manager::update_report((object)['id' => $itemid, 'name' => $newvalue]);
        $reportbuilder = new \tool_reportbuilder\reportbuilder($itemid);
        return \tool_reportbuilder\manager::get_name_inplace_editable($reportbuilder->get('name'), $reportbuilder->get('id'), true);
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
        $column = \tool_reportbuilder\local\helpers\aggregation::set_aggregation($itemid, $newvalue);
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
 * @return mixed
 * @throws coding_exception
 */
function tool_reportbuilder_output_fragment_filters_form(array $args) {
    // TODO SP-422 missing access validation.
    $report = \tool_reportbuilder\manager::get_report($args['reportid']);
    $activefilters = filters_helper::get_active_filters($report->get_id());
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
 * @return array
 */
function tool_reportbuilder_potential_users_selector(string $area) {
    if (!in_array($area, ['usercreator', 'usersmanually'])) {
        return null;
    }
    \tool_reportbuilder\permission::require_can_manage_schedules();
    list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u');
    return [$join, $where, $params];
}