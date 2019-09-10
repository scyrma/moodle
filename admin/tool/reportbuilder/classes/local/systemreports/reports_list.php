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
 * Class for define the system report of the reports lists.
 *
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\systemreports;

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\constants;
use tool_reportbuilder\helper;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\system_report;
use tool_wp\db;

/**
 * Class reports_list
 *
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_reportbuilder
 */
class reports_list extends system_report {

    /**
     * Initialise report
     */
    protected function initialise() {
        global $DB;
        $this->set_columns();
        $this->set_main_table('tool_reportbuilder', 'rb');
        $this->add_base_condition_simple('rb.type', constants::TYPE_DATASOURCE);
        $this->add_base_fields('rb.id, rb.name, rb.tenantid, rb.source, rb.type, rb.idnumber'); // Necessary for actions.
        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);
        if (!permission::can_view_any()) {
            // User can view the list of reports but only the ones that have org support.
            $sources = helper::get_sources_with_organisation_support();
            list($sql, $params) = $DB->get_in_or_equal($sources, SQL_PARAMS_NAMED, db::generate_param_name() . '_',
                true, true);
            $this->add_base_condition_sql('rb.source ' . $sql, $params);
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
     * Set the columns for the report.
     *
     * @return mixed
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function set_columns() : void {
        $this->annotate_entity('tool_reportbuilder', new \lang_string('entityreportbuilder', 'tool_reportbuilder'));

        $canmanage = permission::can_manage_reports();
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
                ->add_field('rb.id', 'rbid')
                ->add_field('rb.tenantid')
                ->set_is_default(true);

            if ($field === 'name') {
                $newcolumn->add_callback(function($value, $row) use ($canmanage) {
                    global $OUTPUT;
                    $ie = manager::get_name_inplace_editable($value, $row->rbid, $canmanage);
                    return $ie->render($OUTPUT);
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
    }

    /**
     * Set the actions icons of the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function add_actions() {
        // Go to report editor.
        $editurl = new \moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => ':id']);
        $icon = new \pix_icon('t/right', get_string('editreport', 'tool_reportbuilder'), 'core');
        $action = new report_action($editurl, $icon);
        $action->add_callback([\tool_reportbuilder\permission::class, 'can_view_edit_icon']);
        $this->add_action($action);

        // Edit details.
        $icon = new \pix_icon('i/settings', get_string('editreportdetails', 'tool_reportbuilder'), 'core');
        $action = (new report_action(new \moodle_url('#'), $icon,
                ['data-action' => 'editdetails', 'data-id' => ':id', 'data-reportname' => ':name']))
            ->add_callback([\tool_reportbuilder\permission::class, 'can_view_edit_icon'])
            ->add_callback(function($row) {
                $row->name = format_string($row->name, true, ['escape' => false]);
                return true;
            });
        $this->add_action($action);

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
        $icon = new \pix_icon('i/trash', get_string('deletereport', 'tool_reportbuilder'), 'core');
        $action = (new report_action(new \moodle_url('#'), $icon,
            array(
                'data-action' => 'delete',
                'data-reportname' => ':name',
                'data-id' => ':id'
            )
        ))
            ->add_callback([\tool_reportbuilder\permission::class, 'can_view_delete_icon'])
            ->add_callback(function($row) {
                $row->name = format_string($row->name, true, ['escape' => false]);
                return true;
            });
        $this->add_action($action);
    }
}