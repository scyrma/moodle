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

use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\{column, filter};
use tool_organisation\local\helpers\format;
use tool_organisation\reportbuilder\local\filters\department;
use tool_organisation\reportbuilder\local\filters\org_structure;
use tool_organisation\reportbuilder\local\filters\position;
use tool_organisation\reportbuilder\local\filters\showpast;
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

        // Department.
        $columns[] = (new column(
            'department',
            new lang_string('department', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("JOIN {tool_organisation_department} {$department} ON {$department}.id = {$job}.departmentid")
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$department}.name")
            ->set_is_sortable(true)
            ->add_callback(static function(string $name): string {
                return format_string($name);
            });

        // Position.
        $columns[] = (new column(
            'position',
            new lang_string('position', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid")
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$position}.name")
            ->set_is_sortable(true)
            ->add_callback(static function(string $name): string {
                return format_string($name);
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

        // Permissions with icons.
        $columns[] = (new column(
            'permissionswithicons',
            new lang_string('positionpermissions', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid")
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$position}.id, {$position}.departmentmanager, {$position}.departmentpermissions,
                {$position}.globalmanager, {$position}.globalpermissions")
            ->add_callback([format::class, 'permissions_with_icons']);

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
            ->add_join("JOIN {tool_organisation_department} {$department} ON {$department}.id = {$job}.departmentid");

        // Position.
        $filters[] = (new filter(
            position::class,
            'position',
            new lang_string('position', 'tool_organisation'),
            $this->get_entity_name(),
            $position
        ))
            ->add_joins($this->get_joins())
            ->add_join("JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid");

        // Past jobs.
        $filters[] = (new filter(
            showpast::class,
            'showpast',
            new lang_string('showpastjobs', 'tool_organisation'),
            $this->get_entity_name(),
            "{$job}.enddate",
        ))
            ->add_joins($this->get_joins());

        // Organisation structure.
        $filters[] = (new filter(
            org_structure::class,
            'orgstructure',
            new lang_string('orgstructure', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins());

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
}
