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
 * Class managed_users_table
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation;

defined('MOODLE_INTERNAL') || die();

use tool_organisation\output\user_with_jobs;
use tool_organisation\tool_reportbuilder\filter\department_select;
use tool_organisation\tool_reportbuilder\filter\position_select;
use tool_reportbuilder\local\filter\text;
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
 */
class managed_users_table extends system_report {

    /** @var user_with_jobs */
    protected $manager;

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_main_table('user', 'u');
        $this->add_base_condition_simple('u.deleted', 0);
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);

        $this->manager = organisation::get_user_with_jobs();
        list($where, $params) = helper::get_managed_users_select($this->manager);
        $this->add_base_condition_sql($where, $params);

        $f = new report_filter(
            department_select::class,
            'department',
            new \lang_string('department', 'tool_organisation'),
            'tool_organisation_department',
            'u.id'
        );
        $f->set_is_default(true);
        $f->set_options(organisation::get_managed_users_departments_menu($this->manager, 0,
            ['' => get_string('anydepartment', 'tool_organisation')]));
        $this->add_filter($f);

        $f = new report_filter(
            position_select::class,
            'position',
            new \lang_string('position', 'tool_organisation'),
            'tool_organisation_position',
            'u.id'
        );
        $f->set_is_default(true);
        $f->set_options(organisation::get_managed_users_positions_menu($this->manager, 0,
            ['' => get_string('anyposition', 'tool_organisation')]));
        $this->add_filter($f);

        global $DB;
        $fullname  = $DB->sql_fullname('u.firstname', 'u.lastname');
        $f = new report_filter(
            text::class,
            'fullname',
            new \lang_string('fullname'),
            'user',
            $fullname
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

        // Column "userinfo".
        $newcolumn = (new report_column(
            'userinfo',
            null,
            'user'
        ))
            ->add_fields(join(',', array_keys($DB->get_columns('user')))) // TODO we don't need all fiels.
            ->set_is_default(true);
        $newcolumn->add_callback([$this, 'userinfo']);
        $this->add_column($newcolumn);
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
