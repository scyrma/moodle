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

namespace tool_tenant\reportbuilder\local\entities;

use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\{column, filter};
use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_tenant\reportbuilder\local\filters\{tenant as tenant_filter, tenant_with_empty};

/**
 * Tenant entity class implementation
 *
 * @package    tool_tenant
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Paul Holden <paulh@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenant extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return string[]
     */
    protected function get_default_table_aliases(): array {
        return [\tool_tenant\tenant::TABLE => 't', \tool_tenant\tenant_user::TABLE => 'tu'];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('tenant', 'tool_tenant');
    }

    /**
     * Initialise the entity, add all report elements
     *
     * @return base
     */
    public function initialise(): base {

        // This permission is used for setting element availability.
        $tenantavailability = permission::can_show_tenant_report_column();

        $columns = $this->get_all_columns($tenantavailability);
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        // All the filters defined by the entity can also be used as conditions.
        $filters = $this->get_all_filters($tenantavailability);
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
     * @param bool $tenantavailability
     * @return column[]
     */
    protected function get_all_columns(bool $tenantavailability): array {
        $tablealias = $this->get_table_alias(\tool_tenant\tenant::TABLE);

        // Tenant name.
        $columns[] = (new column(
            'name',
            new lang_string('name', 'tool_tenant'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.id, {$tablealias}.name")
            ->set_is_sortable(true, ["{$tablealias}.name"])
            ->set_is_available($tenantavailability)
            ->set_callback(static function($tenantid): string {
                if (empty($tenantid)) {
                    return '';
                }

                return (string) tenancy::get_tenant_name_from_id((int) $tenantid);
            });

        return $columns;
    }

    /**
     * Returns list of all available filters
     *
     * @param bool $tenantavailability
     * @return filter[]
     */
    protected function get_all_filters(bool $tenantavailability): array {
        $tablealias = $this->get_table_alias(\tool_tenant\tenant::TABLE);

        // Tenant name.
        $filters[] = (new filter(
            tenant_filter::class,
            'name',
            new lang_string('name', 'tool_tenant'),
            $this->get_entity_name(),
            "{$tablealias}.id"
        ))
            ->add_joins($this->get_joins())
            ->set_is_available($tenantavailability);

        // Tenant name (with empty).
        $filters[] = (new filter(
            tenant_with_empty::class,
            'namewithempty',
            new lang_string('namewithempty', 'tool_tenant'),
            $this->get_entity_name(),
            "{$tablealias}.id"
        ))
            ->add_joins($this->get_joins())
            ->set_is_available($tenantavailability);

        return $filters;
    }

    /** @var string */
    protected $tempalias = null;

    /**
     * SQL joins for tenant table
     *
     * When there is no record in {tool_tenant_user} for an existing user it means that user belongs
     * to the default tenant.
     *
     * @param string $userfield as "tablealias.field" (e.g. "u.id")
     * @return string[]
     */
    public function get_user_tenant_joins(string $userfield): array {
        $tenantuser = $this->get_table_alias('tool_tenant_user');
        $tenant = $this->get_table_alias('tool_tenant');
        $defaulttenantid = tenancy::get_default_tenant_id();

        if ($this->tempalias === null) {
            // We need to add a temporary table to the query but we need to make sure that the alias
            // stays the same if function get_user_tenant_joins() is called several times.
            $this->tempalias = \core_reportbuilder\local\helpers\database::generate_alias();
        }

        // When there is no record in {tool_tenant_user} for an existing user or it refers to archived tenant, it means that user
        // belongs to the default tenant. At the same time when there is a left join with {user} table and there is no user,
        // check that $userfield is not null to make sure we return null in the tenant column and not the "Default tenant".
        return [
            "LEFT JOIN {tool_tenant_user} {$tenantuser} ON {$tenantuser}.userid = {$userfield}",
            "LEFT JOIN {tool_tenant} {$this->tempalias} ON " .
                "{$this->tempalias}.id = {$tenantuser}.tenantid AND {$this->tempalias}.archived = 0",
            "LEFT JOIN {tool_tenant} {$tenant} ON " .
                "{$tenant}.id = COALESCE({$this->tempalias}.id, {$defaulttenantid}) AND {$userfield} IS NOT NULL",
        ];
    }

    /**
     * Callback for adding tenant column to the user datasource
     *
     * @param string $usertablealias
     * @return static
     */
    public static function prepare_for_user_datasource(string $usertablealias): self {
        $tenantentity = new self();
        $tenantentity->add_joins($tenantentity->get_user_tenant_joins($usertablealias.'.id'));
        return $tenantentity;
    }
}
