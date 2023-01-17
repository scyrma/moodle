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

namespace tool_program\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use lang_string;
use tool_organisation\reportbuilder\local\entities\job;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_completion;
use tool_program\reportbuilder\local\entities\program_content;
use tool_program\reportbuilder\local\entities\program_user;
use tool_tenant\hierarchy;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * Programs allocation completion datasource
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Toni Barbera <toni@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programs_allocation_completion extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        // Add program user entity as main entity.
        $programuserentity = new program_user();
        $programuserentityname = $programuserentity->get_entity_name();
        $programusers = $programuserentity->get_table_alias('tool_program_users');
        $this->add_entity($programuserentity);

        $this->set_main_table('tool_program_users', $programusers);

        // Add user entity.
        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $userentityname = $userentity->get_entity_name();
        $this->add_entity($userentity);

        // Add user table as a base join because is needed in the tenancy check.
        $this->add_join("LEFT JOIN {user} {$user} ON {$user}.id = {$programusers}.userid");
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$user}.id"));

        // Add tenant entity for the user.
        $tenantalias = database::generate_alias();
        $tenantentity = new tenant();
        $tenantentity
            ->set_table_alias('tool_tenant', $tenantalias)
            ->add_joins($tenantentity->get_user_tenant_joins("{$user}.id"))
            ->set_entity_title(new lang_string('usertenant', 'tool_tenant'))
            ->set_entity_name('tool_tenant_user');
        $this->add_entity($tenantentity);

        // Add program entity.
        $programentity = new program();
        $programentityname = $programentity->get_entity_name();
        $program = $programentity->get_table_alias('tool_program');
        $programjoin = "JOIN {tool_program} {$program} ON {$programusers}.programid = {$program}.id";
        // Add program join as a base join to be able to add tenant base sql condition.
        $this->add_join($programjoin);
        $this->add_entity($programentity);

        // Add tenancy sql to show current tenant programs plus shared programs from the Shared space.
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql("{$program}.tenantid",
            "{$program}.shared=1");
        $this->add_base_condition_sql($sql, $params);

        // Add program content entity.
        $programcontententity = new program_content();
        $programset = $programcontententity->get_table_alias('tool_program_set');
        $programcontententityname = $programcontententity->get_entity_name();

        // Add program completion entity.
        $programcompentity = new program_completion();
        $programcomp = $programcompentity->get_table_alias('tool_program_set_completion');
        $programcompentityname = $programcompentity->get_entity_name();

        $programcontentjoin = "JOIN {tool_program_sets} {$programset} ON {$programset}.programid = {$program}.id";
        $programcompletionjoin = "LEFT JOIN {tool_program_set_completion} {$programcomp}
        ON {$programcomp}.setid = {$programset}.id AND {$programcomp}.userid = {$programusers}.userid";

        $programcontententity
            ->add_join($programcontentjoin)
            ->add_join($programcompletionjoin);
        $this->add_entity($programcontententity);

        $programcompentity
            ->add_join($programcontentjoin)
            ->add_join($programcompletionjoin);
        $this->add_entity($programcompentity);

        // Add Job entity.
        $jobentity = new job();
        $job = $jobentity->get_table_alias('tool_organisation_job');
        $jobentityname = $jobentity->get_entity_name();
        $jobjoin = "LEFT JOIN {tool_organisation_job} {$job} ON {$job}.userid = {$user}.id AND " .
            job::get_job_tenant_join("{$job}.tenantid");
        $jobentity->add_join($jobjoin);
        $this->add_entity($jobentity);

        // Add tenant entity for the program.
        $tenantentity = new tenant();
        $tooltenant = $tenantentity->get_table_alias('tool_tenant');
        $tenantentityname = $tenantentity->get_entity_name();
        $tenantentity
            ->add_join("JOIN {tool_tenant} {$tooltenant} ON {$tooltenant}.id = {$program}.tenantid")
            ->set_entity_title(new lang_string('programtenant', 'tool_program'));
        $this->add_entity($tenantentity);

        // Add program user entity columns/filters/conditions.
        $this->add_columns_from_entity($programuserentityname);
        $this->add_filters_from_entity($programuserentityname);
        $this->add_conditions_from_entity($programuserentityname);

        // Add program entity columns/filters/conditions.
        $this->add_columns_from_entity($programentityname);
        $this->add_filters_from_entity($programentityname);
        $this->add_conditions_from_entity($programentityname);

        // Add program content entity columns/filters/conditions.
        $this->add_columns_from_entity($programcontententityname);
        $this->add_filters_from_entity($programcontententityname);
        $this->add_conditions_from_entity($programcontententityname);

        // Add program tenant entity columns/filters/conditions.
        $this->add_columns_from_entity($tenantentityname);
        $this->add_filters_from_entity($tenantentityname);
        $this->add_conditions_from_entity($tenantentityname);

        // Add program completion entity columns/filters/conditions.
        $this->add_columns_from_entity($programcompentityname);
        $this->add_filters_from_entity($programcompentityname);
        $this->add_conditions_from_entity($programcompentityname);

        // Add user entity columns/filters/conditions.
        $this->add_columns_from_entity($userentityname);
        $this->add_filters_from_entity($userentityname);
        $this->add_conditions_from_entity($userentityname);

        // Add user entity columns/filters/conditions.
        $this->add_columns_from_entity('tool_tenant_user');
        $this->add_filters_from_entity('tool_tenant_user');
        $this->add_conditions_from_entity('tool_tenant_user');

        // Add job entity columns/filters/conditions.
        $this->add_columns_from_entity($jobentityname);
        $this->add_filters_from_entity($jobentityname);
        $this->add_conditions_from_entity($jobentityname);

        // Add custom columns for this report.
        $this->add_columns($programusers, $program, $programcomp, $user, [$programcontentjoin, $programcompletionjoin]);

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        $canviewinactiveusers = \tool_tenant\permission::can_view_inactive_users();
        if (!$canviewinactiveusers) {
            $this->add_base_condition_simple("{$user}.deleted", 0);
            $this->add_base_condition_simple("{$user}.confirmed", 1);
        }
    }

    /**
     * Adds the columns we want to display in the report
     *
     * @param string $programuser
     * @param string $program
     * @param string $programcompletion
     * @param string $user
     * @param array $joins
     */
    public function add_columns(string $programuser, string $program, string $programcompletion, string $user,
                                array $joins): void {

        // Custom actions column for this datasource.
        $this->add_column((new column(
            'actions',
            new lang_string('actions', 'tool_program'),
            'program_user'
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$user}.id", 'userid')
            ->add_field("{$programuser}.certificationid", 'certificationid')
            ->add_field("{$program}.id", 'programid')
            ->add_callback([\tool_program\reportbuilder\local\formatters\program_user::class, 'actions'])
            ->set_disabled_aggregation_all());

        // Days taking program column.
        $this->add_column((new column(
            'daystakingprogram',
            new lang_string('daystakingprogram', 'tool_program'),
            'program_completion'
        ))
            ->add_joins($joins)
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$programuser}.startdate")
            ->add_field("{$programuser}.timecreated")
            ->add_field("{$programcompletion}.completeddate")
            ->set_is_sortable(true)
            ->add_callback([\tool_program\reportbuilder\local\formatters\program_user::class, 'daystakingprogram']));

        // Days since allocation column.
        $this->add_column((new column(
            'dayssinceallocation',
            new lang_string('dayssinceallocation', 'tool_program'),
            'program_completion'
        ))
            ->add_joins($joins)
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$programuser}.timecreated")
            ->add_field("{$programcompletion}.completeddate")
            ->set_is_sortable(true)
            ->add_callback([\tool_program\reportbuilder\local\formatters\program_user::class, 'dayssinceallocation']));
    }

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportprogramsallocationcompletion', 'tool_program');
    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        $columns = $this->get_columns();
        return array_merge([
            'program:fullname',
            'user:fullnamewithpicturelink',
            'user:lastaccess',
            'program_user:timecreated',
            'program_user:duedate',
            'program_user:enddate',
            'program_user:programstatus',
            'program_completion:completeddate',
        ], array_key_exists('tenant:name', $columns) ? ['tenant:name'] : [],
        ['program_user:actions']);
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        $filters = $this->get_filters();
        return array_merge([
            'program:programselector',
            'program_user:filterablestatus',
            'program_user:timecreated',
            'program_completion:completeddate',
            'user:fullname',
            'job:department',
            'job:position',
        ], array_key_exists('tenant:name', $filters) ? ['tenant:name'] : []);
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'program:archived',
            'program:visible',
        ];
    }

    /**
     * Return the conditions values that will be added to the report once is created
     *
     * TODO this method is pending the landing of MDL-73916.
     *
     * @return string[]
     */
    public function get_default_condition_values(): array {
        return [
            'program:archived_operator' => boolean_select::NOT_CHECKED,
            'program:visible_operator' => boolean_select::CHECKED,
        ];
    }
}
