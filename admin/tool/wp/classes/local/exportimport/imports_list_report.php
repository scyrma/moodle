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

namespace tool_wp\local\exportimport;

use tool_reportbuilder\local\entities\user;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;
use tool_tenant\tool_reportbuilder\filter\tenant_filter;
use tool_tenant\tool_reportbuilder\filter\tenant_filter_with_empty;
use tool_wp\db;
use tool_wp\permission;

/**
 * System report that shows all previous imports
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class imports_list_report extends system_report {

    /**
     * Initialise system report
     */
    public function initialise() {
        global $USER;
        $this->set_main_table('tool_wp_import', 'e', false);
        $p = db::generate_param_name();
        $this->add_base_condition_sql('e.status <> :'.$p, [$p => helper::STATUS_CREATED]);
        if (!permission::can_view_all_exports_imports()) {
            $this->add_base_condition_simple('e.createdby', $USER->id);
        }
        $canviewothertenants = \tool_tenant\permission::can_switch_tenant();
        if (!$canviewothertenants) {
            $this->add_base_condition_simple('e.tenantid', tenancy::get_tenant_id());
        }

        $this->add_base_fields('e.id, e.tenantid, e.createdby, e.status, e.id as url');

        $this->add_entity((new user())->add_join('LEFT JOIN {user} u ON e.createdby = u.id') );
        $this->annotate_entity('tool_wp_import', new \lang_string('imports', 'tool_wp'));

        $this->add_column((new report_column('date', new \lang_string('date'), 'tool_wp_import'))
            ->add_fields('e.timemodified')
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshort'))
            ->set_is_default(true, 0)
            ->set_is_sortable(true, true, 1, SORT_DESC));

        $this->get_column('user:fullnamewithlink')
            ->set_is_default(true, 1)
            ->set_visiblename(new \lang_string('createdby', 'tool_wp'));

        $this->add_column((new report_column('tenantid', new \lang_string('tenant', 'tool_tenant'), 'tool_wp_import'))
            ->add_fields('e.tenantid')
            ->set_is_default(true, 3)
            ->set_callback([$this, 'format_tenantid'])
            ->set_is_available($canviewothertenants));

        $this->add_column((new report_column('importer', new \lang_string('importer', 'tool_wp'), 'tool_wp_import'))
            ->add_fields('e.importer, e.entrypoint, e.entrypointid')
            ->set_is_default(true, 2)
            ->set_callback([$this, 'format_importer']));

        $this->add_column((new report_column('status', new \lang_string('importstatus', 'tool_wp'), 'tool_wp_import'))
            ->add_fields('e.status')
            ->set_is_default(true, 5)
            ->set_callback([$this, 'format_status']));

        $this->add_column((new report_column('size', new \lang_string('size'), 'tool_wp_import'))
            ->add_fields('e.id as size')
            ->set_is_default(true, 4)
            ->set_callback([$this, 'format_size']));

        $this->add_actions();
        $this->set_filters();

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
     * Formatter for the 'tenant' column
     *
     * @param mixed $value
     * @return string
     */
    public function format_tenantid($value) {
        if (!$value) {
            return '';
        }
        return tenancy::get_tenant_name_from_id($value);
    }

    /**
     * Formatter for the 'status' column
     *
     * @param int $value
     * @return string
     */
    public function format_status($value): string {
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
     * Visible name of the data source.
     *
     * @return string
     * @throws \moodle_exception
     */
    public static function get_name() {
        return get_string('imports', 'tool_wp');
    }

    /**
     * Set the actions icons of the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function add_actions() {
        $url = new \moodle_url('#');

        $icon = new \pix_icon('t/download', get_string('download'), 'core');
        $action = new report_action($url, $icon, ['data-action' => 'download', 'data-id' => ':id', 'data-url' => ':url']);
        $action->add_callback(function(\stdClass $row) {
            $url = import_manager::get_import_file_url($row->id);
            $row->url = $url ? $url->out(false) : '';
            return true;
        });
        $this->add_action($action);

        // View report.
        $icon = new \pix_icon('bar-chart', get_string('viewimport', 'tool_wp'), 'tool_wp');
        $logurl = helper::import_url(':id');
        $action = new report_action($logurl, $icon, ['data-action' => 'log', 'data-id' => ':id']);
        $this->add_action($action);

        // Delete import.
        $icon = new \pix_icon('i/trash', get_string('delete'), 'core');
        $params = ['class' => 'confirm_delete_import', 'data-action' => 'delete', 'data-id' => ':id', 'data-url' => ':url'];
        $action = new report_action($url, $icon, $params);
        $action->add_callback(static function(\stdClass $row) {
            return permission::can_delete_import($row->id);
        });
        $this->add_action($action);
    }

    /**
     * Set the report filters
     *
     * @return void
     */
    private function set_filters(): void {
        $tablealias = $this->get_main_table_alias();

        // We only show the tenant filter to users who can switch tenants.
        $this->add_filter((new report_filter(
            tenant_filter_with_empty::class,
            'tenant',
            new \lang_string('tenant', 'tool_tenant'),
            'tool_wp_import',
            "{$tablealias}.tenantid"
        ))
            ->set_is_default(true)
            ->set_is_available(\tool_tenant\permission::can_switch_tenant())
        );

        // Filter by importer.
        $this->add_filter((new report_filter(
            select::class,
            'importer',
            new \lang_string('importer', 'tool_wp'),
            'tool_wp_import',
            "{$tablealias}.importer"
        ))
            ->set_options_callback([$this, 'get_importers'])
            ->set_is_default(true)
        );

        // Filter by date.
        $this->add_filter((new report_filter(
            date_filter::class,
            'date',
            new \lang_string('date'),
            'tool_wp_import',
            "{$tablealias}.timemodified"
        ))
            ->set_is_default(true)
        );

        // Filter by status.
        $this->add_filter((new report_filter(
            select::class,
            'status',
            new \lang_string('importstatus', 'tool_wp'),
            'tool_wp_import',
            "{$tablealias}.status"
        ))
            ->set_options_callback([helper::class, 'get_status_list'])
            ->set_is_default(true)
        );
    }
}
