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
 * Main file
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$reportid = required_param('id', PARAM_INT);

admin_externalpage_setup('tool_reportbuilder', '', ['id' => $reportid]);

if ($newreportid = get_config('tool_reportbuilder', 'converted-'.$reportid)) {
    redirect(new moodle_url('/reportbuilder/edit.php', ['id' => $newreportid]));
}
$report = \tool_reportbuilder\manager::get_report($reportid);
\tool_reportbuilder\permission::require_can_view_manage_report_page($report->get_persistent());

$reporturl = new moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $report->get_id()]);
$PAGE->set_url($reporturl);

$title = format_string($report->get_reportname());
$PAGE->navbar->add($title);

$PAGE->set_title($title);
$PAGE->set_heading($title);

$output = $PAGE->get_renderer('tool_reportbuilder');
$warning = '';

if (\tool_reportbuilder\permission::can_edit($report)) {
    $edit = new \tool_wp\output\page_header_button(get_string('editreportdetails', 'tool_reportbuilder'),
        ['data-action' => 'editdetails', 'data-id' => $reportid, 'data-reportname' => $title]);
    $PAGE->set_button($edit->render($output) . $PAGE->button);
    $message = get_string('editinglegacywarning', 'tool_reportbuilder');
    if (\core_reportbuilder\permission::can_create_report()) {
        $converturl = new moodle_url('/admin/tool/reportbuilder/convert.php',
            ['id' => $report->get_id(), 'sesskey' => sesskey()]);
        $message .= ' ' . html_writer::link($converturl, get_string('convertocore', 'tool_reportbuilder'));
    }
    $warning = $OUTPUT->notification($message, 'warning', false);
} else if (\tool_reportbuilder\permission::can_edit_in_report_tenant($report)) {
    $editdetailsstr = get_string('editdetailsinsharedspace', 'tool_tenant');
    $redirect = new moodle_url('/admin/tool/tenant/switchtenant.php', ['switchtenantid' => $report->get_tenant_id(),
        'redirecturl' => $reporturl->out_as_local_url(false), 'sesskey' => sesskey()]);
    $buttonparams = ['data-action' => 'editdetailsswitchtenant', 'data-redirect' => $redirect->out(false)];
    $edit = new \tool_wp\output\page_header_button($editdetailsstr, $buttonparams);
    $PAGE->set_button($edit->render($output) . $PAGE->button);
}

$tabs = \tool_reportbuilder\manager::get_tabs($reportid);
echo $OUTPUT->header();
if ($text = \tool_wp\workplace::workplace_license_not_agreed_message()) {
    echo $text;
} else {
    echo $warning;
    echo $output->render_from_template('tool_reportbuilder/report', $tabs);
}
echo $OUTPUT->footer();
