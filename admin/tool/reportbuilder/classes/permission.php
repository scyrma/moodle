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
 * Permission class for tool_reportbuilder.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use moodle_exception;
use tool_reportbuilder\local\helpers\audience;
use tool_reportbuilder\local\models\schedules;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class permission.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission {

    /**
     * Whether current user has capability to edit reports
     *
     * @return bool
     */
    protected static function has_edit_capability(): bool {
        return has_capability('tool/reportbuilder:edit', \context_system::instance());
    }

    /**
     * Whether current user has capability to read reports
     *
     * @return bool
     */
    protected static function has_read_capability(): bool {
        return has_capability('tool/reportbuilder:read', \context_system::instance());
    }

    /**
     * User can view any report.
     *
     * @return bool
     */
    public static function can_view_any(): bool {
        return (self::has_edit_capability() || self::has_read_capability());
    }

    /**
     * User can manage reports in the current tenant (loose check for displaying additional data on the "Manage reports" page)
     *
     * @param int $tenantid
     * @return bool
     */
    public static function can_manage_reports(int $tenantid) : bool {
        return (self::has_edit_capability() && $tenantid == tenancy::get_tenant_id());
    }

    /**
     * Require current user has the ability to manage reports in the current tenant
     *
     * @param int $tenantid
     * @throws \required_capability_exception
     */
    public static function require_can_manage_reports(int $tenantid) : void {
        if (!self::can_manage_reports($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/reportbuilder:edit', 'nopermissions', 'error');
        }
    }

    /**
     * Return if the user can view the "Manage reports" page
     *
     * @return bool
     */
    public static function can_view_reports_list() : bool {
        $reports = audience::user_reports_list();

        return (self::can_view_any() || !empty($reports));
    }

    /**
     * Check if current user can edit the given report.
     *
     * @param \stdClass $row Complete row
     * @return bool
     */
    public static function can_view_edit_icon(\stdClass $row): bool {
        $persistent = new reportbuilder(0, $row);

        try {
            $report = manager::get_report_from_persistent($persistent);
            $result = self::can_edit($report);
        } catch (moodle_exception $exception) {
            $result = false;
        }

        return $result;
    }

    /**
     * Check if current user can delete the given report.
     *
     * @param \stdClass $row Complete row
     * @return bool
     */
    public static function can_view_delete_icon(\stdClass $row): bool {
        $persistent = new reportbuilder(0, $row);

        try {
            $report = manager::get_report_from_persistent($persistent);
            $result = self::can_delete($report);
        } catch (moodle_exception $exception) {
            // Report source no longer exists, allow someone who can manage reports to delete this one.
            $result = self::can_manage_reports($persistent->get('tenantid'));
        }

        return $result;
    }

    /**
     * Check if the current user can duplicate the given report.
     *
     * @param \stdClass $row
     * @return bool
     */
    public static function can_view_duplicate_icon(\stdClass $row) : bool {
        $persistent = new reportbuilder(0, $row);

        try {
            $report = manager::get_report_from_persistent($persistent);
            $result = self::can_duplicate($report);
        } catch (moodle_exception $exception) {
            $result = false;
        }

        return $result;
    }

    /**
     * User can view the report if not is a system report and can view any report (cap check) or the report support the
     * organisations position filter and the user has a manager position that allow it.
     *
     * @param report_base $report the report to check
     * @return bool
     */
    public static function can_view(report_base $report) : bool {
        $reports = audience::user_reports_list();

        return !self::is_system_report($report) &&
            (self::can_view_any() || array_search($report->get_id(), $reports) !== false);
    }

    /**
     * User can view the report if not is a system report and can view any report (cap check) or the report support the
     * organisations position filter and the user has a manager position that allow it.
     *
     * @param report_base $report the report to check
     * @throws moodle_exception
     */
    public static function require_can_view(report_base $report): void {
        if (!self::can_view($report)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/reportbuilder:read', 'nopermissions', 'error');
        }
    }

    /**
     * Whether configured site limit for number of custom reports threshold has been reached
     *
     * @return bool
     */
    public static function is_sitelimit_reached() : bool {
        global $CFG;

        $params = ['type' => constants::TYPE_DATASOURCE];

        return !empty($CFG->tool_reportbuilder_limitsenabled) && isset($CFG->tool_reportbuilder_sitelimit) &&
            $CFG->tool_reportbuilder_sitelimit <= reportbuilder::count_records($params);
    }

    /**
     * Whether configured tenant limit for number of custom reports threshold has been reached
     *
     * @return bool
     */
    public static function is_tenantlimit_reached() : bool {
        global $CFG;

        $params = ['type' => constants::TYPE_DATASOURCE, 'tenantid' => tenancy::get_tenant_id()];

        return !empty($CFG->tool_reportbuilder_limitsenabled) && isset($CFG->tool_reportbuilder_tenantlimit) &&
            $CFG->tool_reportbuilder_tenantlimit <= reportbuilder::count_records($params);
    }

    /**
     * Can create reports. Observes configured site/tenant limit on number of custom reports unless $ignorelimit is true
     *
     * @param bool $ignorelimit
     * @return bool
     */
    public static function can_create(bool $ignorelimit = false) : bool {
        if (!$ignorelimit && (self::is_sitelimit_reached() || self::is_tenantlimit_reached())) {
            return false;
        }

        return self::has_edit_capability();
    }

    /**
     * User can edit the given report.
     *
     * @param report_base $report the report to edit.
     *
     * @return bool
     */
    public static function can_edit(report_base $report) : bool {
        if (!$report->get_id()) {
            return false;
        }

        // Belongs to same tenant.
        if ($report->get_tenant_id() != tenancy::get_tenant_id()) {
            return false;
        }

        if (!self::is_system_report($report) && (self::has_edit_capability())) {
            return true;
        }

        return false;
    }

    /**
     * User can delete the given report.
     *
     * @param report_base $report the report to delete.
     * @return bool
     */
    public static function can_delete(report_base $report) : bool {
        return self::can_edit($report);
    }

    /**
     * User can duplicate the given report.
     *
     * @param report_base $report
     * @return bool
     */
    public static function can_duplicate(report_base $report) : bool {
        return self::can_edit($report);
    }

    /**
     * User can create a schedule for the given report.
     *
     * @param report_base $report
     * @return bool
     */
    public static function can_schedule(report_base $report) : bool {
        return self::can_edit($report);
    }

    /**
     * Check if the given report is a system report.
     *
     * @param report_base $report
     * @return bool
     */
    public static function is_system_report(report_base $report) : bool {
        return ($report instanceof system_report) &&
            $report->get_persistent()->get('type') == constants::TYPE_SYSTEM;
    }

    /**
     * Check if the report belongs to the same tenant.
     *
     * @param report_base $report
     * @return bool
     */
    public static function check_belongs_same_tenant(report_base $report): bool {
        return $report->get_tenant_id() == tenancy::get_tenant_id();
    }

    /**
     * Checks if user can delete the given report.
     *
     * @param report_base $report
     * @throws moodle_exception
     */
    public static function require_can_delete(report_base $report): void {
        if (!self::can_delete($report)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/reportbuilder:edit', 'nopermissions', 'error');
        }
    }

    /**
     * Checks if user can edit the given report.
     *
     * @param report_base $report the report to check.
     * @throws moodle_exception
     */
    public static function require_can_edit(report_base $report): void {
        if (!self::can_edit($report)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/reportbuilder:edit', 'nopermissions', 'error');
        }
    }

    /**
     * Checks if user can create reports
     *
     * @throws moodle_exception
     */
    public static function require_can_create(): void {
        if (!self::can_create()) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/reportbuilder:edit', 'nopermissions', 'error');
        }
    }

    /**
     * can_view_all_schedules_list
     *
     * @return bool
     */
    public static function can_view_all_schedules_list(): bool {
        return self::has_edit_capability();
    }

    /**
     * User can view the "Schedules" tab inside a report
     *
     * @param report_base $report
     * @return bool
     */
    public static function can_view_schedules_list(report_base $report): bool {
        return self::has_edit_capability()
            && $report->get_tenant_id() == tenancy::get_tenant_id();
    }

    /**
     * Determine whether current user can create schedules (when $report is specified we also check user can edit that too)
     *
     * @param report_base|null $report
     * @return bool
     */
    public static function can_create_schedule(?report_base $report = null): bool {
        return self::has_edit_capability() && (is_null($report) || self::can_edit($report));
    }

    /**
     * Require that current user can create schedules
     *
     * @param report_base|null $report
     * @throws moodle_exception
     */
    public static function require_can_create_schedule(?report_base $report = null) {
        if (!self::can_create_schedule($report)) {
            throw new moodle_exception('errormanageschedules', 'tool_reportbuilder');
        }
    }

    /**
     * can_edit_schedule
     *
     * @param schedules $schedule
     * @param int $tenantid tenantid of the associated report, if known. May be passed to increase performance
     * @return bool
     */
    public static function can_edit_schedule(schedules $schedule, ?int $tenantid = null): bool {
        return $schedule->get('id') &&
            self::has_edit_capability() &&
            ($tenantid = isset($tenantid) ? $tenantid : $schedule->get_tenantid()) &&
            $tenantid == tenancy::get_tenant_id();
    }

    /**
     * can_delete_schedule
     *
     * @param schedules $schedule
     * @param int $tenantid tenantid of the associated report, if known. May be passed to increase performance
     * @return bool
     */
    public static function can_delete_schedule(schedules $schedule, ?int $tenantid = null): bool {
        return self::can_edit_schedule($schedule, $tenantid);
    }

    /**
     * can_send_schedule
     *
     * @param schedules $schedule
     * @param int $tenantid tenantid of the associated report, if known. May be passed to increase performance
     * @return bool
     */
    public static function can_send_schedule(schedules $schedule, ?int $tenantid = null): bool {
        return self::can_edit_schedule($schedule, $tenantid);
    }

    /**
     * require_can_edit_schedule
     *
     * @param schedules $schedule
     * @throws moodle_exception
     */
    public static function require_can_edit_schedule(schedules $schedule) {
        if (!self::can_edit_schedule($schedule)) {
            throw new moodle_exception('errormanageschedules', 'tool_reportbuilder');
        }
    }

    /**
     * require_can_delete_schedule
     *
     * @param schedules $schedule
     * @throws moodle_exception
     */
    public static function require_can_delete_schedule(schedules $schedule) {
        if (!self::can_delete_schedule($schedule)) {
            throw new moodle_exception('errormanageschedules', 'tool_reportbuilder');
        }
    }

    /**
     * require_can_send_schedule
     *
     * @param schedules $schedule
     * @throws moodle_exception
     */
    public static function require_can_send_schedule(schedules $schedule) {
        if (!self::can_send_schedule($schedule)) {
            throw new moodle_exception('errormanageschedules', 'tool_reportbuilder');
        }
    }

    /**
     * Whether current user can view schedule edit icon
     *
     * @param \stdClass $row
     * @return bool
     */
    public static function can_view_schedule_edit_icon(\stdClass $row): bool {
        $tenantid = isset($row->tenantid) ? $row->tenantid : null;
        $schedule = new schedules(0, $row);

        return self::can_edit_schedule($schedule, $tenantid);
    }

    /**
     * Whether current user can view schedule send icon
     *
     * @param \stdClass $row
     * @return bool
     */
    public static function can_view_schedule_send_icon(\stdClass $row): bool {
        $tenantid = isset($row->tenantid) ? $row->tenantid : null;
        $schedule = new schedules(0, $row);

        return self::can_send_schedule($schedule, $tenantid);
    }

    /**
     * Whether current user can view schedule delete icon
     *
     * @param \stdClass $row
     * @return bool
     */
    public static function can_view_schedule_delete_icon(\stdClass $row): bool {
        $tenantid = isset($row->tenantid) ? $row->tenantid : null;
        $schedule = new schedules(0, $row);

        return self::can_delete_schedule($schedule, $tenantid);
    }

    /**
     * User can view the "Access" tab for the given report
     *
     * @param report_base $report
     * @return bool
     */
    public static function can_view_access_tab(report_base $report): bool {
        return self::can_edit($report);
    }

    /**
     * User can view the "Access" tab for the given report
     *
     * @param report_base $report
     * @throws \moodle_exception
     */
    public static function require_can_view_access_tab(report_base $report): void {
        if (!self::can_view_access_tab($report)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/reportbuilder:edit', 'nopermissions', 'error');
        }
    }
}
