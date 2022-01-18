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
 * Class for listing users with access to a given report
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\systemreports;

use context_system;
use lang_string;
use stdClass;
use tool_reportbuilder\audience_base;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\system_report;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\local\helpers\audience;
use tool_reportbuilder\local\models\audiences;
use tool_tenant\hierarchy;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_wp\db;

/**
 * Class report_access_list
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
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
        $this->set_main_table('user', 'u', false);
        $this->joinalias = db::generate_alias();

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        if (!\tool_tenant\permission::can_view_inactive_users($this->get_tenant_id())) {
            $this->add_confirmed_user_condition();
        }

        $this->add_base_fields('u.confirmed, u.suspended'); // Necessary for get_row_class.

        // Check if report tenant has subtenants in case tenant column/filter need to be shown.
        $report = $this->get_report();
        $hassubtenants = $report ? $report->is_shared() && hierarchy::has_subtenants($report->get_tenant_id()) : false;
        $this->addtenantinfo = $hassubtenants && \tool_tenant\permission::can_switch_tenant();

        // Find users allowed to view the report thru the report audiences.
        [$wheres, $params] = self::get_users_by_audience_sql($this->get_report_id());

        $basejoin = '';
        $basecondition = '';

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

        if (!empty($wheres)) {
            // Wrap each OR condition into brackets.
            $allwheres = '(' . implode(') OR (', $wheres) . ')';
            $allwheres .= ' OR ' . $basecondition;
        } else {
            $allwheres = $basecondition;
        }

        $tenancywhere = tenancy::get_users_subquery(false, false, 'u.id', 0, true);
        $this->add_base_condition_sql("{$tenancywhere} AND ($allwheres)", $params, false);

        $this->set_columns();
        $this->set_filters();

        $this->set_downloadable(false);
    }

    /**
     * Find users who can access this report based on the audience and add them to the report.
     *
     * @param int $reportid
     * @return array
     */
    public static function get_users_by_audience_sql(int $reportid): array {
        // If report belongs to Shared space and is not shared, then no audiences are returned.
        $report = new reportbuilder($reportid);
        if (sharedspace::is_shared_space($report->get('tenantid')) && !$report->get('shared')) {
            return [[], []];
        }

        $audiences = audiences::get_records(['reportid' => $reportid]);
        return audience::user_audience_sql($audiences);
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
        $sqljoin = get_with_capability_join(context_system::instance(), $rbcapabilities, 'u2.id');
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
            $basecondition = " $p2.userid IS NOT NULL";

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

        if ($col = $this->get_column('user:fullnamewithpicturelink')) {
            $col->set_is_default(true, 1)
                ->set_is_available(true)
                ->set_visiblename(new lang_string('fullname'))
                ->set_is_sortable(true, true, 1)
                ->add_field('u.id', 'userid')
                ->add_callback(function (string $value, stdClass $row) {
                    $rbcapabilities = ['tool/reportbuilder:edit', 'tool/reportbuilder:read'];
                    if (has_any_capability($rbcapabilities, context_system::instance(), $row->userid)) {
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
    }

    /**
     * CSS class for the row.
     * Add 'dimmed_text' class to suspended and non-confirmed user rows.
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        // Suspended and non-confirmed users must be dimmed.
        return ($row->suspended || !$row->confirmed) ? 'dimmed_text' : '';
    }
}
