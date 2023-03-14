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

use core_course\reportbuilder\local\entities\course_category;
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
        $course = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity);

        $this->set_main_table('course', $course);

        // Add course category entity.
        $coursecatentity = new course_category();
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
        $appointment = $appointmententity->get_table_alias('appointment');
        $appointmentjoin = "JOIN {appointment} {$appointment} ON {$appointment}.course = {$course}.id";
        $appointmententity->add_join($appointmentjoin);
        $this->add_entity($appointmententity);

        // Add appointment session entity.
        $sessionentity = new session();
        $session = $sessionentity->get_table_alias('appointment_sessions');
        $sessionjoin = "JOIN {appointment_sessions} {$session} ON {$session}.appointment = {$appointment}.id";
        $sessionentity->add_joins([$appointmentjoin, $sessionjoin]);
        $this->add_entity($sessionentity);

        // Add appointment session dates entity.
        $sessiondateentity = new session_date();
        $sessiondate = $sessiondateentity->get_table_alias('appointment_sessions_dates');
        $sessiondatejoin = "JOIN {appointment_sessions_dates} {$sessiondate} ON {$sessiondate}.sessionid = {$session}.id";
        $sessiondateentity->add_joins([$appointmentjoin, $sessionjoin, $sessiondatejoin]);
        $this->add_entity($sessiondateentity);

        // Add appointment attendee entity.
        $attendeeentity = new attendee();
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
        $user = $userentity->get_table_alias('user');
        $userjoin = "JOIN {user} {$user} ON {$user}.id = {$attendee}.userid";
        $userentity->add_joins([$appointmentjoin, $sessionjoin, $attendeeentityjoin, $attendeestatusentityjoin, $userjoin]);
        $this->add_entity($userentity);

        // Add enrol entity.
        $enrolentity = new \core_course\reportbuilder\local\entities\enrolment();
        $enrol = $enrolentity->get_table_alias('enrol');
        $userenrolment = $enrolentity->get_table_alias('user_enrolments');
        $enroljoins = [
            "JOIN {user_enrolments} {$userenrolment} ON {$userenrolment}.userid = {$user}.id",
            "JOIN {enrol} {$enrol} ON {$enrol}.id = {$userenrolment}.enrolid AND {$enrol}.courseid = {$course}.id"
        ];
        $enrolentity
            ->add_joins([$appointmentjoin, $sessionjoin, $attendeeentityjoin, $attendeestatusentityjoin, $userjoin])
            ->add_joins($enroljoins);
        $this->add_entity($enrolentity);

        // Add course completion entity.
        $completionentity = new \core_course\reportbuilder\local\entities\completion();
        $completion = $completionentity->get_table_alias('course_completion');
        $completionjoins = [
            "LEFT JOIN {course_completions} {$completion} ON $completion.course = {$course}.id AND $completion.userid = {$user}.id"
        ];
        $completionentity
            ->add_joins([$appointmentjoin, $sessionjoin, $attendeeentityjoin, $attendeestatusentityjoin, $userjoin])
            ->add_joins($completionjoins);
        $this->add_entity($completionentity);

        // Add Job entity.
        $jobentity = new job();
        $job = $jobentity->get_table_alias('tool_organisation_job');
        $jobjoin = "LEFT JOIN {tool_organisation_job} {$job} ON {$job}.userid = {$user}.id AND " .
            job::get_job_tenant_join("{$job}.tenantid");
        $jobentity->add_joins([$appointmentjoin, $sessionjoin, $attendeeentityjoin, $attendeestatusentityjoin,
            $userjoin, $jobjoin]);
        $this->add_entity($jobentity);

        // Add all entities columns/filters/conditions.
        $this->add_all_from_entities();
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
