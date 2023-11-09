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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_organisation\reportbuilder\local\systemreports;

use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use core_user\fields;
use html_writer;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use tool_organisation\{helper, permission};
use tool_organisation\local\helpers\format;
use tool_organisation\reportbuilder\local\entities\job;
use tool_tenant\tenancy;

/**
 * System report class for showing assigned jobs for a user
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_jobs_assigned extends system_report {

    /** @var int Default paging limit */
    public const DEFAULT_REPORT_PAGESIZE = 5;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $jobentity = new job();
        $jobalias = $jobentity->get_table_alias('tool_organisation_job');
        $this->add_entity($jobentity);

        $this->set_main_table('tool_organisation_job', $jobalias);
        $this->add_base_condition_simple('toj.tenantid', tenancy::get_tenant_id());

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity);

        // We need to generate SQL for users fullname for later.
        [$sqlfullname, $sqlfullnameparams] = fields::get_sql_fullname($useralias);
        $this->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$jobalias}.userid", $sqlfullnameparams, false);
        $this->add_base_condition_simple("{$useralias}.deleted", 0);

        $managedid = $this->get_parameter('userid', 0, PARAM_INT);
        $this->add_base_condition_simple("{$useralias}.id", $managedid);

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        if (!\tool_tenant\permission::can_view_inactive_users(tenancy::get_tenant_id())) {
            $this->add_base_condition_sql("{$useralias}.suspended = 0 AND {$useralias}.confirmed = 1");
        }

        // Required for actions.
        $this->add_base_fields("{$jobalias}.id, {$jobalias}.userid, {$jobalias}.departmentid, {$jobalias}.tenantid,
            {$jobalias}.positionid, {$jobalias}.enddate, {$sqlfullname} AS userfullname");

        if (!\tool_tenant\permission::can_switch_tenant()) {
            // Check tenant id on users in case they have been moved to another tenant.
            [$join, $where, $params] = tenancy::get_users_sql($useralias, tenancy::get_tenant_id());
            $this->add_join($join);
            $this->add_base_condition_sql($where, $params);
        }

        $this->add_columns($jobentity);
        $this->add_actions();

        $this->set_downloadable(false);
        $this->set_default_per_page(self::DEFAULT_REPORT_PAGESIZE);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        $managedid = $this->get_parameter('userid', 0, PARAM_INT);
        return permission::can_view_user_org_profile($managedid);
    }

    /**
     * Set the columns for the report.
     * @param job $jobentity
     */
    protected function add_columns(job $jobentity): void {
        $job = $jobentity->get_table_alias('tool_organisation_job');

        // Let's rewrite the positiondepartment column to get the position and department in the format required.
        $this->add_column_from_entity('job:positiondepartment')
            ->add_field("{$job}.enddate")
            ->add_callback(static function(?string $value, stdClass $row): string {
                global $OUTPUT;
                $job = (object) [
                    'department' => $row->department,
                    'position' => $row->position,
                    'isactive' => !($row->enddate && $row->enddate <= helper::round_time(time())),
                ];
                $context = ['isuserview' => true, 'jobs' => [$job]];

                return $OUTPUT->render_from_template('tool_organisation/userjobs', $context);
            });

        // Let's rewrite the startdate column to get the start/end dates in the format required.
        $this->add_column_from_entity('job:startdate')
            ->add_field("{$job}.enddate")
            ->add_callback(static function(?string $value, stdClass $row): string {
                global $OUTPUT;
                $icon = $OUTPUT->render(new \pix_icon('i/calendar', ''));
                if ($row->startdate > helper::round_time(time())) {
                    $dateformatted = get_string('jobfrom', 'tool_organisation', format::jobdate($row->startdate));
                } else {
                    $to = !empty($row->enddate) ? format::jobdate($row->enddate) : get_string('present', 'tool_organisation');
                    $dateformatted = get_string('jobfromto', 'tool_organisation', ['from' => $value, 'to' => $to]);
                }

                return $icon . '&nbsp;' . $dateformatted;
            });

        $this->add_column_from_entity('job:permissionswithicons');

        $this->set_initial_sort_column('job:positiondepartment', SORT_ASC);
    }

    /**
     * Set the actions for the report.
     */
    private function add_actions(): void {

        // Set the reports selectors that will be reloaded in each action.
        $reportselectors['jobsassigned'] = '.userjobs-section-jobsassigned';
        $reportselectors['reportsto'] = '.userjobs-section-reportsto';
        $reportselectors['peoplereportingto'] = '.userjobs-section-peoplereportingto';

        // Swap department or position.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('long-arrow-right', '', 'tool_wp'),
            [
                'data-action' => 'transfertojob',
                'data-id' => ':id',
                'data-userid' => ':userid',
                'data-fullusername' => ':userfullname',
                'data-reportselector' => implode(',', $reportselectors),
            ],
            false,
            new lang_string('transferjob', 'tool_organisation')
        ))
            ->add_callback(static function(stdClass $row): bool {
                $haspermission = permission::can_edit_job(new \tool_organisation\job(0, $row));
                $hasnotended = empty($row->enddate) || $row->enddate >= strtotime('today');
                return $haspermission && $hasnotended;
            })
        );

        // Set job as finished.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('flag', '', 'tool_wp'),
            [
                'data-action' => 'setjobfinished',
                'data-id' => ':id',
                'data-userid' => ':userid',
                'data-fullusername' => ':userfullname',
                'data-reportselector' => implode(',', $reportselectors),
            ],
            false,
            new lang_string('setjobfinished', 'tool_organisation')
        ))
            ->add_callback(static function(stdClass $row): bool {
                $haspermission = permission::can_edit_job(new \tool_organisation\job(0, $row));
                $hasnoenddate = empty($row->enddate);
                return $haspermission && $hasnoenddate;
            })
        );

        // Edit job dates.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/calendar', '', 'core'),
            [
                'data-action' => 'editjobdates',
                'data-id' => ':id',
                'data-userid' => ':userid',
                'data-fullusername' => ':userfullname',
                'data-reportselector' => implode(',', $reportselectors),
            ],
            false,
            new lang_string('editdates', 'tool_organisation')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return permission::can_edit_job(new \tool_organisation\job(0, $row));
            })
        );

        // Delete job.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('delete-o', '', 'tool_wp'),
            [
                'data-action' => 'deletejob',
                'data-id' => ':id',
                'data-userid' => ':userid',
                'data-fullusername' => ':userfullname',
                'data-reportselector' => implode(',', $reportselectors),
            ],
            false,
            new lang_string('deletejob', 'tool_organisation')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return permission::can_edit_job(new \tool_organisation\job(0, $row));
            })
        );
    }
}
