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

/**
 * Class report_appointments
 *
 * @package   mod_appointment
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\tool_reportbuilder\datasources;

use mod_appointment\local\helpers\appointment_activity_entity;
use mod_appointment\local\helpers\appointment_entity;
use mod_appointment\local\helpers\appointment_sessions_entity;
use mod_appointment\local\helpers\appointment_signups_entity;
use tool_reportbuilder\convert_not_implemented;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\entities\course as course_entity;
use tool_reportbuilder\local\entities\course_completion as course_completion_entity;
use tool_reportbuilder\local\entities\course_enrolment as course_enrolment_entity;
use tool_reportbuilder\local\entities\user;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;

/**
 * Class report_appointments
 *
 * @package   mod_appointment
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_appointments extends datasource {

    /**
     * When converting custom report to core reportbuilder which class corresponds to this datasource
     *
     * @return string name of the class extending {@see \core_reportbuilder\datasource}
     */
    public function convert_get_datasource_class(): string {
        return \mod_appointment\reportbuilder\datasource\appointments::class;
    }

    /**
     * Get the entity name that corresponds to the given entity in the converted datasource
     *
     * @param string $oldentityname entity name in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string entity name in the converted datasource
     */
    public function convert_get_entity_name(string $oldentityname, \core_reportbuilder\datasource $newsource): string {
        if ($oldentityname === 'appointment_activity') {
            return 'appointment';
        } else if ($oldentityname === 'appointment') {
            return 'session';
        } else if ($oldentityname === 'appointment_sessions') {
            return 'session_date';
        } else if ($oldentityname === 'appointment_signups') {
            return 'attendee';
        } else if ($oldentityname === 'course_completion') {
            return 'completion';
        }
        return parent::convert_get_entity_name($oldentityname, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which column corresponds to the given column
     *
     * @param report_column $oldcolumn the column in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding column in the converted datasource
     */
    public function convert_get_column_unique_identifier(report_column $oldcolumn,
                                                         \core_reportbuilder\datasource $newsource): string {
        if ($oldcolumn->get_unique_identifier() === 'appointment_signups:fullname') {
            return 'user:fullname';
        } else if ($oldcolumn->get_unique_identifier() === 'appointment_signups:fullnamewithlink') {
            return 'user:fullnamewithlink';
        } else if ($oldcolumn->get_unique_identifier() === 'appointment_signups:fullnamewithpicture') {
            return 'user:fullnamewithpicture';
        } else if ($oldcolumn->get_unique_identifier() === 'appointment_signups:fullnamewithpicturelink') {
            return 'user:fullnamewithpicturelink';
        } else if ($oldcolumn->get_unique_identifier() === 'appointment_signups:firstname') {
            return 'user:firstname';
        } else if ($oldcolumn->get_unique_identifier() === 'appointment_signups:lastname') {
            return 'user:lastname';
        } else if ($oldcolumn->get_unique_identifier() === 'appointment_signups:email') {
            return 'user:email';
        } else if ($oldcolumn->get_unique_identifier() === 'course:category') {
            return 'course_category:name';
        }
        return parent::convert_get_column_unique_identifier($oldcolumn, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which filter corresponds to the given filter
     *
     * @param report_filter $oldfilter the filter in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding filter in the converted datasource
     * @throws convert_not_implemented
     */
    public function convert_get_filter_unique_identifier(report_filter $oldfilter,
                                                         \core_reportbuilder\datasource $newsource): string {
        if ($oldfilter->get_unique_identifier() === 'appointment_signups:fullname') {
            return 'user:fullname';
        } else if ($oldfilter->get_unique_identifier() === 'appointment_signups:fullnamewithlink') {
            return 'user:fullnamewithlink';
        } else if ($oldfilter->get_unique_identifier() === 'appointment_signups:fullnamewithpicture') {
            return 'user:fullnamewithpicture';
        } else if ($oldfilter->get_unique_identifier() === 'appointment_signups:fullnamewithpicturelink') {
            return 'user:fullnamewithpicturelink';
        } else if ($oldfilter->get_unique_identifier() === 'appointment_signups:firstname') {
            return 'user:firstname';
        } else if ($oldfilter->get_unique_identifier() === 'appointment_signups:lastname') {
            return 'user:lastname';
        } else if ($oldfilter->get_unique_identifier() === 'appointment_signups:email') {
            return 'user:email';
        } else if ($oldfilter->get_unique_identifier() === 'course:category') {
            return 'course_category:name';
        }
        return parent::convert_get_filter_unique_identifier($oldfilter, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which condition corresponds to the given condition
     *
     * @param report_filter $oldcondition the condition in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding condition in the converted datasource
     * @throws convert_not_implemented
     */
    public function convert_get_condition_unique_identifier(report_filter $oldcondition,
                                                            \core_reportbuilder\datasource $newsource): string {
        if ($oldcondition->get_unique_identifier() === 'appointment_signups:fullname') {
            return 'user:fullname';
        } else if ($oldcondition->get_unique_identifier() === 'appointment_signups:fullnamewithlink') {
            return 'user:fullnamewithlink';
        } else if ($oldcondition->get_unique_identifier() === 'appointment_signups:fullnamewithpicture') {
            return 'user:fullnamewithpicture';
        } else if ($oldcondition->get_unique_identifier() === 'appointment_signups:fullnamewithpicturelink') {
            return 'user:fullnamewithpicturelink';
        } else if ($oldcondition->get_unique_identifier() === 'appointment_signups:firstname') {
            return 'user:firstname';
        } else if ($oldcondition->get_unique_identifier() === 'appointment_signups:lastname') {
            return 'user:lastname';
        } else if ($oldcondition->get_unique_identifier() === 'appointment_signups:email') {
            return 'user:email';
        } else if ($oldcondition->get_unique_identifier() === 'course:category') {
            return 'course_category:name';
        }
        return parent::convert_get_condition_unique_identifier($oldcondition, $newsource);
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('appointment', 'ap');
        $this->add_base_join('JOIN {course} c ON c.id = ap.course');
        $this->add_base_join('JOIN {course_categories} cat ON c.category = cat.id');

        if (!sharedspace::is_shared_space()) {
            // Only filter courses in the category of the current tenant.
            $categoryid = tenancy::get_tenants()[tenancy::get_tenant_id()]->categoryid;
            $p1 = \tool_wp\db::generate_param_name();
            $p2 = \tool_wp\db::generate_param_name();
            $this->add_base_condition_sql("(cat.id = :{$p1} OR cat.path LIKE :{$p2})",
                [$p1 => $categoryid, $p2 => '/' . $categoryid . '/%']);
        }

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
            'appointment_sessions_dates' => 'JOIN {appointment_sessions_dates} apsd ON apsd.sessionid = aps.id',
            'appointment_signups' => 'LEFT JOIN {appointment_signups} apsg ON apsg.sessionid = aps.id',
            'appointment_signups_status' =>
                'LEFT JOIN {appointment_signups_status} apsgst ON apsgst.signupid = apsg.id AND apsgst.superceded = 0',
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
        $allowtenant = permission::can_show_tenant_column(tenancy::get_tenant_id());
        $this->add_entity((new user())
            ->set_allow_tenant_columns($allowtenant)
            ->add_join($joins['appointment_sessions'])
            ->add_join($joins['appointment_signups'])
            ->add_join($joins['user'])
            ->set_table_alias('user', 'u')
            ->set_include_only_fields(['fullname*', 'firstname', 'lastname', 'email', 'tenant'])
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
            $entity = new \tool_organisation\local\entities\jobs();
            $alias = $entity->get_table_alias('tool_organisation_job');

            $this->add_entity($entity
                ->add_join($joins['appointment_sessions'])
                ->add_join($joins['appointment_signups'])
                ->add_join($joins['user'])
                ->add_join("LEFT JOIN {tool_organisation_job} {$alias} ON {$alias}.userid = u.id AND " .
                    \tool_organisation\local\entities\jobs::get_job_tenant_join($alias))
            );
        }
    }
}
