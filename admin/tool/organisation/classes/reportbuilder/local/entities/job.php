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

declare(strict_types=1);

namespace tool_organisation\reportbuilder\local\entities;

use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format as rb_formatter;
use lang_string;
use stdClass;
use tool_organisation\helper;
use tool_organisation\local\helpers\format;
use tool_organisation\organisation;
use tool_organisation\reportbuilder\local\filters\department;
use tool_organisation\reportbuilder\local\filters\org_structure;
use tool_organisation\reportbuilder\local\filters\position;
use tool_organisation\reportbuilder\local\filters\position_permissions;
use tool_organisation\reportbuilder\local\filters\showjobs;
use tool_organisation\reportbuilder\local\filters\showpast;
use tool_organisation\reportbuilder\local\formatters\job as job_formatter;
use tool_tenant\hierarchy;

/**
 * Columns, filters and conditions that defines the jobs entity and can be reused in any report datasource.
 *
 * Note that calling code MUST use, or define the join for, the jobs table/alias (typically 'toj') when using this entity
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class job extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'tool_organisation_job' => 'toj',
            'tool_organisation_department' => 'tod',
            'tool_organisation_position' => 'topos',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityjob', 'tool_organisation');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        // All the filters defined by the entity can also be used as conditions.
        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $job = $this->get_table_alias('tool_organisation_job');
        $department = $this->get_table_alias('tool_organisation_department');
        $position = $this->get_table_alias('tool_organisation_position');

        $departmentjoin = "LEFT JOIN {tool_organisation_department} {$department} ON {$department}.id = {$job}.departmentid";
        $positionjoin = "LEFT JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid";

        // Department.
        $columns[] = (new column(
            'department',
            new lang_string('department', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($departmentjoin)
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$department}.name")
            ->set_is_sortable(true)
            ->add_callback(static function(?string $name): string {
                return format_string($name);
            });

        // Position.
        $columns[] = (new column(
            'position',
            new lang_string('position', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($positionjoin)
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$position}.name")
            ->set_is_sortable(true)
            ->add_callback(static function(?string $name): string {
                return format_string($name);
            });

        // Column position department.
        $columns[] = (new column(
            'positiondepartment',
            new lang_string('jobpositiondepartment', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_joins([$positionjoin, $departmentjoin])
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$position}.name", 'position')
            ->add_field("{$department}.name", 'department')
            ->set_is_sortable(true, ['position', 'department'])
            ->add_callback(static function(?string $name, stdClass $row): string {
                if (!$name) {
                    return '';
                }
                $array = ['position' => format_string($row->position), 'department' => format_string($row->department)];
                return get_string('positionanddepartmentdisplay', 'tool_organisation', $array);
            });

        // Start date.
        $columns[] = (new column(
            'startdate',
            new lang_string('startdate', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$job}.startdate")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'jobdate']);

        // End date.
        $columns[] = (new column(
            'enddate',
            new lang_string('enddate', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$job}.enddate")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'jobdate']);

        // Column globalmanagementicons.
        $columns[] = (new column(
            'globalmanagementicons',
            new lang_string('globalmanagementicons', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($positionjoin)
            ->set_type(column::TYPE_TEXT)
            ->add_field("CASE WHEN {$position}.globalmanager = 1
                THEN {$position}.globalpermissions
                ELSE NULL END", 'globalpermissions')
            ->add_callback([job_formatter::class, 'managementicons']);

        // Column departmentmanagementicons.
        $columns[] = (new column(
            'departmentmanagementicons',
            new lang_string('departmentmanagementicons', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($positionjoin)
            ->set_type(column::TYPE_TEXT)
            ->add_field("CASE WHEN {$position}.departmentmanager = 1
                THEN {$position}.departmentpermissions
                ELSE NULL END", 'departmentpermissions')
            ->add_callback([job_formatter::class, 'managementicons'], 'departmentpermissions');

        // Column positionframework.
        $sql = helper::get_framework_id_sql($position.'.path');
        $posframework = database::generate_alias();
        $columns[] = (new column(
            'positionframework',
            new lang_string('positionframework', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($positionjoin)
            ->add_join(" LEFT JOIN {tool_organisation_position} {$posframework} ON {$posframework}.id = $sql ")
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$posframework}.name")
            ->add_callback(static function($value) {
                return format_string($value);
            });

        // Column departmentframework.
        $sql = helper::get_framework_id_sql($department.'.path');
        $depframework = database::generate_alias();
        $columns[] = (new column(
            'departmentframework',
            new lang_string('departmentframework', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($departmentjoin)
            ->add_join(" LEFT JOIN {tool_organisation_department} {$depframework} ON {$depframework}.id = $sql ")
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$depframework}.name")
            ->add_callback(static function($value) {
                return format_string($value);
            });

        // Column department with permissions icons.
        $columns[] = (new column(
            'departmentnamewithpermissions',
            new lang_string('departmentwithicons', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($departmentjoin)
            ->add_join($positionjoin)
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$department}.name", 'entityname')
            ->add_fields("{$position}.id, {$position}.departmentmanager, {$position}.departmentpermissions")
            ->add_callback([job_formatter::class, 'entityname_and_permissions'], 'departmentmanager');

        // Column position with permissions icons.
        $columns[] = (new column(
            'positionnamewithpermissions',
            new lang_string('positionwithicons', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($departmentjoin)
            ->add_join($positionjoin)
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$position}.name", 'entityname')
            ->add_fields("{$position}.id, {$position}.globalmanager, {$position}.globalpermissions")
            ->add_callback([job_formatter::class, 'entityname_and_permissions'], 'globalmanager');

        // Permissions with icons.
        $columns[] = (new column(
            'permissionswithicons',
            new lang_string('positionpermissions', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($positionjoin)
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$position}.id, {$position}.departmentmanager, {$position}.departmentpermissions,
                {$position}.globalmanager, {$position}.globalpermissions")
            ->add_callback([format::class, 'permissions_with_icons']);

        // Column timecreated.
        $dateformat = get_string('strftimedatefullshort', 'core_langconfig');
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$job}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([rb_formatter::class, 'userdate'], $dateformat);

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $job = $this->get_table_alias('tool_organisation_job');
        $department = $this->get_table_alias('tool_organisation_department');
        $position = $this->get_table_alias('tool_organisation_position');

        // Department.
        $filters[] = (new filter(
            department::class,
            'department',
            new lang_string('department', 'tool_organisation'),
            $this->get_entity_name(),
            $department
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_organisation_department} {$department} ON {$department}.id = {$job}.departmentid");

        // Position.
        $filters[] = (new filter(
            position::class,
            'position',
            new lang_string('position', 'tool_organisation'),
            $this->get_entity_name(),
            $position
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid");

        // Past jobs.
        $filters[] = (new filter(
            showpast::class,
            'showpast',
            new lang_string('showpastjobs', 'tool_organisation'),
            $this->get_entity_name(),
            "{$job}.enddate",
        ))
            ->add_joins($this->get_joins());

        // Start date filter.
        $filters[] = (new filter(
            date::class,
            'startdate',
            new lang_string('startdate', 'tool_organisation'),
            $this->get_entity_name(),
            "{$job}.startdate",
        ))
            ->add_joins($this->get_joins());

        // End date filter.
        $filters[] = (new filter(
            date::class,
            'enddate',
            new lang_string('enddate', 'tool_organisation'),
            $this->get_entity_name(),
            "{$job}.enddate",
        ))
            ->add_joins($this->get_joins());

        // Is "Manager or Department lead" filter.
        $filters[] = (new filter(
            boolean_select::class,
            'manager',
            new lang_string('anymanager', 'tool_organisation'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid")
            ->set_field_sql("(CASE WHEN ({$position}.globalmanager = 1 OR {$position}.departmentmanager = 1) THEN 1 ELSE 0 END)");

        // Is "Manager" filter.
        $filters[] = (new filter(
            boolean_select::class,
            'globalmanager',
            new lang_string('globalmanager', 'tool_organisation'),
            $this->get_entity_name(),
            "{$position}.globalmanager"
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid");

        // Is "Department lead" filter.
        $filters[] = (new filter(
            boolean_select::class,
            'departmentmanager',
            new lang_string('departmentmanager', 'tool_organisation'),
            $this->get_entity_name(),
            "{$position}.departmentmanager"
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid");

        // Can receive notifications filter.
        $filters[] = (new filter(
            position_permissions::class,
            'canreceivenotifications',
            new lang_string('conditioncanreceivenotifications', 'tool_organisation'),
            $this->get_entity_name(),
            "{$position}"
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid")
            ->set_options(['type' => organisation::PERM_RECEIVE_NOTIFICATIONS]);

        // Can allocate to programs filter.
        $filters[] = (new filter(
            position_permissions::class,
            'canallocateprograms',
            new lang_string('conditioncanallocateprograms', 'tool_organisation'),
            $this->get_entity_name(),
            "{$position}"
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid")
            ->set_options(['type' => organisation::PERM_ALLOCATE_PROGRAMS]);

        // Organisation structure.
        $filters[] = (new filter(
            org_structure::class,
            'orgstructure',
            new lang_string('orgstructure', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins());

        // Show jobs filter.
        $filters[] = (new filter(
            showjobs::class,
            'showjobs',
            new lang_string('showjobs', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql($job);

        return $filters;
    }

    /**
     * Generate SQL snippet suitable for adding to the entity join on the jobs table. We should only include jobs according to
     * the hierarchy of the current tenant
     *
     * This should be called within datasources or reports that don't otherwise filter by the tenant
     *
     * @param string $tenantfield as "tablealias.field" (e.g. "job.tenantid")
     * @param int $tenantid
     * @return string
     */
    public static function get_job_tenant_join(string $tenantfield, int $tenantid = 0): string {
        [$sql, $params] = hierarchy::filter_own_or_sub_entities_sql($tenantfield, $tenantid);

        // We can't use parameters in entity joins, so interpolate them straight into the SQL.
        return preg_replace_callback('/(?::(?<param>[a-z_0-9]+))/', static function(array $matches) use ($params) {
            $value = $params[$matches['param']];

            return is_int($value) ? $value : "'{$value}'";
        }, $sql);
    }

    /**
     * Callback for adding tenant column to the user datasource
     *
     * @param string $usertablealias
     * @return static
     */
    public static function prepare_for_user_datasource(string $usertablealias): self {
        $jobentity = new self();
        $job = $jobentity->get_table_alias('tool_organisation_job');
        $jobjoin = "LEFT JOIN {tool_organisation_job} {$job} ON {$job}.userid = {$usertablealias}.id";
        $jobentity->add_join($jobjoin);
        return $jobentity;
    }
}
