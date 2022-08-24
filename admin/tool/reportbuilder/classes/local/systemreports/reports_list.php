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
 * Class for define the system report of the reports lists.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\systemreports;

use moodle_url;
use pix_icon;
use stdClass;
use tool_reportbuilder\constants;
use tool_reportbuilder\datasource;
use tool_reportbuilder\helper;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\helpers\audience;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\system_report;
use tool_tenant\hierarchy;
use tool_wp\db;

/**
 * Class reports_list
 *
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * @package   tool_reportbuilder
 */
class reports_list extends system_report {

    /**
     * Initialise report
     */
    protected function initialise() {
        global $DB;

        $this->set_main_table('tool_reportbuilder', 'rb', false);
        $this->add_base_condition_simple('rb.type', constants::TYPE_DATASOURCE);
        $this->add_base_fields('rb.id, rb.name, rb.tenantid, rb.source, rb.type, rb.idnumber, rb.shared'); // Necessary for actions.

        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('rb.tenantid', 'rb.shared=1');
        $this->add_base_condition_sql($sql, $params);

        $this->set_columns();
        $this->set_filters();
        $this->add_actions();
        $this->set_downloadable(false);

        // If user can't view all reports, limit the returned list to those they can see.
        if (!permission::can_view_any()) {
            $reports = audience::user_reports_list();

            if (empty($reports)) {
                $this->add_base_condition_sql('1=2');
            } else {
                $paramprefix = db::generate_param_name() . '_';

                list($where, $params) = $DB->get_in_or_equal($reports, SQL_PARAMS_NAMED, $paramprefix);
                $select = "{$this->get_main_table_alias()}.id {$where}";

                $this->add_base_condition_sql($select, $params);
            }
        }
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_reports_list();
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('reportlists', 'tool_reportbuilder');
    }

    /**
     * CSS classes to add to the row
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row) : string {
        if (!manager::report_source_valid($row->source, datasource::class)) {
            return 'dimmed_text';
        }

        return '';
    }

    /**
     * Set the columns for the report.
     *
     * @return mixed
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function set_columns() : void {
        $this->annotate_entity('tool_reportbuilder', new \lang_string('entityreportbuilder', 'tool_reportbuilder'));

        $canmanage = permission::can_manage_reports($this->get_tenant_id());

        $fields = ['name', 'source', 'timecreated', 'timemodified'];
        $headers = array(
            new \lang_string('reportname', 'tool_reportbuilder'),
            new \lang_string('plugin'),
            new \lang_string('datecreated', 'tool_reportbuilder'),
            new \lang_string('lastmodified', 'tool_reportbuilder')
        );

        foreach ($fields as $key => $field) {
            $newcolumn = (new report_column(
                $field,
                $headers[$key],
                'tool_reportbuilder'
            ))
                ->add_field('rb.' . $field)
                ->add_field('rb.id')
                ->add_field('rb.tenantid')
                ->add_field('rb.source')
                ->add_field('rb.shared')
                ->set_is_default(true);

            if ($field === 'name') {
                $newcolumn->add_callback(function($value, $row) use ($canmanage) {
                    global $OUTPUT;
                    $canedit = permission::can_view_edit_icon($row);
                    $canviewediticon = permission::can_view_inplace_editable_icon($row);
                    $ie = manager::get_name_inplace_editable($value, $row->id, $canedit, $canviewediticon);
                    $result = $ie->render($OUTPUT);

                    // Display the "Shared space" badge if applicable.
                    if ($canmanage && in_array($row->tenantid, hierarchy::get_parent_tenants_ids())) {
                        $result .= ' ' . \html_writer::span(get_string('sharedspace', 'tool_tenant'),
                                'badge badge-secondary');
                    }

                    // Add a warning for missing or unavailable report source.
                    if (!manager::report_source_exists($row->source, datasource::class)) {
                        $result .= \html_writer::span(get_string('error'), 'badge badge-warning broken ml-3',
                            ['title' => get_string('errormissingreportsource', 'tool_reportbuilder')]);
                    } else if (!manager::report_source_available($row->source)) {
                        $result .= \html_writer::span(get_string('error'), 'badge badge-warning broken ml-3',
                            ['title' => get_string('errorunavailablereportsource', 'tool_reportbuilder')]);
                    }

                    return $result;
                })
                    ->set_is_sortable(true, true);
            } else {
                $newcolumn->set_is_available($canmanage);
            }
            if ($field === 'source') {
                $newcolumn
                    ->add_callback([format::class, 'source_plugin'])
                    ->set_is_sortable(true, true);
            }
            if (in_array($field, ['timecreated', 'timemodified'])) {
                $newcolumn
                    ->add_callback([format::class, 'userdate'], get_string('strftimedatefullshort'))
                    ->set_is_sortable(true);
            }

            $this->add_column($newcolumn);
        }

        // User modified field.
        $this->add_column((new report_column(
            'usermodified',
            new \lang_string('modifiedby', 'tool_reportbuilder'),
            'tool_reportbuilder'
        ))
            ->add_fields(user_entity::get_all_user_name_fields(true, 'u') . ', rb.usermodified')
            ->add_join('join {user} u ON u.id = rb.usermodified')
            ->set_is_default(true)
            ->set_is_available($canmanage)
            ->add_callback([format::class, 'fullname']))
            ->set_is_sortable(true);

        if (hierarchy::has_subtenants($this->get_tenant_id())) {
            // Share report.
            $this->add_column((new report_column(
                'shared',
                new \lang_string('shared', 'tool_reportbuilder'),
                'tool_reportbuilder'
            ))
                ->add_field('rb.shared')
                ->set_is_default(true)
                ->add_callback([format::class, 'checkbox_as_text'])
                ->set_is_available($canmanage))
                ->set_is_sortable(true);
        }
    }

    /**
     * Set the available filters for the report
     *
     * @return void
     */
    public function set_filters() : void {
        $this->add_filter((new report_filter(
            select::class,
            'source',
            new \lang_string('reportsource', 'tool_reportbuilder'),
            'tool_reportbuilder',
            "{$this->get_main_table_alias()}.source"
        ))
            ->set_options_callback([helper::class, 'get_sources'])
            ->set_is_default(true));
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions() {
        // Go to report editor.
        $this->add_action((new report_action(
            new moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => ':id']),
            new pix_icon('t/right', get_string('editreport', 'tool_reportbuilder'))
        ))
            ->add_callback(static function(stdClass $row): bool {
                return manager::report_source_valid($row->source, datasource::class) &&
                    permission::can_view_edit_icon($row);
            })
        );

