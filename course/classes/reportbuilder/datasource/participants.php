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

namespace core_course\reportbuilder\datasource;

use core_course\local\entities\course_category;
use core_course\reportbuilder\local\entities\access;
use core_course\reportbuilder\local\entities\completion;
use core_course\reportbuilder\local\entities\enrolment;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;

/**
 * Course participants datasource
 *
 * @package     core_course
 * @copyright   2022 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participants extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity);

        $this->set_main_table('course', $course);

        // Exclude site course.
        $paramsiteid = database::generate_param_name();
        $this->add_base_condition_sql("{$course}.id != :{$paramsiteid}", [$paramsiteid => SITEID]);

        // Join the course category entity.
        $coursecatentity = new course_category();
        $categories = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_join("JOIN {course_categories} {$categories} ON {$categories}.id = {$course}.category"));

        // Join the enrolments entity.
        $enrolmententity = new enrolment();
        $userenrolment = $enrolmententity->get_table_alias('user_enrolments');
        $enrol = $enrolmententity->get_table_alias('enrol');
        $enroljoin = "LEFT JOIN {enrol} {$enrol} ON {$enrol}.courseid = {$course}.id";
        /** @uses \tool_tenant\tenancy::get_users_subquery */
        $tenantsql = component_class_callback('\tool_tenant\tenancy',
            'get_users_subquery', [false, true, "{$userenrolment}.userid"], '');
        $userenrolmentjoin =
            " LEFT JOIN {user_enrolments} {$userenrolment} ON {$tenantsql} {$userenrolment}.enrolid = {$enrol}.id";
        $enrolmententity->add_joins([$enroljoin, $userenrolmentjoin]);
        $this->add_entity($enrolmententity);

        // Join user entity.
        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $userentity->add_joins($enrolmententity->get_joins());
        $userentity->add_join("LEFT JOIN {user} {$user} ON {$userenrolment}.userid = {$user}.id AND {$user}.deleted = 0");
        $this->add_entity($userentity);

        // Join completion entity.
        $completionentity = new completion();
        $completion = $completionentity->get_table_alias('course_completion');
        $completionentity->add_joins($userentity->get_joins());
        $completionentity->add_join("
            LEFT JOIN {course_completions} {$completion}
                   ON {$completion}.course = {$course}.id AND {$completion}.userid = {$user}.id
        ");
        $completionentity->set_table_alias('user', $user);
        $this->add_entity($completionentity);

        // Join course access entity.
        $accessentity = new access();
        $lastaccess = $accessentity->get_table_alias('user_lastaccess');
        $accessentity->add_joins($userentity->get_joins());
        $accessentity->add_join("
            LEFT JOIN {user_lastaccess} {$lastaccess}
                   ON {$lastaccess}.userid = {$user}.id AND {$lastaccess}.courseid = {$course}.id
        ");
        $this->add_entity($accessentity);

        // Add Job entity.
        /** @uses \tool_organisation\reportbuilder\local\entities\job::prepare_for_participants_datasource */
        if ($jobentity = component_class_callback('\tool_organisation\reportbuilder\local\entities\job',
            'prepare_for_participants_datasource', [$user, $userentity->get_joins()])) {
            $this->add_entity($jobentity);
        }

        // Add Tenant entity.
        /** @uses \tool_tenant\reportbuilder\local\entities\tenant::prepare_for_participants_datasource */
        if ($tenantentity = component_class_callback('\tool_tenant\reportbuilder\local\entities\tenant',
            'prepare_for_participants_datasource', [$user, $userentity->get_joins()])) {
            $this->add_entity($tenantentity);
        }

        // Add all entities columns/filters/conditions.
        $this->add_all_from_entities();
    }

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportcourseparticipants', 'tool_reportbuilder');
    }

    /**
     * Return the columns that will be added to the report as part of default setup
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        /** @uses \tool_tenant\reportbuilder\local\entities\tenant::add_tenant_information */
        $tenantname = component_class_callback('\tool_tenant\reportbuilder\local\entities\tenant',
            'add_tenant_information', []);
        return array_merge([
            'course:coursefullnamewithlink',
            'enrolment:method',
            'user:fullnamewithlink',
        ], $tenantname);
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        /** @uses \tool_tenant\reportbuilder\local\entities\tenant::add_tenant_information */
        $tenantname = component_class_callback('\tool_tenant\reportbuilder\local\entities\tenant',
            'add_tenant_information', []);
        return array_merge([
            'user:suspended',
            'user:confirmed',
        ], $tenantname);
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        /** @uses \tool_tenant\reportbuilder\local\entities\tenant::add_tenant_information */
        $tenantname = component_class_callback('\tool_tenant\reportbuilder\local\entities\tenant',
            'add_tenant_information', []);
        return array_merge([
            'user:suspended',
            'user:confirmed',
        ], $tenantname);
    }
}
