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
 * View report for rule.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\reportbuilder\local\systemreports\users_matching_rule;

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

$ruleid = required_param('id', PARAM_INT);

\tool_wp\admin_externalpage::setup_page('tool_dynamicrule', '', ['id' => $ruleid], '/admin/tool/dynamicrule/rule.php');
$rule = \tool_dynamicrule\api::get_rule($ruleid);
\tool_dynamicrule\permission::require_can_view_matched_users_report($rule);
\tool_wp\admin_externalpage::setup_subpage($rule->get('name'));
$PAGE->set_secondary_navigation(false);

$renderer = $PAGE->get_renderer('tool_dynamicrule');
$report = \tool_tenant\system_report_factory::create(users_matching_rule::class, ['ruleid' => $rule->get('id')]);
$PAGE->requires->js_call_amd('tool_dynamicrule/show_matching_details', 'init');
echo $renderer->header(),
     $report->output(),
     $renderer->footer();
