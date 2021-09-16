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
 * Class for listing users with access to a given report
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\systemreports;

use lang_string;
use tool_organisation\helper as org_helper;
use tool_organisation\local\entities\jobs as jobs_entity;
use tool_reportbuilder\local\helpers\audience;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;
use tool_reportbuilder\system_report;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_tenant\hierarchy;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_tenant\tenant;
use tool_wp\db;

/**
 * Class report_access_list
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_access_list extends system_report {

    /** @var report_base $report */
    protected $report;

    /** @var string $joinalias */
    protected $joinalias;

    /** @var bool $addtenantinfo Add tenant column/filter only when tenant has subtenants and user can switch between tenants */
    protected $addtenantinfo;

    /**
     * Return current report
     *
     * @return report_base|null
     */
    protected function get_report() : ?report_base {
        if ($this->report === null && $reportid = $this->get_parameter('id', 0, PARAM_INT)) {
            return $this->report = manager::get_report($reportid);
        }

        return $this->report;
    }

    /**
     * Return ID for current report
     *
     * @return int
     */
    protected function get_report_id() : int {
        $report = $this->get_report();

        return ($report ? $report->get_id() : 0);
    }

    /**
     * Initialise report
     *
     * @return void
     */
    protected function initialise() : void {
        global $CFG, $DB;
        $this->set_main_table('user', 'u', false);
        $this->joinalias = db::generate_alias();

        // Check if report tenant has subtenants in case tenant column/filter need to be shown.
        $report = $this->get_report();
        $hassubtenants = $report ? $report->is_shared() && hierarchy::has_subtenants($report->get_tenant_id()) : false;
        $this->addtenantinfo = $hassubtenants && \tool_tenant\permission::can_switch_tenant();

        // Find users who can access this report based on the audience and add them to the report.
        [$basejoin, $basecondition, $params] = self::get_users_by_audience_sql($this->get_report_id(),
            $this->get_main_table_alias(), $this->joinalias, $hassubtenants);

        // Find users with capabilities to manage/view reports and add them to the report.
        // If the current user can not view users from other tenants, they should not see them in this report.
        [$cannotmatchanyrows, $basejoincap, $baseconditioncap, $paramscap] = self::get_users_by_capabilities_sql($hassubtenants);

        if (!$cannotmatchanyrows) {
            $basejoin .= $basejoincap;
            $basecondition .= $baseconditioncap;
            $params = array_merge($params, $paramscap);
        }

        // We pass $sqljoin->cannotmatchanyrows as the third parameter because if no extra rows are being added, then we
        // still get the parameter validation.
        $this->add_base_join($basejoin, $params, $cannotmatchanyrows ?? true);

        [$adminsql, $baseconditionparams] =
            $DB->get_in_or_equal(explode(",", $CFG->siteadmins), SQL_PARAMS_NAMED, db::generate_param_name() . '_');
        $basecondition .= " OR u.id $adminsql";
        $this->add_base_condition_sql("($basecondition)", $baseconditionparams);

        $this->set_columns();
        $this->set_filters();

        $this->set_downloadable(false);
    }

    /**
     * Find users who can access this report based on the audience and add them to the report.
     *
     * @param int $reportid
     * @param string $maintablealias
     * @param string $joinalias
     * @param bool $hassubtenants
     * @return array
     */
    public static function get_users_by_audience_sql(int $reportid, string $maintablealias, string $joinalias,
                                                     bool $hassubtenants) {
        $audiencealias = db::generate_alias();
        [$join, $joinparams] = audience::user_reports_job_sql($audiencealias);
        [$where, $whereparams] = org_helper::job_time_and_tenant_select(0);

        $params = array_merge($joinparams, $whereparams);

        $paramreportid = db::generate_param_name();
        $params[$paramreportid] = $reportid;

        $tenantcondition = tenancy::get_users_subquery(false, true, 'j.userid', 0, $hassubtenants);
        $audiencesql = "SELECT j.userid, j.positionid, j.departmentid
                  FROM {tool_reportbuilder} rb
             LEFT JOIN {tool_reportbuilder_audience} {$audiencealias} ON {$audiencealias}.reportid = rb.id
                       {$join}
                 WHERE {$tenantcondition} rb.id = :{$paramreportid} AND {$where}";

        $basejoin = "LEFT JOIN ({$audiencesql}) {$joinalias}
              ON {$joinalias}.userid = {$maintablealias}.id";
        $basecondition = "{$joinalias}.userid IS NOT NULL";

        return [$basejoin, $basecondition, $params];
    }

    /**
     * Find users with capabilities to manage/view reports and add them to the report.
     * If the current user can not view users from other tenants, they should not see them in this report.
     *
     * @param bool $hassubtenants
     * @return array
     * @throws \dml_exception
     */
    public static function get_users_by_capabilities_sql(bool $hassubtenants) {
        $rbcapabilities = ['tool/reportbuilder:edit', 'tool/reportbuilder:read'];
        $sqljoin = get_with_capability_join(\context_system::instance(), $rbcapabilities, 'u2.id');
        if (!$sqljoin->cannotmatchanyrows) {
            $tenantcondition2 = tenancy::get_users_subquery(false, false, 'u2.id', 0, $hassubtenants);
            $switchcondition = '1=0'; // TODO WP-2361 can switch to the report tenant.
            $permissionsql = "
                SELECT u2.id AS userid
                FROM {user} u2
                $sqljoin->joins
                WHERE $sqljoin->wheres AND ($tenantcondition2 OR $switchcondition)
            ";

            $p2 = db::generate_alias();
            $basejoin = "\nLEFT JOIN ($permissionsql) $p2 ON $p2.userid = u.id";
            $basecondition = " OR $p2.userid IS NOT NULL";

            return [$sqljoin->cannotmatchanyrows, $basejoin, $basecondition, $sqljoin->params];
        }

        return [true, '', '', []];
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view() : bool {
        $report = $this->get_report();

        return ($report && permission::can_view_access_tab($report));
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name() : string {
        return get_string('report_access_list', 'tool_reportbuilder');
    }

    /**
     * Set the columns for the report.
     *
     * @return void
     */
    protected function set_columns() : void {
        $this->add_entity((new user_entity())->set_allow_tenant_columns(sharedspace::is_shared_space())
            ->set_table_alias('user', $this->get_main_table_alias()));
        $this->add_entity((new jobs_entity())
            ->set_table_alias('tool_organisation_job', $this->joinalias));

        if ($col = $this->get_column('user:fullnamewithpicturelink')) {
            $col->set_is_default(true, 1)
                ->add_field($this->joinalias . '.positionid')
                ->add_field($this->joinalias . '.departmentid')
                ->set_is_available(true)
                ->set_visiblename(new lang_string('fullname'))
                ->set_is_sortable(true, true, 1)
                ->add_callback(function($value, $row) {
                    if (empty($row->positionid) && empty($row->departmentid)) {
                        $value .= ' ' . \html_writer::span(get_string('canviewallreports', 'tool_reportbuilder'),
                                'badge badge-secondary');
                    }
                    return $value;
                });
        }

        // Show tenant column only when tenant has subtenants and user can switch between tenants.
        if ($col = $this->get_column('user:tenant')) {
            $col->set_is_default(true, 2)
                ->set_is_available($this->addtenantinfo)
                ->set_is_sortable(true, true, 2);
        }

        // Show Organisation columns only when user is not in shared space.
        $isnotsharedspace = !sharedspace::is_shared_space();
        if ($col = $this->get_column('tool_organisation_jobs:position')) {
            $col->set_is_default(true, 3)
                ->set_is_available($isnotsharedspace)
                ->set_is_sortable(true, true, 3);
        }
        if ($col = $this->get_column('tool_organisation_jobs:department')) {
            $col->set_is_default(true, 4)
                ->set_is_available($isnotsharedspace)
                ->set_is_sortable(true, true, 4);
        }
    }

    /**
     * Set the filters for the report.
     *
     * @return void
     */
    protected function set_filters(): void {
        if ($f = $this->get_filter('user:fullname')) {
            $f->set_is_default(true)
                ->set_is_available(true);
        }

        // Show tenant filter only when tenant has subtenants and user can switch between tenants.
        if ($f = $this->get_filter('user:tenant')) {
            $f->set_is_default($this->addtenantinfo)
                ->set_is_available(true);
        }

        // Show Organisation filters only when user is not in shared space.
        $isnotsharedspace = !sharedspace::is_shared_space();
        if ($f = $this->get_filter('tool_organisation_jobs:position')) {
            $f->set_is_default(true)
                ->set_is_available($isnotsharedspace);
        }
        if ($f = $this->get_filter('tool_organisation_jobs:department')) {
            $f->set_is_default(true)
                ->set_is_available($isnotsharedspace);
        }
    }
}
