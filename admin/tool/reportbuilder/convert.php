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

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$reportid = required_param('id', PARAM_INT);
require_sesskey();

admin_externalpage_setup('tool_reportbuilder', '', null,
    new moodle_url('/admin/tool/reportbuilder/convert.php', ['id' => $reportid]));

/** @var \tool_program\tool_reportbuilder\datasources\report_programs $report */
$report = \tool_reportbuilder\manager::get_report($reportid);
\tool_reportbuilder\permission::require_can_edit($report);
\core_reportbuilder\permission::require_can_create_report();

$reporturl = new moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $report->get_id()]);

$title = format_string($report->get_reportname());
$PAGE->navbar->add($title);

$PAGE->set_title($title);
$PAGE->set_heading($title);

$output = $PAGE->get_renderer('tool_reportbuilder');

echo $OUTPUT->header();

// TODO WP-3706 - improve UI, do not hardcode strings.
echo "Converting report  {$report->get_id()}<br><br>";
echo "datasource: ".get_class($report)."<br><br>";

echo "<pre>";
foreach ($report->get_active_columns() as $column) {
    echo $column->get_unique_identifier()."\n";
}
echo "</pre>";

$newreportid = $report->convert();
echo "<p>".html_writer::link(new moodle_url('/reportbuilder/edit.php', ['id' => $newreportid]),
        'converted report '.$newreportid)."</p>";

echo $OUTPUT->footer();
