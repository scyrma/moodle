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
 * List of all users
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use core_reportbuilder\system_report_factory;
use tool_tenant\manager;
use tool_tenant\output\users_list;
use tool_tenant\reportbuilder\local\systemreports\users;

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

\tool_wp\admin_externalpage::setup_page('tool_tenant_allusers');

$manager = new manager();

$heading = get_string('allusers', 'tool_tenant');
$PAGE->set_secondary_navigation(false);
echo $OUTPUT->header();

// We don't call tool_tenant\system_report_factory() here because the report is not linked to a tenant.
$report = system_report_factory::create(users::class, context_system::instance(), '', '', 0, ['showall' => 1]);
$userdata = new users_list($report, 0);
echo $OUTPUT->render_from_template('tool_tenant/users_list', $userdata->export_for_template($OUTPUT));

echo $OUTPUT->footer();
