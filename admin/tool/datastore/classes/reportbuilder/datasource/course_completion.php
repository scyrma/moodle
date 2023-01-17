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

namespace tool_datastore\reportbuilder\datasource;

use core_course\reportbuilder\local\entities\course_category;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use tool_datastore\reportbuilder\local\entities\course as datastore_course;
use tool_datastore\reportbuilder\local\entities\completion as datastore_completion;
use tool_datastore\reportbuilder\local\entities\user as datastore_user;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * Report source for course completion from the datastore
 *
 * @package     tool_datastore
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_completion extends datasource {

    /**
     * Return user friendly name of the report source
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportcoursecompletion', 'tool_datastore');
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $datastorecompletionentity = new datastore_completion();
        $datastoreaction = $datastorecompletionentity->get_table_alias('tool_datastore_action');

        $this->set_main_table('tool_datastore_action', $datastoreaction);
        $this->add_base_condition_simple("{$datastoreaction}.action", 'course_completed');

        $this->add_entity($datastorecompletionentity);

        // Add a base join on the user table, for tenant and confirmed/suspended condition.
        $userentity = new user();
        $user = $userentity->get_table_alias('user');

        $this->add_join("LEFT JOIN {user} {$user} ON {$user}.id = {$datastoreaction}.relateduserid AND {$user}.deleted = 0");

        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$user}.id", 0, true));
        if (!\tool_tenant\permission::can_view_inactive_users()) {
            $this->add_base_condition_sql("{$user}.suspended = 0 AND {$user}.confirmed = 1");
        }

        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');
        $coursejoin = "LEFT JOIN {course} {$course} ON {$course}.id = {$datastoreaction}.originalcourseid";

        // Add our datastore course/user entities.
        $datastorecourseentity = (new datastore_course())
            ->set_entity_name('datastorecourse')
            ->set_table_alias('tool_datastore_action', $datastoreaction)
            ->set_table_alias('course', $course)
            ->add_course_join($coursejoin);
        $this->add_entity($datastorecourseentity);

        $datastoreuserentity = (new datastore_user())
            ->set_entity_name('datastoreuser')
            ->set_table_alias('tool_datastore_action', $datastoreaction)
            ->set_table_alias('user', $user);
        $this->add_entity($datastoreuserentity);

        // Add original course/user entities.
        $this->add_entity($courseentity->add_join($coursejoin));
        $this->add_entity($userentity);

        // Add course category entity.
        $coursecatentity = new course_category();
        $coursecategories = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_join($coursejoin)
            ->add_join("LEFT JOIN {course_categories} {$coursecategories} ON {$coursecategories}.id = {$course}.category"));

        // Add tenant entity for the user.
        $tenantentity = new tenant();
        $tenantentity->add_joins($tenantentity->get_user_tenant_joins("{$user}.id"));
        $this->add_entity($tenantentity);

        // Add all columns from entities to be available in custom reports.
        $this->add_columns_from_entity($datastorecourseentity->get_entity_name());
        $this->add_columns_from_entity($datastoreuserentity->get_entity_name());
        $this->add_columns_from_entity($datastorecompletionentity->get_entity_name());
        $this->add_columns_from_entity($courseentity->get_entity_name());
        $this->add_columns_from_entity($coursecatentity->get_entity_name());
        $this->add_columns_from_entity($userentity->get_entity_name());
        $this->add_columns_from_entity($tenantentity->get_entity_name());

        // Add all filters from entities to be available in custom reports.
        $this->add_filters_from_entity($datastorecourseentity->get_entity_name());
        $this->add_filters_from_entity($datastoreuserentity->get_entity_name());
        $this->add_filters_from_entity($datastorecompletionentity->get_entity_name());
        $this->add_filters_from_entity($courseentity->get_entity_name());
        $this->add_filters_from_entity($coursecatentity->get_entity_name());
        $this->add_filters_from_entity($userentity->get_entity_name());
        $this->add_filters_from_entity($tenantentity->get_entity_name());

        // Add all conditions from entities to be available in custom reports.
        $this->add_conditions_from_entity($datastorecourseentity->get_entity_name());
        $this->add_conditions_from_entity($datastoreuserentity->get_entity_name());
        $this->add_conditions_from_entity($datastorecompletionentity->get_entity_name());
        $this->add_conditions_from_entity($courseentity->get_entity_name());
        $this->add_conditions_from_entity($coursecatentity->get_entity_name());
        $this->add_conditions_from_entity($userentity->get_entity_name());
        $this->add_conditions_from_entity($tenantentity->get_entity_name());
    }

    /**
     * Return default columns that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'datastorecourse:fullname',
            'datastoreuser:firstname',
            'datastoreuser:lastname',
            'completion:timecompleted',
        ];
    }

    /**
     * Return default filters that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'datastorecourse:fullname',
        ];
    }

    /**
     * Return default conditions that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }
}
