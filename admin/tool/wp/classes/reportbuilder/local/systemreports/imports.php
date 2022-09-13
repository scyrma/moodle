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
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\filters\{date, select};
use core_reportbuilder\local\report\{action, column, filter};
use tool_tenant\tenancy;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_wp\permission;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\helper;
use core_reportbuilder\local\entities\user;

/**
 * System report that shows all previous imports
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class imports extends system_report {

    /**
     * The name of our internal report entity
     *
     * @return string
     */
    private function get_import_entity_name(): string {
        return 'import';
    }

    /**
     * Initialise system report
     */
    public function initialise(): void {
        global $USER;

        $this->set_main_table('tool_wp_import', 'e');

        $p = database::generate_param_name();
        $this->add_base_condition_sql('e.status <> :'.$p, [$p => helper::STATUS_CREATED]);

        if (!permission::can_view_all_exports_imports()) {
            $this->add_base_condition_simple('e.createdby', $USER->id);
        }

        $canviewothertenants = \tool_tenant\permission::can_switch_tenant();
        if (!$canviewothertenants) {
            $this->add_base_condition_simple('e.tenantid', tenancy::get_tenant_id());
        }

        $this->add_base_fields('e.id, e.tenantid, e.createdby, e.status, e.id as url');

        // User who created the import.
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');
        $this->add_entity($entityuser
            ->add_join("LEFT JOIN {user} {$entityuseralias} ON {$entityuseralias}.id = e.createdby"));

        // Tenant of the export.
        $entitytenant = new tenant();
        $entitytenantalias = $entitytenant->get_table_alias('tool_tenant');
        $this->add_entity($entitytenant
            ->add_join("LEFT JOIN {tool_tenant} {$entitytenantalias} ON {$entitytenantalias}.id = e.tenantid"));

        // Define our internal entity for import elements.
        $this->annotate_entity($this->get_import_entity_name(), new lang_string('imports', 'tool_wp'));

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(true);
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
     * Return a list of available importers, keyed on their class name
     *
     * @return array
     */
    public function get_importers(): array {
        $result = [];

        $importers = helper::get_all_importers();
        foreach ($importers as $importer) {
            $result[get_class($importer)] = $importer->get_name();
        }

        \core_collator::asort($result);

        return $result;
    }

    /**
     * Formatter for the 'importer' column
     *
     * @param string $value
     * @param \stdClass $row
     * @return string
     */
    public function format_importer($value, $row) {
        return helper::format_importer($value, $row);
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
        $importfile = helper::get_import_file($value);
        return $importfile ? display_size($importfile->get_filesize()) : '';
    }

    /**
     * Add report columns
     */
    protected function add_columns(): void {
        $maintablealias = $this->get_main_table_alias();

        $this->add_column((new column(
            'date',
            new lang_string('date'),
            $this->get_import_entity_name()
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
            'importer',
            new lang_string('importer', 'tool_wp'),
            $this->get_import_entity_name()
        ))
            ->add_fields("{$maintablealias}.importer, {$maintablealias}.entrypoint, {$maintablealias}.entrypointid")
            ->set_callback([$this, 'format_importer'])
        );

        $this->add_column((new column(
            'status',
            new lang_string('importstatus', 'tool_wp'),
            $this->get_import_entity_name()
        ))
            ->add_fields("{$maintablealias}.status")
            ->set_callback([$this, 'format_status'])
        );

        $this->add_column((new column(
            'size',
            new lang_string('size'),
            $this->get_import_entity_name()
        ))
            ->add_field("{$maintablealias}.id", 'size')
            ->set_callback([$this, 'format_size'])
        );
    }

    /**
     * Add report actions
     */
    private function add_actions() {

        // Download export file.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/download', ''),
            ['data-action' => 'download', 'data-url' => ':url'],
            false,
            new lang_string('download')
        ))
            ->add_callback(static function(stdClass $row): bool {
                $url = import_manager::get_import_file_url($row->id);
                $row->url = $url ? $url->out(false) : '';
                return true;
            })
        );

        // View report.
        $this->add_action((new action(
            helper::import_url(':id'),
            new pix_icon('bar-chart', '', 'tool_wp'),
            ['data-action' => 'log', 'data-id' => ':id'],
            false,
            new lang_string('viewimport', 'tool_wp')
        )));

        // Delete import.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/trash', ''),
            ['data-action' => 'delete', 'data-id' => ':id'],
            false,
            new lang_string('delete')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return permission::can_delete_import($row->id);
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

        // Filter by importer.
        $this->add_filter((new filter(
            select::class,
            'importer',
            new lang_string('importer', 'tool_wp'),
            $this->get_import_entity_name(),
            "{$tablealias}.importer"
        ))
            ->set_options_callback([$this, 'get_importers'])
        );

        // Filter by date.
        $this->add_filter((new filter(
            date::class,
            'date',
            new lang_string('date'),
            $this->get_import_entity_name(),
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
            new lang_string('importstatus', 'tool_wp'),
            $this->get_import_entity_name(),
            "{$tablealias}.status"
        ))
            ->set_options_callback([helper::class, 'get_status_list'])
        );
    }
}
