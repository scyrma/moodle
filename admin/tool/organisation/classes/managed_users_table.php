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
 * Class managed_users_table
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use context_system;
use tool_organisation\output\user_with_jobs;
use tool_organisation\tool_reportbuilder\filter\org_structure_filter;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\db;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;

/**
 * Class managed_users_table
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class managed_users_table extends system_report {

    /** @var user_with_jobs */
    protected $manager;

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_main_table('user', 'u');
        $this->add_base_condition_simple('u.deleted', 0);
        $this->set_downloadable(false);

        // If the current user can not browse users, then don't show suspended or not confirmed users in the report.
        if (!\tool_tenant\permission::can_browse_users($this->get_tenant_id())) {
            $this->add_base_condition_simple('u.suspended', 0);
            $this->add_base_condition_simple('u.confirmed', 1);
        }
        $this->add_base_fields('u.confirmed, u.suspended'); // Necessary for get_row_class.

        $this->manager = organisation::get_user_with_jobs();
        list($where, $params) = helper::get_managed_users_select($this->manager);
        $this->add_base_condition_sql($where, $params);

        $this->set_columns();

        $f = new report_filter(
            org_structure_filter::class,
            'org_structure',
            new \lang_string('orgstructure', 'tool_organisation'),
            'tool_organisation_position',
            'u.id'
        );
        $f->set_is_default(true);
        $this->add_filter($f);

        $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
        [$fullnamesql, $fullnameparams] = db::sql_fullname($this->get_main_table_alias(), $viewfullnames);
        $f = new report_filter(
            text::class,
            'fullname',
            new \lang_string('fullname'),
            'user',
            $fullnamesql,
            $fullnameparams
        );
        $f->set_is_default(true);
        $this->add_filter($f);
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
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('myteams', 'tool_organisation');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns() {
        global $DB;

        $this->annotate_entity('user', new \lang_string('entityuser', 'tool_reportbuilder'));
        $this->annotate_entity('tool_organisation_position', new \lang_string('entityposition', 'tool_organisation'));
        $this->annotate_entity('tool_organisation_department', new \lang_string('entitydepartment', 'tool_organisation'));

        $usertablealias = $this->get_main_table_alias();

        // Get all fields required to show the user profile (note: any of the fields may be used in callbacks).
        $userfields = implode(', ', array_map(function(\database_column_info $column) use ($usertablealias) {
            return "{$usertablealias}.{$column->name}";
        }, $DB->get_columns('user')));

        // Sort user according to the site fullname configuration.
        $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
        $usersort = db::sql_fullname($usertablealias, $viewfullnames, true);

        // Column "userinfo".
        $this->add_column((new report_column(
            'userinfo',
            null,
            'user'
        ))
            ->add_fields($userfields)
            ->set_is_sortable(true, true, 1, SORT_ASC, explode(', ', $usersort))
            ->set_is_default(true)
            ->add_callback([$this, 'userinfo']));
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
        $user = organisation::get_user_with_jobs($row->id);
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
     * Add 'dimmed_text' class to suspended and non-confirmed user rows.
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        // Suspended and non-confirmed users must be dimmed.
        return ($row->suspended || !$row->confirmed) ? 'dimmed_text' : '';
    }
}
