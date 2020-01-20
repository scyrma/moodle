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

/**
 * Class report_appointments
 *
 * @package   mod_appointment
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace mod_appointment\tool_reportbuilder\datasources;

use mod_appointment\local\helpers\appointment_activity_entity;
use mod_appointment\local\helpers\appointment_entity;
use mod_appointment\local\helpers\appointment_sessions_entity;
use mod_appointment\local\helpers\appointment_signups_entity;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\entities\course as course_entity;
use tool_reportbuilder\local\entities\course_completion as course_completion_entity;
use tool_reportbuilder\local\entities\course_enrolment as course_enrolment_entity;
use tool_reportbuilder\local\entities\user;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class report_appointments
 *
 * @package   mod_appointment
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_appointments extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('appointment', 'ap');
        $this->add_base_join('JOIN {course} c ON c.id = ap.course');
        $this->add_base_join('JOIN {tool_tenant} t ON t.categoryid = c.category');
        $this->add_base_condition_simple('t.id', tenancy::get_tenant_id());

        $this->set_downloadable(true);
        $this->set_columns();

        if ($column = $this->get_column('course:fullname')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('appointment:name')) {
            $column->set_is_default(true, 2);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('appointment_sessions:sessionstartdate')) {
            $column->set_is_default(true, 3);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('appointment_sessions:sessionstarttime')) {
            $column->set_is_default(true, 4);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('appointment_sessions:sessionfinishtime')) {
            $column->set_is_default(true, 5);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('appointment:bookedvscapacity')) {
            $column->set_is_default(true, 6);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('appointment:status')) {
            $column->set_is_default(true, 7);
            $column->set_is_sortable(true, true);
        }

        $filters = $this->get_filters();
        $filters['course:fullname']->set_is_default(true);
        $filters['appointment_activity:name']->set_is_default(true);
        $filters['appointment:capacity']->set_is_default(true);
        $filters['appointment:allowwaitlist']->set_is_default(true);
        $filters['appointment:sessionavailability']->set_is_default(true);
        // TODO Appointment status.
        // TODO Session date (range).
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('appointments', 'mod_appointment');
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $joins = [
            'appointment_sessions' => 'LEFT JOIN {appointment_sessions} aps ON aps.appointment = ap.id',
            'appointment_sessions_dates' => 'LEFT JOIN {appointment_sessions_dates} apsd ON apsd.sessionid = aps.id',
            'appointment_signups' => 'LEFT JOIN {appointment_signups} apsg ON apsg.sessionid = aps.id',
            'appointment_signups_status' => 'LEFT JOIN {appointment_signups_status} apsgst ON apsgst.signupid = apsg.id',
            'user' => 'LEFT JOIN {user} u ON u.id = apsg.userid',
        ];

        $this->add_entity((new appointment_activity_entity())
            ->set_table_alias('appointment', 'ap'));

        $this->add_entity((new appointment_entity())
            ->add_join($joins['appointment_sessions'])
            ->set_table_alias('appointment_sessions', 'aps'));

        $this->add_entity((new appointment_sessions_entity())
            ->add_join($joins['appointment_sessions'])
            ->add_join($joins['appointment_sessions_dates'])
            ->set_table_alias('appointment_sessions_dates', 'apsd'));

        // User entity to represent "User".
        $this->add_entity((new user())
            ->add_join($joins['appointment_sessions'])
            ->add_join($joins['appointment_signups'])
            ->add_join($joins['user'])
            ->set_table_alias('user', 'u')
            ->set_include_only_fields(['fullname*', 'firstname', 'lastname', 'email'])
            ->set_entity_name('appointment_signups')
            ->set_entity_title(new \lang_string('attendees', 'mod_appointment')));

        $this->add_entity((new appointment_signups_entity())
            ->add_join($joins['appointment_sessions'])
            ->add_join($joins['appointment_signups'])
            ->add_join($joins['appointment_signups_status'])
            ->add_join($joins['appointment_sessions_dates'])
            ->set_table_alias('appointment_signups', 'apsg')
            ->set_table_alias('appointment_signups_status', 'apsgst'));

        $this->add_entity((new course_entity())
            ->set_table_alias('course', 'c'));

        $courseenrolmententity = (new course_enrolment_entity())
            ->add_join($joins['appointment_sessions'])
            ->add_join($joins['appointment_signups'])
            ->add_join($joins['user'])
            ->add_join('JOIN {user_enrolments} ue ON ue.userid = u.id ')
            ->add_join('JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = c.id')
            ->set_table_alias('user_enrolments', 'ue')
            ->set_table_alias('enrol', 'e');
        $this->add_entity($courseenrolmententity);

        // Course completion data may not exist, so we left join the table.
        $completionentity = new course_completion_entity();
        $completionentity
            ->add_join($joins['appointment_sessions'])
            ->add_join($joins['appointment_signups'])
            ->add_join($joins['user'])
            ->add_join('LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id')
            ->set_table_alias('course_completion', 'cc')
            ->set_table_alias('course', 'c');
        $this->add_entity($completionentity);

        if (class_exists('\tool_organisation\local\entities\jobs')) {
            $this->add_entity((new \tool_organisation\local\entities\jobs())
                ->add_join($joins['appointment_sessions'])
                ->add_join($joins['appointment_signups'])
                ->add_join($joins['user'])
                ->add_join('LEFT JOIN {tool_organisation_job} toj ON u.id = toj.userid')
                ->set_table_alias('tool_organisation_job', 'toj'));
        }
    }
}