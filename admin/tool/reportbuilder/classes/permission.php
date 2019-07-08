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
 * @copyright 2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

use moodle_exception;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class permission.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission {

    /**
     * User can view any report.
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function can_view_any() {
        if (self::can_create() || has_capability('tool/reportbuilder:read', \context_system::instance())) {
            return true;
        }

        return false;
    }

    /**
     * Check if the current user can view some reports as a manager.
     *
     * @return bool
     * @throws moodle_exception
     */
    public static function can_view_as_a_manager() : bool {
        if (!class_exists('tool_organisation\\organisation')) {
            return false;
        }
        $user = \tool_organisation\organisation::get_user_with_jobs();
        return $user && $user->is_manager(\tool_organisation\organisation::PERM_VIEW_REPORTS);
    }

    /**
     * The report supports organisation position filter and the user has a manager position that allows to view reports.
     *
     * @param reportbuilder $report
     * @return bool
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function can_view_report_as_a_manager(reportbuilder $report) : bool {
        /** @var datasource $classname */
        $classname = $report->get('source');
        if (class_exists($classname) && is_subclass_of($classname, datasource::class)
                && $classname::supports_organisation_filter()) {
            return self::can_view_as_a_manager();
        }
        return false;
    }

    /**
     * Return if the user can view the "Manage reports" page
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function can_view_reports_list() : bool {
        if (self::can_create() || self::can_view_any() || self::can_view_as_a_manager()) {
            return true;
        }

        return false;
    }

    /**
     * Check if current user can edit the given report.
     *
     * @param \stdClass $row Complete row
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function can_view_edit_icon(\stdClass $row): bool {
        return self::can_create() && ($row->tenantid == tenancy::get_tenant_id());
    }

    /**
     * Check if current user can delete the given report.
     *
     * @param \stdClass $row Complete row
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function can_view_delete_icon(\stdClass $row): bool {
        return self::can_view_edit_icon($row);
    }

    /**
     * Check if the current user can duplicate the given report.
     *
     * @param \stdClass $row
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function can_view_duplicate_icon(\stdClass $row) : bool {
        return self::can_create();
    }

    /**
     * User can view the report if not is a system report and can view any report (cap check) or the report support the
     * organisations position filter and the user has a manager position that allow it.
     *
     * @param int $reportid The id of the report to check
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function require_can_view(int $reportid) : bool {
        $report = new reportbuilder($reportid);
        if (!self::is_system_report($report) && (self::can_view_any() || self::can_view_report_as_a_manager($report))) {
            return true;
        }

        throw new moodle_exception('errorcannotviewreports');
    }

    /**
     * Can create reports.
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function can_create() {
        $context = \context_system::instance();
        return has_capability('tool/reportbuilder:edit', $context);
    }

    /**
     * User can edit the given report.
     *
     * @param int $reportid The id of the report to edit.
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function can_edit(int $reportid) : bool {
        if ($reportid <= 0) {
            return false;
        }
        $report = new reportbuilder($reportid);

        // Belongs to same tenant.
        if (!self::check_belongs_same_tenant($report)) {
            return false;
        }

        if (!self::is_system_report($report) && (self::can_create())) {
            return true;
        }

        return false;
    }

    /**
     * User can delete the given report.
     *
     * @param int $reportid The id of the report to delete.
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function can_delete(int $reportid) : bool {
        return self::can_edit($reportid);
    }

    /**
     * User can duplicate the given report.
     *
     * @param int $reportid
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function can_duplicate(int $reportid) : bool {
        return self::can_edit($reportid);
    }

    /**
     * User can create a schedule for the given report.
     *
     * @param int $reportid
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function can_schedule(int $reportid) : bool {
        return self::can_edit($reportid);
    }

    /**
     * Check if the given report is a system report.
     *
     * @param reportbuilder $report
     * @return bool
     * @throws \coding_exception
     */
    public static function is_system_report(reportbuilder $report) : bool {
        $type = $report->get('type');
        if ($type == constants::TYPE_SYSTEM) {
            return true;
        }
        return false;
    }

    /**
     * Check if the report belongs to the same tenant.
     *
     * @param reportbuilder $report
     * @return bool
     * @throws \coding_exception
     */
    public static function check_belongs_same_tenant(reportbuilder $report): bool {
        global $USER;
        // Belongs to same tenant.
        $tenantcert = $report->get('tenantid');
        $tenantuser = tenancy::get_tenant_id($USER->id);

        if ((int) $tenantcert !== (int) $tenantuser) {
            return false;
        }
        return true;
    }

    /**
     * Checks if user can delete the given report.
     *
     * @param int $reportid The ID of the report to check.
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_delete($reportid): void {
        if (!self::can_delete($reportid)) {
            throw new moodle_exception('cannotdeletereport');
        }
    }

    /**
     * Checks if user can edit the given report.
     *
     * @param int $reportid The ID of the report to check.
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_edit($reportid): void {
        if (!self::can_edit($reportid)) {
            throw new moodle_exception('cannoteditreport');
        }
    }

    /**
     * Checks if user can create reports
     *
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_create(): void {
        if (!self::can_create()) {
            throw new moodle_exception('cannotcreatereport');
        }
    }
}
