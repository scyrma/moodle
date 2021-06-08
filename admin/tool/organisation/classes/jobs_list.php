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

/**
 * Class jobs_list
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use tool_organisation\local\entities\jobs as job_entity;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\system_report;
use tool_reportbuilder\report_action;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class jobs_list
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class jobs_list extends system_report {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_filters();
        $this->set_main_table('tool_organisation_job', 'toj', false);
        $this->add_base_condition_simple('toj.tenantid', tenancy::get_tenant_id());
        $this->add_base_join('JOIN {user} u ON u.id = toj.userid');
        $this->add_base_join('LEFT JOIN {tool_tenant_user} tu ON tu.userid = u.id');
        $this->add_base_fields('toj.id, toj.userid, toj.departmentid, toj.tenantid, toj.positionid, ' .
            'toj.enddate, u.firstname as fullusername, tu.tenantid as usertenantid,' .
            user_entity::get_all_user_name_fields(true, 'u')); // Necessary for actions and row class.
        $this->add_base_condition_simple('u.deleted', 0);

        if (!\tool_tenant\permission::can_switch_tenant()) {
            // Check tenant id on users in case they have been moved to another tenant.
            [$join, $where, $params] = tenancy::get_users_sql('u', tenancy::get_tenant_id());
            $this->add_base_join($join);
            $this->add_base_condition_sql($where, $params);
        }

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
     *
     * @throws \coding_exception
     */
    protected function set_filters() {
        $filters = [
            'user:fullname',
            'tool_organisation_jobs:department',
            'tool_organisation_jobs:position',
            'tool_organisation_jobs:showpastjobs'
        ];
        foreach ($filters as $f) {
            if ($filter = $this->get_filter($f)) {
                $filter->set_is_default(true);
            }
        }
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('jobs', 'tool_organisation');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns() {
        $this->add_entity(new job_entity());
        $this->add_entity(new user_entity());

        if ($column = $this->get_column('user:fullnamewithlink')) {
            $column->set_is_default(true, 1)->set_visiblename(new \lang_string('fullname', 'tool_organisation'));
            $column->set_is_sortable(true, true);
            $column->add_fields('toj.tenantid, tu.tenantid as usertenantid');
            $column->add_callback([$this, 'append_label']);
        }
        $defaultcolumns = [
            'tool_organisation_jobs:department',
            'tool_organisation_jobs:position',
            'tool_organisation_jobs:permissionswithicons',
            'tool_organisation_jobs:startdate',
            'tool_organisation_jobs:enddate'
        ];
        foreach ($defaultcolumns as $key => $c) {
            if ($column = $this->get_column($c)) {
                $column->set_is_default(true, $key + 2);
            }
        }
    }

    /**
     * Set the actions icons of the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function add_actions() {
        $url = new \moodle_url('#');

        $icon = new \pix_icon('i/settings', get_string('editjob', 'tool_organisation'), 'core');
        $action = new report_action($url, $icon, ['data-action' => 'editjob', 'data-id' => ':id',
            'data-userid' => ':userid', 'data-fullusername' => ':fullusername']);
        $action->add_callback(function($row) {
            $row->fullusername = format::fullname('', $row);
            return permission::can_edit_job(new job(0, $row));
        });
        $this->add_action($action);

        $icon = new \pix_icon('t/add', get_string('addjob', 'tool_organisation'), 'core');
        $action = new report_action($url, $icon, ['data-action' => 'addjob',
            'data-userid' => ':userid', 'data-fullusername' => ':fullusername']);
        $action->add_callback(function($row) {
            $row->fullusername = format::fullname('', $row);
            return permission::can_assign_job_to_user($row->userid) && $this->is_user_in_job_tenant($row);
        });
        $this->add_action($action);
    }

    /**
     * CSS class for the row
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        return ($row->enddate && $row->enddate < helper::round_time(time())) ? 'dimmed_text' : '';
    }

    /**
     * Executed before each row
     *
     * @param \stdClass $row
     */
    public function row_callback(\stdClass $row): void {
        if ($this->is_user_in_job_tenant($row)) {
            tenancy::mark_user_as_same_tenant($row->userid);
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
