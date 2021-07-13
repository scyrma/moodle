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
 * Class columns
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use core\output\inplace_editable;
use tool_reportbuilder\event\report_updated;
use tool_reportbuilder\output\reportbuilder_column_exporter;
use tool_reportbuilder\report_base;
use tool_reportbuilder\report_column;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\reportbuilder_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for reportbuilder columns
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class columns {

    /**
     * Disable all column aggregation methods for databases that can't aggregate columns containing sub-queries
     *
     * @param report_column $column
     * @return void
     */
    public static function disable_column_aggregation(report_column $column) : void {
        foreach (['count', 'countdistinct', 'groupconcat', 'groupconcatdistinct', 'min', 'max', 'sum', 'avg'] as $aggregation) {
            $column->disable_aggregation($aggregation);
        }
    }

    /**
     * Get available columns for a given report
     *
     * @param report_base $report
     * @return array
     */
    public static function get_available_columns(report_base $report) : array {
        $available = [];

        $columns = $report->get_columns();
        foreach ($report->get_entities() as $entity => $title) {
            $values = [];

            foreach ($columns as $column) {
                $identifier = $column->get_unique_identifier();

                // For each entity, we want to get all columns that aren't in use.
                if (strcasecmp($column->get_entity(), $entity) !== 0) {
                    continue;
                }

                $values[] = [
                    'visiblename' => $column->get_visiblename(),
                    'value' => $identifier,
                ];
            }

            if (count($values) > 0) {
                $available[] = [
                    'optiongroup' => [
                        'text' => $title->out(),
                        'key' => $entity,
                        'values' => $values,
                    ],
                ];
            }
        }

        return $available;
    }

    /**
     * Add a column to the given report, based on it's unique key
     *
     * @param report_base $report
     * @param string $columnkey
     * @return reportbuilder_column
     *
     * @throws \moodle_exception
     */
    public static function add_column_from_key(report_base $report, string $columnkey) : reportbuilder_column {
        $columns = $report->get_columns();
        if (!array_key_exists($columnkey, $columns)) {
            throw new \moodle_exception('invalidcolumn', 'tool_reportbuilder', '', null, $columnkey);
        }

        $column = $columns[$columnkey];

        $record = new \stdClass();
        $record->reportid = $report->get_id();
        $record->entity = $column->get_entity();
        $record->name = $column->get_name();
        $record->columnorder = 1 + reportbuilder_column::get_max_columnorder($report->get_id(), 'columnorder');
        $record->sortorder = 1 + reportbuilder_column::get_max_columnorder($report->get_id(), 'sortorder');
        $record->heading = '';

        $columnpersistent = new reportbuilder_column(0, $record);
        $columnpersistent->save();

        // Trigger report updated event.
        $persistent = new reportbuilder($record->reportid);
        $event = report_updated::create_from_object($persistent);
        $event->trigger();

        return $columnpersistent;
    }

    /**
     * Remove a column from the report.
     *
     * @param report_base $report
     * @param int $columnid
     *
     * @throws \Exception
     * @throws \coding_exception
     */
    public static function remove_column(report_base $report, $columnid) {
        $columnpersistent = new reportbuilder_column($columnid, null);
        if ($columnpersistent->get('reportid') !== $report->get_id()) {
            throw new \Exception('The column does not match the report id');
        }
        $columnpersistent->delete();
    }

    /**
     * Column header for the report builder editor
     *
     * @param report_base $report
     * @param report_column $column
     * @return string
     */
    public static function get_original_header(report_base $report, report_column $column) {
        return $report->get_entities()[$column->get_entity()] . ': ' . $column->get_visiblename();
    }

    /**
     * Filter out and sort default columns.
     * @param report_column[] $columns
     */
    public static function fix_columns_default_columnorder(array $columns) {
        $defaultcolumns = array_filter($columns, function (report_column $c) {
            return $c->is_default();
        });
        self::sort_columns($defaultcolumns, function(report_column $c) {
            return $c->get_default_column_order();
        });
        $idx = 0;
        foreach ($defaultcolumns as $column) {
            $column->set_is_default(true, $idx++);
        }
    }

    /**
     * Filter out and sort sortable columns.
     * @param report_column[] $columns
     */
    public static function fix_columns_default_sortorder(array $columns) {
        $sortablecolumns = array_filter($columns, function (report_column $c) {
            return $c->get_is_sortable();
        });
        self::sort_columns($sortablecolumns, function(report_column $c) {
            return $c->get_default_sortorder();
        });
        $idx = 0;
        foreach ($sortablecolumns as $column) {
            $column->set_default_sortorder($idx++);
        }
    }

    /**
     * Sort columns by callback
     *
     * @param report_column[] $columns
     * @param callable $callback
     */
    protected static function sort_columns(array &$columns, callable $callback) {
        $indexes = array_flip(array_keys($columns));
        usort($columns, function (report_column $a, report_column $b) use ($callback, $indexes) {
            $sa = $callback($a);
            $sb = $callback($b);
            if ($sa !== null && $sb === null) {
                return -1;
            } else if ($sa === null && $sb !== null) {
                return 1;
            } else if ($sa != $sb) {
                return $sa - $sb;
            }
            return $indexes[$a->get_unique_identifier()] - $indexes[$b->get_unique_identifier()];
        });
    }

    /**
     * Get the columns of the report.
     *
     * @param report_base $report
     * @return reportbuilder_column[]
     */
    public static function get_active_columns(report_base $report) {
        /** @var reportbuilder_column[] $columns */
        $columns = reportbuilder_column::get_records(array('reportid' => $report->get_id()), 'columnorder, id');
        return $columns;
    }

    /**
     * Get the columns of the report.
     *
     * @param report_base $report
     * @return reportbuilder_column[]
     */
    public static function get_active_sortable_columns(report_base $report) {
        /** @var reportbuilder_column[] $columns */
        $columns = reportbuilder_column::get_records(['reportid' => $report->get_id()], 'sortorder, id');
        return array_filter($columns, function(reportbuilder_column $column) use ($report) {
            $reportcolumn = $report->get_column($column->get_unique_identifier());
            return $reportcolumn && $reportcolumn->get_is_sortable();
        });
    }

    /**
     * Formatted name for columns header
     *
     * @param report_column $column column definition
     * @param string $heading heading as entered by user
     * @param int $count
     * @return string
     */
    public static function get_formatted_header(report_column $column, string $heading, ?int $count = null) : string {
        if (strlen($heading)) {
            return format_string($heading, true, ['escape' => false]);
        } else {
            return !$count ? $column->get_visiblename() : $column->get_visiblename() . " $count";
        }
    }

    /**
     * Inplace editable for columns header
     *
     * @param string $displayvalue current heading
     * @param string $heading heading as entered by user
     * @param int $id
     *
     * @return inplace_editable
     */
    public static function get_header_inplace_editable(string $displayvalue, string $heading, int $id) : inplace_editable {
        return new inplace_editable('tool_reportbuilder', 'columnname', $id,
            true, // This function is only called after we checked that user can edit field.
            $displayvalue, $heading, get_string('customizeheader', 'tool_reportbuilder', $displayvalue),
            get_string('newvaluefor', 'tool_reportbuilder', $displayvalue));
    }

    /**
     * Return the columns and the sortable columns exported.
     *
     * @param report_base $report
     * @param \renderer_base $output
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function export(report_base $report, \renderer_base $output) {
        $columnsdata = [];
        $columnscount = [];
        $columnssorting = [];
        $columns = $report->get_active_columns();

        $related = [
            'reportuniqid' => $report->get_report_uniqid(),
            'context' => \context_system::instance()
        ];
        foreach ($columns as $column) {
            $columnkey = $column->get_unique_identifier();
            if (!$reportcolumn = $report->get_column($columnkey)) {
                continue;
            }
            isset($columnscount[$columnkey]) ? $columnscount[$columnkey]++ : $columnscount[$columnkey] = 0;
            $columnexported = self::export_column($column, $related +
                [
                    'reportcolumn' => $report->get_column($column->get_unique_identifier()),
                    'columncount' => $columnscount[$columnkey]
                ],
                $output
            );
            $columnsdata[] = $columnexported;
            if ($reportcolumn->get_is_sortable($columnexported->aggregate)) {
                $columnssorting[$column->get('sortorder')] = $columnexported;
            }
        }

        ksort($columnssorting);
        return [$columnsdata, $columnssorting];
    }

    /**
     * Get the sortable columns already exported.
     *
     * @param report_base $report
     * @param \renderer_base $output
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function export_sortable_columns(report_base $report, \renderer_base $output) {
        $columnsdata = [];
        $columnscount = [];
        $columns = self::get_active_sortable_columns($report);

        $related = [
            'reportuniqid' => $report->get_report_uniqid(),
            'context' => \context_system::instance()
        ];
        foreach ($columns as $column) {
            $columnkey = $column->get_unique_identifier();
            if (!$reportcolumn = $report->get_column($columnkey)) {
                continue;
            }
            isset($columnscount[$columnkey]) ? $columnscount[$columnkey]++ : $columnscount[$columnkey] = 0;
            $columnsdata[] = self::export_column($column, $related +
                [
                    'reportcolumn' => $report->get_column($column->get_unique_identifier()),
                    'columncount' => $columnscount[$columnkey]
                ],
            $output
            );
        }

        return $columnsdata;
    }

    /**
     * Export a column.
     *
     * @param reportbuilder_column $column
     * @param array $related
     * @param \renderer_base $output
     * @return \stdClass
     * @throws \coding_exception
     */
    private static function export_column(reportbuilder_column $column, array $related, \renderer_base $output) : \stdClass {
        $columnexporter = new reportbuilder_column_exporter($column, $related);
        return $columnexporter->export($output);
    }
}
