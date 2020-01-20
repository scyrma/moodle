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
 * Class defintion for system report to list report schedules.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\systemreports;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\helper;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\helpers\schedules;
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
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * @package   tool_reportbuilder
 */
class schedules_list extends system_report {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_filters();
        $this->set_main_table('tool_reportbuilder_scheduled', 'sch');
        if ($reportid = $this->get_parameter('reportid', 0, PARAM_INT)) {
            $this->add_base_condition_simple('reportid', $reportid);
        }
        $this->add_base_fields('sch.id, sch.name, sch.reportid, rb.tenantid'); // Necessary for actions.
        $this->add_actions();
        $this->set_downloadable(false);
        $tenantid = db::generate_param_name();
        $this->add_base_condition_sql("(rb.tenantid = :{$tenantid} OR rb.tenantid = 0)",
            [$tenantid => tenancy::get_tenant_id()]);
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
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function set_columns() {
        $this->annotate_entity('tool_reportbuilder', new \lang_string('entityreportbuilder', 'tool_reportbuilder'));
        $this->annotate_entity('tool_reportbuilder_scheduled', new \lang_string('entityschedule', 'tool_reportbuilder'));

        $fields = array('name', 'reportname', 'scheduled', 'lastsenton', 'format');
        $headers = array(
            new \lang_string('schedulename', 'tool_reportbuilder'),
            new \lang_string('reportname', 'tool_reportbuilder'),
            new \lang_string('scheduled', 'tool_reportbuilder'),
            new \lang_string('lastsenton', 'tool_reportbuilder'),
            new \lang_string('format', 'tool_reportbuilder')
        );

        $newcolumn = (new report_column(
            $fields[0],
            $headers[0],
            'tool_reportbuilder_scheduled'
            ))
            ->add_field('sch.' . $fields[0])
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true)
            ->add_callback([format::class, 'format_string']);

        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            $fields[1],
            $headers[1],
            'tool_reportbuilder_scheduled'
        ))
            ->add_join('join {tool_reportbuilder} rb ON rb.id = sch.reportid')
            ->add_field('rb.name', 'reportname')
            ->add_field('sch.reportid')
            ->set_is_default(true, 2)
            ->set_is_sortable(true, true);

        $newcolumn->add_callback(
            [format::class, 'report_link'],
            array(
                'url' => new \moodle_url('/admin/tool/reportbuilder/view.php')
            )
        );

        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            $fields[2],
            $headers[2],
            'tool_reportbuilder_scheduled'
        ))
            ->add_field('sch.'.$fields[2])
            ->set_is_default(true, 3)
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshort'))
            ->set_is_sortable(true);

        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            $fields[3],
            $headers[3],
            'tool_reportbuilder_scheduled'
        ))
            ->add_field('sch.'.$fields[3])
            ->set_is_default(true, 4)
            ->add_callback([format::class, 'last_sent_on'])
            ->set_is_sortable(true);

        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            $fields[4],
            $headers[4],
            'tool_reportbuilder_scheduled'
        ))
            ->add_field('sch.'.$fields[4])
            ->set_is_default(true, 5)
            ->add_callback([schedules::class, 'get_format']);

        $this->add_column($newcolumn);
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
            ->add_callback([permission::class, 'can_view_schedule_edit_icon']);
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
            ->add_callback([permission::class, 'can_view_schedule_send_icon']);
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
     * @throws \coding_exception
     */
    protected function set_filters() {
        $filter = (new report_filter(
            select::class,
            'reportname',
            new \lang_string('reportname', 'tool_reportbuilder'),
            'tool_reportbuilder_scheduled',
            'rb.id'
        ))
            ->add_join('join {tool_reportbuilder} rb on rb.id = sch.reportid')
            ->set_options([0 => ''] + helper::get_reports_select())
            ->set_is_default(true);

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