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
     * @param null|string $currentvalue Display value
     * @param int $id ID of the column
     * @param string $formatedheader Visible heading
     * @param int $columntype Type of the column
     * @return inplace_editable
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_aggregation_inplace_editable(?string $currentvalue, int $id, string $formatedheader,
                                                            ?int $columntype) : inplace_editable {
        $inplace = new inplace_editable('tool_reportbuilder', 'aggregation', $id,
            has_capability('tool/reportbuilder:edit', \context_system::instance()),
            null, $currentvalue, get_string('selectaggregation', 'tool_reportbuilder', $formatedheader),
            get_string('newaggregationfor', 'tool_reportbuilder', $formatedheader));

        $aggregations = self::get_allowed_aggregations($columntype);
        $inplace->set_type_select($aggregations);

        return $inplace;
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
        $columnpersistent = new \tool_reportbuilder\reportbuilder_column($columnid, null);
        $report = \tool_reportbuilder\manager::get_report($columnpersistent->get('reportid'));
        \tool_reportbuilder\permission::require_can_edit($report->get_id());
        $columnpersistent->set('aggregate', $aggregation);
        $columnpersistent->save();
        $key = $columnpersistent->get_unique_identifier();
        $column = $report->get_column($key);
        return $column;
    }

    /**
     * Get the aggregations
     *
     * @param int $dbtype
     * @return array
     * @throws \coding_exception
     */
    private static function get_allowed_aggregations(?int $dbtype) : array {
        $allowedaggregations = [
            '' => get_string('noaggregation', 'tool_reportbuilder'),
        ];

        $aggregates = \core_component::get_component_classes_in_namespace(
            'tool_reportbuilder',
            'local\\aggregate'
        );

        $founded = array_keys($aggregates);
        foreach ($founded as $aggregate) {
            /** @var aggregation_base $classaggre */
            $classaggre = "\\" . $aggregate;
            if ($classaggre::is_compatible($dbtype)) {
                $allowedaggregations[$classaggre::get_shortname()] = $classaggre::get_displayname();
            }
        }

        return $allowedaggregations;
    }

    /**
     * Get the SQL statement for the given aggregate function
     *
     * @param null|string $aggrefunction
     * @param string $field
     * @param int|null $dbtype
     * @return string
     * @throws \coding_exception
     */
    public static function get_sql(?string $aggrefunction, string $field, int $dbtype = null) : string {
        if (!$aggrefunction) {
            return $field;
        }
        $classaggre = "\\tool_reportbuilder\\local\aggregate\\$aggrefunction";
        if (class_exists($classaggre) && is_subclass_of($classaggre, aggregation_base::class)) {
            /** @var aggregation_base $aggregation */
            $aggregation = new $classaggre($field);
            return $aggregation->get_field($field, $dbtype);
        } else {
            throw new \coding_exception('Aggregation function not supported');
        }
    }

    /**
     * This aggregate functions does not allow sort at same time
     * @return array
     */
    public static function not_allow_sort() : array {
        // TODO: constansts.
        return ['count', 'avg', 'sum', 'min', 'countdistinct'];
    }
}