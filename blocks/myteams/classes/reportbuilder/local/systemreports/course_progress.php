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

namespace block_myteams\reportbuilder\local\systemreports;

use core_user;
use block_myteams\permission;
use block_myteams\reportbuilder\local\formatters\course_progress as formatter;
use core_course\reportbuilder\local\entities\completion;
use core_course\reportbuilder\local\entities\enrolment;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\system_report;
use lang_string;
use tool_organisation\organisation;
use tool_organisation\reportbuilder\local\entities\job;

/**
 * Course progress system report implementation
 *
 * @package   block_myteams
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_progress extends system_report {

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        $userid = $this->get_parameter('userid', 0, PARAM_INT);

        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity);

        $this->set_main_table('course', $course);

        // Exclude site course.
        $paramsiteid = database::generate_param_name();
        $this->add_base_condition_sql("{$course}.id != :{$paramsiteid}", [$paramsiteid => SITEID]);

        // Join the enrolments entity.
        $enrolmententity = new enrolment();
        $userenrolment = $enrolmententity->get_table_alias('user_enrolments');
        $enrol = $enrolmententity->get_table_alias('enrol');
        $this->add_entity($enrolmententity);

        // Join user entity.
        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $this->add_entity($userentity);

        // We are adding some JOINS to the report, rather than the entity.
        $this->add_join("JOIN {enrol} {$enrol} ON {$enrol}.courseid = {$course}.id");
        $this->add_join("JOIN {user_enrolments} {$userenrolment} ON {$userenrolment}.enrolid = {$enrol}.id");
        $this->add_join("JOIN {user} {$user} ON {$userenrolment}.userid = {$user}.id");

        // Specific course based on report parameters.
        if ($userid) {
            $this->add_base_condition_simple("{$user}.id", $userid);
        }

        // Users are only allowed to see their own subordinates.
        $manager = organisation::get_user_with_jobs();
        if (!$manager || !$manager->is_manager()) {
            $this->add_base_condition_sql('1=2');
        } else {
            [$where, $params] = $manager->get_managed_users_select($user, organisation::PERM_VIEW_REPORTS);
            $this->add_base_condition_sql($where, $params);
        }
        // Add job entity, just for the organisation structure (doesn't require joins).
        $this->add_entity(new job());

        // Join completion entity.
        $completionentity = new completion();
        $completion = $completionentity->get_table_alias('course_completion');
        $completionentity->add_join("
            LEFT JOIN {course_completions} {$completion}
                   ON {$completion}.course = {$course}.id AND {$completion}.userid = {$user}.id");
        $completionentity->set_table_alias('user', $user);
        $this->add_entity($completionentity);

        $this->add_columns($course, $userenrolment);
        $this->add_filters();

        if (!empty($userid)) {
            $userfullname = fullname(core_user::get_user($userid, '*', MUST_EXIST),
                has_capability('moodle/site:viewfullnames', $this->get_context()));
            $userfilename = get_string('courseprogressexport', 'block_myteams', $userfullname);
        }

        $this->set_downloadable(true, $userfilename ?? get_string('courseprogress', 'block_myteams'));
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        $userid = $this->get_parameter('userid', 0, PARAM_INT);

        return permission::can_view_course_progress($userid);
    }

    /**
     * Adds the columns we want to display in the report
     *
     * @param string $course course table alias
     * @param string $userenrolment user enrolment table alias
     */
    protected function add_columns(string $course, string $userenrolment): void {
        $userid = $this->get_parameter('userid', 0, PARAM_INT);

        $this->add_columns_from_entities([
            'course:coursefullnamewithlink',
            'user:fullnamewithpicturelink',
            'enrolment:method',
            'enrolment:timestarted',
            'enrolment:timeended',
            'completion:completed',
            'completion:progresspercent',
            'completion:timecompleted',
        ]);

        // User column is dependent on whether we are pre-filtering.
        $this->get_column('user:fullnamewithpicturelink')->set_is_available($userid === 0);

        // Simplify course name column title.
        $this->get_column('course:coursefullnamewithlink')->set_title(new lang_string('course'));

        // Shorten the format used for all date column callbacks.
        $dateformat = get_string('strftimedatefullshort', 'core_langconfig');
        foreach (['enrolment:timestarted', 'enrolment:timeended', 'completion:timecompleted'] as $column) {
            $this->get_column($column)->set_callback([format::class, 'userdate'], $dateformat);
        }

        // Change the completion progresspercent column title and callback.
        $this->get_column('completion:progresspercent')
            ->set_title(new lang_string('activitycompletion', 'completion'))
            ->set_callback([formatter::class, 'course_activity_completion']);

        // Change the completion completed column title, sortable and callback.
        $this->get_column('completion:completed')
            ->set_title(new lang_string('coursestatus', 'block_myteams'))
            ->set_is_sortable(false)
            ->add_fields("{$course}.startdate, {$course}.enddate, {$userenrolment}.timestart,{$userenrolment}.status")
            ->set_callback([formatter::class, 'course_status']);

        $this->set_initial_sort_column('course:coursefullnamewithlink', SORT_ASC);
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_filters(): void {
        $userid = $this->get_parameter('userid', 0, PARAM_INT);

        $this->add_filters_from_entities([
            'user:fullname',
            'job:orgstructure',
            'course:fullname',
            'enrolment:method',
            'enrolment:timestarted',
            'enrolment:timeended',
            'completion:completed',
            'completion:timecompleted',
        ]);

        // User columns are dependent on whether we are pre-filtering.
        $this->get_filter('user:fullname')->set_is_available($userid === 0);
        $this->get_filter('job:orgstructure')->set_is_available($userid === 0);
    }
}
