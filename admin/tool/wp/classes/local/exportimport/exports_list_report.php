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
 * Class exports_list_report
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

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
use tool_wp\permission;

defined('MOODLE_INTERNAL') || die();

/**
 * List of all previous exports
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class exports_list_report extends system_report {

    /**
     * Initialise system report
     */
    public function initialise() {
        global $USER;
        $this->set_main_table('tool_wp_export', 'e', false);
        if (!permission::can_view_all_exports_imports()) {
            $this->add_base_condition_simple('e.createdby', $USER->id);
        }
        $canviewothertenants = \tool_tenant\permission::can_switch_tenant();
        if (!$canviewothertenants) {
            $this->add_base_condition_simple('e.tenantid', tenancy::get_tenant_id());
        }

        $this->add_base_fields('e.id, e.tenantid, e.createdby, e.status, e.id as url, e.id as importid');

        $this->add_entity((new user())->add_join('LEFT JOIN {user} u ON e.createdby = u.id') );
        $this->annotate_entity('tool_wp_export', new \lang_string('exports', 'tool_wp'));

        $this->add_column((new report_column('date', new \lang_string('date'), 'tool_wp_export'))
            ->add_fields('e.timemodified')
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshort'))
            ->set_is_default(true, 0)
            ->set_is_sortable(true, true, 1, SORT_DESC));

        $this->get_column('user:fullnamewithlink')
            ->set_is_default(true, 1)
            ->set_visiblename(new \lang_string('createdby', 'tool_wp'));

        $this->add_column((new report_column('tenantid', new \lang_string('tenant', 'tool_tenant'), 'tool_wp_export'))
            ->add_fields('e.tenantid')
            ->set_is_default(true, 3)
            ->set_callback([$this, 'format_tenantid'])
            ->set_is_available($canviewothertenants));

        $this->add_column((new report_column('exporter', new \lang_string('exporter', 'tool_wp'), 'tool_wp_export'))
            ->add_fields('e.exporter, e.entrypoint, e.entrypointid')
            ->set_is_default(true, 2)
            ->set_callback([$this, 'format_exporter']));

        $this->add_column((new report_column('status', new \lang_string('exportstatus', 'tool_wp'), 'tool_wp_export'))
            ->add_fields('e.status')
            ->set_is_default(true, 5)
            ->set_callback([$this, 'format_status']));

        $this->add_column((new report_column('size', new \lang_string('size'), 'tool_wp_export'))
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
     * Formatter for the 'tenant' column
     *
     * @param mixed $value
     * @return string
     */
    public function format_tenantid($value) {
        return helper::format_tenantid($value);
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
     * Visible name of the data source.
     *
     * @return string
     * @throws \moodle_exception
     */
    public static function get_name() {
        return get_string('exports', 'tool_wp');
    }

    /**
     * Set the actions icons of the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function add_actions() {

        $url = new \moodle_url('#');

        // Import from export file.
        $icon = new \pix_icon('play-circle', get_string('importfromfile', 'tool_wp'), 'tool_wp');
        $importfromfileurl = new \moodle_url('/admin/tool/wp/import.php', ['exportid' => ':id']);
        $action = new report_action($importfromfileurl, $icon);
        $action->add_callback(function(\stdClass $row) {
            return $row->status == helper::STATUS_DONE;
        });
        $this->add_action($action);

        // Download export file.
        $icon = new \pix_icon('t/download', get_string('download'), 'core');
        $action = new report_action($url, $icon, ['data-action' => 'download', 'data-id' => ':id', 'data-url' => ':url']);
        $action->add_callback(function(\stdClass $row) {
            $url = export_manager::get_export_file_url($row->id);
            $row->url = $url ? $url->out(false) : '';
            return $row->status == helper::STATUS_DONE;
        });
        $this->add_action($action);

        // View report.
        $icon = new \pix_icon('bar-chart', get_string('viewexport', 'tool_wp'), 'tool_wp');
        $logurl = helper::export_url(':id');
        $action = new report_action($logurl, $icon, ['data-action' => 'log', 'data-id' => ':id']);
        $this->add_action($action);

        // Delete export.
        $icon = new \pix_icon('i/trash', get_string('delete'), 'core');
        $params = ['class' => 'confirm_delete_export', 'data-action' => 'delete', 'data-id' => ':id', 'data-url' => ':url'];
        $action = new report_action($url, $icon, $params);
        $action->add_callback(static function(\stdClass $row) {
            return permission::can_delete_export($row->id);
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
            'tool_wp_export',
            "{$tablealias}.tenantid"
        ))
            ->set_is_default(true)
            ->set_is_available(\tool_tenant\permission::can_switch_tenant())
        );

        // Filter by exporter.
        $this->add_filter((new report_filter(
            select::class,
            'exporter',
            new \lang_string('exporter', 'tool_wp'),
            'tool_wp_export',
            "{$tablealias}.exporter"
        ))
            ->set_options_callback([$this, 'get_exporters'])
            ->set_is_default(true)
        );

        // Filter by date.
        $this->add_filter((new report_filter(
            date_filter::class,
            'date',
            new \lang_string('date'),
            'tool_wp_export',
            "{$tablealias}.timemodified"
        ))
            ->set_is_default(true)
        );

        // Filter by status.
        $this->add_filter((new report_filter(
            select::class,
            'status',
            new \lang_string('exportstatus', 'tool_wp'),
            'tool_wp_export',
            "{$tablealias}.status"
        ))
            ->set_options_callback([helper::class, 'get_status_list'])
            ->set_is_default(true)
        );
    }
}
