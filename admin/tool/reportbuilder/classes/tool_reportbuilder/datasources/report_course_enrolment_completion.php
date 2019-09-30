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
 * Class containing datasource for course enrolment and completion
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use tool_reportbuilder\datasource;
use tool_reportbuilder\local\entities\course as course_entity;
use tool_reportbuilder\local\entities\course_completion as course_completion_entity;
use tool_reportbuilder\local\entities\course_enrolment as course_enrolment_entity;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Datasource class
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_course_enrolment_completion extends datasource {

    /**
     * Initialise datasource
     *
     * @return void
     */
    protected function initialise() : void {
        $this->set_main_table('user', 'u');
        // Since each user may have several enrolments in one course we may end up with duplicated rows for completion.
        // To prevent it we will create a subquery for the "last enrolment".
        $this->add_base_join('JOIN ('.
                'SELECT uex.userid, ex.courseid, '.
                    'MAX(CASE WHEN uex.timestart = 0 THEN uex.timecreated ELSE uex.timestart END) AS lastenroldate '.
                'FROM {user_enrolments} uex, {enrol} ex '.
                'WHERE uex.enrolid = ex.id '.
                'GROUP BY uex.userid, ex.courseid '.
            ') uelast ON uelast.userid = u.id');
        $this->add_base_join('JOIN {course} c ON uelast.courseid = c.id');

        list($join, $where, $params) = tenancy::get_users_sql('u');
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);

        $this->add_organisation_condition('u');

        $this->set_columns();

        // Set default columns/sorting.
        $this->get_column('course:fullname')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true);
        $this->get_column('user:fullnamewithlink')
            ->set_is_default(true, 2)
            ->set_is_sortable(true, true);
        $this->get_column('course_enrolment:timecreated')->set_is_default(true, 3);
        $this->get_column('course_completion:completed')->set_is_default(true, 4);
    }

    /**
     * Get the visible name of the datasource
     *
     * @return string
     */
    public static function get_name() {
        return get_string('reportcourseenrolmentcompletion', 'tool_reportbuilder');
    }

    /**
     * Set the columns available for the datasource
     *
     * @return void
     */
    protected function set_columns() : void {
        $this->add_entity(new course_entity('', 'c'));
        $this->add_entity(new user_entity('', 'u'));
        $this->add_entity(new course_enrolment_entity('JOIN {user_enrolments} ue ON ue.userid = u.id '.
            'JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = c.id', 'ue', [], 'e'));

        // Course completion data may not exist, so we left join the table.
        $coursecompletionjoin = 'LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id';
        $this->add_entity(new course_completion_entity($coursecompletionjoin, 'cc', [], 'c', 'uelast.lastenroldate'));
    }

    /**
     * This report is available to organisation managers with the permission to view reports
     *
     * Only users who are managed by the current user will be displayed
     *
     * @return bool
     */
    public static function supports_organisation_filter() : bool {
        return true;
    }
}