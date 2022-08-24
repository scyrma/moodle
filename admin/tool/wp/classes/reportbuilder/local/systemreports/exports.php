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

namespace tool_wp\reportbuilder\local\systemreports;

use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use core_reportbuilder\system_report;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\filters\{date, select};
use core_reportbuilder\local\report\{action, column, filter};
use tool_tenant\tenancy;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_wp\permission;
use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\helper;
use tool_wp\reportbuilder\local\entities\user;

/**
 * List of all previous exports
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class exports extends system_report {

    /**
     * The name of our internal report entity
     *
     * @return string
     */
    private function get_export_entity_name(): string {
        return 'export';
    }

    /**
     * Initialise system report
     */
    public function initialise(): void {
        global $USER;

        $this->set_main_table('tool_wp_export', 'e');

        if (!permission::can_view_all_exports_imports()) {
            $this->add_base_condition_simple('e.createdby', $USER->id);
        }

        $canviewothertenants = \tool_tenant\permission::can_switch_tenant();
        if (!$canviewothertenants) {
            $this->add_base_condition_simple('e.tenantid', tenancy::get_tenant_id());
        }

        $this->add_base_fields('e.id, e.tenantid, e.createdby, e.status, e.id as url, e.id as importid');

        // User who created the export.
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');
        $this->add_entity($entityuser
            ->add_join("LEFT JOIN {user} {$entityuseralias} ON {$entityuseralias}.id = e.createdby"));

        // Tenant of the export.
        $entitytenant = new tenant();
        $entitytenantalias = $entitytenant->get_table_alias('tool_tenant');
        $this->add_entity($entitytenant
            ->add_join("LEFT JOIN {tool_tenant} {$entitytenantalias} ON {$entitytenantalias}.id = e.tenantid"));

        // Define our internal entity for export elements.
        $this->annotate_entity($this->get_export_entity_name(), new lang_string('exports', 'tool_wp'));

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(true);

        // Default sorting.
        $this->set_initial_sort_column($this->get_export_entity_name() . ':date', SORT_DESC);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * This is necessary to implement here and not on the page that embeds the system report
     * because second and consequtive pages of the report are rendered via web services.
     */
    public function can_view(): bool {
        return permission::can_use_export_import();
    }

    /**
     * Return a list of available exporters, keyed on their class name
     *
     * @return array
     */
    public function get_exporters(): array {
        $result = [];

        $exporters = helper::get_all_exporters();
        foreach ($exporters as $exporter) {
            $result[get_class($exporter)] = $exporter->get_name();
        }

        \core_collator::asort($result);

        return $result;
    }

    /**
     * Formatter for the 'exporter' column
     *
     * @param string $value
     * @param \stdClass $row
     * @return string
     */
    public function format_exporter($value, $row) {
        return helper::format_exporter($value, $row);
    }

    /**
     * Formatter for the 'status' column
     *
     * @param int $value
     * @return string
     */
    public static function format_status($value): string {
        $statusstr = helper::format_export_import_status((int)$value);
        return helper::get_export_import_status_formatted($statusstr);
    }

    /**
     * Formatter for the 'size' column
     *
     * @param int $value
     * @return string
     */
    public static function format_size($value): string {
        $exportfile = helper::get_export_file($value);
        return $exportfile ? display_size($exportfile->get_filesize()) : '';
    }

    /**
     * Add report columns
     */
    protected function add_columns(): void {
        $maintablealias = $this->get_main_table_alias();

        $this->add_column((new column(
            'date',
            new lang_string('date'),
            $this->get_export_entity_name()
        ))
            ->add_field("{$maintablealias}.timemodified")
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshort'))
            ->set_is_sortable(true)
        );

        // Retrieve user/tenant columns from respective entities.
        $this->add_columns_from_entities([
            'user:fullnamewithlink',
            'tenant:name',
        ]);

        $this->add_column((new column(
            'exporter',
            new lang_string('exporter', 'tool_wp'),
            $this->get_export_entity_name()
        ))
            ->add_fields("{$maintablealias}.exporter, {$maintablealias}.entrypoint, {$maintablealias}.entrypointid")
            ->set_callback([$this, 'format_exporter'])
        );

        $this->add_column((new column(
            'status',
            new lang_string('exportstatus', 'tool_wp'),
            $this->get_export_entity_name()
        ))
            ->add_fields("{$maintablealias}.status")
            ->set_callback([$this, 'format_status'])
        );

        $this->add_column((new column(
            'size',
            new lang_string('size'),
            $this->get_export_entity_name()
        ))
            ->add_field("{$maintablealias}.id", 'size')
            ->set_callback([$this, 'format_size'])
        );
    }

    /**
     * Add report actions
     */
    private function add_actions(): void {

        // Import from export file.
        $this->add_action((new action(
            new moodle_url('/admin/tool/wp/import.php', ['exportid' => ':id']),
            new pix_icon('play-circle', '', 'tool_wp'),
            [],
            false,
            new lang_string('importfromfile', 'tool_wp')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return $row->status == helper::STATUS_DONE;
            })
        );

        // Download export file.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/download', ''),
            ['data-action' => 'download', 'data-url' => ':url'],
            false,
            new lang_string('download')
        ))
            ->add_callback(static function(stdClass $row): bool {
                $url = export_manager::get_export_file_url($row->id);
                $row->url = $url ? $url->out(false) : '';
                return $row->status == helper::STATUS_DONE;
            })
        );

        // View report.
        $this->add_action((new action(
            helper::export_url(':id'),
            new pix_icon('bar-chart', '', 'tool_wp'),
            ['data-action' => 'log', 'data-id' => ':id'],
            false,
            new lang_string('viewexport', 'tool_wp')
        )));

        // Delete export.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/trash', ''),
            ['data-action' => 'delete', 'data-id' => ':id'],
            false,
            new lang_string('delete')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return permission::can_delete_export($row->id);
            })
        );
    }

    /**
     * Add report filters
     */
    private function add_filters(): void {
        $tablealias = $this->get_main_table_alias();

        // Filter by tenant.
        $this->add_filter_from_entity('tenant:namewithempty')
            ->set_header(new lang_string('name', 'tool_tenant'));

        // Filter by exporter.
        $this->add_filter((new filter(
            select::class,
            'exporter',
            new lang_string('exporter', 'tool_wp'),
            $this->get_export_entity_name(),
            "{$tablealias}.exporter"
        ))
            ->set_options_callback([$this, 'get_exporters'])
        );

        // Filter by date.
        $this->add_filter((new filter(
            date::class,
            'date',
            new lang_string('date'),
            $this->get_export_entity_name(),
            "{$tablealias}.timemodified"
        ))
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_LAST,
                date::DATE_CURRENT,
                date::DATE_RANGE,
            ])
        );

        // Filter by status.
        $this->add_filter((new filter(
            select::class,
            'status',
            new lang_string('exportstatus', 'tool_wp'),
            $this->get_export_entity_name(),
            "{$tablealias}.status"
        ))
            ->set_options_callback([helper::class, 'get_status_list'])
        );
    }
}
