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
 * Performs conversion from the tool_reportbuilder report to core_reportbuilder
 *
 * @package   tool_reportbuilder
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$reportid = required_param('id', PARAM_INT);
require_sesskey();

\tool_wp\admin_externalpage::setup_page('tool_reportbuilder', '', null,
    new moodle_url('/admin/tool/reportbuilder/convert.php', ['id' => $reportid]));

/** @var \tool_program\tool_reportbuilder\datasources\report_programs $report */
$report = \tool_reportbuilder\manager::get_report($reportid);
\tool_reportbuilder\permission::require_can_edit($report);
\core_reportbuilder\permission::require_can_create_report();

$reporturl = new moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $report->get_id()]);

$title = get_string('convertingreport', 'tool_reportbuilder', format_string($report->get_reportname()));
$PAGE->navbar->add($title);

$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_secondary_navigation(false);

$output = $PAGE->get_renderer('tool_reportbuilder');

echo $OUTPUT->header();

$url = new moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $report->get_id()]);
try {
    $newreportid = $report->convert();
} catch (\tool_reportbuilder\convert_not_implemented $e) {
    $message = get_string('convertnotimplementeddesc', 'tool_reportbuilder') .
        '<br><br>' .
        '<ul><li>' . $e->getMessage() . '</li></ul>' .
        html_writer::link($url, get_string('backtoreport', 'tool_reportbuilder'));
    echo $OUTPUT->notification($message, 'error', false);
    echo $OUTPUT->footer();
    exit;
} catch (\tool_reportbuilder\convert_not_possible $e) {
    $message = get_string('convertnotpossibledesc', 'tool_reportbuilder') .
        '<br><br>' .
        '<ul><li>' . $e->getMessage() . '</li></ul>' .
        html_writer::link($url, get_string('backtoreport', 'tool_reportbuilder'));
    echo $OUTPUT->notification($message, 'error', false);
    echo $OUTPUT->footer();
    exit;
}

$message = get_string('reportconverted', 'tool_reportbuilder') . '<br>';
$message .= html_writer::link(new moodle_url('/reportbuilder/edit.php', ['id' => $newreportid]),
        get_string('viewconvertedreport', 'tool_reportbuilder'));
echo $OUTPUT->notification($message, 'success', false);

echo $OUTPUT->footer();
