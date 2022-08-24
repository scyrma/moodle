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

namespace tool_organisation\reportbuilder\local\systemreports;

use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use core_reportbuilder\system_report;
use core_reportbuilder\local\report\action;
use core_user\fields;
use tool_organisation\helper;
use tool_organisation\permission;
use tool_organisation\reportbuilder\local\entities\job;
use tool_tenant\tenancy;
use tool_wp\reportbuilder\local\entities\user;

/**
 * Class jobs_list
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class jobs extends system_report {

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
        $this->add_base_fields("{$useralias}.confirmed, {$useralias}.suspended"); // Necessary for get_row_class.

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        if (!\tool_tenant\permission::can_view_inactive_users(tenancy::get_tenant_id())) {
            $this->add_base_condition_sql("{$useralias}.suspended = 0 AND {$useralias}.confirmed = 1");
        }

        $this->add_join("LEFT JOIN {tool_tenant_user} tu ON tu.userid = {$useralias}.id");

        // Required for actions and row class.
        $this->add_base_fields("{$jobalias}.id, {$jobalias}.userid, {$jobalias}.departmentid, {$jobalias}.tenantid,
            {$jobalias}.positionid, {$jobalias}.enddate, tu.tenantid AS usertenantid, {$sqlfullname} AS userfullname");

        if (!\tool_tenant\permission::can_switch_tenant()) {
            // Check tenant id on users in case they have been moved to another tenant.
            [$join, $where, $params] = tenancy::get_users_sql($useralias, tenancy::get_tenant_id());
            $this->add_join($join);
            $this->add_base_condition_sql($where, $params);
        }

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

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
     * Set the filters of the report
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'user:fullname',
            'job:department',
            'job:position',
            'job:showpast',
        ]);
    }

    /**
     * Set the columns for the report.
     */
    protected function add_columns(): void {
        if ($column = $this->add_column_from_entity('user:fullnamewithlink')) {
            $column->add_fields('toj.tenantid, tu.tenantid AS usertenantid')
                ->add_callback([$this, 'append_label']);
        }

        $this->add_columns_from_entities([
            'job:department',
            'job:position',
            'job:permissionswithicons',
            'job:startdate',
            'job:enddate',
        ]);

        $this->set_initial_sort_column('user:fullnamewithlink', SORT_ASC);
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {

        // Swap department or position.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('long-arrow-right', '', 'tool_wp'),
            [
                'data-action' => 'transfertojob',
                'data-id' => ':id',
                'data-userid' => ':userid',
                'data-fullusername' => ':userfullname',
            ],
            false,
            new lang_string('transfertonewjob', 'tool_organisation')
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

        // Add job.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/assignroles', '', 'core'),
            ['data-action' => 'addjob', 'data-userid' => ':userid', 'data-fullusername' => ':userfullname'],
            false,
            new lang_string('assignnewjob', 'tool_organisation')
        ))
            ->add_callback(function(stdClass $row): bool {
                return permission::can_assign_job_to_user((int) $row->userid) && $this->is_user_in_job_tenant($row);
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
            ['data-action' => 'deletejob', 'data-id' => ':id', 'data-userid' => ':userid', 'data-fullusername' => ':userfullname'],
            false,
            new lang_string('deletejob', 'tool_organisation')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return permission::can_edit_job(new \tool_organisation\job(0, $row));
            })
        );
    }

    /**
     * CSS class for the row
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        return ($row->enddate && $row->enddate < helper::round_time(time()))
                || ($row->suspended || !$row->confirmed) ? 'text-muted' : '';
    }

    /**
     * Executed before each row
     *
     * @param \stdClass $row
     */
    public function row_callback(\stdClass $row): void {
        if ($this->is_user_in_job_tenant($row)) {
            tenancy::mark_user_as_same_tenant((int) $row->userid);
        }
    }

    /**
     * Append the 'Does not belong to current tenant' label after user fullname when needed.
     *
     * @param string $value
     * @param \stdClass $row
     * @return string
     */
    public function append_label($value, \stdClass $row): string {
        if (!$this->is_user_in_job_tenant($row)) {
            $value .= \html_writer::tag('i', '', [
                'class' => 'icon fa fa-exclamation-circle text-danger ml-2',
                'aria-hidden' => 'true',
                'title' => new \lang_string('jobtenantdoesnotmatch', 'tool_organisation'),
            ]);
        }
        return $value;
    }

    /**
     * Checks of user tenant matching job tenant.
     *
     * @param \stdClass $row
     * @return bool
     */
    private function is_user_in_job_tenant(\stdClass $row): bool {
        $usertenantid = $row->usertenantid ?: \tool_tenant\tenancy::get_default_tenant_id();
        return (int)$row->tenantid === (int)$usertenantid;
    }
}
