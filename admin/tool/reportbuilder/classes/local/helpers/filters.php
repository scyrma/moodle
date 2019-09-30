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
 * Class filters
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\helpers;

use core\output\inplace_editable;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\local\report\reportbuilder_filter;
use tool_reportbuilder\output\reportbuilder_filter_exporter;

defined('MOODLE_INTERNAL') || die();

/**
 * Class filters
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filters {

    /** @var int $reportid */
    protected $reportid;

    /**
     * filters constructor.
     * @param int $reportid
     */
    public function __construct(int $reportid) {
        $this->reportid = $reportid;
    }

    /**
     * Get the active filters of the report.
     *
     * @param int $reportid
     *
     * @return reportbuilder_filter[]
     */
    public static function get_active_filters($reportid) {
        return reportbuilder_filter::get_records(array('reportid' => $reportid), 'sortorder', 'ASC');
    }

    /**
     * Add filter to user preferences for the given report.
     *
     * @param int $reportid ID of the report
     * @param null|\stdClass $values
     * @return bool
     * @throws \coding_exception
     */
    public static function set_filter(int $reportid, ?\stdClass $values) : bool {
        $key = 'filters_report_' . $reportid;
        return set_user_preference($key, json_encode($values));
    }

    /**
     * Get filters from user preferences for the given report.
     *
     * @return array
     * @throws \coding_exception
     */
    public function get_report_filters(): ?array {
        $key = 'filters_report_' . $this->reportid;
        $jsonfilters = get_user_preferences($key, json_encode([]));
        return json_decode($jsonfilters, true);
    }

    /**
     * Add a filter to the given report.
     *
     * @param int $reportid The report to add the filter
     * @param report_filter $filter The filter to add
     * @return int
     * @throws \dml_exception
     */
    public static function add_filter(int $reportid, report_filter $filter) : int {
        $record = new \stdClass();
        $record->reportid = $reportid;
        $record->entity = $filter->get_entity();
        $record->name = $filter->get_name();
        $record->heading = '';
        $record->sortorder = 1 + reportbuilder_filter::get_max_sortorder($reportid);

        $filterpersistent = new reportbuilder_filter(0, $record);
        $filterpersistent->save();
        return $filterpersistent->get('id');
    }

    /**
     * Get a filter.
     *
     * @param int $reportid
     * @param report_filter $filter
     * @return reportbuilder_filter|false
     */
    public static function get_filter(int $reportid, report_filter $filter) {
        $params = [
            'reportid' => $reportid,
            'entity' => $filter->get_entity(),
            'name' => $filter->get_name(),
        ];
        return reportbuilder_filter::get_record($params);
    }

    /**
     * Get the filters exported.
     *
     * @param array $filtersinuse
     * @param \tool_reportbuilder\report_filter[] $filters
     * @return array
     * @throws \coding_exception
     */
    public static function get_filters_with_data(array $filtersinuse, $filters) {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_reportbuilder');

        $filtersdata = array();
        foreach ($filtersinuse as $filter) {
            $filterexporter = new reportbuilder_filter_exporter($filter,
                [
                    'filtersdefinition' => $filters
                ]
            );
            if (!$filterexporter->is_valid()) {
                continue;
            }
            $filterdata = $filterexporter->export(
                $output
            );
            $filtersdata[$filterdata->key] = $filterdata;
        }

        return $filtersdata;
    }

    /**
     * Reset all filters for the current user and the given report.
     *
     * @return bool
     * @throws \coding_exception
     */
    public function reset_all() : bool {
        $key = 'filters_report_' . $this->reportid;
        return unset_user_preference($key);
    }

    /**
     * Reset the given filter for the current user.
     *
     * @param int $filterid The filter to be reset.
     * @return bool
     * @throws \coding_exception
     */
    public function reset_filter(int $filterid) {
        $filterkey = self::get_filter_key_from_id($filterid);
        $activefilter = self::get_report_filters();
        $name = 'filters_report_' . $this->reportid;
        unset($activefilter[$filterkey]);
        unset($activefilter[$filterkey.'_op']);

        return set_user_preference($name, json_encode($activefilter));
    }

    /**
     * Get the key of the given filter.
     *
     * @param int $id
     * @return string
     * @throws \coding_exception
     */
    private static function get_filter_key_from_id(int $id) {
        /** @var reportbuilder_filter $persistent */
        $persistent = reportbuilder_filter::get_record(array('id' => $id));
        return $persistent->get_unique_identifier();
    }

    /**
     * Formatted name for filter header
     *
     * @param report_filter $filter filter definition
     * @param string $heading heading as entered by user
     *
     * @return string
     */
    public static function get_formatted_header(report_filter $filter, string $heading) : string {
        if (strlen($heading)) {
            return format_string($heading, true, ['escape' => false]);
        } else {
            return $filter->get_header();
        }
    }

    /**
     * Inplace editable for filters
     *
     * @param report_filter $filter filter definition
     * @param string $heading heading as entered by user
     * @param int $id
     *
     * @return inplace_editable
     */
    public static function get_header_inplace_editable(report_filter $filter, string $heading, int $id) : inplace_editable {
        if (strlen($heading)) {
            $displayvalue = format_string($heading, true, ['escape' => false]);
        } else {
            $displayvalue = $filter->get_header();
        }
        return new \core\output\inplace_editable('tool_reportbuilder', 'filtername', $id,
            true,  // This function is only called after we checked that user can edit field.
            $displayvalue, $heading, get_string('customizefilter', 'tool_reportbuilder'),
            get_string('newvaluefor', 'tool_reportbuilder', $displayvalue));
    }
}