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
 * Edit rule.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$ruleid = required_param('id', PARAM_INT);

admin_externalpage_setup('tool_dynamicrule', '', ['id' => $ruleid], '/admin/tool/dynamicrule/rule.php');

$rule = \tool_dynamicrule\api::get_rule($ruleid);
\tool_dynamicrule\permission::require_can_edit_rule($rule);
\tool_dynamicrule\api::disable_rule($ruleid);
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
