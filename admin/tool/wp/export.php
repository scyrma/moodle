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
 * Creating new export or displaying the status of past export
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

$entrypoint = optional_param('entrypoint', '', PARAM_ALPHANUMEXT);
$entrypointid = optional_param('entrypointid', 0, PARAM_INT);
$exportid = optional_param('exportid', 0, PARAM_INT);

$url = \tool_wp\local\exportimport\helper::export_url($exportid, $entrypoint, $entrypointid);
\tool_wp\admin_externalpage::setup_page('tool_wp_exportimport', '', null, $url);
\tool_wp\admin_externalpage::setup_subpage(get_string('doexport', 'tool_wp'));
$PAGE->set_secondary_navigation(false);

if ($exportid) {
    \tool_wp\permission::require_can_view_export($exportid);
}

echo $OUTPUT->header();

$configdata = $exportid ? [] : ['entrypoint' => $entrypoint, 'entrypointid' => $entrypointid];
$export = new \tool_wp\local\exportimport\export($exportid, $configdata);
echo $OUTPUT->render_from_template('tool_wp/export', $export->export_for_template($OUTPUT));

echo $OUTPUT->footer();
