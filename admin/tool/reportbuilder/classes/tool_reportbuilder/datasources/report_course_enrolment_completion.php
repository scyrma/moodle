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
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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

        $this->set_columns();
        $this->add_last_course_access();

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
        $this->add_entity(new course_entity());

        $this->add_entity(new user_entity());
        $courseenrolmententity = (new course_enrolment_entity())
            ->add_join('JOIN {user_enrolments} ue ON ue.userid = u.id ')
            ->add_join('JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = c.id')
            ->set_table_alias('user_enrolments', 'ue')
            ->set_table_alias('enrol', 'e');
        $this->add_entity($courseenrolmententity);

        // Course completion data may not exist, so we left join the table.
        $completionentity = new course_completion_entity();
        $completionentity
            ->add_join('LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id')
            ->set_table_alias('course_completion', 'cc')
            ->set_table_alias('course', 'c');
        $completionentity
            ->set_last_enroldate_field_sql('uelast.lastenroldate');
        $this->add_entity($completionentity);
    }

    /**
     * Add column, condition and action for last course access
     *
     * TODO: create a new entity
     */
    protected function add_last_course_access() {
        $this->annotate_entity('user_lastaccess', new \lang_string('lastcourseaccess', 'tool_reportbuilder'));

        $join = 'LEFT JOIN {user_lastaccess} ula ON ula.userid = u.id AND ula.courseid = c.id';

        $this->add_column((new \tool_reportbuilder\report_column('timeaccess',
                new \lang_string('lastcourseaccess', 'tool_reportbuilder'),
                    'user_lastaccess'))
            ->add_join($join)
            ->add_field('ula.timeaccess')
            ->set_type(\tool_reportbuilder\constants::DB_TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_callback([\tool_reportbuilder\local\helpers\format::class, 'userdate'], get_string('strftimedatetimeshort'))
        );

        $this->add_filter((new \tool_reportbuilder\report_filter(
            \tool_reportbuilder\local\filter\date_filter::class,
            'timeaccess',
            new \lang_string('lastcourseaccess', 'tool_reportbuilder'),
            'user_lastaccess'
        ))
            ->add_join($join)
            ->set_field_sql("ula.timeaccess")
        );

        $this->add_condition((new \tool_reportbuilder\report_filter(
            \tool_reportbuilder\local\filter\date_condition::class,
            'timeaccess',
            new \lang_string('lastcourseaccess', 'tool_reportbuilder'),
            'user_lastaccess'
        ))
            ->add_join($join)
            ->set_field_sql("ula.timeaccess")
        );
    }
}