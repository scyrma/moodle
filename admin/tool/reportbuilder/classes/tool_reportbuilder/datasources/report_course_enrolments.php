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

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use tool_organisation\local\entities\jobs;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\entities\course as course_entity;
use tool_reportbuilder\local\entities\course_completion as course_completion_entity;
use tool_reportbuilder\local\entities\course_enrolment as course_enrolment_entity;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\local\entities\user_lastaccess as user_lastaccess_entity;
use tool_reportbuilder\permission;
use tool_tenant\tenancy;

/**
 * Datasource class for course enrolments
 *
 * @package     tool_reportbuilder
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_course_enrolments extends datasource {

    /**
     * Initialise datasource
     */
    protected function initialise(): void {
        $this->set_main_table('user', 'u');

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        $canviewinactiveusers = \tool_tenant\permission::can_view_inactive_users($this->get_tenant_id());
        if (!$canviewinactiveusers) {
            $this->add_confirmed_user_condition();
        }

        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, 'u.id', 0, true));

        $this->set_columns();

        // Set default columns/sorting.
        $this->get_column('course:coursefullnamewithlink')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true);
        $this->get_column('user:fullnamewithlink')
            ->set_is_default(true, 2)
            ->set_is_sortable(true, true);
        $this->get_column('course_enrolment:method')
            ->set_is_default(true, 3)
            ->set_is_sortable(true, true);
        $this->get_column('course_enrolment:timestarted')
            ->set_is_default(true, 4);
        $this->get_column('course_enrolment:timeended')
            ->set_is_default(true, 5);

        $canshowtenantcolumn = \tool_reportbuilder\permission::can_show_tenant_column($this->get_tenant_id());
        if ($column = $this->get_column('user:tenant')) {
            $column->set_is_default(true, 6)
                ->set_is_sortable(true, true)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($filter = $this->get_filter('user:tenant')) {
            $filter->set_is_default(true)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($condition = $this->get_condition('user:tenant')) {
            $condition->set_is_available($canshowtenantcolumn);
        }

        // Set suspended/confirmed availability in condition/filter based on 'can_view_inactive_users' permission.
        $this->get_conditions()['user:suspended']->set_is_available($canviewinactiveusers);
        $this->get_conditions()['user:confirmed']->set_is_available($canviewinactiveusers);
        $this->get_filters()['user:suspended']->set_is_available($canviewinactiveusers);
        $this->get_filters()['user:confirmed']->set_is_available($canviewinactiveusers);
    }

    /**
     * Get the visible name of the datasource
     *
     * @return string
     */
    public static function get_name() {
        return get_string('reportcourseenrolments', 'tool_reportbuilder');
    }

    /**
     * Helper method for returning course enrolment table joins used by the entities of this report
     *
     * @param array $joinlimit Limit returned joins to these tables if specified, otherwise all are returned
     * @return array
     */
    private function get_entity_joins(array $joinlimit = []): array {
        $joins = [
            'user_enrolments' => 'JOIN {user_enrolments} ue ON ue.userid = u.id',
            'enrol' => 'JOIN {enrol} e ON e.id = ue.enrolid',
            'course' => 'JOIN {course} c ON c.id = e.courseid',
        ];

        if (!empty($joinlimit)) {
            $joins = array_intersect_key($joins, array_flip($joinlimit));
        }

        return array_values($joins);
    }


    /**
     * Set the columns available for the datasource
     */
    protected function set_columns(): void {
        $this->add_entity((new course_entity())
            ->add_joins($this->get_entity_joins())
            ->set_table_alias('course', 'c'));

        $allowtenant = permission::can_show_tenant_column(tenancy::get_tenant_id());
        $this->add_entity((new user_entity())
            ->set_allow_tenant_columns($allowtenant));

        $courseenrolmententity = (new course_enrolment_entity())
            ->add_joins($this->get_entity_joins(['user_enrolments', 'enrol']))
            ->set_table_alias('user_enrolments', 'ue')
            ->set_table_alias('enrol', 'e');
        $this->add_entity($courseenrolmententity);

        // Since each user may have several enrolments in one course we may end up with duplicated rows. To prevent it we will
        // create a subquery for the "last enrolment", which is used by the entity to calculate the number of days enrolled.
        $lastenroledatejoin = 'JOIN (
            SELECT ex.courseid, uex.userid,
                   MAX(CASE WHEN uex.timestart = 0 THEN uex.timecreated ELSE uex.timestart END) AS lastenroldate
              FROM {enrol} ex
              JOIN {user_enrolments} uex ON uex.enrolid = ex.id
          GROUP BY ex.courseid, uex.userid
        ) uelast ON uelast.courseid = c.id AND uelast.userid = u.id';

        // Course completion data may not exist, so we left join the table.
        $this->add_entity((new course_completion_entity())
            ->add_joins($this->get_entity_joins())
            ->add_join('LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id')
            ->set_last_enroldate_field_sql('uelast.lastenroldate', $lastenroledatejoin)
            ->set_table_alias('course_completion', 'cc')
            ->set_table_alias('course', 'c'));

        $this->add_entity((new user_lastaccess_entity())
            ->add_joins($this->get_entity_joins())
            ->add_join('LEFT JOIN {user_lastaccess} ula ON ula.userid = u.id AND ula.courseid = c.id')
            ->set_table_alias('user_lastaccess', 'ula'));

        $entity = new jobs();
        $alias = $entity->get_table_alias('tool_organisation_job');
        $this->add_entity($entity->add_join(
            "LEFT JOIN {tool_organisation_job} {$alias} ON {$alias}.userid = u.id AND " .
            jobs::get_job_tenant_join($alias))
        );
    }
}
