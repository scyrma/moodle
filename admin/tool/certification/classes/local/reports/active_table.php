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
 * Class for define the system report of the active certifications.
 *
 * @package    tool_certification
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\reports;

defined('MOODLE_INTERNAL') || die();

use tool_certification\certification;
use tool_certification\local\helpers\certification_entity;
use tool_certification\permission;
use tool_program\local\helpers\program_entity;
use tool_reportbuilder\report_action;
use tool_reportbuilder\system_report;
use context_system;
use moodle_url;
use pix_icon;
use tool_tenant\tenancy;

/**
 * Class active_table
 *
 * @package    tool_certification
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class active_table extends system_report {

    /** @var certification */
    protected $lastcertification;

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_main_table('tool_certification', 'tc');
        $this->add_base_condition_simple('tc.archived', 0);
        $this->add_base_condition_simple('tc.tenantid', tenancy::get_tenant_id());
        $this->add_base_join('left join {tool_program} tp ON tp.id = tc.program');
        $certfields = 'tc.'.join(', tc.', array_diff(array_keys(certification::properties_definition()),
                ['usermodified', 'description']));
        $this->add_base_fields($certfields . ', tp.archived as programarchived, '.
            'tp.visible as programvisible'); // Necessary for actions and row class.
        $this->add_actions();
        $this->set_downloadable(false);

        // Default columns.
        if ($column = $this->get_column('tool_certification:fullname')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
            $column->add_field('tc.id');
            $column->set_callback([\tool_certification\local\helpers\format::class, 'inplace_editable']);
        }
        if ($column = $this->get_column('tool_certification:tags')) {
            $column->set_is_default(true, 2);
        }
        if ($column = $this->get_column('tool_program:fullname')) {
            $column->set_is_default(true, 3);
            $column->add_fields('tc.program, tp.id AS programid, tp.fullname as programfullname, tp.archived as programarchived');
            $column->set_callback([\tool_certification\local\helpers\format::class, 'program']);
        }
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_list(context_system::instance());
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name(): string {
        return get_string('reportactivecerts', 'tool_certification');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns(): void {
        $this->add_entity(new certification_entity());

        $this->add_entity(new program_entity());
    }

    /**
     * Set the actions icons of the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function add_actions(): void {
        // Edit content.
        $editurl = new moodle_url('/admin/tool/certification/edit.php', ['id' => ':id']);
        $editicon = new pix_icon('t/right', get_string('editcontent', 'tool_certification'), 'core');
        $action = new report_action($editurl, $editicon, [
            'class' => 'action-icon edit_certification',
            'data-certificationid' => ':id'
        ]);
        $action->add_callback(function() {
            return permission::can_edit_details($this->lastcertification);
        });
        $this->add_action($action);

        // Edit details.
        $icon = new pix_icon('i/settings', get_string('editdetails', 'tool_certification'), 'core');
        $action = (new report_action(new moodle_url('#'), $icon, [
            'class' => 'action-icon edit_details',
            'data-action' => 'editdetails',
            'data-id' => ':id'
        ]))
            ->add_callback(function($row) {
                $row->fullname = format_string($row->fullname, true, ['escape' => false]);
                return permission::can_edit_details($this->lastcertification);
            });
        $this->add_action($action);

        // Only users with edit details capability can duplicate.
        $duplicateurl = new moodle_url('/admin/tool/certification/duplicate.php', ['id' => ':id']);
        $duplicatestr = get_string('duplicate', 'tool_certification');
        $duplicaticon = new pix_icon('e/manage_files', $duplicatestr, 'core');
        $action = new report_action($duplicateurl, $duplicaticon, [
            'class' => 'action-icon duplicate_certification',
            'data-action' => 'duplicate',
            'data-certificationid' => ':id'
        ]);
        $action->add_callback(function() {
            return permission::can_duplicate($this->lastcertification);
        });
        $this->add_action($action);

        // Go to allocate users tab.
        $usersurl = new moodle_url('/admin/tool/certification/edit.php#certification_users_tab', ['id' => ':id']);
        $str = get_string('allocateusers', 'tool_certification');
        $usericon = new pix_icon('i/enrolusers', $str, 'core');
        $action = new report_action($usersurl, $usericon, [
            'class' => 'action-icon allocate_users',
            'data-certificationid' => ':id',
            'data-action' => 'allocateusers',
        ]);
        $action->add_callback(function() {
            return permission::can_allocate_anybody($this->lastcertification);
        });
        $this->add_action($action);

        // Progress report.
        $reporturl = new moodle_url('/admin/tool/certification/progress.php', ['id' => ':id']);
        $reporticon = new pix_icon('bar-chart', get_string('progressreport', 'tool_certification'), 'tool_wp');
        $action = new report_action($reporturl, $reporticon, [
            'class' => 'action-icon report_certification',
            'data-certificationid' => ':id'
        ]);
        $action->add_callback(function() {
            return permission::can_view_users_progress($this->lastcertification);
        });
        $this->add_action($action);

        // Only users with edit details capability can archive.
        $archiveurl = new moodle_url('/admin/tool/certification/archive.php', ['id' => ':id']);
        $archivestr = get_string('archive', 'tool_certification');
        $archiveicon = new pix_icon('archive', $archivestr, 'tool_wp');
        $action = new report_action($archiveurl, $archiveicon, [
            'class' => 'action-icon archive_certification',
            'data-action' => 'archive',
            'data-certificationid' => ':id',
            'data-archive' => ':archived'
        ]);
        $action->add_callback(function() {
            return permission::can_archive($this->lastcertification);
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
        return (!$row->program || $row->programarchived || !$row->programvisible) ? 'dimmed_text' : '';
    }

    /**
     * Executed before each row
     *
     * @param \stdClass $row
     */
    public function row_callback(\stdClass $row): void {
        $this->lastcertification = new certification(0, $row);
    }
}