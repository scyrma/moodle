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

declare(strict_types=1);

namespace core_user\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\helpers\database;

/**
 * Users datasource
 *
 * @package   core_reportbuilder
 * @copyright 2021 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users extends datasource {

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('users');
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $CFG;

        $userentity = new user();
        $usertablealias = $userentity->get_table_alias('user');

        $this->set_main_table('user', $usertablealias);

        $userparamguest = database::generate_param_name();
        /** @uses \tool_tenant\tenancy::get_users_subquery() */
        $tenantsql = component_class_callback(\tool_tenant\tenancy::class, 'get_users_subquery',
            [false, true, "{$usertablealias}.id"], '');
        $this->add_base_condition_sql("{$usertablealias}.id != :{$userparamguest} AND {$tenantsql} {$usertablealias}.deleted = 0", [
            $userparamguest => $CFG->siteguest,
        ]);

        // Add all columns from entities to be available in custom reports.
        $this->add_entity($userentity);

        $userentityname = $userentity->get_entity_name();
        $this->add_columns_from_entity($userentityname);
        $this->add_filters_from_entity($userentityname);
        $this->add_conditions_from_entity($userentityname);

        // Add Job entity.
        /** @uses \tool_organisation\reportbuilder\local\entities\job::prepare_for_user_datasource */
        if ($jobentity = component_class_callback('\tool_organisation\reportbuilder\local\entities\job',
            'prepare_for_user_datasource', [$usertablealias])) {
            $this->add_entity($jobentity);
            $this->add_columns_from_entity($jobentity->get_entity_name());
            $this->add_filters_from_entity($jobentity->get_entity_name());
            $this->add_conditions_from_entity($jobentity->get_entity_name());
        }

        // Add Tenant entity.
        /** @uses \tool_tenant\reportbuilder\local\entities\tenant::prepare_for_user_datasource */
        if ($tenantentity = component_class_callback('\tool_tenant\reportbuilder\local\entities\tenant',
            'prepare_for_user_datasource', [$usertablealias])) {
            $this->add_entity($tenantentity);
            $this->add_columns_from_entity($tenantentity->get_entity_name());
            $this->add_filters_from_entity($tenantentity->get_entity_name());
            $this->add_conditions_from_entity($tenantentity->get_entity_name());
        }
    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        $columns = ['user:fullname', 'user:username', 'user:email'];
        if (array_key_exists('tenant:name', $this->get_columns())) {
            $columns[] = 'tenant:name';
        }
        return $columns;
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        $filters = ['user:fullname', 'user:username', 'user:email'];
        if (array_key_exists('tenant:name', $this->get_filters())) {
            $filters[] = 'tenant:name';
        }
        if (array_key_exists('user:hascurrentjobs', $this->get_filters())) {
            $filters[] = 'user:hascurrentjobs';
        }
        return $filters;
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'user:fullname',
            'user:username',
            'user:email',
            'user:suspended',
        ];
    }

    /**
     * Return the conditions values that will be added to the report once is created
     *
     * @return array
     */
    public function get_default_condition_values(): array {
        return [
            'user:suspended_operator' => boolean_select::NOT_CHECKED,
        ];
    }
}
