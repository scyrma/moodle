<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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

namespace mod_appointment\reportbuilder\datasource;

use core_course\local\entities\course_category;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\helpers\database;
use mod_appointment\reportbuilder\local\entities\appointment;
use mod_appointment\reportbuilder\local\entities\attendee;
use mod_appointment\reportbuilder\local\entities\session;
use mod_appointment\reportbuilder\local\entities\session_date;
use tool_organisation\reportbuilder\local\entities\job;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * Appointments datasource
 *
 * @package   mod_appointment
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appointments extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        // Add course entity as main entity.
        $courseentity = new course();
        $courseentityname = $courseentity->get_entity_name();
        $course = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity);

        $this->set_main_table('course', $course);

        // Add course category entity.
        $coursecatentity = new course_category();
        $coursecatentityname = $coursecatentity->get_entity_name();
        $coursecategories = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_join("JOIN {course_categories} {$coursecategories} ON {$coursecategories}.id = {$course}.category"));

        if (!sharedspace::is_shared_space()) {
            // Only filter courses in the category of the current tenant.
            $categoryid = tenancy::get_tenants()[tenancy::get_tenant_id()]->categoryid;
            $param1 = database::generate_param_name();
            $param2 = database::generate_param_name();
            $category = database::generate_alias();
            $this->add_join("JOIN {course_categories} {$category} ON {$course}.category = {$category}.id");
            $this->add_base_condition_sql("({$category}.id = :{$param1} OR {$category}.path LIKE :{$param2})",
                [$param1 => $categoryid, $param2 => '/' . $categoryid . '/%']);
        }

        // Add appointment entity.
        $appointmententity = new appointment();
        $appointmententityname = $appointmententity->get_entity_name();
        $appointment = $appointmententity->get_table_alias('appointment');
        $appointmentjoin = "JOIN {appointment} {$appointment} ON {$appointment}.course = {$course}.id";
        $appointmententity->add_join($appointmentjoin);
        $this->add_entity($appointmententity);

        // Add appointment session entity.
        $sessionentity = new session();
        $sessionentityname = $sessionentity->get_entity_name();
        $session = $sessionentity->get_table_alias('appointment_sessions');
        $sessionjoin = "JOIN {appointment_sessions} {$session} ON {$session}.appointment = {$appointment}.id";
        $sessionentity->add_joins([$appointmentjoin, $sessionjoin]);
        $this->add_entity($sessionentity);

        // Add appointment session dates entity.
        $sessiondateentity = new session_date();
        $sessiondateentityname = $sessiondateentity->get_entity_name();
        $sessiondate = $sessiondateentity->get_table_alias('appointment_sessions_dates');
        $sessiondatejoin = "JOIN {appointment_sessions_dates} {$sessiondate} ON {$sessiondate}.sessionid = {$session}.id";
        $sessiondateentity->add_joins([$appointmentjoin, $sessionjoin, $sessiondatejoin]);
        $this->add_entity($sessiondateentity);

        // Add appointment attendee entity.
        $attendeeentity = new attendee();
        $attendeeentityname = $attendeeentity->get_entity_name();
        $attendee = $attendeeentity->get_table_alias('appointment_signups');
        $attendeestatus = $attendeeentity->get_table_alias('appointment_signups_status');
        $attendeeentityjoin = "LEFT JOIN {appointment_signups} {$attendee} ON {$attendee}.sessionid = {$session}.id";
        $attendeestatusentityjoin = "
            LEFT JOIN {appointment_signups_status} {$attendeestatus}
            ON {$attendeestatus}.signupid = {$attendee}.id
        ";
        $attendeeentity->add_joins([$appointmentjoin, $sessionjoin, $attendeeentityjoin, $attendeestatusentityjoin]);
        $this->add_entity($attendeeentity);

        // Add user entity.
        $userentity = new user();
        $userentityname = $userentity->get_entity_name();
        $user = $userentity->get_table_alias('user');
        $userjoin = "JOIN {user} {$user} ON {$user}.id = {$attendee}.userid";
        $userentity->add_joins([$appointmentjoin, $sessionjoin, $attendeeentityjoin, $attendeestatusentityjoin, $userjoin]);
        $this->add_entity($userentity);

        // TODO LEFT JOIN course enrolments.

        // Add Job entity.
        $jobentity = new job();
        $job = $jobentity->get_table_alias('tool_organisation_job');
        $jobentityname = $jobentity->get_entity_name();
        $jobjoin = "LEFT JOIN {tool_organisation_job} {$job} ON {$job}.userid = {$user}.id AND " .
            job::get_job_tenant_join("{$job}.tenantid");
        $jobentity->add_joins([$appointmentjoin, $sessionjoin, $attendeeentityjoin, $attendeestatusentityjoin,
            $userjoin, $jobjoin]);
        $this->add_entity($jobentity);

        // Add course entity columns/filters/conditions.
        $this->add_columns_from_entity($courseentityname);
        $this->add_filters_from_entity($courseentityname);
        $this->add_conditions_from_entity($courseentityname);

        // Add course category entity columns/filters/conditions.
        $this->add_columns_from_entity($coursecatentityname);
        $this->add_filters_from_entity($coursecatentityname);
        $this->add_conditions_from_entity($coursecatentityname);

        // Add appointment entity columns/filters/conditions.
        $this->add_columns_from_entity($appointmententityname);
        $this->add_filters_from_entity($appointmententityname);
        $this->add_conditions_from_entity($appointmententityname);

        // Add appointment session entity columns/filters/conditions.
        $this->add_columns_from_entity($sessionentityname);
        $this->add_filters_from_entity($sessionentityname);
        $this->add_conditions_from_entity($sessionentityname);

        // Add appointment session dates entity columns/filters/conditions.
        $this->add_columns_from_entity($sessiondateentityname);
        $this->add_filters_from_entity($sessiondateentityname);
        $this->add_conditions_from_entity($sessiondateentityname);

        // Add appointment attendee entity columns/filters/conditions.
        $this->add_columns_from_entity($attendeeentityname);
        $this->add_filters_from_entity($attendeeentityname);
        $this->add_conditions_from_entity($attendeeentityname);

        // Add user entity columns/filters/conditions.
        $this->add_columns_from_entity($userentityname);
        $this->add_filters_from_entity($userentityname);
        $this->add_conditions_from_entity($userentityname);

        // Add job entity columns/filters/conditions.
        $this->add_columns_from_entity($jobentityname);
        $this->add_filters_from_entity($jobentityname);
        $this->add_conditions_from_entity($jobentityname);
    }

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('appointments', 'mod_appointment');
    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'course:fullname',
            'appointment:name',
            'session_date:datestart',
            'session_date:starttime',
            'session_date:finishtime',
            'session:bookedvscapacity',
            'session:status',
        ];
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'course:fullname',
            'appointment:name',
            'session:capacity',
            'session:allowwaitlist',
            'session:sessionavailability',
        ];
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
