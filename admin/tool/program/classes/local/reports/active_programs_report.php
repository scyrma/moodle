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
 * Class for define the system report of the archived programs.
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
use tool_tenant\hierarchy;
use tool_tenant\tenancy;
use tool_wp\db;

/**
 * Class active_table
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * @package   tool_program
 */
class active_programs_report extends system_report {

    /** @var program */
    protected $lastprogram;
    /** @var bool $lastbelongstocertification */
    protected $lastbelongstocertification;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_columns();
        $this->set_main_table('tool_program', 'tp', false);
        $this->add_base_condition_simple('tp.archived', 0);

        // Tenant condition.
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tp.tenantid', 'tp.shared=1');
        $this->add_base_condition_sql($sql, $params);

        // Fields necessary for actions and row class - we need all fields except description.
        // Nedeed to get certification program associated to check archive action because we cannot archive a program if.
        // It is associated with a certification.
        $c = \tool_wp\db::generate_alias();
        $sql = "(SELECT COUNT($c.id) FROM {tool_certification} $c WHERE $c.program = tp.id AND $c.archived = 0)
        AS certificationcount";
        $this->add_base_fields('tp.'.join(', tp.', array_diff(array_keys(program::properties_definition()),
                ['usermodified', 'description'])) . ", $sql");

        $this->add_actions();
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
        $this->add_entity(new program_entity());
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {

        // Edit content icon.
        $editurl = new moodle_url('/admin/tool/program/edit.php', ['id' => ':id']);
        $editicon = new pix_icon('t/right', get_string('editcontent', 'tool_program'), 'core');
        $action = new report_action($editurl, $editicon, [
            'class' => 'edit_program',
            'data-programid' => ':id'
        ]);
        $action->add_callback(function() {
            return permission::can_edit_details($this->lastprogram);
        });
        $this->add_action($action);

        // Edit details icon.
        $icon = new pix_icon('i/settings', get_string('editdetails', 'tool_program'), 'core');
        $action = (new report_action(new moodle_url('#'), $icon, [
            'class' => 'edit_details',
            'data-action' => 'editdetails',
            'data-id' => ':id',
            'data-name' => ':fullname',
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
            'class' => 'duplicate_program',
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
            'class' => 'update_visibility',
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
            'class' => 'update_visibility',
            'data-programid' => ':id',
            'data-action' => 'updatevisibility',
            'data-visibility' => '0'
        ]);
        $action->add_callback(function() {
            return $this->lastprogram->get('visible') && permission::can_edit_details($this->lastprogram);
        });
        $this->add_action($action);

        // Users icon.
        $usersurl = new moodle_url('/admin/tool/program/edit.php#program_users_tab', ['id' => ':id']);
        $str = get_string('users', 'tool_program');
        $usericon = new pix_icon('i/users', $str, 'core');
        $action = new report_action($usersurl, $usericon, ['class' => 'view_users']);
        $action->add_callback(function() {
            return permission::can_view_allocated_users($this->lastprogram);
        });
        $this->add_action($action);

        // Progress report icon.
        $reporturl = new moodle_url('/admin/tool/program/usersprogress.php', ['id' => ':id']);
        $reporticon = new pix_icon('bar-chart', get_string('progressreport', 'tool_program'), 'tool_wp');
        $action = new report_action($reporturl, $reporticon, [
            'class' => 'report_program',
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
            'class' => 'archive_program',
            'data-programid' => ':id',
            'data-name' => ':fullname',
            'data-action' => 'archive',
            'data-archive' => ':archived'
        ]);
        $action->add_callback(function() {
            return permission::can_archive($this->lastprogram, true, $this->lastbelongstocertification);
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
        $this->lastbelongstocertification = $row->certificationcount > 0;
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
        $displayvalue = format_string($value, true, ['escape' => false]);
        $url = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
        $editlabel = get_string('newvaluefor', 'form', $displayvalue);
        $displayvalue = \html_writer::link($url, $displayvalue);
        $editable = permission::can_edit_details($program);
        $inlineeditable = new inplace_editable('tool_program', 'programname', $program->get('id'), $editable,
            $displayvalue, $value, $edithint, $editlabel);

        // Display the "Shared space" badge if applicable.
        $badge = '';
        if (in_array($program->get('tenantid'), hierarchy::get_parent_tenants_ids())) {
            $badge = ' ' . \html_writer::span(get_string('sharedspace', 'tool_tenant'),
                    'badge badge-secondary');
        }

        // If user has only permission to allocate users and program is hidden, show hidden icon next to program name.
        $eyeslashicon = '';
        if (!$editable && !$program->get('visible')) {
            $str = get_string('hidden', 'tool_program');
            $eyeslashicon = \html_writer::span($str, '', ['class' => 'badge badge-warning ml-2']);
        }

        return $OUTPUT->render($inlineeditable) . $eyeslashicon . $badge;
    }
}
