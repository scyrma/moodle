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

declare(strict_types=1);

namespace tool_program\reportbuilder\local\systemreports;

use core\output\inplace_editable;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use html_writer;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\formatters\program as programformatter;
use tool_program\persistent\program as programpersistent;
use tool_program\permission;
use tool_tenant\hierarchy;

/**
 * Active programs system report implementation
 *
 * @package   tool_program
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class active_programs extends system_report {

    /** @var programpersistent */
    protected $lastprogram;
    /** @var bool $lastbelongstocertification */
    protected $lastbelongstocertification;

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        // Our main entity, it contains all of the column definitions that we need.
        $entitymain = new program();
        $entitymainalias = $entitymain->get_table_alias('tool_program');

        $this->set_main_table('tool_program', $entitymainalias);
        $this->add_entity($entitymain);

        // Show only non-archived programs.
        $this->add_base_condition_simple("{$entitymainalias}.archived", 0);

        // Tenant condition.
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql("{$entitymainalias}.tenantid",
            "{$entitymainalias}.shared=1");
        $this->add_base_condition_sql($sql, $params);

        // Any columns required by actions should be defined here to ensure they're always available.
        $c = database::generate_alias();
        $sql = "(SELECT COUNT($c.id) FROM {tool_certification} $c WHERE $c.program = {$entitymainalias}.id AND $c.archived = 0)
        AS certificationcount";
        $this->add_base_fields("{$entitymainalias}." . implode(", {$entitymainalias}.",
                array_diff(array_keys(programpersistent::properties_definition()),
                ['usermodified', 'description'])) . ", $sql");

        $this->add_columns($entitymainalias);
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(false);
        $this->set_initial_sort_column('program:fullname', SORT_ASC);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_list();
    }

    /**
     * Adds the columns we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     *
     * @param string $entitymainalias
     */
    public function add_columns(string $entitymainalias): void {
        $columns = [
            'program:fullname',
            'program:tags',
            'program:associatedcertifications',
        ];

        $this->add_columns_from_entities($columns);

        // Add inplaceeditable, visible icon and shared badge (if needed) to program fullname column.
        if ($column = $this->get_column('program:fullname')) {
            $column
                ->set_callback([$this, 'fullnameeditable'])
                ->add_field("{$entitymainalias}.tenantid")
                ->add_callback([programformatter::class, 'append_shared_badge']);
        }
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_filters(): void {
        // TODO WP-3147 WP-3702 add 'tool_program:tags'.
        $filters = [
            'program:fullname',
            'program:certification',
        ];

        $this->add_filters_from_entities($filters);
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     *
     * Note the use of ":id" placeholder which will be substituted according to actual values in the row
     */
    protected function add_actions(): void {
        // Action to duplicate program.
        $this->add_action((new action(
            new moodle_url('#', ['id' => ':id']),
            new pix_icon('e/manage_files', '', 'core'),
            [
                'data-action' => 'duplicate',
                'data-programid' => ':id',
            ],
            false,
            new lang_string('duplicate', 'tool_program')
        ))->add_callback(function() {
            return permission::can_duplicate($this->lastprogram);
        }));

        // Action to show program.
        $this->add_action((new action(
            new moodle_url('#', ['id' => ':id']),
            new pix_icon('i/hide', '', 'core'),
            [
                'data-programid' => ':id',
                'data-action' => 'updatevisibility',
                'data-visibility' => '1',
            ],
            false,
            new lang_string('show')
        ))->add_callback(function() {
            return !$this->lastprogram->get('visible') && permission::can_edit_details($this->lastprogram);
        }));

        // Action to hide program.
        $this->add_action((new action(
            new moodle_url('#', ['id' => ':id']),
            new pix_icon('i/show', '', 'core'),
            [
                'data-programid' => ':id',
                'data-action' => 'updatevisibility',
                'data-visibility' => '0',
            ],
            false,
            new lang_string('hide')
        ))->add_callback(function() {
            return $this->lastprogram->get('visible') && permission::can_edit_details($this->lastprogram);
        }));

        // Action to archive program.
        $this->add_action((new action(
            new moodle_url('#', ['id' => ':id']),
            new pix_icon('archive', '', 'tool_wp'),
            [
                'data-programid' => ':id',
                'data-name' => ':fullname',
                'data-action' => 'archive',
                'data-archive' => ':archived',
            ],
            false,
            new lang_string('archive', 'tool_program')
        ))->add_callback(function() {
            return permission::can_archive($this->lastprogram, true, $this->lastbelongstocertification);
        }));

        // Action to open user allocations report.
        $this->add_action((new action(
            new moodle_url('/admin/tool/program/edit.php', ['id' => ':id'], 'program_users_tab'),
            new pix_icon('i/users', '', 'core'),
            [],
            false,
            new lang_string('users', 'tool_program')
        ))->add_callback(function() {
            return permission::can_view_allocated_users($this->lastprogram);
        }));

        // Action to open progress report.
        $this->add_action((new action(
            new moodle_url('/admin/tool/program/usersprogress.php', ['id' => ':id']),
            new pix_icon('bar-chart', '', 'tool_wp'),
            [
                'data-programid' => ':id',
            ],
            false,
            new lang_string('progressreport', 'tool_program')
        ))->add_callback(function() {
            return permission::can_view_users_progress($this->lastprogram);
        }));
    }

    /**
     * Remembers the current program
     *
     * @param stdClass $row
     */
    public function row_callback(stdClass $row): void {
        $this->lastprogram = new programpersistent(0, $row);
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
        $displayvalue = $program->get_formatted_name();
        $url = new moodle_url('/admin/tool/program/edit.php', ['id' => $program->get('id')]);
        $editlabel = get_string('newvaluefor', 'form', $displayvalue);
        $displayvalue = html_writer::link($url, $displayvalue);
        $editable = permission::can_edit_details($program);
        $inlineeditable = new inplace_editable('tool_program', 'programname', $program->get('id'), $editable,
            $displayvalue, $value, $edithint, $editlabel);

        // If user has only permission to allocate users and program is hidden, show hidden icon next to program name.
        $hiddenicon = '';
        if (!$editable && !$program->get('visible')) {
            $str = get_string('hidden', 'tool_program');
            $hiddenicon = html_writer::span($str, '', ['class' => 'badge badge-warning ml-2']);
        }

        return $OUTPUT->render($inlineeditable) . $hiddenicon;
    }

    /**
     * CSS classes to add to the row
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        return !$row->visible ? 'text-muted' : '';
    }
}
