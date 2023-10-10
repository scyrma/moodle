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

use tool_dynamicrule\api;
use tool_dynamicrule\permission;

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

$ruleid = required_param('id', PARAM_INT);
$rule = api::get_rule($ruleid);

\tool_wp\admin_externalpage::setup_page('tool_dynamicrule', '', ['id' => $ruleid], '/admin/tool/dynamicrule/rule.php');
permission::require_can_use_edit_rule_interface($rule);
\tool_wp\admin_externalpage::setup_subpage($rule->get('name'));

$outputpage = new \tool_dynamicrule\output\rule_page($ruleid);
/** @var tool_dynamicrule\output\renderer|core_renderer $renderer */
$renderer = $PAGE->get_renderer('tool_dynamicrule');

$actionmenulinks = $renderer->get_action_menu_links($rule);
if (!empty($actionmenulinks)) {
    $PAGE->requires->js_call_amd('tool_dynamicrule/kebab_menu', 'init');

    $actionmenu = new action_menu($actionmenulinks);
    $icon = $renderer->pix_icon('i/menu', get_string('actions'));
    $actionmenu->set_menu_trigger($icon, 'btn btn-icon pt-2 rounded-circle no-caret');
    $PAGE->add_header_action($renderer->render($actionmenu));
}

echo $renderer->header();
echo $renderer->render_from_template('tool_wp/secondary_tabs', $outputpage->export_for_template($renderer));
echo $renderer->footer();
