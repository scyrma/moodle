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
 * Program ad-hoc task for de-allocating users from previous tenant programs.
 *
 * This is triggered by upgrade script only. Until now when user was moved between tenants
 * we were keeping user allocation to previous programs in previous tenants.
 * Now user needs to be deallocated from all programs in those previous tenants.
 * See WP-1015 for details.
 *
 * @package     tool_program
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\task;

use core\task\adhoc_task;
use tool_program\api;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die;

/**
 * Ad-hoc task deallocate_from_previous_tenant_programs
 *
 * @package     tool_program
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class deallocate_from_previous_tenant_programs extends adhoc_task {

    /**
     * Execute the task
     *
     * @return void
     */
    public function execute() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        if (!tenancy::is_site_multi_tenant()) {
            return;
        }

        // Deallocate users from programs that do not belong to the users current tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $sql = "
            SELECT tpu.*
            FROM {tool_program_users} tpu
            JOIN {tool_program} tp ON tpu.programid = tp.id
            LEFT JOIN {tool_tenant_user} ttu ON ttu.userid = tpu.userid
            WHERE tpu.certificationid = 0 AND tp.tenantid <> COALESCE(ttu.tenantid, $defaulttenantid)
        ";
        $allocations = $DB->get_records_sql($sql);

        foreach ($allocations as $allocation) {
            api::deallocate_user($allocation->programid, $allocation->userid);
        }

        // Remove users from all component-less tenant groups that do not belong to their current tenant,
        // regardless of any program allocations.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $sql = "
            SELECT gm.id, gm.groupid, gm.userid
              FROM {groups_members} gm
              JOIN {tool_tenant_group} ttg ON ttg.groupid = gm.groupid
              JOIN {groups} g ON g.courseid = ttg.courseid AND gm.groupid = g.id
         LEFT JOIN {tool_tenant_user} ttu ON ttu.userid = gm.userid
             WHERE ttg.component IS NULL AND ttg.area IS NULL AND ttg.itemid IS NULL
               AND ttg.tenantid <> COALESCE(ttu.tenantid, $defaulttenantid)
               AND gm.component = 'enrol_program'
        ";
        $params = [];
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            groups_remove_member($record->groupid, $record->userid);
        }
    }
}