        // Edit details.
        $this->add_action((new report_action(
            new moodle_url('#'),
            new pix_icon('i/settings', get_string('editreportdetails', 'tool_reportbuilder')),
            ['data-action' => 'editdetails', 'data-id' => ':id', 'data-reportname' => ':name']
        ))
            ->add_callback(static function(stdClass $row): bool {
                return manager::report_source_valid($row->source, datasource::class) &&
                    permission::can_view_edit_details_icon($row);
            })
        );

        // Preview report.
        $this->add_action((new report_action(
            new moodle_url('/admin/tool/reportbuilder/view.php', ['id' => ':id']),
            new pix_icon('i/search', get_string('preview'))
        ))
            ->add_callback(static function(stdClass $row): bool {
                return manager::report_source_valid($row->source, datasource::class);
            })
        );

        // Duplicate action.
        if (false) {
            // TODO WP-259 not implemented.
            $icon = new \pix_icon('e/manage_files', get_string('duplicatereport', 'tool_reportbuilder'), 'core');
            $action = new report_action(new \moodle_url('#'), $icon,
                array(
                    'data-action' => 'duplicate',
                    'data-id' => ':id'
                )
            );
            $action->add_callback([\tool_reportbuilder\permission::class, 'can_view_duplicate_icon']);
            $this->add_action($action);
        }

        // Delete icon.
        $this->add_action((new report_action(
            new moodle_url('#'),
            new pix_icon('i/trash', get_string('deletereport', 'tool_reportbuilder')),
            ['data-action' => 'delete', 'data-id' => ':id', 'data-reportname' => ':name']
        ))
            ->add_callback([permission::class, 'can_view_delete_icon'])
        );

        // Convert report.
        $this->add_action((new report_action(
            new moodle_url('/admin/tool/reportbuilder/convert.php', ['id' => ':id', 'sesskey' => sesskey()]),
            new pix_icon('i/reload', 'Convert') // TODO WP-3706 better icon, do not hardcode string.
        ))
            ->add_callback(static function(stdClass $row): bool {
                return manager::report_source_valid($row->source, datasource::class) &&
                    permission::can_view_edit_details_icon($row) &&
                    \core_reportbuilder\permission::can_create_report();
            })
        );
    }
}
