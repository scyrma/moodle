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

use core\output\inplace_editable;
use moodle_url;
use pix_icon;
use stdClass;
use tool_program\local\helpers\program_entity;
use tool_program\permission;
use tool_program\persistent\program;
use tool_reportbuilder\report_action;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;

/**
 * Class active_table
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_program
 */
class active_programs_report extends system_report {

    /** @var program */
    protected $lastprogram;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_columns();
        $this->set_main_table('tool_program', 'tp');
        $this->add_base_condition_simple('tp.archived', 0);
        $this->add_base_condition_simple('tp.tenantid', tenancy::get_tenant_id());
        // Fields necessary for actions and row class - we need all fields except description.
        $this->add_base_fields('tp.'.join(', tp.', array_diff(array_keys(program::properties_definition()),
                ['usermodified', 'description'])));
        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);

        // Default columns.
        if ($column = $this->get_column('tool_program:fullname')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
            $column->set_callback([$this, 'fullnameeditable']);
        }
        if ($column = $this->get_column('tool_program:tags')) {
            $column->set_is_default(true, 2);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('tool_program:associatedcertificationswithlink')) {
            $column->set_is_default(true, 3);
            $column->set_is_sortable(true, true);
            $column->set_visiblename(new \lang_string('associatedcertifications', 'tool_program'));
        }
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_list();
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportactiveprograms', 'tool_program');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns(): void {
        $this->add_entity(new program_entity('', 'tp', $this->get_program_excluded_columns()));
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {

        // Edit content icon.
        $editurl = new moodle_url('/admin/tool/program/edit.php', ['id' => ':id']);
        $editicon = new pix_icon('t/right', get_string('editcontent', 'tool_program'), 'core');
        $action = new report_action($editurl, $editicon, [
            'class' => 'action-icon edit_program',
            'data-programid' => ':id'
        ]);
        $action->add_callback(function() {
            return permission::can_edit_details($this->lastprogram);
        });
        $this->add_action($action);

        // Edit details icon.
        $icon = new pix_icon('i/settings', get_string('editdetails', 'tool_program'), 'core');
        $action = (new report_action(new moodle_url('#'), $icon, [
            'class' => 'action-icon edit_details',
            'data-action' => 'editdetails',
            'data-id' => ':id'
        ]))
            ->add_callback(function($row) {
                $row->fullname = format_string($row->fullname, true, ['escape' => false]);
                return permission::can_edit_details($this->lastprogram);
            });
        $this->add_action($action);

        // Duplicate icon.
        $duplicateurl = new moodle_url('/admin/tool/program/duplicate.php', ['id' => ':id']);
        $duplicatestr = get_string('duplicate', 'tool_program');
        $duplicaticon = new pix_icon('e/manage_files', $duplicatestr, 'core');
        $action = new report_action($duplicateurl, $duplicaticon, [
            'class' => 'action-icon duplicate_program',
            'data-action' => 'duplicate',
            'data-programid' => ':id'
        ]);
        $action->add_callback(function() {
            return permission::can_duplicate($this->lastprogram);
        });
        $this->add_action($action);

        // Show icon.
        $visibilityurl = new moodle_url('/admin/tool/program/visibility.php', ['id' => ':id']);
        $visibilityiconshow = new pix_icon('i/show', get_string('show'), 'core');
        $action = new report_action($visibilityurl, $visibilityiconshow, [
            'class' => 'action-icon update_visibility',
            'data-programid' => ':id',
            'data-action' => 'updatevisibility',
            'data-visibility' => '1'
        ]);
        $action->add_callback(function() {
            return !$this->lastprogram->get('visible') && permission::can_edit_details($this->lastprogram);
        });
        $this->add_action($action);

        // Hide icon.
        $visibilityiconhide = new pix_icon('i/hide', get_string('hide'), 'core');
        $action = new report_action($visibilityurl, $visibilityiconhide, [
            'class' => 'action-icon update_visibility',
            'data-programid' => ':id',
            'data-action' => 'updatevisibility',
            'data-visibility' => '0'
        ]);
        $action->add_callback(function() {
            return $this->lastprogram->get('visible') && permission::can_edit_details($this->lastprogram);
        });
        $this->add_action($action);

        // Allocate icon.
        $usersurl = new moodle_url('/admin/tool/program/edit.php#!program_users_tab', ['id' => ':id']);
        $str = get_string('allocateusers', 'tool_program');
        $usericon = new pix_icon('i/enrolusers', $str, 'core');
        $action = new report_action($usersurl, $usericon, [
            'class' => 'action-icon allocate_users',
            'data-programid' => ':id',
            'data-action' => 'allocateusers',
        ]);
        $action->add_callback(function() {
            return permission::can_allocate_anybody($this->lastprogram);
        });
        $this->add_action($action);

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

        // Archive icon.
        $archiveurl = new moodle_url('/admin/tool/program/archive.php', ['id' => ':id']);
        $archivestr = get_string('archive', 'tool_program');
        $archiveicon = new pix_icon('archive', $archivestr, 'tool_wp');
        $action = new report_action($archiveurl, $archiveicon, [
            'class' => 'action-icon archive_program',
            'data-programid' => ':id',
            'data-action' => 'archive',
            'data-archive' => ':archived'
        ]);
        $action->add_callback(function() {
            return permission::can_archive($this->lastprogram);
        });
        $this->add_action($action);
    }

    /**
     * CSS class for the row
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        return (!$row->visible) ? 'dimmed_text' : '';
    }

    /**
     * Remembers the current program
     *
     * @param stdClass $row
     */
    public function row_callback(\stdClass $row): void {
        $this->lastprogram = new program(0, $row);
    }

    /**
     * Program name with inplace editable.
     *
     * @return string
     */
    public function fullnameeditable(): string {
        global $OUTPUT;
        $program = $this->lastprogram;
        $value = $program->get('fullname');
        $edithint = get_string('editprogramname', 'tool_program');
        $displayvalue = format_string($value);
        $url = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
        $editlabel = get_string('newvaluefor', 'form', $displayvalue);
        $displayvalue = \html_writer::link($url, $displayvalue);
        $editable = permission::can_edit_details($program);
        $inlineeditable = new inplace_editable('tool_program', 'programname', $program->get('id'), $editable,
            $displayvalue, $value, $edithint, $editlabel);

        return $OUTPUT->render($inlineeditable);
    }

    /**
     * Returns an array with the excluded columns for program_entity.
     *
     * @return array
     */
    private function get_program_excluded_columns(): array {
        return ['fullnamewithimage', 'programimage', 'idnumber', 'description', 'startdate', 'duedate', 'enddate',
            'archived', 'allowdirectallocation', 'allocationstartdate', 'allocationenddate', 'visible',
            'timemodified', 'timecreated', 'numbercoursesunique', 'associatedcertifications', 'numbercurrentallocatedusers'];
    }
}
