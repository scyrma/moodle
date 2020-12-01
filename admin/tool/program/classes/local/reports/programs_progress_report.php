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
 * Class for define the system report for active/overdue programs.
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
use tool_reportbuilder\report_action;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;
use tool_wp\db;

/**
 * Class user_programs
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
        ON tcu.userid = tpu.userid AND tcu.certificationid = tpu.certificationid
        LEFT JOIN {tool_certification_compltion} tcc
        ON tcc.userid = tcu.userid AND tcc.certificationid = tcu.certificationid AND tcc.timerevoked = 0 AND tcc.islast = 1
        ');

        // Base condition is a visibility check (programs non archived, from correct tenant and not hidden).
        $this->add_base_condition_sql("
                tp.archived = 0
                AND tps.parent = 0
                AND tp.visible = 1 ", []);

        $this->add_base_fields('tp.id AS programid, tpu.id AS allocationid');
        $this->add_actions();
        $this->set_downloadable(false);

        // Add default columns.
        if ($column = $this->get_column('tool_program:fullname')) {
            $column->set_is_default(true, 1);
            // We want to use a custom callback.
            $column->add_field('tp.id', 'programid');
            $column->set_callback([programuser_format::class, 'userprogramname'], ['userid' => $this->userid]);
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
        if ($column = $this->get_column('tool_program_users:programprogress')) {
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
        $this->add_entity(new program_entity());
        $this->add_entity(new programuser_entity());
        $this->add_entity(new programcompletion_entity());
        $this->add_entity(new certificationuser_entity());
    }

    /**
     * Set the actions icons of the report.
     */
    protected function add_actions(): void {
        global $CFG;

        // Progress report icon.
        $urlparams = ['programid' => ':programid', 'userid' => $this->userid];
        $progressreporturl = new \moodle_url("/$CFG->admin/tool/program/programprogress.php", $urlparams);
        $progressreporticon = new \pix_icon('bar-chart',
            get_string('progressreport', 'tool_program'),
            'tool_wp');
        $action = new report_action($progressreporturl, $progressreporticon);
        $action->add_callback(function(\stdClass $row) {
            global $USER;
            return $USER->id != $this->userid && permission::can_view_user_programs_progress($this->userid);
        });
        $this->add_action($action);

        // Progress overview icon.
        $progressoverviewurl = new \moodle_url("#");
        $progressoverviewicon = new \pix_icon('i/dashboard',
            get_string('progressoverview', 'tool_program'),
            'core');
        $action = new report_action($progressoverviewurl, $progressoverviewicon, [
            'class' => 'program-progress-overview-trigger',
            'data-allocationid' => ':allocationid',
            'data-title' => get_string('progressoverview', 'tool_program'),
            'data-contextid' => \context_system::instance()->id
        ]);
        $action->add_callback(function(\stdClass $row) {
            global $USER;
            return $USER->id != $this->userid && permission::can_view_user_programs_progress($this->userid);
        });
        $this->add_action($action);

    }
}
