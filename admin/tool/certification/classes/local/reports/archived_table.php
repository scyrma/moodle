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
 * Class for define the system report of the archived certifications.
 *
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\reports;

defined('MOODLE_INTERNAL') || die();

use tool_certification\certification;
use tool_certification\permission;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use context_system;
use moodle_url;
use pix_icon;
use tool_tenant\tenancy;

/**
 * Class archived_table
 *
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_certification
 */
class archived_table extends system_report {

    /** @var certification */
    protected $lastcertification;

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_main_table('tool_certification', 'ct');
        $this->add_base_condition_simple('ct.archived', 1);
        $certfields = 'ct.'.join(', ct.', array_diff(array_keys(certification::properties_definition()),
                ['usermodified', 'description']));
        $this->add_base_fields($certfields); // Necessary for actions.
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
        return permission::can_view_archived_list();
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('reportarchivedcerts', 'tool_certification');
    }

    /**
     * Set the columns for the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function set_columns(): void {
        $this->annotate_entity('tool_certification', new \lang_string('entitycertification', 'tool_certification'));

        // Column "name".
        $newcolumn = (new report_column(
            'fullname',
            new \lang_string('name', 'tool_certification'),
            'tool_certification'
        ))
            ->add_field('ct.fullname')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true)
            ->add_callback([\tool_reportbuilder\local\helpers\format::class, 'format_string']);
        $this->add_column($newcolumn);

        // Column "timearchived".
        $newcolumn = (new report_column(
            'timearchived',
            new \lang_string('archivedon', 'tool_certification'),
            'tool_certification'
        ))
            ->add_field('ct.timearchived')
            ->set_is_default(true, 1)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);
    }

    /**
     * Set the actions icons of the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function add_actions(): void {

        // Progress report icon.
        $reporturl = new moodle_url('/admin/tool/certification/progress.php', ['id' => ':id']);
        $reportstr = get_string('progressreport', 'tool_certification');
        $reporticon = new pix_icon('bar-chart', $reportstr, 'tool_wp');
        $action = new report_action($reporturl, $reporticon, [
            'class' => 'action-icon report_certification',
            'data-certificationid' => ':id'
        ]);
        $action->add_callback(function() {
            return permission::can_view_users_progress($this->lastcertification);
        });
        $this->add_action($action);

        // Restore icon.
        $retoreeurl = new moodle_url('/admin/tool/certification/restore.php', ['id' => ':id']);
        $restorestr = get_string('restore', 'tool_certification');
        $restoreicon = new pix_icon('arrow-circle-left', $restorestr, 'tool_wp');
        $action = new report_action($retoreeurl, $restoreicon, [
            'class' => 'action-icon restore_certification',
            'data-action' => 'restore',
            'data-certificationid' => ':id',
            'data-archive' => 1
        ]);
        $action->add_callback(function() {
            return permission::can_restore($this->lastcertification);
        });
        $this->add_action($action);

        // Delete icon.
        $deleteurl = new moodle_url('/admin/tool/certification/delete.php', ['id' => ':id']);
        $deleteicon = new pix_icon('i/trash', get_string('delete'), 'core');
        $action = new report_action($deleteurl, $deleteicon, [
            'class' => 'action-icon delete_certification',
            'data-action' => 'delete',
            'data-certificationid' => ':id'
        ]);
        $action->add_callback(function() {
            return permission::can_delete($this->lastcertification);
        });
        $this->add_action($action);
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