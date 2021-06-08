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
 * Callbacks.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 * @return \core\output\inplace_editable|bool
 */
function tool_dynamicrule_inplace_editable($itemtype, $itemid, $newvalue) {
    external_api::validate_context(context_system::instance());

    // Clean new value according to persistent field type.
    $newvalue = clean_param($newvalue, PARAM_TEXT);

    if ($itemtype === 'rulename') {
        $rule = \tool_dynamicrule\api::get_rule($itemid);
        \tool_dynamicrule\permission::require_can_edit_rule($rule);
        $rule = \tool_dynamicrule\api::update_rule($itemid, (object)['name' => $newvalue]);
        return  \tool_dynamicrule\api::get_name_inplace_editable($rule);
    }

    return false;
}

/**
 * Callback for tool_certificate - the fields available for the certificates
 */
function tool_dynamicrule_tool_certificate_fields() {
    global $CFG;
    // TODO WP-1212 add tests that this is the same list as in
    // the \tool_dynamicrule\tool_dynamicrule\condition\course_completed :: get_available_data_for_outcome().
    if (!class_exists('tool_certificate\customfield\issue_handler')) {
        return;
    }

    $handler = tool_certificate\customfield\issue_handler::create();

    $handler->ensure_field_exists('courseid', 'numeric',
        get_string('courseinternalid', 'tool_dynamicrule'), false, 1);
    $handler->ensure_field_exists('courseshortname', 'text', get_string('shortnamecourse'),
        true, get_string('previewcourseshortname', 'tool_dynamicrule'));
    $handler->ensure_field_exists('coursefullname', 'text', get_string('fullnamecourse'),
        true, get_string('previewcoursefullname', 'tool_dynamicrule'));
    $handler->ensure_field_exists('courseurl', 'text',
        get_string('courseurl', 'tool_dynamicrule'),
        true, $CFG->wwwroot . '/course/view.php?id=1');
    $handler->ensure_field_exists(
        'coursecompletiondate',
        'text',
        get_string('course') . ': ' . get_string('coursecompletiondate', 'tool_dynamicrule'),
        true,
        get_string('coursecompletiondate', 'tool_dynamicrule')
    );
    $handler->ensure_field_exists(
        'coursegrade',
        'text',
        get_string('course') . ': ' . get_string('gradenoun'),
        true,
        get_string('gradenoun')
    );

    // Get the course custom fields.
    $coursehandler = \core_course\customfield\course_handler::create();
    foreach ($coursehandler->get_fields() as $field) {
        $handler->ensure_field_exists(
            'coursecustomfield_' . $field->get('shortname'),
            $field->get('type'),
            get_string('course') . ': ' . $field->get_formatted_name(),
            true,
            $field->get_formatted_name()
        );
    }
}
