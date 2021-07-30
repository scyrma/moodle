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
 * Edit certification view
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
$editcertificationurl = new moodle_url('/admin/tool/certification/edit.php', ['id' => $certificationid]);
$PAGE->set_url($editcertificationurl);

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
} else if (permission::can_edit_details_in_certification_tenant($certification)) {
    $editdetailsstr = get_string('editdetailsinsharedspace', 'tool_tenant');
    $redirect = new moodle_url('/admin/tool/tenant/switchtenant.php', ['switchtenantid' => $certification->get('tenantid'),
        'redirecturl' => $editcertificationurl->out_as_local_url(false), 'sesskey' => sesskey()]);
    $buttonparams = ['data-action' => 'editdetailsswitchtenant', 'data-redirect' => $redirect->out(false)];
    $edit = new \tool_wp\output\page_header_button($editdetailsstr, $buttonparams);
    $PAGE->set_button($edit->render($output) . $PAGE->button);
}

echo $output->header();
echo $output->render_edit_certification($certificationid, $context->id);
echo $output->footer();
