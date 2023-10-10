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

use core_reportbuilder\local\helpers\report as reporthelper;
use core_reportbuilder\manager;
use tool_organisation\local\entities\jobs;
use tool_reportbuilder\convert_not_implemented;
use tool_reportbuilder\convert_not_possible;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\entities\course as course_entity;
use tool_reportbuilder\local\entities\course_completion as course_completion_entity;
use tool_reportbuilder\local\entities\course_enrolment as course_enrolment_entity;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\local\entities\user_lastaccess as user_lastaccess_entity;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\local\models\reportbuilder_conditions;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_column;
use tool_tenant\tenancy;

/**
 * Datasource class for course participants
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_course_enrolment_completion extends datasource {

    /**
     * When converting custom report to core reportbuilder which class corresponds to this datasource
     *
     * @return string name of the class extending {@see \core_reportbuilder\datasource}
     */
    public function convert_get_datasource_class(): string {
        return \core_course\reportbuilder\datasource\participants::class;
    }

    /**
     * Get the entity name that corresponds to the given entity in the converted datasource
     *
     * @param string $oldentityname entity name in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string entity name in the converted datasource
     */
    public function convert_get_entity_name(string $oldentityname, \core_reportbuilder\datasource $newsource): string {
        if ($oldentityname === 'tool_organisation_jobs') {
            return 'job';
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
        if ($oldcolumn->get_unique_identifier() === 'course:category') {
            return 'course_category:name';
        }
        if ($oldcolumn->get_unique_identifier() === 'user:tenant') {
            return 'tenant:name';
        }
        return parent::convert_get_column_unique_identifier($oldcolumn, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which filter corresponds to the given filter
     *
     * @param \tool_reportbuilder\report_filter $oldfilter the filter in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding filter in the converted datasource
     */
    public function convert_get_filter_unique_identifier(\tool_reportbuilder\report_filter $oldfilter,
                                                         \core_reportbuilder\datasource $newsource): string {
        if ($oldfilter->get_unique_identifier() === 'course:category') {
            return 'course_category:name';
        }
        if ($oldfilter->get_unique_identifier() === 'user:tenant') {
            return 'tenant:name';
        }
        return parent::convert_get_filter_unique_identifier($oldfilter, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which filter corresponds to the given filter
     *
     * @param \tool_reportbuilder\report_filter $oldcondition the condition in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding filter in the converted datasource
     */
    public function convert_get_condition_unique_identifier(\tool_reportbuilder\report_filter $oldcondition,
                                                         \core_reportbuilder\datasource $newsource): string {
        if ($oldcondition->get_unique_identifier() === 'course:category') {
            return 'course_category:name';
        }
        if ($oldcondition->get_unique_identifier() === 'user:tenant') {
            return 'tenant:name';
        }
        return parent::convert_get_condition_unique_identifier($oldcondition, $newsource);
    }

    /**
     * Convert a custom report with this datasource to the core reportbuilder
     *
     * Can throw exceptions {@see \tool_reportbuilder\convert_not_implemented} and/or
     * {@see \tool_reportbuilder\convert_not_possible}.
     *
     * @param bool $deleteoriginal
     * @return int id of the core reportbuilder custom report or throws an exception
     * @throws convert_not_implemented
     * @throws convert_not_possible
     */
    public function convert(bool $deleteoriginal = true): int {
        // If is converting course participants datasource, check if username conditions is really used and if not, add a new
        // condition "username is not empty" to avoid getting empty rows due that now core course participants datasource
        // is using LEFT JOINs instead of JOINs.
        $addusernamecondition = true;
        $conditions = (array) json_decode($this->get_persistent()->get('conditions'));
        $condition = reportbuilder_conditions::get_record(
            ['reportid' => $this->get_id(), 'entity' => 'user', 'name' => 'username']);
        if ($condition) {
            if (array_key_exists('user:username', $conditions) &&
                ((($conditions['user:username_op'] == 0 || $conditions['user:username_op'] == 1 ||
                        $conditions['user:username_op'] == 2 || $conditions['user:username_op'] == 3 ||
                        $conditions['user:username_op'] == 4) && $conditions['user:username'] !== '')
                || $conditions['user:username_op'] == 5 || $conditions['user:username_op'] == 6)) {
                $addusernamecondition = false;
            }
        }

        $reportid = parent::convert($deleteoriginal);

        if ($addusernamecondition) {
            if (!$condition) {
                // Add condition if it doesn't exist in old report.
                reporthelper::add_report_condition($reportid, 'user:username');
            }

            $newreport = manager::get_report_from_id($reportid);
            // Using array merge here will override the username option value in case it already exists from the conversion.
            $values = array_merge($newreport->get_condition_values(),
                ['user:username_operator' => \core_reportbuilder\local\filters\text::IS_NOT_EMPTY]);
            $newreport->set_condition_values($values);
        }

        return $reportid;
    }

    /**
     * Initialise datasource
     */
    protected function initialise() : void {
        $this->set_main_table('user', 'u');

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        $canviewinactiveusers = \tool_tenant\permission::can_view_inactive_users($this->get_tenant_id());
        if (!$canviewinactiveusers) {
            $this->add_confirmed_user_condition();
        }

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

        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, 'u.id', 0, true));

        $this->set_columns();

        // Set default columns/sorting.
        $this->get_column('course:coursefullnamewithlink')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true);
        $this->get_column('user:fullnamewithlink')
            ->set_is_default(true, 2)
            ->set_is_sortable(true, true);
        $this->get_column('course_completion:completed')
            ->set_is_default(true, 3);

        $canshowtenantcolumn = \tool_reportbuilder\permission::can_show_tenant_column($this->get_tenant_id());
        if ($column = $this->get_column('user:tenant')) {
            $column->set_is_default(true, 4)
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
        return get_string('reportcourseparticipants', 'tool_reportbuilder');
    }

    /**
     * Set the columns available for the datasource
     */
    protected function set_columns(): void {
        $this->add_entity(new course_entity());

        $allowtenant = permission::can_show_tenant_column(tenancy::get_tenant_id());
        $this->add_entity((new user_entity())->set_allow_tenant_columns($allowtenant));

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

        $this->add_entity((new user_lastaccess_entity())
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
