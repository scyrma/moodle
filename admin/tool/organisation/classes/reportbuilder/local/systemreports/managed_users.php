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

use core_reportbuilder\system_report;
use tool_organisation\helper;
use tool_organisation\organisation;
use tool_organisation\output\user_with_jobs;
use tool_organisation\reportbuilder\local\entities\job;
use tool_tenant\permission;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * System report class for showing managed users for the current user
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class managed_users extends system_report {

    /** @var user_with_jobs */
    protected $manager;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('user', 'u');

        $this->add_base_condition_simple('u.deleted', 0);

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        if (!permission::can_view_inactive_users(tenancy::get_tenant_id())) {
            $this->add_base_condition_sql('u.suspended = 0 AND u.confirmed = 1');
        }

        $this->add_base_fields('u.confirmed, u.suspended'); // Necessary for get_row_class.

        $this->manager = organisation::get_user_with_jobs();
        list($where, $params) = helper::get_managed_users_select($this->manager);
        $this->add_base_condition_sql($where, $params);

        // Add our report entities.
        $this->add_entity((new user())->set_table_alias('user', $this->get_main_table_alias()));
        $this->add_entity(new job());

        $this->add_columns();
        $this->add_filters();

        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return $this->manager && $this->manager->is_manager();
    }

    /**
     * Set the columns for the report.
     */
    protected function add_columns(): void {
        global $DB;

        $usertablealias = $this->get_main_table_alias();

        // Get all fields required to show the user profile (note: any of the fields may be used in callbacks).
        $userfields = implode(', ', array_map(function(\database_column_info $column) use ($usertablealias) {
            return "{$usertablealias}.{$column->name}";
        }, $DB->get_columns('user')));

        // Column "userinfo".
        $this->add_column_from_entity('user:fullname')
            ->add_fields($userfields)
            ->set_title(null)
            ->set_callback([$this, 'userinfo']);

        $this->set_initial_sort_column('user:fullname', SORT_ASC);
    }

    /**
     * Define report filters
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'user:fullname',
            'job:orgstructure',
        ]);
    }

    /**
     * User jobs
     *
     * @param mixed $value
     * @param \stdClass $row
     * @return string
     */
    public function userinfo($value, \stdClass $row) {
        global $PAGE;
        $user = organisation::get_user_with_jobs((int) $row->id);
        if (!$user) {
            // Error may occur here when user who has jobs was moved to another tenant.
            return '';
        }
        $user->set_full_user_record($row);
        // TODO SP-141 remove jobs irrelevant to the $user.

        $output = $PAGE->get_renderer('tool_organisation');
        $context = $user->export($output);
        // Uniqueid is generated and used in all related templates, so collapse/toggle elements work correctly
        // with multiple instances. For example using 'Learning' tab alongside 'Learning' block.
        $context->uniqueid = random_string(10);
        return $output->render_from_template('tool_organisation/dashboard_team_user', $context);
    }

    /**
     * CSS class for the row.
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        return ($row->suspended || !$row->confirmed) ? 'text-muted' : '';
    }
}
