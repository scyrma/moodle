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
 * Edit rule.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$ruleid = required_param('id', PARAM_INT);

admin_externalpage_setup('tool_dynamicrule', '', ['id' => $ruleid], '/admin/tool/dynamicrule/rule.php');

$rule = \tool_dynamicrule\api::get_rule($ruleid);
\tool_dynamicrule\permission::require_can_use_edit_rule_interface($rule);
$title = $rule->get_formatted_name();

$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title);

$renderer = $PAGE->get_renderer('tool_dynamicrule');

$editdetailsbtnstr = get_string('editdetailsbutton', 'tool_dynamicrule');
$editdetailsstr = get_string('editdetails', 'tool_dynamicrule', $rule->get_formatted_name());
$buttonparams = ['data-action' => 'editdetails', 'data-ruleid' => $ruleid, 'data-title' => $editdetailsstr];
$edit = new tool_wp\output\page_header_button($editdetailsbtnstr, $buttonparams);

$PAGE->set_button($edit->render($renderer) . $PAGE->button);

echo $renderer->header();
echo $renderer->rule($ruleid);
echo $renderer->footer();
