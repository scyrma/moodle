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
 * Edit certification view
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_certification\certification;
use tool_certification\permission;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/tablelib.php');

// Get URL parameters.
$certificationid = required_param('id', PARAM_INT); // Certification id.
$certification = new certification($certificationid);
$duplicatecertification = optional_param('duplicatecertification', 0, PARAM_INT); // If we are duplicating.

// Check permissions.
$context = context_system::instance();
$PAGE->set_context($context);
require_login();
permission::require_can_view_details($certification);

$titlestr = get_string('editcertificationsettings', 'tool_certification');
$fullname = format_string($certification->get('fullname'));

$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url('/admin/tool/certification/edit.php', ['id' => $certificationid]));

$PAGE->navbar->add(get_string('administrationsite'), new moodle_url('/admin/search.php'));
$PAGE->navbar->add(get_string('coursesadmintab', 'tool_wp'), new moodle_url('/admin/category.php', ['category' => 'courses']));
$PAGE->navbar->add(get_string('certifications', 'tool_certification'), new moodle_url('/admin/tool/certification/index.php'));
$PAGE->navbar->add($fullname);
$PAGE->set_title($titlestr);
$PAGE->set_heading($fullname);

/** @var tool_certification\output\renderer|core_renderer $output */
$output = $PAGE->get_renderer('tool_certification');

if (permission::can_edit_details($certification)) {
    $edit = new \tool_wp\output\page_header_button(get_string('editdetails', 'tool_certification'),
        ['data-action' => 'editdetails', 'data-certificationid' => $certificationid, 'data-certificationname' => $fullname]);
    $PAGE->set_button($edit->render($output) . $PAGE->button);
}

echo $output->header();
echo $output->render_edit_certification($certificationid, $context->id);
echo $output->footer();