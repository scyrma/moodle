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
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\reports;

defined('MOODLE_INTERNAL') || die();

use tool_certification\certification;
use tool_certification\permission;
use tool_reportbuilder\db;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use tool_reportbuilder\local\helpers\format;
use context_system;
use moodle_url;
use pix_icon;
use tool_tenant\tenancy;

/**
 * Class active_table
 *
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_certification
 */
class active_table extends system_report {

    /** @var certification */
    protected $lastcertification;

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_main_table('tool_certification', 'ct');
        $this->add_base_condition_simple('ct.archived', 0);
        $this->add_base_condition_simple('ct.tenantid', tenancy::get_tenant_id());
        $this->add_base_join('left join {tool_program} p ON p.id = ct.program');
        $certfields = 'ct.'.join(', ct.', array_diff(array_keys(certification::properties_definition()),
                ['usermodified', 'description']));
        $this->add_base_fields($certfields . ', p.archived as programarchived, '.
            'p.visible as programvisible'); // Necessary for actions and row class.
        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);
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
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function set_columns(): void {
        $this->annotate_entity('tool_certification', new \lang_string('entitycertification', 'tool_certification'));
        $this->annotate_entity('tool_program', new \lang_string('entityprogram', 'tool_program'));

        // Column "name".
        $newcolumn = (new report_column(
            'fullname',
            new \lang_string('name', 'tool_certification'),
            'tool_certification'
        ))
            ->add_fields('ct.fullname, ct.id')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true);
        $newcolumn->add_callback([\tool_certification\local\helpers\format::class, 'inplace_editable']);
        $this->add_column($newcolumn);

        // Column "tags".
        list($tagsql, $tagparams) = db::sql_tag_field('ct', 'tool_certification');

        $newcolumn = (new report_column(
            'tags',
            new \lang_string('tags', 'tool_certification'),
            'tool_certification'
        ))
            ->add_field($tagsql, 'tags', $tagparams)
            ->set_groupby_sql('ct.id')
            ->set_is_default(true, 2)
            ->add_callback([format::class, 'tags_replace_all']);
        $this->add_column($newcolumn);

        // Column "program".
        $newcolumn = (new report_column(
            'program',
            new \lang_string('program', 'tool_certification'),
            'tool_program'
        ))
            ->add_join('left join {tool_program} p ON p.id = ct.program')
            ->add_fields('ct.program, p.id AS programid, p.fullname as programfullname, p.archived as programarchived')
            ->set_is_default(true, 3)
            ->add_callback([\tool_certification\local\helpers\format::class, 'program']);
        $this->add_column($newcolumn);
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
        $usersurl = new moodle_url('/admin/tool/certification/edit.php#!certification_users_tab', ['id' => ':id']);
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