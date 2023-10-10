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

namespace tool_wp\reportbuilder\local\exportimport;

use core_reportbuilder\local\filters\base;
use core_reportbuilder\local\report\filter;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use core_reportbuilder\local\filters\course_selector;

/**
 * Implementations of conditions export-import mapping for the filters defined outside of Workplace
 *
 * @package   tool_wp
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class condition_mapping_helper {

    /**
     * Add condition values mapping during export
     *
     * For filters defined in Workplace that implement interface {@see condition_with_mapping} it calls method
     * from this interface.
     *
     * For filters defined in core (such as course_selector or course category selector) it calls
     * our own method.
     *
     * @param filter $condition
     * @param exporter_base $exporter
     * @param array $values all condition values for the report (may also contain values for other conditions)
     * @return void
     */
    public static function add_exporter_mapping(filter $condition, exporter_base $exporter, array $values) {
        $newinstance = base::create($condition);
        if ($newinstance instanceof condition_with_mapping) {
            $newinstance->add_exporter_mapping($exporter, $values);
        } else if ($newinstance instanceof course_selector) {
            self::course_selector_add_exporter_mapping($newinstance, $exporter, $values);
        }
    }

    /**
     * Substitute condition values with the mapped values during import
     *
     * For filters defined in Workplace that implement interface {@see condition_with_mapping} it calls method
     * from this interface.
     *
     * For filters defined in core (such as course_selector or course category selector) it calls
     * our own method.
     *
     * @param filter $condition
     * @param importer_base $importer
     * @param array $values all condition values for the report (may also contain values for other conditions)
     */
    public static function get_importer_mapping(filter $condition, importer_base $importer, array &$values): void {
        $newinstance = base::create($condition);
        if ($newinstance instanceof condition_with_mapping) {
            $newinstance->get_importer_mapping($importer, $values);
        } else if ($newinstance instanceof course_selector) {
            self::course_selector_get_importer_mapping($newinstance, $importer, $values);
        }
    }

    /**
     * Add course ID condition field mapping during export
     *
     * @param course_selector $filter
     * @param exporter_base $exporter
     * @param array $values all condition values for the report (may also contain values for other conditions)
     */
    protected static function course_selector_add_exporter_mapping(course_selector $filter,
                                                                   exporter_base $exporter, array $values): void {
        $name = $filter->get_filter_persistent()->get('uniqueidentifier');
        $courseids = $values["{$name}_values"] ?? [];
        foreach ($courseids as $courseid) {
            $exporter->add_mapping('course', $courseid);
        }
    }

    /**
     * Get course ID condition field mapping during import
     *
     * @param course_selector $filter
     * @param importer_base $importer
     * @param array $values all condition values for the report (may also contain values for other conditions)
     */
    protected static function course_selector_get_importer_mapping(course_selector $filter,
                                                                   importer_base $importer, array &$values) {
        $name = $filter->get_filter_persistent()->get('uniqueidentifier');
        if (!empty($values["{$name}_values"])) {
            $values["{$name}_values"] = array_map(static function(int $courseid) use ($importer): int {
                return $importer->get_mapping('course', $courseid, IGNORE_MISSING) ?? 0;
            }, $values["{$name}_values"]);
        }
    }
}
