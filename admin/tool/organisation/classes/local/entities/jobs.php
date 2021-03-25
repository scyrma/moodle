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
 * File for class jobs.
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\local\entities;

use lang_string;
use tool_organisation\helper;
use tool_organisation\organisation;
use tool_organisation\tool_reportbuilder\filter\job_department;
use tool_organisation\tool_reportbuilder\filter\job_position;
use tool_organisation\tool_reportbuilder\filter\job_time;
use tool_organisation\tool_reportbuilder\filter\position_permissions;
use tool_organisation\tool_reportbuilder\filter\showpastjobs;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\db;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;

/**
 * Columns, filters and conditions that defines the jobs entity and can be reused in any report datasource.
 *
 * Note that calling code MUST use, or define the join for, the jobs table/alias (typically 'toj') when using this entity
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class jobs extends entity_base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'tool_organisation_job' => 'toj',
            'tool_organisation_department' => 'tod',
            'tool_organisation_position' => 'topos'
        ];
    }

    /**
     * Executed when entity is added to the datasource or system report
     */
    public function add_to_report() {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $conditions = $this->get_filters_or_conditions(true);
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        $filters = $this->get_filters_or_conditions(false);
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }
    }

    /**
     * Generate SQL snippet suitable for adding to the entity join on the jobs table. We should only include jobs according to
     * the hierarchy of the current tenant
     *
     * This should be called within datasources or reports that don't otherwise filter by the tenant
     *
     * @param string $jobalias
     * @param int $tenantid
     * @return string
     */
    public static function get_job_tenant_join(string $jobalias, int $tenantid = 0): string {
        [$sql, $params] = hierarchy::filter_own_or_sub_entities_sql("{$jobalias}.tenantid", $tenantid);

        // We can't use parameters in entity joins, so interpolate them straight into the SQL.
        return preg_replace_callback('/(?::(?<param>[a-z_0-9]+))/', function(array $matches) use ($params) {
            $value = $params[$matches['param']];

            return is_int($value) ? $value : "'{$value}'";
        }, $sql);
    }

    /**
     * Joins for jobs
     *
     * @return array
     */
    protected function get_joins(): array {
        $jobalias = $this->get_table_alias('tool_organisation_job');
        $posalias = $this->get_table_alias('tool_organisation_position');
        $deptalias = $this->get_table_alias('tool_organisation_department');

        // Merge joins defined by the datasource/report, with those for position/department tables.
        return array_merge(parent::get_joins(), [
            "LEFT JOIN {tool_organisation_position} {$posalias} ON {$posalias}.id = {$jobalias}.positionid",
            "LEFT JOIN {tool_organisation_department} {$deptalias} ON {$deptalias}.id = {$jobalias}.departmentid",
        ]);
    }

    /**
     * Entity name
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'tool_organisation_jobs';
    }

    /**
     * Entity title
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('jobs', 'tool_organisation');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns(): array {
        $jobalias = $this->get_table_alias('tool_organisation_job');
        $posalias = $this->get_table_alias('tool_organisation_position');
        $deptalias = $this->get_table_alias('tool_organisation_department');
        $columns = [];

        // Column position.
        $newcolumn = (new report_column(
            'position',
            new lang_string('position', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_field("{$posalias}.name")
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column department.
        $newcolumn = (new report_column(
            'department',
            new lang_string('department', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_field("{$deptalias}.name")
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column position department.
        [$sqlposdept, $paramsposdept] = db::sql_get_string('positionanddepartmentdisplay', 'tool_organisation',
            ['position' => "{$posalias}.name", 'department' => "{$deptalias}.name"]);
        $sqlposdeptgrp = db::sql_get_string('positionanddepartmentdisplay', 'tool_organisation',
            ['position' => "{$posalias}.name", 'department' => "{$deptalias}.name"], true);

        $newcolumn = (new report_column(
            'positiondepartment',
            new lang_string('jobpositiondepartment', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_field($sqlposdept, 'positiondepartment', $paramsposdept)
            ->add_callback([format::class, 'format_string'])
            ->set_groupby_sql($sqlposdeptgrp);

        $columns[] = $newcolumn;

        // Column startdate.
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_field("{$jobalias}.startdate")
            ->add_callback([\tool_organisation\local\helpers\format::class, 'jobdate']);
        $columns[] = $newcolumn;

        // Column enddate.
        $newcolumn = (new report_column(
            'enddate',
            new lang_string('enddate', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_field("$jobalias.enddate")
            ->add_callback([\tool_organisation\local\helpers\format::class, 'jobdate']);
        $columns[] = $newcolumn;

        // Column globalmanagementicons.
        $newcolumn = (new report_column(
            'globalmanagementicons',
            new lang_string('globalmanagementicons', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("CASE WHEN {$posalias}.globalmanager = 1
                THEN {$posalias}.globalpermissions
                ELSE NULL END", 'globalpermissions')
            ->add_callback([\tool_organisation\local\helpers\format::class, 'managementicons'], 'globalpermissions')
            ->add_aggregation_callback('groupconcat',
                [\tool_organisation\local\helpers\format::class, 'managementicons_group'], 'globalpermissions')
            ->add_aggregation_callback('groupconcatdistinct',
                [\tool_organisation\local\helpers\format::class, 'managementicons_group'], 'globalpermissions');
        $columns[] = $newcolumn;

        // Column departmentmanagementicons.
        $newcolumn = (new report_column(
            'departmentmanagementicons',
            new lang_string('departmentmanagementicons', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("CASE WHEN {$posalias}.departmentmanager = 1
                THEN {$posalias}.departmentpermissions
                ELSE NULL END", 'departmentpermissions')
            ->add_callback([\tool_organisation\local\helpers\format::class, 'managementicons'], 'departmentpermissions')
            ->add_aggregation_callback('groupconcat',
                [\tool_organisation\local\helpers\format::class, 'managementicons_group'], 'departmentpermissions')
            ->add_aggregation_callback('groupconcatdistinct',
                [\tool_organisation\local\helpers\format::class, 'managementicons_group'], 'departmentpermissions');
        $columns[] = $newcolumn;

        // Column positionframework.
        $sql = helper::get_framework_id_sql($posalias.'.path');
        $newcolumn = (new report_column(
            'positionframework',
            new lang_string('positionframework', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join(' LEFT JOIN {tool_organisation_position} topfram ON topfram.id = ' . $sql . ' ')
            ->add_field('topfram.name')
            ->add_callback([format::class, 'format_string'])
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true);
        $columns[] = $newcolumn;

        // Column departmentframework.
        $sql = helper::get_framework_id_sql($deptalias.'.path');
        $newcolumn = (new report_column(
            'departmentframework',
            new lang_string('departmentframework', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join(' LEFT JOIN {tool_organisation_department} todfram ON todfram.id = ' . $sql . ' ')
            ->add_field('todfram.name')
            ->add_callback([format::class, 'format_string'])
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true);
        $columns[] = $newcolumn;

        // Column department with permissions icons.
        $newcolumn = (new report_column(
            'departmentnamewithpermissions',
            new \lang_string('departmentwithicons', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$deptalias}.name", 'entityname')
            ->add_fields("{$posalias}.id, {$posalias}.departmentmanager, {$posalias}.departmentpermissions")
            // Argument passed to callback here is the type of permission and helps in rendering permission icons.
            ->add_callback([\tool_organisation\local\helpers\format::class, 'entityname_and_permissions'], 'departmentmanager');
        $columns[] = $newcolumn;

        // Column position with permissions icons.
        $namecolumn = (new report_column(
            'positionnamewithpermissions',
            new \lang_string('positionwithicons', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$posalias}.name", 'entityname')
            ->add_fields("{$posalias}.id, {$posalias}.globalmanager, {$posalias}.globalpermissions")
            // Argument passed to callback here is the type of permission and helps in rendering permission icons.
            ->add_callback([\tool_organisation\local\helpers\format::class, 'entityname_and_permissions'], 'globalmanager');
        $columns[] = $namecolumn;

        // Column Permissions with icons.
        $newcolumn = (new report_column(
            'permissionswithicons',
            new \lang_string('positionpermissions', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_fields("{$posalias}.id, {$posalias}.departmentmanager, {$posalias}.departmentpermissions,
                {$posalias}.globalmanager, {$posalias}.globalpermissions")
            ->add_callback([\tool_organisation\local\helpers\format::class, 'permissions_with_icons']);
        $columns[] = $newcolumn;

        $newcolumn = (new report_column(
            'timecreated',
            new \lang_string('timecreated', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$jobalias.timecreated")
            ->add_callback([format::class, 'userdate'])
            ->add_aggregation_callback('min', [format::class, 'userdate'])
            ->add_aggregation_callback('max', [format::class, 'userdate']);
        $columns[] = $newcolumn;

        return $columns;
    }

    /**
     * Filters/conditions for jobs
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];
        $jobalias = $this->get_table_alias('tool_organisation_job');
        $posalias = $this->get_table_alias('tool_organisation_position');
        $deptalias = $this->get_table_alias('tool_organisation_department');

        // Position select filter.
        $filters[] = (new report_filter(
            job_position::class,
            'position',
            new lang_string('position', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql($posalias)
            ->set_options_callback(function() {
                return organisation::get_all_positions_menu(
                    ['' => get_string('anyposition', 'tool_organisation')]);
            });

        // Department select filter.
        $filters[] = (new report_filter(
            job_department::class,
            'department',
            new lang_string('department', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql($deptalias)
            ->set_options_callback(function() {
                return organisation::get_all_departments_menu(
                    ['' => get_string('anydepartment', 'tool_organisation')]);
            });

        // Start date filter.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'startdate',
            new lang_string('startdate', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql($jobalias . '.startdate');

        // End date filter.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'enddate',
            new lang_string('enddate', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql($jobalias . '.enddate');

        // Is "Manager or Department lead" filter.
        $filters[] = (new report_filter(
            checkbox::class,
            'manager',
            new lang_string('anymanager', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("(CASE WHEN ({$posalias}.globalmanager = 1 OR {$posalias}.departmentmanager = 1) THEN 1 ELSE 0 END)");

        // Is "Manager" filter.
        $filters[] = (new report_filter(
            checkbox::class,
            'globalmanager',
            new lang_string('globalmanager', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("{$posalias}.globalmanager");

        // Is "Department lead" filter.
        $filters[] = (new report_filter(
            checkbox::class,
            'departmentmanager',
            new lang_string('departmentmanager', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("{$posalias}.departmentmanager");

        // Can receive notifications filter.
        $filters[] = (new report_filter(
            position_permissions::class,
            'canreceivenotifications',
            new lang_string('conditioncanreceivenotifications', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql($posalias);

        // Can allocate to programs filter.
        $filters[] = (new report_filter(
            position_permissions::class,
            'canallocateprograms',
            new lang_string('conditioncanallocateprograms', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql($posalias);

        // Show jobs filter.
        $filters[] = (new report_filter(
            job_time::class,
            'showjobs',
            new lang_string('showjobs', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql($jobalias);

        // Show past jobs filter.
        $filters[] = (new report_filter(
            showpastjobs::class,
            'showpastjobs',
            new \lang_string('showpastjobs', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("{$jobalias}.enddate");

        return $filters;
    }
}
