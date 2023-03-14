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
 * Class report_users_list
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Toni Barbera <toni@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\permission;
use tool_reportbuilder\{report_column, report_filter};
use tool_tenant\tenancy;

/**
 * Class report_users_list
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Toni Barbera <toni@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_users_list extends \tool_reportbuilder\datasource {

    /**
     * When converting custom report to core reportbuilder which class corresponds to this datasource
     *
     * @return string name of the class extending {@see \core_reportbuilder\datasource}
     */
    public function convert_get_datasource_class(): string {
        return \core_user\reportbuilder\datasource\users::class;
    }

    /**
     * When converting to core reportbuilder, map column names to appropriate values in new report/entities
     *
     * @param report_column $oldcolumn
     * @param \core_reportbuilder\datasource $newsource
     * @return string
     */
    public function convert_get_column_unique_identifier(report_column $oldcolumn,
            \core_reportbuilder\datasource $newsource): string {

        if ($oldcolumn->get_unique_identifier() === 'user:tenant') {
            return 'tenant:name';
        }

        return parent::convert_get_column_unique_identifier($oldcolumn, $newsource);
    }

    /**
     * When converting to core reportbuilder, map condition names to appropriate values in new report/entities
     *
     * @param report_filter $oldcondition
     * @param \core_reportbuilder\datasource $newsource
     * @return string
     */
    public function convert_get_condition_unique_identifier(report_filter $oldcondition,
            \core_reportbuilder\datasource $newsource): string {

        if ($oldcondition->get_unique_identifier() === 'user:tenant') {
            return 'tenant:name';
        }

        return parent::convert_get_condition_unique_identifier($oldcondition, $newsource);
    }

    /**
     * When converting to core reportbuilder, map filter names to appropriate values in new report/entities
     *
     * @param report_filter $oldfilter
     * @param \core_reportbuilder\datasource $newsource
     * @return string
     */
    public function convert_get_filter_unique_identifier(report_filter $oldfilter,
            \core_reportbuilder\datasource $newsource): string {

        if ($oldfilter->get_unique_identifier() === 'user:tenant') {
            return 'tenant:name';
        }

        return parent::convert_get_filter_unique_identifier($oldfilter, $newsource);
    }

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_main_table('user', 'u');

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        $canviewinactiveusers = \tool_tenant\permission::can_view_inactive_users($this->get_tenant_id());
        if (!$canviewinactiveusers) {
            $this->add_confirmed_user_condition();
        }

        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, 'u.id', 0, true) .
            ' AND u.deleted = 0');

        $this->set_columns();

        $this->get_column('user:fullname')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 1);

        $this->get_column('user:email')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 2);

        $this->get_column('user:country')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 3);

        $this->get_column('user:city')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 4);

        $canshowtenantcolumn = permission::can_show_tenant_column($this->get_tenant_id());
        if ($column = $this->get_column('user:tenant')) {
            $column->set_is_default(true)
                ->set_is_sortable(true, true, 5)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($filter = $this->get_filter('user:tenant')) {
            $filter->set_is_default(true)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($condition = $this->get_condition('user:tenant')) {
            $condition->set_is_available($canshowtenantcolumn);
        }

        // Set suspended/confirmed availability in condition/filter based on 'can_view_inactive_users' permission.
        $this->get_conditions()['user:suspended']->set_is_available($canviewinactiveusers);
        $this->get_conditions()['user:confirmed']->set_is_available($canviewinactiveusers);
        $this->get_filters()['user:suspended']->set_is_available($canviewinactiveusers);
        $this->get_filters()['user:confirmed']->set_is_available($canviewinactiveusers);

        $this->get_conditions()['user:suspended']->set_is_default(true, ['suspended_op' => 2]);

        $this->get_filters()['user:hascurrentjobs']
            ->set_is_default(true);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('reportuserslist', 'tool_reportbuilder');
    }

    /**
     * Set the columns available for the report and the definition of each.
     */
    protected function set_columns() {
        $tenantid = tenancy::get_tenant_id();
        $this->add_entity((new user_entity())->set_allow_tenant_columns(permission::can_show_tenant_column($tenantid)));

        if (class_exists('tool_organisation\local\entities\jobs')) {
            $entity = new \tool_organisation\local\entities\jobs();
            $alias = $entity->get_table_alias('tool_organisation_job');

            $this->add_entity($entity->add_join(
                "LEFT JOIN {tool_organisation_job} {$alias} ON {$alias}.userid = u.id AND " .
                    \tool_organisation\local\entities\jobs::get_job_tenant_join($alias, $tenantid))
            );
        }
    }
}
