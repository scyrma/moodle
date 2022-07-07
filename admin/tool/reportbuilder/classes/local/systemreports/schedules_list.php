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
 * Class defintion for system report to list report schedules.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\systemreports;

use moodle_url;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\helper;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\helpers\schedules;
use tool_reportbuilder\local\models\schedule;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;
use tool_wp\db;

/**
 * Class schedules_list
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * @package   tool_reportbuilder
 */
class schedules_list extends system_report {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_main_table(schedule::TABLE, 'sch');
        $this->add_base_join('JOIN {tool_reportbuilder} rb ON rb.id = sch.reportid');
        $this->add_base_fields('sch.id, sch.name, sch.enabled, sch.reportid, rb.tenantid, rb.source'); // Necessary for actions.

        // Limit schedules to a specific report if requested.
        if ($reportid = $this->get_reportid()) {
            $this->add_base_condition_simple('rb.id', $reportid);
        }

        $this->set_columns();
        $this->set_filters();
        $this->add_actions();
        $this->set_downloadable(false);

        $tenantid = db::generate_param_name();
        $this->add_base_condition_sql("(rb.tenantid = :{$tenantid} OR rb.tenantid = 0)",
            [$tenantid => tenancy::get_tenant_id()]);
    }

    /**
     * Returns passed report ID parameter if specified
     *
     * @return int
     */
    protected function get_reportid(): int {
        return $this->get_parameter('reportid', 0, PARAM_INT);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_all_schedules_list();
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('reportschedules', 'tool_reportbuilder');
    }

    /**
     * Set the columns for the report.
     *
     * @return void
     */
    protected function set_columns() {
        $tablealias = $this->get_main_table_alias(schedule::TABLE);

        $this->annotate_entity('tool_reportbuilder_scheduled', new \lang_string('entityschedule', 'tool_reportbuilder'));

        // Enabled.
        $this->add_column((new report_column(
            'enabled',
            null,
            'tool_reportbuilder_scheduled'
        ))
            ->add_fields("{$tablealias}.enabled, {$tablealias}.id, rb.source")
            ->set_is_default(true)
            ->add_attributes(['data-region' => 'wp-toggle'])
            ->add_callback([format::class, 'toggle_schedule_enabled'])
        );

        // Schedule name.
        $this->add_column((new report_column(
            'name',
            new \lang_string('schedulename', 'tool_reportbuilder'),
            'tool_reportbuilder_scheduled'
        ))
            ->add_field("{$tablealias}.name")
            ->set_is_default(true)
            ->set_is_sortable(true, true)
            ->add_callback([format::class, 'format_string'])
        );

        // Report name.
        $this->add_column((new report_column(
            'reportname',
            new \lang_string('reportname', 'tool_reportbuilder'),
            'tool_reportbuilder_scheduled'
        ))
            ->add_fields("{$tablealias}.reportid, rb.name, rb.source")
            ->set_is_default(true)
            ->set_is_sortable(true)
            ->set_is_available($this->get_reportid() === 0)
            ->add_callback([format::class, 'report_link'])
        );

        // Schedule date.
        $this->add_column((new report_column(
            'scheduled',
            new \lang_string('scheduled', 'tool_reportbuilder'),
            'tool_reportbuilder_scheduled'
        ))
            ->add_field("{$tablealias}.scheduled")
            ->set_is_default(true)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshort', 'langconfig'))
        );

        // Last sent.
        $this->add_column((new report_column(
            'lastsenton',
            new \lang_string('lastsenton', 'tool_reportbuilder'),
            'tool_reportbuilder_scheduled'
        ))
            ->add_field("{$tablealias}.lastsenton")
            ->set_is_default(true)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'last_sent_on'])
        );

        // Format.
        $this->add_column((new report_column(
            'format',
            new \lang_string('format', 'tool_reportbuilder'),
            'tool_reportbuilder_scheduled'
        ))
            ->add_field("{$tablealias}.format")
            ->set_is_default(true)
            ->add_callback([schedules::class, 'get_format'])
        );
    }

    /**
     * Set row class, dimmed for disabled schedules or those referring to invalid report sources
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        if (!$row->enabled || !manager::report_source_valid($row->source, datasource::class)) {
            return 'dimmed_text';
        }

        return '';
    }

    /**
     * Set the actions icons of the report.
     *
     * @return void
     */
    private function add_actions() {
        $actionurl = new moodle_url('#');

        // Edit icon.
        $icon = new \pix_icon('i/settings', get_string('editschedule', 'tool_reportbuilder'), 'core');
        $action = (new report_action($actionurl, $icon, ['data-action' => 'edit', 'data-id' => ':id']))
            ->add_callback(static function(\stdClass $row): bool {
                return manager::report_source_valid($row->source, datasource::class) &&
                    permission::can_view_schedule_edit_icon($row);
            });
        $this->add_action($action);

        // Duplicate icon.
        if (false) {
            // TODO WP-902 not implemented yet.
            $icon = new \pix_icon('e/manage_files', get_string('duplicate', 'tool_reportbuilder'), 'core');
            $action = new report_action(new \moodle_url('#'), $icon, array('data-action' => 'duplicate', 'data-id' => ':id'));
            $this->add_action($action);
        }

        // Send now icon.
        $icon = new \pix_icon('paper-plane-o', get_string('send', 'tool_reportbuilder'), 'tool_wp');
        $action = (new report_action($actionurl, $icon,
                ['data-action' => 'send', 'data-id' => ':id', 'data-schedulename' => ':name']))
            ->add_callback(static function(\stdClass $row): bool {
                return manager::report_source_valid($row->source, datasource::class) &&
                    permission::can_view_schedule_send_icon($row);
            });
        $this->add_action($action);

        // Delete icon.
        $icon = new \pix_icon('i/trash', get_string('deleteschedule', 'tool_reportbuilder'), 'core');
        $action = (new report_action($actionurl, $icon,
                ['data-action' => 'delete', 'data-id' => ':id', 'data-schedulename' => ':name']))
            ->add_callback([permission::class, 'can_view_schedule_delete_icon']);
        $this->add_action($action);
    }

    /**
     * Set the filters of the report
     *
     * @return void
     */
    protected function set_filters() {

        $filter = (new report_filter(
            select::class,
            'reportname',
            new \lang_string('reportname', 'tool_reportbuilder'),
            'tool_reportbuilder_scheduled',
            'sch.reportid'
        ))
            ->set_options_callback(function() {
                return [0 => ''] + helper::get_reports_select();
            })
            ->set_is_default(true);

        // We only add this filter if we are viewing all schedules (not just those for a specific report).
        $filter->set_is_available($this->get_reportid() === 0);
        $this->add_filter($filter);

        $filter = (new report_filter(
            text::class,
            'schedulename',
            new \lang_string('schedulename', 'tool_reportbuilder'),
            'tool_reportbuilder_scheduled',
            'sch.name'
        ))
            ->set_is_default(true);

        $this->add_filter($filter);
    }
}
