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

namespace tool_wp\reportbuilder\datasource;

use core_course\local\entities\course_category;
use lang_string;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use tool_certification\reportbuilder\local\entities\certification;
use tool_program\reportbuilder\local\entities\program;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_tenant\tenancy;
use tool_wp\db;
use tool_wp\reportbuilder\local\entities\course_reset as course_reset_entity;

/**
 * Class Course reset datasource
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_reset extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {

        $coursereseterentity = new course_reset_entity();
        $courseresetentityname = $coursereseterentity->get_entity_name();
        $coursereset = $coursereseterentity->get_table_alias('tool_wp_course_reset');
        $this->set_main_table('tool_wp_course_reset', $coursereset);

        // Add course reset entity.
        $this->add_entity($coursereseterentity);

        // Add user entity.
        $userentity = new user();
        $userentityname = $userentity->get_entity_name();
        $user = $userentity->get_table_alias('user');
        $userentity->add_join("LEFT JOIN {user} {$user} ON {$user}.id = {$coursereset}.userid");
        $this->add_entity($userentity);

        // Show only users from the current tenant and/or its subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$coursereset}.userid"));

        // Add user who requested entity.
        $userrequestedentity = new user();
        $userrequested = db::generate_alias();
        $this->add_entity(($userrequestedentity)
            ->add_join("LEFT JOIN {user} {$userrequested} ON {$userrequested}.id = {$coursereset}.userrequested")
            ->set_entity_name('tool_wp_userrequested')
            ->set_table_alias('user', $userrequested)
            ->set_entity_title(new lang_string('userrequested', 'tool_wp')));

        // Add course entity.
        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');
        $courseentityname = $courseentity->get_entity_name();
        $coursejoin = "LEFT JOIN {course} {$course} ON {$course}.id = {$coursereset}.courseid";
        $this->add_entity(($courseentity)
            ->add_join($coursejoin));

        // Add course category entity.
        $coursecatentity = new course_category();
        $coursecatentityname = $coursecatentity->get_entity_name();
        $coursecategories = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_join($coursejoin)
            ->add_join("LEFT JOIN {course_categories} {$coursecategories} ON {$coursecategories}.id = {$course}.category"));

        // Add program entity.
        $programentity = new program();
        $programentityname = $programentity->get_entity_name();
        $program = $programentity->get_table_alias('tool_program');
        $this->add_entity(($programentity)
            ->add_join("LEFT JOIN {tool_program} {$program} ON {$program}.id = {$coursereset}.programid"));

        // Add certification entity.
        $certificationentity = new certification();
        $certificationentityname = $certificationentity->get_entity_name();
        $certification = $certificationentity->get_table_alias('tool_certification');
        $this->add_entity(($certificationentity)
            ->add_join("LEFT JOIN {tool_certification} {$certification} ON {$certification}.id = {$coursereset}.certificationid"));

        // Add tenant entity.
        $tenantentity = new tenant();
        $tenantentityname = $tenantentity->get_entity_name();
        $tenantentity
            ->add_joins($tenantentity->get_user_tenant_joins("{$coursereset}.userid"))
            ->set_entity_title(new lang_string('usertenant', 'tool_tenant'));
        $this->add_entity($tenantentity);

        // Add course reset entity columns/filters/conditions.
        $this->add_columns_from_entity($courseresetentityname);
        $this->add_filters_from_entity($courseresetentityname);
        $this->add_conditions_from_entity($courseresetentityname);

        // Add user entity columns/filters/conditions.
        $this->add_columns_from_entity($userentityname);
        $this->add_filters_from_entity($userentityname);
        $this->add_conditions_from_entity($userentityname);

        // Add "User who requested" columns.
        $this->add_columns_from_entity('tool_wp_userrequested',
            ['fullname', 'fullnamewithlink', 'fullnamewithpicture', 'fullnamewithpicturelink', 'firstname', 'lastname', 'email']);

        // Add course entity columns/filters/conditions.
        $this->add_columns_from_entity($courseentityname);
        $this->add_filters_from_entity($courseentityname);
        $this->add_conditions_from_entity($courseentityname);

        // Add course category entity columns/filters/conditions.
        $this->add_columns_from_entity($coursecatentityname);
        $this->add_filters_from_entity($coursecatentityname);
        $this->add_conditions_from_entity($coursecatentityname);

        // Add program entity columns/filters/conditions.
        $this->add_columns_from_entity($programentityname);
        $this->add_filters_from_entity($programentityname);
        $this->add_conditions_from_entity($programentityname);

        // Add certification entity columns/filters/conditions.
        $this->add_columns_from_entity($certificationentityname);
        $this->add_filters_from_entity($certificationentityname);
        $this->add_conditions_from_entity($certificationentityname);

        // Add tenant entity columns/filters/conditions.
        $this->add_columns_from_entity($tenantentityname);
        $this->add_filters_from_entity($tenantentityname);
        $this->add_conditions_from_entity($tenantentityname);

        $this->set_downloadable(true);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('coursereset', 'tool_wp');
    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        $columns = ['course:fullname', 'user:fullname', 'course_reset:timereseted', 'tenant:name'];

        return $columns;
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [];
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }
}
