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
 * Workplace license
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define('WORKPLACELICENSEPAGE', 1);

require_once(__DIR__ . '/../../../config.php');

$PAGE->set_url(new \moodle_url('/admin/tool/wp/license.php'));
require_login(0, false);
if (!is_siteadmin()) {
    throw new moodle_exception('nopermissions', 'error', '', '');
}
$PAGE->set_context(context_system::instance());

$redirect = optional_param('redirect', '/', PARAM_LOCALURL);
if (optional_param('agree', false, PARAM_BOOL) && confirm_sesskey()) {
    unset_config('wplicensepending');
    redirect(new moodle_url($redirect));
}

$title = get_string('workplacelicenseheader', 'tool_wp');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('core', 'admin');
echo \tool_wp\workplace::copyright_notice_text($renderer, $PAGE->url, false);
if (!empty($CFG->wplicensepending)) {
    $continue = new \single_button(new \moodle_url($PAGE->url, array(
        'lang' => $CFG->lang, 'agree' => 1, 'sesskey' => sesskey(), 'redirect' => $redirect)),
        get_string('continue'), 'get');
    echo $renderer->confirm(get_string('doyouagree'), $continue, "https://moodle.com/workplace");
}
echo \tool_wp\workplace::install_logo();

echo $OUTPUT->footer();
