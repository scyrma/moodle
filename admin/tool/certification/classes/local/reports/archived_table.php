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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Class for define the system report of the archived certifications.
 *
 * @package    tool_certification
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\reports;

use tool_certification\certification;
use tool_certification\local\helpers\certification_entity;
use tool_certification\permission;
use tool_reportbuilder\report_action;
use tool_reportbuilder\system_report;
use moodle_url;
use pix_icon;
use tool_tenant\hierarchy;

/**
 * Class archived_table
 *
 * @package    tool_certification
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class archived_table extends system_report {

    /** @var certification */
    protected $lastcertification;

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_main_table('tool_certification', 'tc', false);
        $this->add_base_condition_simple('tc.archived', 1);

        // Tenant condition.
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tc.tenantid', 'tc.shared=1');
        $this->add_base_condition_sql($sql, $params);

        $certfields = 'tc.'.join(', tc.', array_diff(array_keys(certification::properties_definition()),
                ['usermodified', 'description']));
        $this->add_base_fields($certfields); // Necessary for actions.
        $this->add_actions();
        $this->set_downloadable(false);

        // Default columns.
        if ($column = $this->get_column('tool_certification:fullname')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
            $column->set_callback([$this, 'fullname']);
        }
        if ($column = $this->get_column('tool_certification:timearchived')) {
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
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('reportarchivedcerts', 'tool_certification');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns(): void {
        $this->add_entity(new certification_entity());
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
            'class' => 'report_certification',
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
            'class' => 'restore_certification',
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
            'class' => 'delete_certification',
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

    /**
     * Certification name with 'shared badge'.
     *
     * @return string
     */
    public function fullname(): string {
        $certification = $this->lastcertification;

        $badge = '';
        if (in_array($certification->get('tenantid'), hierarchy::get_parent_tenants_ids())) {
            $badge = ' ' . \html_writer::span(get_string('sharedspace', 'tool_tenant'),
                    'badge badge-secondary');
        }

        return format_string($certification->get('fullname'), true, ['escape' => false]) . $badge;
    }
}
