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
 * Class for define the system report for active/overdue programs.
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\reports;

defined('MOODLE_INTERNAL') || die();

use tool_certification\local\helpers\certificationuser_entity;
use tool_program\api;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programcompletion_entity;
use tool_program\local\helpers\programuser_entity;
use tool_program\local\helpers\programuser_format;
use tool_program\permission;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;
use tool_wp\db;

/**
 * Class user_programs
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_program
 */
class programs_progress_report extends system_report {
    /** @var int $userid */
    private $userid;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->userid = $this->get_parameter('userid', 0, PARAM_INT);
        $type = $this->get_parameter('type', -1, PARAM_INT);

        $this->set_columns();
        $this->set_main_table('user', 'u');
        $this->add_base_condition_simple('u.id', $this->userid);
        $this->add_base_join(api::get_status_sql_join('u', 'tpu', 'tp', 'tps', 'tpsc'));
        $this->add_base_join('LEFT JOIN {tool_certification_users} tcu
        ON tcu.userid = tpu.userid AND tcu.certificationid = tpu.certificationid');

        // Base condition is a visibility check (programs non archived, from correct tenant and not hidden).
        $usertenantid = tenancy::get_tenant_id($this->userid);
        $tenant = db::generate_param_name();
        $this->add_base_condition_sql("
                tp.archived = 0
                AND tps.parent = 0
                AND tp.tenantid = :{$tenant}
                AND tp.visible = 1 ", [$tenant => $usertenantid]);

        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);

        // Add default columns.
        if ($column = $this->get_column('tool_program:fullname')) {
            $column->set_is_default(true, 1);
            // We want to use a custom callback.
            $column->add_field('tp.id', 'programid');
            $column->add_callback([programuser_format::class, 'userprogramname'], ['userid' => $this->userid]);
        }
        if ($column = $this->get_column('tool_program_users:associatedcertification')) {
            $column->set_is_default(true, 2);
        }
        if ($column = $this->get_column('tool_certification_users:expirydate')) {
            $column->set_is_default(true, 3);
        }
        if ($column = $this->get_column('tool_program_users:duedate')) {
            $column->set_is_default(true, 4);
        }
        if ($column = $this->get_column('tool_program_users:programstatus')) {
            $column->set_is_default(true, 5);
        }
        if ($column = $this->get_column('tool_program_users:programprogresswithoverview')) {
            $column->set_is_default(true, 6);
        }
        if ($column = $this->get_column('tool_program_set_completion:completeddate')) {
            $column->set_is_default(true, 7);
        }

        // Add default filter.
        $filters = $this->get_filters();
        $statusparams = ['filterablestatus_op' => 2, 'filterablestatus' => $type];
        $filters['tool_program_users:filterablestatus']->set_is_default(true, $statusparams);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_user_programs_progress($this->userid);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportuserprograms', 'tool_program');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns(): void {
        $this->add_entity(new program_entity('', 'tp'));
        $this->add_entity(new programuser_entity('', 'tpu'));
        $this->add_entity(new programcompletion_entity('', 'tpsc'));
        $this->add_entity(new certificationuser_entity('', 'tcu'));
    }

    /**
     * Set the actions icons of the report.
     */
    protected function add_actions(): void {
        // No actions defined.
    }
}
