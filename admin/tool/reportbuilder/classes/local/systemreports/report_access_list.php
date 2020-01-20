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
 * Class for listing users with access to a given report
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\systemreports;

defined('MOODLE_INTERNAL') || die();

use lang_string;
use tool_organisation\helper as org_helper;
use tool_organisation\local\entities\jobs as jobs_entity;
use tool_reportbuilder\local\helpers\audience;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;
use tool_reportbuilder\system_report;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_tenant\tenancy;
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
        $this->set_main_table('user', 'u', false);

        list($join, $where, $params) = tenancy::get_users_sql(
            $this->get_main_table_alias(), $this->get_tenant_id());
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);

        list($join, $joinparams) = audience::user_reports_job_sql('rba');
        list($where, $whereparams) = org_helper::job_time_and_tenant_select(0);

        $params = array_merge($joinparams, $whereparams);

        $paramreportid = db::generate_param_name();
        $params[$paramreportid] = $this->get_report_id();

        $sql = "SELECT j.userid, j.positionid, j.departmentid
                  FROM {tool_reportbuilder} rb
             LEFT JOIN {tool_reportbuilder_audience} rba ON rba.reportid = rb.id
                       {$join}
                 WHERE (rb.id = :{$paramreportid} AND {$where})";

        $this->joinalias = db::generate_alias();
        $this->add_base_join("
            JOIN ({$sql}) {$this->joinalias}
              ON {$this->joinalias}.userid = {$this->get_main_table_alias()}.id", $params);

        $this->set_columns();
        $this->set_downloadable(false);

        // Set default columns.
        $this->get_column('user:fullnamewithpicturelink')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true, 1)
            ->set_visiblename(new lang_string('fullname'));
        $this->get_column('tool_organisation_jobs:positiondepartment')
            ->set_is_default(true, 2)
            ->set_is_sortable(true, true, 2);
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
        $this->add_entity((new user_entity())
            ->set_table_alias('user', $this->get_main_table_alias()));
        $this->add_entity((new jobs_entity())
            ->set_table_alias('tool_organisation_job', $this->joinalias));
    }
}