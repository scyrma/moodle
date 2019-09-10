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
 * Callbacks.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Fragment to obtain matching users system_report.
 *
 * @param array $args
 * @return null|string
 */
function tool_dynamicrule_output_fragment_matching_users(array $args) {
    global $PAGE;

    if (empty($args['ruleid'])) {
        throw new \invalid_parameter_exception('Invalid rule id.');
    }

    $PAGE->set_context(\context_system::instance());
    \tool_dynamicrule\permission::can_view_matching_users(\tool_dynamicrule\api::get_rule($args['ruleid']));
    $report = \tool_reportbuilder\system_report_factory::create(\tool_dynamicrule\matching_users_report::class, $args);
    return $report->output();
}

/**
 * Inplace editable for rule name.
 *
 * @package tool_dynamicrule
 *
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 *
 * @return \core\output\inplace_editable | false
 * @throws coding_exception
 * @throws dml_exception
 */
function tool_dynamicrule_inplace_editable($itemtype, $itemid, $newvalue) {
    global $PAGE;
    external_api::validate_context(context_system::instance());

    if ($itemtype === 'rulename') {
        $rule = \tool_dynamicrule\api::get_rule($itemid);
        \tool_dynamicrule\permission::require_can_edit_rule($rule);
        $rule = \tool_dynamicrule\api::update_rule($itemid, (object)['name' => $newvalue]);
        return  \tool_dynamicrule\api::get_name_inplace_editable($rule);
    }

    return false;
}
