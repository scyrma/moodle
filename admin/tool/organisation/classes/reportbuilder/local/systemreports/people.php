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
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use html_writer;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use tool_organisation\helper;
use tool_organisation\organisation;
use tool_organisation\permission;
use tool_organisation\reportbuilder\local\entities\job;
use tool_tenant\permission as tenantpermission;
use tool_tenant\tenancy;

/**
 * Class people
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class people extends system_report {
    /** @var bool $ismanager */
    private bool $ismanager;
    /** @var array $jobs */
    private array $jobs;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity);
        $this->set_main_table('user', $useralias);

        $jobentity = new job();
        $jobalias = $jobentity->get_table_alias('tool_organisation_job');
        $this->add_join("LEFT JOIN {tool_organisation_job} {$jobalias} ON {$jobalias}.userid = {$useralias}.id");
        $this->add_entity($jobentity);

        $this->add_base_condition_simple("{$useralias}.deleted", 0);
        $this->add_base_fields("DISTINCT {$useralias}.id AS userid");

        // Check tenant id on users in case they have been moved to another tenant.
        [$join, $where, $params] = tenancy::get_users_sql($useralias, tenancy::get_tenant_id());
        $this->add_join($join);
        $this->add_base_condition_sql($where, $params);

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        if (!tenantpermission::can_view_inactive_users(tenancy::get_tenant_id())) {
            $this->add_base_condition_sql("{$useralias}.suspended = 0 AND {$useralias}.confirmed = 1");
        }

        if (!tenantpermission::can_switch_tenant()) {
            // Check tenant id on users in case they have been moved to another tenant.
            [$join, $where, $params] = tenancy::get_users_sql($useralias, tenancy::get_tenant_id());
            $this->add_join($join);
            $this->add_base_condition_sql($where, $params);
        }

        $this->add_columns($userentity);
        $this->add_filters($userentity, $jobentity);
        $this->add_actions();

        // Make all columns not sortable.
        $columns = $this->get_columns();
        foreach ($columns as $column) {
            if ($column->get_name() === 'fullnamewithpicture') {
                continue;
            }
            $column->set_is_sortable(false);
        }

        $this->set_checkbox_toggleall(static function (stdClass $row): array {
            return [$row->userid, get_string('select'), 'data-bulkuserid'];
        });
        $this->set_initial_sort_column('user:fullnamewithpicture', SORT_ASC);
        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_jobs();
    }

    /**
     * Set the filters of the report.
     * @param user $userentity
     * @param job $jobentity
     */
    protected function add_filters(user $userentity, job $jobentity): void {
        $job = $jobentity->get_table_alias('tool_organisation_job');
        $position = $jobentity->get_table_alias('tool_organisation_position');
        $useralias = $userentity->get_table_alias('user');

        $mam = database::generate_alias();

        $this->add_filters_from_entities([
            'user:fullname',
            'job:department',
            'job:position',
        ]);

        // Add the managers (globalmanager, departmentlead and manually assigned manager) filter to the report.
        $this->add_filter((new filter(
            boolean_select::class,
            'managers',
            new lang_string('ismanager', 'tool_organisation'),
            $jobentity->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_organisation_position} {$position} ON {$position}.id = {$job}.positionid")
            ->add_join("LEFT JOIN {tool_organisation_manual_mgr} {$mam} ON {$mam}.managerid = {$useralias}.id")
            ->set_field_sql("(CASE WHEN ({$position}.globalmanager = 1 OR {$position}.departmentmanager = 1
             OR {$mam}.managerid = {$useralias}.id) THEN 1 ELSE 0 END)"));

        // Add the "Show people with jobs" filter to the report.
        $this->add_filter((new filter(
            boolean_select::class,
            'peoplewithjobs',
            new lang_string('showpeoplewithjobs', 'tool_organisation'),
            $jobentity->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("(CASE WHEN ({$job}.positionid IS NOT NULL) THEN 1 ELSE 0 END)"));

        // Add the "Show people with managers" filter to the report.
        $this->add_filter((new filter(
            boolean_select::class,
            'peoplewithmanagers',
            new lang_string('showpeoplewithmanagers', 'tool_organisation'),
            $jobentity->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql(helper::users_with_managers_sql($useralias)));
    }

    /**
     * Set the columns for the report.
     * @param user $userentity
     */
    protected function add_columns(user $userentity): void {
        $useralias = $this->get_main_table_alias();

        $this->add_column_from_entity('user:fullnamewithpicture')
            ->add_callback([$this, 'add_ismanager_badge']);

        $this->add_column_from_entity('user:email');

        $this->add_column((new column(
            'jobsassigned',
            new lang_string('jobsassigned', 'tool_organisation'),
            $userentity->get_entity_name()
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$useralias}.id AS userid")
            ->add_callback([$this, 'format_jobs_assigned']));
    }

    /**
     * Add the "Is manager" badge to the user name.
     * @param null|string $value
     * @param stdClass $row
     * @return string
     */
    public function add_ismanager_badge(?string $value, stdClass $row): string {
        if ($this->ismanager) {
            $value .= html_writer::span(get_string('ismanager', 'tool_organisation'), 'badge badge-pill badge-info ml-1');
        }
        return $value;
    }

    /**
     * Format the jobs assigned column adding positions and departments.
     * @param null|string $value
     * @param stdClass $row
     * @return string
     */
    public function format_jobs_assigned(?string $value, stdClass $row): string {
        global $OUTPUT;

        if (!$this->jobs) {
            return '';
        }

        $context = ['isuserview' => false, 'jobs' => $this->jobs];
        return $OUTPUT->render_from_template('tool_organisation/userjobs', $context);
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        $this->add_action((new action(
            new moodle_url('/admin/tool/organisation/user.php', ['id' => ':userid']),
            new pix_icon('briefcase', '', 'tool_wp'),
            [],
            false,
            new lang_string('viewjobsandreporting', 'tool_organisation')
        ))->add_callback(static function (stdClass $row): bool {
            return permission::can_view_user_org_profile((int)$row->userid);
        }));
    }

    /**
     * Executed before each row
     *
     * @param stdClass $row
     */
    public function row_callback(stdClass $row): void {
        $userwithjobs = organisation::get_user_with_jobs((int) $row->userid, null);

        $jobs = [];
        foreach ($userwithjobs->get_jobs() as $job) {
            $jobs[] = (object) [
                'department' => $job->get_department()->get('name'),
                'position' => $job->get_position()->get('name'),
                'isactive' => !($job->get('enddate') && $job->get('enddate') <= helper::round_time(time())),
            ];
        }

        // Sort jobs active ones first and then by position and then department.
        usort($jobs, function ($a, $b) {
            if ($a->isactive === $b->isactive) {
                if (strcasecmp($a->position, $b->position) === 0) {
                    return strcasecmp($a->department, $b->department);
                }
                return strcasecmp($a->position, $b->position);
            }
            return $b->isactive <=> $a->isactive;
        });

        $this->ismanager = $userwithjobs->is_manager();
        $this->jobs = $jobs;
    }
}
