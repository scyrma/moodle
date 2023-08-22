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

use core_course\local\entities\course_category;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\report\column;
use lang_string;
use tool_certification\reportbuilder\local\entities\certification;
use tool_certification\reportbuilder\local\entities\certification_user;
use tool_certification\reportbuilder\local\formatters\certification as certification_format;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_content;
use tool_program\reportbuilder\local\entities\program_user;
use tool_tenant\hierarchy;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_tenant\permission;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;

/**
 * Certifications datasource
 *
 * @package   tool_certification
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certifications extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        // Add certification entity as main entity.
        $certificationentity = new certification();
        $certificationentityname = $certificationentity->get_entity_name();
        $toolcertification = $certificationentity->get_table_alias('tool_certification');
        $this->add_entity($certificationentity);

        $this->set_main_table('tool_certification', $toolcertification);

        // Add program entity.
        $programentity = new program();
        $toolprogram = $programentity->get_table_alias('tool_program');
        $programentityname = $programentity->get_entity_name();
        $programjoin = "JOIN {tool_program} {$toolprogram} ON {$toolprogram}.id = {$toolcertification}.program";
        $programentity->add_join($programjoin);
        $this->add_entity($programentity);

        // Add program content entity.
        $programcontententity = new program_content();
        $toolprogramset = $programcontententity->get_table_alias('tool_program_set');
        $toolprogramcourse = $programcontententity->get_table_alias('tool_program_course');
        $programcontententityname = $programcontententity->get_entity_name();
        $programcontentjoin = "LEFT JOIN {tool_program_sets} {$toolprogramset} ON {$toolprogramset}.programid = {$toolprogram}.id";
        $programcontententity
            ->add_join($programjoin)
            ->add_join($programcontentjoin);
        $this->add_entity($programcontententity);

        // Add course entity.
        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');
        $courseentityname = $courseentity->get_entity_name();
        $coursejoins = [
            "LEFT JOIN {tool_program_courses} {$toolprogramcourse} ON {$toolprogramcourse}.setid = {$toolprogramset}.id",
            "LEFT JOIN {course} {$course} ON {$course}.id = {$toolprogramcourse}.courseid",
        ];
        $courseentity
            ->add_join($programjoin)
            ->add_join($programcontentjoin)
            ->add_joins($coursejoins);
        $this->add_entity($courseentity);

        // Add course category entity.
        $coursecatentity = new course_category();
        $coursecatentityname = $coursecatentity->get_entity_name();
        $coursecategories = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_join($programjoin)
            ->add_join($programcontentjoin)
            ->add_joins($coursejoins)
            ->add_join("LEFT JOIN {course_categories} {$coursecategories} ON {$coursecategories}.id = {$course}.category"));

        // Add certification user alocation entity.
        $certificationuserentity = new certification_user();
        $certificationusers = $certificationuserentity->get_table_alias('tool_certification_users');
        $certificationusername = $certificationuserentity->get_entity_name();
        $tenantselect = tenancy::get_users_subquery(false, false, "{$certificationusers}.userid");

        // Add certification completion entity.
        $certificationcompletionentity = new certification_user();
        $certificationcompletion = $certificationcompletionentity->get_table_alias('tool_certification_compltion');

        // Add program user entity.
        $programuserentity = new program_user();
        $programusers = $programuserentity->get_table_alias('tool_program_users');

        // Add user entity.
        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $userentityname = $userentity->get_entity_name();

        $userjoins = [
            "LEFT JOIN {tool_certification_users} {$certificationusers}
                ON {$certificationusers}.certificationid = {$toolcertification}.id AND $tenantselect",
            "LEFT JOIN {user} {$user} ON {$user}.id = {$certificationusers}.userid"
        ];

        $certificationuserjoins = [
            "LEFT JOIN {tool_certification_compltion} {$certificationcompletion}
                ON {$certificationcompletion}.certificationid = {$certificationusers}.certificationid
                AND {$certificationcompletion}.userid = {$certificationusers}.userid
                AND {$certificationcompletion}.timerevoked = 0
                AND {$certificationcompletion}.islast = 1",
            "LEFT JOIN {tool_program_users} {$programusers}
                ON {$programusers}.programid = {$certificationcompletion}.programid
                AND {$certificationusers}.certificationid = {$programusers}.certificationid
                AND {$certificationusers}.userid = {$programusers}.userid"
        ];

        $certificationuserentity
            ->add_joins($userjoins)
            ->add_joins($certificationuserjoins);
        $this->add_entity($certificationuserentity);

        $userentity->add_joins($userjoins);
        $this->add_entity($userentity);

        // Add tenant entity for the certification.
        $certificationtenantentity = new tenant();
        $tooltenant = $certificationtenantentity->get_table_alias('tool_tenant');
        $certificationtenantentityname = 'certification_tenant';
        $certificationtenantentity
            ->set_entity_name($certificationtenantentityname)
            ->add_join("JOIN {tool_tenant} {$tooltenant} ON {$tooltenant}.id = {$toolcertification}.tenantid")
            ->set_entity_title(new lang_string('certificationtenant', 'tool_certification'));
        $this->add_entity($certificationtenantentity);

        // Add tenant entity for the program.
        $programtenantentity = new tenant();
        $tooltenantalias = database::generate_alias();
        $programtenantentityname = 'program_tenant';
        $programtenantentity
            ->set_table_alias('tool_tenant', $tooltenantalias)
            ->set_entity_name($programtenantentityname)
            ->add_join($programjoin)
            ->add_join("JOIN {tool_tenant} {$tooltenantalias} ON {$tooltenantalias}.id = {$toolprogram}.tenantid")
            ->set_entity_title(new lang_string('programtenant', 'tool_program'));
        $this->add_entity($programtenantentity);

        // Add certification entity columns/filters/conditions.
        $this->add_columns_from_entity($certificationentityname);
        $this->add_filters_from_entity($certificationentityname);
        $this->add_conditions_from_entity($certificationentityname);

        // Add certification tenant entity columns/filters/conditions.
        $this->add_columns_from_entity($certificationtenantentityname);
        $this->add_filters_from_entity($certificationtenantentityname);
        $this->add_conditions_from_entity($certificationtenantentityname);

        // Add program entity columns/filters/conditions.
        $this->add_columns_from_entity($programentityname);
        $this->add_filters_from_entity($programentityname);
        $this->add_conditions_from_entity($programentityname);

        // Add program content entity columns/filters/conditions.
        $this->add_columns_from_entity($programcontententityname);
        $this->add_filters_from_entity($programcontententityname);
        $this->add_conditions_from_entity($programcontententityname);

        // Add program tenant entity columns/filters/conditions.
        $this->add_columns_from_entity($programtenantentityname);
        $this->add_filters_from_entity($programtenantentityname);
        $this->add_conditions_from_entity($programtenantentityname);

        // Add course entity columns/filters/conditions.
        $this->add_columns_from_entity($courseentityname);
        $this->add_filters_from_entity($courseentityname);
        $this->add_conditions_from_entity($courseentityname);

        // Add course category entity columns/filters/conditions.
        $this->add_columns_from_entity($coursecatentityname);
        $this->add_filters_from_entity($coursecatentityname);
        $this->add_conditions_from_entity($coursecatentityname);

        // Add certification user entity columns/filters/conditions.
        $this->add_columns_from_entity($certificationusername);
        $this->add_filters_from_entity($certificationusername);
        $this->add_conditions_from_entity($certificationusername);

        // Add user entity columns/filters/conditions.
        $this->add_columns_from_entity($userentityname);
        $this->add_filters_from_entity($userentityname);
        $this->add_conditions_from_entity($userentityname);

        // Add custom columns for this report.
        $this->add_columns($toolcertification);

        // Add tenancy sql to show current tenant certification plus shared certifications from the Shared space.
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql("{$toolcertification}.tenantid",
            "{$toolcertification}.shared=1");
        $this->add_base_condition_sql($sql, $params);
    }

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('certifications', 'tool_certification');
    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        $canshowtenantcolumn = permission::can_show_tenant_report_column();

        $defaultcolumns = array_merge([
            'certification:fullname',
            'program:fullnamewithimage',
            'program:numbercoursesunique',
            'program_content:coursesinsetcommaseparatedlinks',
        ], $canshowtenantcolumn ? ['certification_tenant:name'] : []);

        return $defaultcolumns;
    }

    /**
     * Adds the columns we want to display in the report
     *
     * @param string $toolcertificationalias
     */
    public function add_columns(string $toolcertificationalias): void {
        // Custom actions column for this datasource.
        $this->add_column((new column(
            'actions',
            new lang_string('actions', 'tool_certification'),
            'certification'
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$toolcertificationalias}.id")
            ->add_callback([certification_format::class, 'actions'])
            ->set_disabled_aggregation_all());
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        $canshowtenantcolumn = permission::can_show_tenant_report_column();

        $defaultfilters = array_merge([
            'certification:fullname',
            'program:programselector',
            'certification:archived',
            'program:course',
            'certification:timecreated',
            'certification:timemodified',
        ], $canshowtenantcolumn ? ['certification_tenant:name'] : []);

        return $defaultfilters;
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'certification:archived',
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
        return [];
    }
}
