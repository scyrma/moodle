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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Creating new export or displaying the status of past export
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$entrypoint = optional_param('entrypoint', '', PARAM_ALPHANUMEXT);
$entrypointid = optional_param('entrypointid', 0, PARAM_INT);
$exportid = optional_param('exportid', 0, PARAM_INT);

$url = \tool_wp\local\exportimport\helper::export_url($exportid, $entrypoint, $entrypointid);
admin_externalpage_setup('tool_wp_exportimport', '', null, $url);
$PAGE->set_heading(get_string('doexport', 'tool_wp'));
$PAGE->navbar->add(get_string('doexport', 'tool_wp'));


if ($exportid) {
    \tool_wp\permission::require_can_view_export($exportid);
}

echo $OUTPUT->header();

$configdata = $exportid ? [] : ['entrypoint' => $entrypoint, 'entrypointid' => $entrypointid];
$tabsoutput = new \tool_wp\local\exportimport\export($exportid, $configdata);
echo $OUTPUT->render_from_template('tool_wp/export', $tabsoutput->export_for_template($OUTPUT));

echo $OUTPUT->footer();
