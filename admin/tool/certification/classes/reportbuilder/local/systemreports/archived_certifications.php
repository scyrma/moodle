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

namespace tool_certification\reportbuilder\local\systemreports;

use context_system;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use tool_certification\certification as certificationpersistent;
use tool_certification\permission;
use tool_certification\reportbuilder\local\entities\certification;
use tool_certification\reportbuilder\local\formatters\certification as certificationformatter;
use tool_tenant\hierarchy;

/**
 * Archived certifications system report implementation
 *
 * @package   tool_certification
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class archived_certifications extends system_report {

    /** @var certificationpersistent */
    protected $lastcertification;

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        // Our main entity, it contains all of the column definitions that we need.
        $entitymain = new certification();
        $entitymainalias = $entitymain->get_table_alias('tool_certification');

        $this->set_main_table('tool_certification', $entitymainalias);
        $this->add_entity($entitymain);

        // Show only archived certifications.
        $this->add_base_condition_simple("{$entitymainalias}.archived", 1);

        // Tenant condition to show tenant certifications and certifications from Shared space.
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql("{$entitymainalias}.tenantid",
            "{$entitymainalias}.shared=1");
        $this->add_base_condition_sql($sql, $params);

        // Any columns required by actions should be defined here to ensure they're always available.
        $this->add_base_fields("{$entitymainalias}." . implode(", {$entitymainalias}.",
                array_diff(array_keys(certificationpersistent::properties_definition()), ['usermodified', 'description'])));

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        // Add Shared space badge (if needed) to certification fullname column.
        if ($column = $this->get_column('certification:fullname')) {
            $column
                ->add_field("{$entitymainalias}.tenantid")
                ->add_callback([certificationformatter::class, 'append_shared_badge']);
        }

        $this->set_downloadable(false);
        $this->set_initial_sort_column('certification:fullname', SORT_ASC);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_archived_list(context_system::instance());
    }

    /**
     * Adds the columns we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    public function add_columns(): void {
        $columns = [
            'certification:fullname',
            'certification:timearchived',
        ];

        $this->add_columns_from_entities($columns);
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_filters(): void {
        $filters = [
            'certification:fullname',
            'certification:timearchived',
        ];

        $this->add_filters_from_entities($filters);
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     *
     * Note the use of ":id" placeholder which will be substituted according to actual values in the row
     */
    protected function add_actions(): void {
        // Action to open progress report.
        $this->add_action((new action(
            new moodle_url('/admin/tool/certification/progress.php', ['id' => ':id']),
            new pix_icon('bar-chart', '', 'tool_wp'),
            [],
            false,
            new lang_string('progressreport', 'tool_certification')
        ))->add_callback(function() {
            return permission::can_view_users_progress($this->lastcertification);
        }));

        // Action to restore certification.
        $this->add_action((new action(
            new moodle_url('#', ['id' => ':id']),
            new pix_icon('arrow-circle-left', '', 'tool_wp'),
            [
                'data-certificationid' => ':id',
                'data-action' => 'restore',
            ],
            false,
            new lang_string('restore', 'tool_certification')
        ))->add_callback(function() {
            return permission::can_restore($this->lastcertification);
        }));

        // Action to delete certification.
        $this->add_action((new action(
            new moodle_url('#', ['id' => ':id']),
            new pix_icon('i/trash', '', 'core'),
            [
                'data-certificationid' => ':id',
                'data-name' => ':fullname',
                'data-action' => 'delete',
            ],
            false,
            new lang_string('delete')
        ))->add_callback(function() {
            return permission::can_delete($this->lastcertification);
        }));
    }

    /**
     * Executed before each row
     *
     * @param stdClass $row
     */
    public function row_callback(stdClass $row): void {
        $this->lastcertification = new certificationpersistent(0, $row);
    }
}
