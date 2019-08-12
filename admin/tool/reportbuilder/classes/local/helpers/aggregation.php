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
 * Class aggregation
 *
 * @package     tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\helpers;

use core\output\inplace_editable;
use tool_reportbuilder\aggregation_base;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for reportbuilder aggregation
 *
 * @package     tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aggregation {

    /**
     * Inplace editable for columns aggregation
     *
     * @param null|string $currentvalue Current aggregation type
     * @param int $id ID of the column
     * @param string $formatedheader Visible heading
     * @param null|int $columntype Type of the column
     * @param array $disabledaggregations List of disabled aggregation types for this column
     * @return inplace_editable
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_aggregation_inplace_editable(?string $currentvalue,
            int $id, string $formatedheader, ?int $columntype, array $disabledaggregations) : inplace_editable {

        $inplace = new inplace_editable('tool_reportbuilder', 'aggregation', $id,
            true, // This function is only called after we checked that user can edit field.
            null, $currentvalue, get_string('selectaggregation', 'tool_reportbuilder', $formatedheader),
            get_string('newaggregationfor', 'tool_reportbuilder', $formatedheader));

        $aggregations = self::get_allowed_aggregations($columntype, $disabledaggregations);
        $inplace->set_type_select($aggregations);

        return $inplace;
    }

    /**
     * Helper function to check if aggregation is valid.
     *
     * @param string $aggregation Aggregation type
     * @return bool
     */
    public static function is_valid(string $aggregation) {
        $classaggre = "\\tool_reportbuilder\\local\aggregate\\$aggregation";
        return (class_exists($classaggre) && is_subclass_of($classaggre, aggregation_base::class));
    }

    /**
     * Helper function to check if aggregation supports sorting.
     *
     * @param string $aggregation Aggregation type
     * @param bool $columnissortable Sortable flag for the column.
     * @return bool
     */
    public static function is_sortable(string $aggregation, bool $columnissortable) {
        $classaggre = "\\tool_reportbuilder\\local\aggregate\\$aggregation";
        return $classaggre::is_sortable($columnissortable);
    }

    /**
     * Set the selected aggregation for a column.
     *
     * @param int $columnid Column ID for set the aggregation selected.
     * @param string $aggregation Aggregation type.
     * @return report_column
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function set_aggregation(int $columnid, string $aggregation) : report_column {
        if ($aggregation && !self::is_valid($aggregation)) {
            throw new \coding_exception("Invalid aggregation '$aggregation'.");
        }

        $columnpersistent = new \tool_reportbuilder\reportbuilder_column($columnid, null);
        $report = \tool_reportbuilder\manager::get_report($columnpersistent->get('reportid'));
        \tool_reportbuilder\permission::require_can_edit($report);
        $columnpersistent->set('aggregate', $aggregation);
        $columnpersistent->save();
        $key = $columnpersistent->get_unique_identifier();
        $column = $report->get_column($key);
        return $column;
    }

    /**
     * Get the aggregations
     *
     * @param null|int $dbtype Type of the column
     * @param array $disabledaggregations List of disabled aggregation types for this column
     * @return array
     * @throws \coding_exception
     */
    private static function get_allowed_aggregations(?int $dbtype, array $disabledaggregations) : array {
        $allowedaggregations = [
            '' => get_string('noaggregation', 'tool_reportbuilder'),
        ];

        $aggregations = \core_component::get_component_classes_in_namespace(
            'tool_reportbuilder',
            'local\\aggregate'
        );

        foreach (array_keys($aggregations) as $aggregation) {
            /** @var aggregation_base $classaggre */
            $classaggre = "\\" . $aggregation;
            if ($classaggre::is_compatible($dbtype) && !in_array($classaggre::get_shortname(), $disabledaggregations)) {
                $allowedaggregations[$classaggre::get_shortname()] = $classaggre::get_displayname();
            }
        }

        return $allowedaggregations;
    }

    /**
     * Get the SQL statement for the given aggregation
     *
     * @param null|string $aggregation Aggregation type
     * @param string $field
     * @param int|null $dbtype
     * @return string
     * @throws \coding_exception
     */
    public static function get_sql(?string $aggregation, string $field, int $dbtype = null) : string {
        if (!$aggregation) {
            return $field;
        }

        if (self::is_valid($aggregation)) {
            $classaggre = "\\tool_reportbuilder\\local\aggregate\\$aggregation";
            /** @var aggregation_base $aggregationinstance */
            $aggregationinstance = new $classaggre($field);
            return $aggregationinstance->get_field($field, $dbtype);
        } else {
            throw new \coding_exception("Invalid aggregation '$aggregation'.");
        }
    }
}