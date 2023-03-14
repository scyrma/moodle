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
 * Class observer for tool_organisation.
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use core\event\user_deleted;
use tool_organisation\job_manager;

/**
 * Class tool_organisation_observer
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_observer {
    /**
     * User deleted observer
     *
     * @param user_deleted $event
     */
    public static function user_deleted(user_deleted $event): void {
        job_manager::delete_user_jobs($event->objectid);
    }

    /**
     * Tenant deleted observer
     *
     * @param \tool_tenant\event\tenant_deleted $event
     */
    public static function tenant_deleted(\tool_tenant\event\tenant_deleted $event): void {
        job_manager::delete_all_jobs_for_tenant($event->objectid);
        (new \tool_organisation\department_manager())->delete_all_departments_for_tenant($event->objectid);
        (new \tool_organisation\position_manager())->delete_all_positions_for_tenant($event->objectid);
    }

    /**
     * User moved between tenants event
     *
     * @param \tool_tenant\event\tenant_user_updated $event
     * @return void
     */
    public static function tenant_user_updated(\tool_tenant\event\tenant_user_updated $event) {
        // Move user jobs to the new tenant if applicable.
        job_manager::move_jobs_to_new_tenant($event->relateduserid, $event->other['oldtenantid'], $event->other['tenantid']);
    }
}
