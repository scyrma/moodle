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
 * Class for define the system report of the archived programs.
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\reports;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use pix_icon;
use tool_program\local\helpers\program_entity;
use tool_program\permission;
use tool_program\persistent\program;
use tool_reportbuilder\report_action;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;

/**
 * Class archived_table
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_program
 */
class archived_programs_report extends system_report {

    /** @var program */
    protected $lastprogram;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_columns();
        $this->set_main_table('tool_program', 'tp');
        $this->add_base_condition_simple('tp.archived', 1);
        $this->add_base_condition_simple('tp.tenantid', tenancy::get_tenant_id());
        $this->add_base_fields('tp.id, tp.archived, tp.visible, tp.tenantid'); // Field necessary for actions.
        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);

        // Default columns.
        if ($column = $this->get_column('tool_program:fullname')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('tool_program:timearchived')) {
            $column->set_is_default(true, 2);
            $column->set_is_sortable(true, true);
        }
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_archived_list();
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportarchivedprograms', 'tool_program');
    }

    /**
     * Set the columns for the report.
     *
     * @return mixed
     */
    protected function set_columns(): void {
        $this->add_entity(new program_entity('', 'tp', $this->get_program_excluded_columns()));
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {

        // Progress report icon.
        $reporturl = new moodle_url('/admin/tool/program/usersprogress.php', ['id' => ':id']);
        $reporticon = new pix_icon('bar-chart', get_string('progressreport', 'tool_program'), 'tool_wp');
        $action = new report_action($reporturl, $reporticon, [
            'class' => 'action-icon report_program',
            'data-programid' => ':id'
        ]);
        $action->add_callback(function() {
            return permission::can_view_users_progress($this->lastprogram);
        });
        $this->add_action($action);

        // Restore icon.
        $retoreeurl = new moodle_url('/admin/tool/program/restore.php', ['id' => ':id']);
        $restorestr = get_string('restore', 'tool_program');
        $restoreicon = new pix_icon('arrow-circle-left', $restorestr, 'tool_wp');
        $action = new report_action($retoreeurl, $restoreicon, [
            'class' => 'action-icon restore_program',
            'data-programid' => ':id',
            'data-action' => 'restore',
            'data-archive' => 1
        ]);
        $action->add_callback(function() {
            return permission::can_restore($this->lastprogram);
        });
        $this->add_action($action);

        // Delete icon.
        $deleteurl = new moodle_url('/admin/tool/program/delete.php', ['id' => ':id']);
        $deleteicon = new pix_icon('i/trash', get_string('delete'), 'core');
        $action = new report_action($deleteurl, $deleteicon, [
            'class' => 'action-icon delete_program',
            'data-action' => 'delete',
            'data-programid' => ':id'
        ]);
        $action->add_callback(function() {
            return permission::can_delete($this->lastprogram);
        });
        $this->add_action($action);

    }

    /**
     * Remembers the current program
     *
     * @param \stdClass $row
     */
    public function row_callback(\stdClass $row): void {
        $this->lastprogram = new program(0, $row);
    }

    /**
     * Returns an array with the excluded columns for program_entity.
     *
     * @return array
     */
    private function get_program_excluded_columns(): array {
        return ['fullnamewithimage', 'programimage', 'idnumber', 'tags', 'description', 'startdate', 'duedate', 'enddate',
            'archived', 'allowdirectallocation', 'allocationstartdate', 'allocationenddate', 'visible',
            'timemodified', 'timecreated', 'numbercoursesunique', 'associatedcertifications', 'numbercurrentallocatedusers'];
    }
}
