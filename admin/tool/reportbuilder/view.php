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
 * The view of report
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\event\report_viewed;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$reportid = required_param('id', PARAM_INT);
$download = optional_param('download', false, PARAM_BOOL);

admin_externalpage_setup('tool_reportbuilder', '', ['id' => $reportid]);

$report = \tool_reportbuilder\manager::get_report($reportid);
\tool_reportbuilder\permission::require_can_view($report);

$PAGE->set_url(new \moodle_url('/admin/tool/reportbuilder/view.php', ['id' => $report->get_id()]));

if (!\tool_reportbuilder\permission::can_create()) {
    $PAGE->navbar->add(get_string('myreports', 'tool_reportbuilder'),
        new \moodle_url('/admin/tool/reportbuilder/index.php'));
}

$title = format_string($report->get_reportname());
$PAGE->navbar->add($title);

$PAGE->set_title($title);
$PAGE->set_heading($title);

$outputpage = new \tool_reportbuilder\output\report_view($reportid, false);
$output = $PAGE->get_renderer('tool_reportbuilder');

$report = new reportbuilder($reportid);

if (!$download) {
    echo $output->header();
    echo $output->render($outputpage);
    echo $output->footer();
    $event = report_viewed::create_from_object($report, 'view');
} else {
    echo $output->render($outputpage);
    $event = report_viewed::create_from_object($report, 'download');
}

$event->trigger();