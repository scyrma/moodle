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
 * Manager class.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * Class manager
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {

    /**
     * Create new reportbuilder persistent instance
     *
     * @param stdClass|array $formdata
     * @param bool $currenttenant Whether to create the report in the current tenant. Unit tests and imported can disable this
     * @return reportbuilder
     */
    public static function save_report($formdata, bool $currenttenant = true): reportbuilder {
        global $USER;

        // Cast formdata, check whether default configuration should be added later.
        $formdata = (object) $formdata;
        $adddefault = !empty($formdata->adddefault);

        // Create the report's unique idnumber.
        if (!isset($formdata->idnumber)) {
            $formdata->idnumber = self::generate_idnumber();
        }

        // Use the current tenant if not specified in form data or $currenttenant is true.
        if (empty($formdata->tenantid) || $currenttenant) {
            $formdata->tenantid = tenancy::get_tenant_id();
        }

        // Shared can only be active if set in the form in the shared space.
        $formdata->shared = $formdata->shared ?? 0;
        $formdata->shared = (sharedspace::is_shared_space($formdata->tenantid) && $formdata->shared) ? 1 : 0;

        $formdata->usercreated = $USER->id;

        // Create persistent, trigger creation event.
        $persistent = (new reportbuilder(0, $formdata))->create();
        report_created::create_from_object($persistent)->trigger();

        if ($adddefault) {
            self::add_default_configuration($persistent);
        }

        return $persistent;
    }

    /**
     * Generate a unique ID number for a report
     *
     * @return string
     */
    protected static function generate_idnumber(): string {
        do {
            $idnumber = uniqid();
        } while (reportbuilder::get_record(['idnumber' => $idnumber]) !== false);

        return $idnumber;
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
     * Verify that report source exists and extends appropriate base classes
     *
     * @param string $classname
     * @param string $additionalbaseclass Specify addition base class that given classname should extend
     * @return bool
     */
    public static function report_source_exists(string $classname, string $additionalbaseclass = ''): bool {
        return (class_exists($classname) && is_subclass_of($classname, report_base::class) &&
            (empty($additionalbaseclass) || is_subclass_of($classname, $additionalbaseclass)));
    }

    /**
     * Verify given report source is available. Note that it is assumed caller has already checked that it exists
     *
     * @param string $classname
     * @return bool
     */
    public static function report_source_available(string $classname): bool {
        return call_user_func([$classname, 'is_available']);
    }

    /**
     * Verify that report source both exists and is available
     *
     * @param string $classname
     * @param string $additionalbaseclass Specify addition base class that given classname should extend
     * @return bool
     */
    public static function report_source_valid(string $classname, string $additionalbaseclass = ''): bool {
        return self::report_source_exists($classname, $additionalbaseclass) && self::report_source_available($classname);
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

        // Throw exception for missing or unavailable report source.
        if (!self::report_source_exists($classname)) {
            throw new \moodle_exception('errormissingreportsource', 'tool_reportbuilder', '', null, $classname);
        } else if (!self::report_source_available($classname)) {
            throw new \moodle_exception('errorunavailablereportsource', 'tool_reportbuilder', '', null, $classname);
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
     * @return int|bool
     */
    public static function update_report($data) {
        $persistent = new reportbuilder($data->id);
        if (property_exists($data, 'description')) {
            $persistent->set('description', $data->description);
        }
        if (property_exists($data, 'shared')) {
            $persistent->set('shared', $data->shared);
        }
        $persistent->set('name', $data->name);
        $event = report_updated::create_from_object($persistent);

        // There is no need to perform this check, the method either returns true or throws an exception.
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
     * @param report_base     $report
     *
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public static function check_columns(report_base $report) : void {
        $reportid = $report->get_id();
        $activecolumns = $report->get_active_columns();
        $columns = $report->get_columns();

        foreach ($columns as $column) {
            // Find if the column with this identifier is already active.
            $record = null;
            foreach ($report->get_active_columns() as $activecolumn) {
                if ($column->get_unique_identifier() === $activecolumn->get_unique_identifier()) {
                    $record = $activecolumn;
                    break;
                }
            }

            if (!$record) {
                // Add this column as active.
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
                $columnpersistent = new reportbuilder_column(0, $data);
                $columnpersistent->save();
            } else {
                // Make sure the existing active column has the same definition.
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

                if ($record->get('columnorder') != $oldrecord->columnorder ||
                        $record->get('sortorder') != $oldrecord->sortorder ||
                        $record->get('sortdirection') != $oldrecord->sortdirection ||
                        $record->get('sortenabled') != $oldrecord->sortenabled ||
                        '' . $record->get('heading') !== '' . $oldrecord->heading) {
                    $record->update();
                }
            }
        }

        // Delete unused columns.
        foreach ($activecolumns as $activecolumn) {
            $key = $activecolumn->get_unique_identifier();
            if (!array_key_exists($key, $columns)) {
                $activecolumn->delete();
            }
        }
    }

    /**
     * Check if a default filter need to be added.
     *
     * @param report_base $report
     * @return void
     */
    public static function check_filters(report_base $report) : void {
        $activefilters = $report->get_active_filters();
        $filters = $report->get_filters();

        foreach ($filters as $filter) {
            if ($filter->is_default()) {
                // Check if the filter with this identifier is already added.
                $record = null;
                foreach ($activefilters as $activefilter) {
                    if ($filter->get_unique_identifier() === $activefilter->get_unique_identifier()) {
                        $record = $activefilter;
                        break;
                    }
                }
                // If not found, add it.
                if (!$record) {
                    filters_helper::add_filter_from_key($report, $filter->get_unique_identifier());
                }
            }
        }

        // Delete old filters.
        foreach ($activefilters as $activefilter) {
            $key = $activefilter->get_unique_identifier();
            if (!array_key_exists($key, $filters)) {
                $activefilter->delete();
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
     * @return array
     */
    public static function get_tabs($reportid) {
        global $OUTPUT;

        $attributestab = ['id' => 'report-builder', 'reportid' => $reportid];
        $tabsoutput = new \tool_wp\output\tabs($attributestab);

        $report = new \tool_reportbuilder\reportbuilder($reportid);

        $data = ['reportid' => $reportid];
        $tabsoutput->add_tab(new \tool_reportbuilder\output\tabs\table($data));
        if ($report->get('tenantid') == tenancy::get_tenant_id()) {
            $tabsoutput->add_tab(new \tool_reportbuilder\output\tabs\schedule($data));
            if (!sharedspace::is_shared_space()) {
                $tabsoutput->add_tab(new \tool_reportbuilder\output\tabs\audience($data));
            }
        }
        $tabsoutput->add_tab(new \tool_reportbuilder\output\tabs\access($data));

        return $tabsoutput->export_for_template($OUTPUT);
    }

    /**
     * Inplace editable for columns header
     *
     * @param string $name name as entered by user
     * @param int $id
     * @param bool $canedit User can edit report OR can edit and switch to the parent tenant report
     * @param bool $caneditname User can edit report name
     *
     * @return inplace_editable
     */
    public static function get_name_inplace_editable(string $name, int $id, bool $canedit, bool $caneditname) : inplace_editable {
        $displayvalue = $formattedvalue = format_string($name);
        if ($canedit) {
            $url = new \moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $id]);
            $displayvalue = \html_writer::link($url, $displayvalue);
        } else {
            $url = new \moodle_url('/admin/tool/reportbuilder/view.php', ['id' => $id]);
            $displayvalue = \html_writer::link($url, $displayvalue);
        }
        return new inplace_editable('tool_reportbuilder', 'reportname', $id,
            $caneditname,
            $displayvalue, $name, get_string('editreportname', 'tool_reportbuilder'),
            get_string('newvaluefor', 'tool_reportbuilder', $formattedvalue));
    }
}
