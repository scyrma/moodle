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
 * Switch current tenant (for admins)
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$tenantid = required_param('switchtenantid', PARAM_INT);
$url = optional_param('redirecturl', null, PARAM_LOCALURL);

require_login();
require_sesskey();
\tool_tenant\permission::require_can_switch_tenant();
$message = null;
if (array_key_exists($tenantid, \tool_tenant\tenancy::get_tenants())) {
    \tool_tenant\tenancy::set_switched_tenant_id($tenantid);
    $message = get_string('switchedto', 'tool_tenant',
        \tool_tenant\tenancy::get_tenant_name_from_id($tenantid));
}
redirect(new moodle_url($url ? '/' . ltrim($url, '/') : '/'), $message);
