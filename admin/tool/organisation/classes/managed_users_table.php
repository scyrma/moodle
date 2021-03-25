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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class managed_users_table
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        return $output->render_from_template('tool_organisation/dashboard_team_user',
            $user->export($output));
    }
}
