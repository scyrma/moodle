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
 * Switch current tenant (for admins)
 *
 * @package     tool_tenant
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$tenantid = required_param('switchtenantid', PARAM_INT);
$url = optional_param('redirecturl', null, PARAM_LOCALURL);

require_login();
require_sesskey();
\tool_tenant\permission::require_can_switch_tenant();
if (array_key_exists($tenantid, \tool_tenant\tenancy::get_tenants())) {
    \tool_tenant\tenancy::set_switched_tenant_id($tenantid);
}
redirect(new moodle_url($url ? '/' . ltrim($url, '/') : '/'));
