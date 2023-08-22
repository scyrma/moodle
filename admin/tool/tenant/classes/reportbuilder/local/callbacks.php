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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_tenant\reportbuilder\local;

use core_reportbuilder\datasource;
use core_reportbuilder\local\models\report as report_model;
use core_reportbuilder\local\report\base;
use core_reportbuilder\local\systemreports\reports_list;
use html_writer;
use stdClass;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\models\report;
use tool_tenant\hierarchy;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use core_reportbuilder\local\models\schedule;
use tool_tenant\config;

/**
 * Tenant callbacks for core reportbuilder hacks
 *
 * @package    tool_tenant
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Paul Holden <paulh@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
final class callbacks {

    /**
     * Ensure when new reports are created, we attach tenant details to them
     *
     * @param stdClass $report
     * @return stdClass
     */
    public static function set_report_tenant(stdClass $report): stdClass {
        $report->component = 'tool_tenant';
        $report->itemid = tenancy::get_tenant_id();

        $hassubtenants = hierarchy::has_subtenants($report->itemid);
        if ($hassubtenants && isset($report->area)) {
            $report->area = $report->area ? 'shared' : '';
            if ($report->id) {
                // Unfortunately \core_reportbuilder\local\helpers\report::update_report() does not update 'area' attribute.
                $reportmodel = report_model::get_record(['id' => $report->id, 'type' => base::TYPE_CUSTOM_REPORT]);
                if ($reportmodel->get('area') !== $report->area) {
                    $reportmodel->set('area', $report->area)->save();
                }
            }
        }

        return $report;
    }

    /**
     * Add 'shared' checkbox to the report creation form
     *
     * @param \core_reportbuilder\form\report $form
     * @param \MoodleQuickForm $mform
     * @param datasource|null $datasource
     * @return void
     */
    public static function create_report_definition(\core_reportbuilder\form\report $form, \MoodleQuickForm $mform,
            ?datasource $datasource) {
        $hassubtenants = hierarchy::has_subtenants(tenancy::get_tenant_id());
        if ($hassubtenants) {
            $mform->addElement('advcheckbox', 'area', get_string('availableinalltenants', 'tool_reportbuilder'),
                null, null, ['', 'shared']);
            $mform->setDefault('area', 'shared');
            $mform->addHelpButton('area', 'availableinalltenants', 'tool_reportbuilder');
        }
    }

    /**
     * Return extra fields for report listing to ensure they are passed to the permission methods
     *
     * @param string $reportalias
     * @return string
     */
    public static function get_reports_list_tenant_fields(string $reportalias): string {
        return "{$reportalias}.component, {$reportalias}.itemid";
    }

    /**
     * Return SQL clause to restrict report listing to those in the given tenant
     *
     * @param string $reportalias
     * @return array
     */
    public static function get_reports_list_tenant_clause(string $reportalias): array {
        $paramcomponent = database::generate_param_name();
        $paramitemid = database::generate_param_name();
        $sharedspaceid = sharedspace::get_shared_space_id();

        if ($sharedspaceid && tenancy::get_tenant_id() !== $sharedspaceid) {
            $paramsharedspaceid = database::generate_param_name();
            $paramarea = database::generate_param_name();
            return ["{$reportalias}.component = :{$paramcomponent} AND ".
                "({$reportalias}.itemid = :{$paramitemid} OR ".
                "({$reportalias}.itemid = :{$paramsharedspaceid} AND {$reportalias}.area = :{$paramarea}))", [
                    $paramcomponent => 'tool_tenant',
                    $paramitemid => tenancy::get_tenant_id(),
                    $paramsharedspaceid => $sharedspaceid,
                    $paramarea => 'shared',
                ]];
        }

        return ["{$reportalias}.component = :{$paramcomponent} AND {$reportalias}.itemid = :{$paramitemid}", [
            $paramcomponent => 'tool_tenant',
            $paramitemid => tenancy::get_tenant_id(),
        ]];
    }

    /**
     * Allows to override the permission check to view report
     *
     * If the function returns null we will continue to the audience check, however it can override
     * to "always allow" or "always prevent" by returning true or false respectively.
     *
     * Called from the {@see \core_reportbuilder\permission::can_view_report()}
     *
     * @param report $report
     * @param int|null $userid
     * @return bool|null
     */
    public static function override_can_view_report(report $report, ?int $userid = null): ?bool {
        global $USER;
        if ($report->get('component') === 'tool_tenant') {
            $isownorshared = hierarchy::is_own_or_parent_shared_entity($report->get('itemid'), $report->get('area') === 'shared',
                tenancy::get_tenant_id($userid));
            if (!$isownorshared) {
                // This report is hidden from this user because it is defined in another tenant and can only be viewed
                // when switched to that tenant.
                return false;
            }
            if (!$userid || $USER->id == $userid) {
                if ($isownorshared) {
                    // This is a report defined in the parent tenant, if the user can edit reports, they should always be able
                    // to view it even if they are not in the audience.
                    // This check is only executed if the $userid is the current user, otherwise it's too complicated
                    // and also not used anywhere.

                    // To edit their own reports, users must have either of the 'edit' or 'editall' capabilities. For reports
                    // belonging to other users, they must have the specific 'editall' capability.
                    $caps = array_merge(
                        ['moodle/reportbuilder:editall'],
                        ($report->get('usercreated') === $USER->id) ? ['moodle/reportbuilder:edit'] : []);
                    if (has_any_capability($caps, \context_system::instance())) {
                        return true;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Allows to override the permission check to edit report
     *
     * If the function returns null we will continue to the capabilities check, however it can override
     * to "always allow" or "always prevent" by returning true or false respectively.
     *
     * Called from the {@see \core_reportbuilder\permission::can_edit_report()}
     *
     * @param report $report
     * @param int|null $userid
     * @return bool
     */
    public static function override_can_edit_report(report $report, ?int $userid = null): ?bool {
        if ($report->get('component') === 'tool_tenant') {
            // Editing is only allowed when user switches to the tenant where report is defined.
            // This is still subject to capabilities check, so if we are in the correct tenant we return null.
            if ($report->get('itemid') != tenancy::get_tenant_id($userid)) {
                return false;
            }
        }
        return null;
    }

    /**
     * Wheter the report name needs to add the 'Shared space' badge next to its name
     *
     * Called from {@see \core_reportbuilder\local\systemreports\reports_list::add_columns()}.
     *
     * @param reports_list $report
     * @param string $entityname
     * @param string $tablealias
     */
    public static function add_shared_space_badge(reports_list $report, string $entityname, string $tablealias): void {
        ($report->get_column($entityname . ':name'))
            ->add_fields("{$tablealias}.itemid, {$tablealias}.area, {$tablealias}.component")
            ->add_callback(function(string $value, stdClass $row) {
                if ($row->area === 'shared' && $row->component === 'tool_tenant' &&
                    tenancy::get_tenant_id() !== (int)$row->itemid) {
                    return  $value . ' ' . html_writer::span(get_string('sharedspace', 'tool_tenant'),
                            'badge badge-pill badge-secondary');
                }
                return $value;
            });
    }

    /** @var report[] */
    protected static array $schedulecache = [];

    /**
     * Get report from schedule (uses static cache)
     *
     * @param schedule $schedule
     * @return report
     */
    protected static function get_schedule_report(schedule $schedule): report {
        if (!array_key_exists($schedule->get('id'), self::$schedulecache)) {
            self::$schedulecache[$schedule->get('id')] = new report($schedule->get('reportid'));
        }
        return self::$schedulecache[$schedule->get('id')];
    }

    /**
     * Callback executed when generating scheduled reports to ensure we switch to the correct tenant
     *
     * @param schedule $schedule
     * @return void
     */
    public static function setup_user_for_schedule(schedule $schedule): void {
        $report = self::get_schedule_report($schedule);
        $reporttenant = $report->get('component') === 'tool_tenant' ? $report->get('itemid') : 0;
        if ($reporttenant && $reporttenant != tenancy::get_actual_tenant_id()) {
            tenancy::set_switched_tenant_id($reporttenant);
        }
        \cache::make('tool_tenant', 'mytenant')->purge();
        config::push_for_tenant(tenancy::get_tenant_id());
    }

    /**
     * Callback executed after sending report schedules to ensure we reset back the tenant
     *
     * @param schedule $schedule
     * @return void
     */
    public static function reset_user_after_schedule(schedule $schedule): void {
        global $USER;
        unset(self::$schedulecache[$schedule->get('id')]);
        config::pop_all();
        config::push_for_user((int)$USER->id);
    }

    /**
     * Callback executed when sending report schedules to ensure we use correct config
     *
     * @param schedule $schedule
     * @return void
     */
    public static function setup_user_for_schedule_message(schedule $schedule): void {
        config::push_for_tenant(tenancy::get_actual_tenant_id());
    }

    /**
     * Callback executed in report builder datasources to filter by tenant category
     *
     * @param string $categoryfield
     * @return array
     */
    public static function filter_by_tenant_category(string $categoryfield): array {
        global $DB;

        $tenantid = tenancy::get_tenant_id();
        $tenant = tenancy::get_tenants()[$tenantid];
        $categoryid = $tenant->categoryid;

        if (sharedspace::is_shared_space() || !tenancy::is_site_multi_tenant()) {
            return ["1=1", []];
        }

        if (empty($categoryid)) {
            return ["1=2", []];
        }

        $categoryalias = database::generate_alias();
        $categoryparamid = database::generate_param_name();
        $categoryparampath = database::generate_param_name();

        $categorypathlike = $DB->sql_like("{$categoryalias}.path", ":{$categoryparampath}");
        $params = [
            $categoryparamid => $categoryid,
            $categoryparampath => "/{$categoryid}/%",
        ];

        $sql = " {$categoryfield} IN (
            SELECT {$categoryalias}.id
              FROM {course_categories} {$categoryalias}
             WHERE {$categoryalias}.id = :{$categoryparamid}
                OR {$categorypathlike}
        )";

        return [$sql, $params];
    }

    /**
     * Callback executed in report builder datasources to filter by tenant courses
     *
     * @param string $coursefield
     * @return array
     */
    public static function filter_by_tenant_courses(string $coursefield): array {
        $tenantid = tenancy::get_tenant_id();
        $tenant = tenancy::get_tenants()[$tenantid];
        $categoryid = $tenant->categoryid;

        if (sharedspace::is_shared_space() || !tenancy::is_site_multi_tenant()) {
            return ["1=1", []];
        }

        if (empty($categoryid)) {
            return ["1=2", []];
        }

        $coursealias = database::generate_alias();
        [$categoryselect, $categoryparams] = self::filter_by_tenant_category("{$coursealias}.category");

        $sql = " {$coursefield} IN (
            SELECT {$coursealias}.id
              FROM {course} {$coursealias}
             WHERE {$categoryselect}
        )";

        return [$sql, $categoryparams];
    }

}
