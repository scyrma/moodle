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
 * Manager class.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

use core\output\inplace_editable;
use tool_reportbuilder\event\report_created;
use tool_reportbuilder\event\report_updated;
use tool_reportbuilder\form\detail;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_reportbuilder\local\models\reportbuilder_conditions;
use tool_reportbuilder\local\report\reportbuilder_filter;
use tool_tenant\tenancy;
use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * Class manager
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * Save the report.
     *
     * @param stdClass $formdata
     *
     * @return reportbuilder
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public static function save_report(stdClass $formdata) : reportbuilder {
        global $USER;
        unset($formdata->submitbutton);
        $adddefault = $formdata->adddefault;
        unset($formdata->adddefault);

        $uniqid = uniqid();

        if (!isset($formdata->shortname)) {
            $formdata->shortname = self::make_shortname($formdata->name) . '_' . $uniqid;
        }
        if (!isset($formdata->idnumber)) {
            $formdata->idnumber = $uniqid;
        }
        $formdata->tenantid = tenancy::get_tenant_id();
        $formdata->usercreated = $USER->id;
        $persistent = new reportbuilder(0, $formdata);
        $persistent->create();

        // Trigger report created event.
        $event = report_created::create_from_object($persistent);
        $event->trigger();

        if ($adddefault) {
            self::add_default_configuration($persistent);
        }

        return $persistent;
    }

    /**
     * Callback for insert the defaults columns after the report has been created.
     *
     * @param reportbuilder $reportbuilder
     * @throws \coding_exception
     */
    protected static function add_default_configuration(reportbuilder $reportbuilder): void {
        $reportid = $reportbuilder->get('id');
        $report = self::get_report_from_persistent($reportbuilder);

        // Add default columns.
        $columns = $report->get_columns();
        foreach ($columns as $column) {
            if (!$column->is_default()) {
                continue;
            }
            $record = new stdClass();
            $record->reportid = $reportid;
            $record->entity = $column->get_entity();
            $record->name = $column->get_name();
            $record->heading = '';
            $record->columnorder = $column->get_default_column_order();
            $record->sortorder = $column->get_default_sortorder();
            $record->sortenabled = $column->get_default_sortenabled();
            $record->sortdirection = $column->get_default_sortdirection();
            $record->hidden = false;

            $columnpersistent = new reportbuilder_column(0, $record);
            $columnpersistent->save();
        }

        // Add default conditions.
        $defaultvalues = [];
        $sortorder = 1;
        $conditions = $report->get_conditions();
        foreach ($conditions as $condition) {
            if (!$condition->is_default()) {
                continue;
            }
            $record = new stdClass();
            $record->reportid = $reportid;
            $record->entity = $condition->get_entity();
            $record->name = $condition->get_name();
            $record->sortorder = $sortorder++;
            $record->operator = null;
            $record->value = null;
            $record->heading = $condition->get_header();

            $conditionpersistent = new reportbuilder_conditions(0, $record);
            $conditionpersistent->save();

            // Add prefix (entity) for each condition value.
            foreach ($condition->get_default_values() as $key => $value) {
                $defaultvalues[$record->entity . ':' . $key] = $value;
            }
        }
        $conditions = new conditions_helper(self::get_report_from_persistent($reportbuilder));
        $conditions->add_report_conditions(json_encode($defaultvalues));

        // Add default filters.
        $sortorder = 1;
        $filters = $report->get_filters();
        foreach ($filters as $filter) {
            if (!$filter->is_default()) {
                continue;
            }
            $record = new stdClass();
            $record->reportid = $reportid;
            $record->entity = $filter->get_entity();
            $record->name = $filter->get_name();
            $record->sortorder = $sortorder++;
            $record->heading = $filter->get_header();

            $filterpersistent = new reportbuilder_filter(0, $record);
            $filterpersistent->save();
        }
    }

    /**
     * Make a short and unique name for the report.
     *
     * @param string $fullname
     *
     * @return mixed
     */
    private static function make_shortname($fullname) {
        return str_replace(' ', '', strtolower($fullname));
    }

    /**
     * Verify that a given report source exists and extends appropriate base class
     *
     * @param string $classname
     * @return bool
     */
    public static function report_source_exists(string $classname) : bool {
        return ($classname && class_exists($classname) && is_subclass_of($classname, report_base::class));
    }

    /**
     * Get report from persistent object
     *
     * @param reportbuilder $persistent
     * @param array $parameters
     * @param int $page
     * @return report_base
     *
     * @throws \moodle_exception
     */
    public static function get_report_from_persistent(reportbuilder $persistent,
                                                      array $parameters = [], int $page = 0): report_base {
        /** @var report_base $classname */
        $classname = $persistent->get('source');
        if (!self::report_source_exists($classname)) {
            throw new \moodle_exception('errormissingreportsource', 'tool_reportbuilder', '', null, $classname);
        }

        return new $classname($persistent, $parameters, $page);
    }

    /**
     * Initialise the report from report id
     *
     * @param int $reportid
     * @param array $parameters
     * @param int $page
     * @return report_base
     */
    public static function get_report(int $reportid, array $parameters = [], int $page = 0) : report_base {
        $persistent = new reportbuilder($reportid);
        return self::get_report_from_persistent($persistent, $parameters, $page);
    }

    /**
     * Update the report basic data like name, description ...
     *
     * @param stdClass $data
     *
     * @return mixed
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public static function update_report($data) {
        $persistent = new reportbuilder($data->id);
        if (property_exists($data, 'description')) {
            $persistent->set('description', $data->description);
        }
        $persistent->set('name', $data->name);
        $event = report_updated::create_from_object($persistent);

        if ($persistent->update()) {
            $event->trigger();
            return $data->id;
        }
        return false;
    }

    /**
     * Only for system reports.
     *
     * Check if a default columns of the report does not exists in the table.
     * This might occurs when a new column is add or deleted after the report has been created.
     * If a default column has been removed it is deleted from table
     * or if a new column has been added as default column it is created.
     *
     * @param int             $reportid
     * @param report_column[] $columns
     *
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public static function check_columns(int $reportid, array $columns) : void {
        foreach ($columns as $column) {
            $record = reportbuilder_column::check_column($reportid, $column);
            if (!$record) {
                if (!$column->is_default()) {
                    continue;
                }
                $data = new stdClass();
                $data->reportid = $reportid;
                $data->entity = $column->get_entity();
                $data->name = $column->get_name();
                $data->heading = '';
                $data->columnorder = $column->get_default_column_order();
                $data->sortorder = $column->get_default_sortorder();
                $data->sortenabled = $column->get_default_sortenabled();
                $data->sortdirection = $column->get_default_sortdirection();
                $data->hidden = false;
                $columnpersistent = new reportbuilder_column(0, $data);
                $columnpersistent->save();
            } else {
                $oldrecord = $record->to_record();

                // TODO SP-469 remove this unconditional synchronisation when it is possible
                // to customize and reset system reports via UI.
                if (!$column->is_default()) {
                    $record->delete();
                    continue;
                }
                $record->set('columnorder', $column->get_default_column_order());
                $record->set('sortorder', $column->get_default_sortorder());
                $record->set('sortdirection', $column->get_default_sortdirection());
                $record->set('sortenabled', $column->get_default_sortenabled());
                $record->set('heading', '');
                // TODO SP-469 end of sync.

                if (json_encode($record->to_record()) != json_encode($oldrecord)) {
                    $record->update();
                }
            }
        }
    }

    /**
     * Check if a default filter need to be added.
     *
     * @param int $reportid
     * @param \tool_reportbuilder\report_filter[] $filters
     */
    public static function check_filters(int $reportid, $filters) : void {
        foreach ($filters as $filter) {
            $record = filters_helper::get_filter($reportid, $filter);
            if (!$record && $filter->is_default()) {
                filters_helper::add_filter($reportid, $filter);
            }
            // TODO SP-422 this sync is not complete.
        }
    }

    /**
     * Remove old columns from a system report.
     *
     * @param reportbuilder_column[] $currentcolumns Current columns in DB.
     * @param array        $columns        Columns definition
     *
     * @throws \coding_exception
     */
    public static function delete_old_columns($currentcolumns, array $columns) {
        foreach ($currentcolumns as $currentcolumn) {
            $key = $currentcolumn->get_unique_identifier();
            if (!array_key_exists($key, $columns)) {
                $currentcolumn->delete();
            }
        }
    }

    /**
     * Remove old columns from a system report.
     *
     * @param reportbuilder_filter[] $currentfilters Current filters in DB.
     * @param array        $filters        Filters definition
     *
     * @throws \coding_exception
     */
    public static function delete_old_filters($currentfilters, array $filters) {
        foreach ($currentfilters as $currentfilter) {
            $key = $currentfilter->get_unique_identifier();
            if (!array_key_exists($key, $filters)) {
                $currentfilter->delete();
            }
        }
    }

    /**
     * Get the form for the basic info.
     *
     * @param int $reportid
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_detail_form(int $reportid) {
        global $PAGE;

        $persistent = '';
        $reportsourcename = '';
        $formdata = [];

        if ($reportid) {
            $persistent = new \tool_reportbuilder\reportbuilder($reportid);
            $datasource = $persistent->get('source');
            $reportsourcename = $datasource::get_name();
            $formdata = (array)$persistent->to_record();
        }

        $mform = new detail(
            $PAGE->url->out(false),
            [
                'persistent' => $persistent,
                'sourcename' => $reportsourcename,
                'reportid' => $reportid
            ],
            'post',
            '',
            array('class' => 'details'),
            true,
            $formdata
            );
        $mform->set_data($formdata);
        return $mform->render();
    }

    /**
     * Get the tabs for the management view.
     *
     * @param int $reportid
     *
     * @return array
     * @throws \coding_exception
     */
    public static function get_tabs($reportid) {
        global $OUTPUT;
        $attributestab = ['id' => 'report-builder', 'reportid' => $reportid];
        $tabsoutput = new \tool_wp\output\tabs($attributestab);
        $data = ['reportid' => $reportid];
        $tabsoutput->add_tab(new \tool_reportbuilder\output\tabs\table($data));
        // TODO WP-863 Bring back the Schedule tab inside a report.
        if (defined('BEHAT_SITE_RUNNING')) {
            $tabsoutput->add_tab(new \tool_reportbuilder\output\tabs\schedule($data));
        }
        $tabsoutput->add_tab(new \tool_reportbuilder\output\tabs\access($data));
        return $tabsoutput->export_for_template($OUTPUT);
    }

    /**
     * Inplace editable for columns header
     *
     * @param string $name name as entered by user
     * @param int $id
     * @param bool $canedit
     *
     * @return inplace_editable
     */
    public static function get_name_inplace_editable(string $name, int $id, bool $canedit) : inplace_editable {
        $displayvalue = $formattedvalue = format_string($name);
        if ($canedit) {
            $url = new \moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $id]);
            $displayvalue = \html_writer::link($url, $displayvalue);
        } else {
            $url = new \moodle_url('/admin/tool/reportbuilder/view.php', ['id' => $id]);
            $displayvalue = \html_writer::link($url, $displayvalue);
        }
        return new inplace_editable('tool_reportbuilder', 'reportname', $id,
            $canedit,
            $displayvalue, $name, get_string('editreportname', 'tool_reportbuilder'),
            get_string('newvaluefor', 'tool_reportbuilder', $formattedvalue));
    }
}