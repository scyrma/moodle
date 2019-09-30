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
 * Certification progress report view.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_certification\certification;
use tool_certification\permission;
use tool_certification\output\certification_progress_report;

require_once(__DIR__ . '/../../../config.php');

// Get URL parameters.
$certificationid = required_param('id', PARAM_INT);

// Check permissions.
require_login();
$certification = new certification($certificationid);
$context = context_system::instance();
permission::require_can_view_users_progress($certification);

$PAGE->set_pagelayout('admin');
$progressurl = new moodle_url("/$CFG->admin/tool/certification/progress.php", ['id' => $certificationid]);
$PAGE->set_url($progressurl);
$PAGE->set_context($context);

$fullname = format_string($certification->get('fullname'));
$certificationprogressstr = get_string('progressreport', 'tool_certification');

$PAGE->navbar->add(get_string('administrationsite'), new moodle_url("/$CFG->admin/search.php"));
$coursesadminurl = new moodle_url("/$CFG->admin/category.php", ['category' => 'courses']);
$PAGE->navbar->add(get_string('coursesadmintab', 'tool_wp'), $coursesadminurl);
$PAGE->navbar->add(get_string('certifications', 'tool_certification'), new moodle_url("/$CFG->admin/tool/certification/index.php"));
$PAGE->navbar->add($fullname, new moodle_url("/$CFG->admin/tool/certification/edit.php", ['id' => $certificationid]));
$PAGE->navbar->add($certificationprogressstr);
$PAGE->set_title($certificationprogressstr);
$PAGE->set_heading($fullname);

/** @var tool_certification\output\renderer|core_renderer $output */
$output = $PAGE->get_renderer('tool_certification');

echo $output->header();
$renderable = new certification_progress_report($certificationid);
echo $output->render($renderable);
echo $output->footer();