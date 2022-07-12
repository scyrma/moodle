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

namespace tool_certification\reportbuilder\datasource;

use core_reportbuilder\datasource;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_wp\reportbuilder\local\entities\user;
use core_reportbuilder\local\report\column;
use tool_certification\reportbuilder\local\entities\certification;
use tool_certification\reportbuilder\local\entities\certification_completion;
use tool_certification\reportbuilder\local\entities\certification_revoke;
use tool_certification\reportbuilder\local\entities\certification_user;
use tool_certification\reportbuilder\local\formatters\certification_user as certificationuser_formatter;
use tool_organisation\reportbuilder\local\entities\job;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_user;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;
use lang_string;

/**
 * Certification user allocation datasource
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_user_allocation extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {

        // Add certification user entity as main entity.
        $certificationuserentity = new certification_user();
        $certificationuserentityname = $certificationuserentity->get_entity_name();
        $certificationuser = $certificationuserentity->get_table_alias('tool_certification_users');
        $this->add_entity($certificationuserentity);

        $this->set_main_table('tool_certification_users', $certificationuser);

        // Add user entity.
        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $userentityname = $userentity->get_entity_name();
        $this->add_entity($userentity);

        // Add user table as a base join because is needed in the tenancy check.
        $this->add_join("LEFT JOIN {user} {$user} ON {$user}.id = {$certificationuser}.userid");
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$user}.id"));

        // Add certification entity.
        $certificationentity = new certification();
        $certificationentityname = $certificationentity->get_entity_name();
        $certification = $certificationentity->get_table_alias('tool_certification');
        $certificationjoin = "
            JOIN {tool_certification} {$certification}
              ON {$certificationuser}.certificationid = {$certification}.id
        ";
        $this->add_join($certificationjoin);
        $this->add_entity($certificationentity);

        // Add program entity.
        $programentity = new program();
        $programentityname = $programentity->get_entity_name();
        $program = $programentity->get_table_alias('tool_program');
        $programjoin = "LEFT JOIN {tool_program} {$program} ON {$program}.id = {$certificationuser}.currentprogramid";
        $programentity->set_entity_title(new lang_string('currentprogram', 'tool_certification'));
        // We need to add the program join directly to the report because is needed in some cases when columns from
        // the program entity are not added.
        $this->add_join($programjoin);
        $this->add_entity($programentity);

        // Add program user entity. Dates for certification users are stored in program users table.
        $programuserentity = new program_user();
        $programuserentityname = $programuserentity->get_entity_name();
        $programuser = $programuserentity->get_table_alias('tool_program_users');
        $programuserjoin = "
            LEFT JOIN {tool_program_users} {$programuser}
                   ON {$programuser}.userid = {$certificationuser}.userid
                  AND {$programuser}.certificationid = {$certificationuser}.certificationid
                  AND {$programuser}.programid = {$program}.id
        ";
        $programuserentity->add_joins([$programjoin, $programuserjoin]);
        $programuserentity->set_entity_title(new lang_string('programuserallocation', 'tool_certification'));
        $this->add_entity($programuserentity);

        // Add certification completion entity.
        $completionentity = new certification_completion();
        $certificationcompletion = $completionentity->get_table_alias('tool_certification_compltion');
        $completionentityname = $completionentity->get_entity_name();
        $completionjoin = "
            LEFT JOIN {tool_certification_compltion} {$certificationcompletion}
                   ON {$certificationcompletion}.certificationid = {$certificationuser}.certificationid
                  AND {$certificationcompletion}.userid = {$certificationuser}.userid
                  AND {$certificationcompletion}.timerevoked = 0
                  AND {$certificationcompletion}.islast = 1";
        $this->add_join($completionjoin);
        $this->add_entity($completionentity);

        // Add Job entity.
        $jobentity = new job();
        $job = $jobentity->get_table_alias('tool_organisation_job');
        $jobentityname = $jobentity->get_entity_name();
        $jobjoin = "LEFT JOIN {tool_organisation_job} {$job} ON {$job}.userid = {$user}.id AND " .
            job::get_job_tenant_join("{$job}.tenantid");
        $this->add_entity($jobentity->add_join($jobjoin));

        // Certification revoke.
        $revokeentity = new certification_revoke();
        $certificationrevokename = $revokeentity->get_entity_name();
        $revoke = $revokeentity->get_table_alias('tool_certification_compltion');
        // We can have multiple records on completion table when certifying and revoking same user from same certification.
        // We retrieve just the latest record it was revoked.
        $revokejoin = "
            LEFT JOIN
              (SELECT MAX(id) AS id, certificationid, userid
                 FROM {tool_certification_compltion}
                WHERE timerevoked > 0
             GROUP BY certificationid, userid) tcrmax
                   ON tcrmax.certificationid = {$certificationuser}.certificationid
                  AND tcrmax.userid = {$certificationuser}.userid
            LEFT JOIN {tool_certification_compltion} {$revoke} ON {$revoke}.id = tcrmax.id
        ";
        $this->add_entity($revokeentity->add_join($revokejoin));

        // Check if tenant column/filter/condition needs to be shown.
        // Add tenant entity if current tenant has subtenants.
        $canshowtenantcolumn = hierarchy::has_subtenants(tenancy::get_tenant_id());
        if ($canshowtenantcolumn) {
            $tenantentity = new tenant();
            $tooltenant = $tenantentity->get_table_alias('tool_tenant');
            $tenantentityname = $tenantentity->get_entity_name();
            $tenantentity->add_join("JOIN {tool_tenant} {$tooltenant} ON {$tooltenant}.id = {$certification}.tenantid");
            $this->add_entity($tenantentity);
        }

        // User entity to represent "Certified by".
        $certifiedbyentity = (new user())
            ->add_join("LEFT JOIN {user} tccuc ON tccuc.id = {$certificationcompletion}.certifiedby")
            ->set_table_alias('user', 'tccuc')
            ->set_entity_name('tool_certification_certifiedby')
            ->set_entity_title(new lang_string('certifiedby', 'tool_certification'));
        $this->add_entity($certifiedbyentity);

        // Add tenancy sql to show current tenant certifications plus shared certifications from the Shared space.
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql("{$certification}.tenantid",
            "{$certification}.shared=1");
        $this->add_base_condition_sql($sql, $params);

        // Add certification user entity columns/filters/conditions.
        $this->add_columns_from_entity($certificationuserentityname);
        $this->add_filters_from_entity($certificationuserentityname);
        $this->add_conditions_from_entity($certificationuserentityname);

        // Add user entity columns/filters/conditions.
        $this->add_columns_from_entity($userentityname);
        $this->add_filters_from_entity($userentityname);
        $this->add_conditions_from_entity($userentityname);

        // Add certification entity columns/filters/conditions.
        $this->add_columns_from_entity($certificationentityname);
        $this->add_filters_from_entity($certificationentityname);
        $this->add_conditions_from_entity($certificationentityname);

        // Add program entity columns/filters/conditions.
        $this->add_columns_from_entity($programentityname);
        $this->add_filters_from_entity($programentityname);
        $this->add_conditions_from_entity($programentityname);

        // Add program user entity columns/filters/conditions.
        $this->add_columns_from_entity($programuserentityname);
        $this->add_filters_from_entity($programuserentityname);
        $this->add_conditions_from_entity($programuserentityname);

        // Add certification completion entity columns/filters/conditions.
        $this->add_columns_from_entity($completionentityname);
        $this->add_filters_from_entity($completionentityname);
        $this->add_conditions_from_entity($completionentityname);

        // Add job entity columns/filters/conditions.
        $this->add_columns_from_entity($jobentityname);
        $this->add_filters_from_entity($jobentityname);
        $this->add_conditions_from_entity($jobentityname);

        // Add revoke entity columns/filters/conditions.
        $this->add_columns_from_entity($certificationrevokename);
        $this->add_filters_from_entity($certificationrevokename);
        $this->add_conditions_from_entity($certificationrevokename);

        // Add tenant entity columns/filters/conditions.
        if ($canshowtenantcolumn) {
            $this->add_columns_from_entity($tenantentityname);
            $this->add_filters_from_entity($tenantentityname);
            $this->add_conditions_from_entity($tenantentityname);
        }

        // Add "Certified by" columns.
        $this->add_columns_from_entity('tool_certification_certifiedby',
            ['fullname', 'fullnamewithlink', 'fullnamewithpicture', 'fullnamewithpicturelink', 'firstname', 'lastname', 'email']);

        $this->add_columns($certificationuser, $certification, $certificationcompletion, $user);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('entitycertificationusers', 'tool_certification');
    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'certification:fullname',
            'user:fullnamewithpicturelink',
            'user:lastaccess',
            'certification_user:timecreated',
            'program_user:duedate',
            'certification_user:expirydate',
            'certification_user:certificationstatus',
            'certification_completion:certifieddate',
            'certification_user:actions',
        ];
    }

    /**
     * Adds the columns we want to display in the report
     *
     * @param string $certificationuser
     * @param string $certification
     * @param string $completion
     * @param string $user
     */
    public function add_columns(string $certificationuser, string $certification, string $completion, string $user): void {
        // Actions column.
        $column = (new column(
            'actions',
            new lang_string('actions', 'tool_certification'),
            'certification_user'
        ))
            ->add_field("{$user}.id", 'userid')
            ->add_field("{$certification}.id", 'certificationid')
            ->add_field("{$completion}.id", 'completionid')
            ->add_callback([certificationuser_formatter::class, 'actions']);
        $this->add_column($column);

        // Certification progress column.
        $column = (new column(
            'certificationprogress',
            new lang_string('certificationprogress', 'tool_certification'),
            'certification_completion'
        ))
            ->add_field("{$certification}.program", 'programid')
            ->add_field("{$certificationuser}.userid")
            ->add_callback([\tool_program\reportbuilder\local\formatters\program_user::class, 'programprogress']);
        $this->add_column($column);
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'certification:fullname',
            'program:programselector',
            'certification_user:filterablestatus',
            'certification_user:timecreated',
            'certification_completion:certifieddate',
            'certification_completion:expirydate',
            'user:fullname',
            'job:department',
            'job:position',
        ];
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'certification:archived',
        ];
    }
}
