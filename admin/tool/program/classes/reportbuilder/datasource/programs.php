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

use core_course\local\entities\course_category;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\report\column;
use lang_string;
use tool_program\local\helpers\program_format;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_content;
use tool_program\reportbuilder\local\entities\program_user;
use tool_tenant\hierarchy;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * Programs datasource
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programs extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        // Add program entity as main entity.
        $programentity = new program();
        $programentityname = $programentity->get_entity_name();
        $program = $programentity->get_table_alias('tool_program');
        $this->add_entity($programentity);

        $this->set_main_table('tool_program', $program);

        // Add program content entity.
        $programcontententity = new program_content();
        $programset = $programcontententity->get_table_alias('tool_program_set');
        $programcourse = $programcontententity->get_table_alias('tool_program_course');
        $programcontententityname = $programcontententity->get_entity_name();
        $programcontentjoin = "LEFT JOIN {tool_program_sets} {$programset} ON {$programset}.programid = {$program}.id";
        $programcontententity->add_join($programcontentjoin);
        $this->add_entity($programcontententity);

        // Add course entity.
        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');
        $courseentityname = $courseentity->get_entity_name();
        $coursejoins = [
            "LEFT JOIN {tool_program_courses} {$programcourse} ON {$programcourse}.setid = {$programset}.id",
            "LEFT JOIN {course} {$course} ON {$course}.id = {$programcourse}.courseid"
        ];
        $courseentity
            ->add_join($programcontentjoin)
            ->add_joins($coursejoins);
        $this->add_entity($courseentity);

        // Add course category entity.
        $coursecatentity = new course_category();
        $coursecatentityname = $coursecatentity->get_entity_name();
        $coursecategories = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_join($programcontentjoin)
            ->add_joins($coursejoins)
            ->add_join("LEFT JOIN {course_categories} {$coursecategories} ON {$coursecategories}.id = {$course}.category"));

        // Add user entity.
        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $programuserentity = new program_user();
        $programusers = $programuserentity->get_table_alias('tool_program_users');
        $userentityname = $userentity->get_entity_name();
        $tenantselect = tenancy::get_users_subquery(false, false, "{$programusers}.userid");
        $userjoins = [
            "LEFT JOIN {tool_program_users} {$programusers} ON {$programusers}.programid = {$program}.id AND $tenantselect",
            "LEFT JOIN {user} {$user} ON {$user}.id = {$programusers}.userid"
        ];
        $userentity->add_joins($userjoins);
        $this->add_entity($userentity);

        // Add tenant entity.
        $tenantentity = new tenant();
        $tooltenant = $tenantentity->get_table_alias('tool_tenant');
        $tenantentityname = $tenantentity->get_entity_name();
        $tenantentity
            ->add_join("JOIN {tool_tenant} {$tooltenant} ON {$tooltenant}.id = {$program}.tenantid")
            ->set_entity_title(new lang_string('programtenant', 'tool_program'));
        $this->add_entity($tenantentity);

        // Add program entity columns/filters/conditions.
        $this->add_columns_from_entity($programentityname);
        $this->add_filters_from_entity($programentityname);
        $this->add_conditions_from_entity($programentityname);

        // Add program content entity columns/filters/conditions.
        $this->add_columns_from_entity($programcontententityname);
        $this->add_filters_from_entity($programcontententityname);
        $this->add_conditions_from_entity($programcontententityname);

        // Add tenant entity columns/filters/conditions.
        $this->add_columns_from_entity($tenantentityname);
        $this->add_filters_from_entity($tenantentityname);
        $this->add_conditions_from_entity($tenantentityname);

        // Add course entity columns/filters/conditions.
        $this->add_columns_from_entity($courseentityname);
        $this->add_filters_from_entity($courseentityname);
        $this->add_conditions_from_entity($courseentityname);

        // Add course category entity columns/filters/conditions.
        $this->add_columns_from_entity($coursecatentityname);
        $this->add_filters_from_entity($coursecatentityname);
        $this->add_conditions_from_entity($coursecatentityname);

        // Add user entity columns/filters/conditions.
        $this->add_columns_from_entity($userentityname);
        $this->add_filters_from_entity($userentityname);
        $this->add_conditions_from_entity($userentityname);

        // Add custom columns for this report.
        $this->add_columns($program);

        // Add tenancy sql to show current tenant programs plus shared programs from the Shared space.
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql("{$program}.tenantid",
            "{$program}.shared=1");
        $this->add_base_condition_sql($sql, $params);
    }

    /**
     * Adds the columns we want to display in the report
     *
     * @param string $programalias
     */
    public function add_columns(string $programalias): void {
        // Custom actions column for this datasource.
        $this->add_column((new column(
            'actions',
            new lang_string('actions', 'tool_program'),
            'program'
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$programalias}.id")
            ->add_callback([program_format::class, 'actions'])
            ->set_disabled_aggregation_all());
    }

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('programs', 'tool_program');
    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        $columns = $this->get_columns();
        return array_merge([
            'program:fullnamewithimage',
            'program:associatedcertifications',
            'program:numbercoursesunique',
            'program_content:coursesinsetcommaseparatedlinks',
        ], array_key_exists('tenant:name', $columns) ? ['tenant:name'] : []);
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
            'program:archived',
            'program:certification',
            'program:course',
            'program:timecreated',
            'program:timemodified',
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
            'tool_program:archived_operator' => boolean_select::NOT_CHECKED,
            'tool_program:visible_operator' => boolean_select::CHECKED,
        ];
    }
}
