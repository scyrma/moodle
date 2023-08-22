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
 * The view of report
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\event\report_viewed;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$reportid = required_param('id', PARAM_INT);
$parameters = optional_param('parameters', null, PARAM_RAW);
$download = optional_param('download', false, PARAM_BOOL);

admin_externalpage_setup('tool_reportbuilder', '', ['id' => $reportid]);

$report = \tool_reportbuilder\manager::get_report($reportid, (array) json_decode($parameters));
$outputpage = new \tool_reportbuilder\output\report_view($report, false);
$output = $PAGE->get_renderer('tool_reportbuilder');
$reportbuilder = new reportbuilder($reportid);

if (\tool_reportbuilder\permission::is_system_report($report)) {
    // If it is a system report, viewing is not allowed, only downloading.
    if ($download && $report->can_download()) {
        report_viewed::create_from_object($reportbuilder, 'download')->trigger();
        echo $output->render($outputpage);
    } else {
        throw new \required_capability_exception(\context_system::instance(),
            'tool/reportbuilder:read', 'nopermissions', 'error');
    }
} else {
    // It's a custom report.
    \tool_reportbuilder\permission::require_can_view($report);

    $PAGE->set_url(new \moodle_url('/admin/tool/reportbuilder/view.php', ['id' => $report->get_id()]));

    if (!\tool_reportbuilder\permission::can_manage_reports($report->get_tenant_id())) {
        $PAGE->navbar->add(get_string('customreports', 'tool_reportbuilder'),
            new \moodle_url('/admin/tool/reportbuilder/index.php'));
    }

    $title = format_string($report->get_reportname());
    $PAGE->navbar->add($title);

    $PAGE->set_title($title);
    $PAGE->set_heading($title);

    if ($text = \tool_wp\workplace::workplace_license_not_agreed_message()) {
        echo $output->header();
        echo $text;
        echo $output->footer();
        exit;
    }

    if (!$download) {
        report_viewed::create_from_object($reportbuilder, 'view')->trigger();
        echo $output->header();
        echo $output->render($outputpage);
        echo $output->footer();

    } else {
        report_viewed::create_from_object($reportbuilder, 'download')->trigger();
        echo $output->render($outputpage);
    }
}
