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
 * Class export_adhoc_task
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\task;

use core\task\adhoc_task;
use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Class export_adhoc_task
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_adhoc_task extends adhoc_task {

    /**
     * Setter for $customdata.
     * @param mixed $customdata (anything that can be handled by json_encode)
     */
    public function set_custom_data($customdata) {
        if (permission::can_switch_tenant()) {
            $customdata = $customdata ? (array)$customdata : [];
            $customdata['currenttenantid'] = tenancy::get_tenant_id();
        }
        parent::set_custom_data($customdata);
    }

    /**
     * Runs the export as ad-hoc task
     */
    public function execute() {
        try {
            $persistent = new export_persistent($this->get_custom_data()->id);
        } catch (\Throwable $e) {
            // Export was deleted before the task was executed.
            return;
        }

        // Set the user who created the export as the current user.
        try {
            $user = \core_user::get_user($persistent->get('createdby'));
            \core_user::require_active_user($user);
            cron_setup_user($user);
            $oldtenantid = $this->switch_tenant();
        } catch (\moodle_exception $e) {
            // User not found or tenant can not be switched.
            $persistent->set('status', helper::STATUS_ERROR);
            $persistent->save();
            return;
        }

        (new export_manager(0, $persistent))->perform_export();
        if ($oldtenantid) {
            tenancy::set_switched_tenant_id($oldtenantid);
        }
    }

    /**
     * Switch the user tenant to the current tenant as it was during requesting import
     *
     * Throws exception if the switch is not possible (user does not have capability or tenant no longer exists)
     *
     * @return int if tenant was switched - id of the previous tenant, if tenant was not switched 0
     * @throws \moodle_exception
     */
    protected function switch_tenant() {
        if (empty($this->get_custom_data()->currenttenantid) ||
                $this->get_custom_data()->currenttenantid == tenancy::get_tenant_id()) {
            // Nothing to do.
            return 0;
        }

        permission::require_can_switch_tenant();
        $oldtenantid = tenancy::get_tenant_id();
        $currenttenantid = $this->get_custom_data()->currenttenantid;

        if (!array_key_exists($currenttenantid, tenancy::get_tenants())) {
            throw new \moodle_exception('tenantnotfound', 'tool_tenant');
        }
        tenancy::set_switched_tenant_id($currenttenantid);

        return $oldtenantid;
    }
}
