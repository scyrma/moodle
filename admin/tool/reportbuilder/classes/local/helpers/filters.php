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
 * Class filters
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use core\output\inplace_editable;
use tool_reportbuilder\report_base;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\local\report\reportbuilder_filter;
use tool_reportbuilder\output\reportbuilder_filter_exporter;
use core_text;

/**
 * Class filters
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class filters {

    /** @var int The size of each chunk, matching the maximum length of a single user preference */
    private const PREFERENCE_CHUNK_SIZE = 1333;
    /** @var string The prefix used to name the stored user preferences */
    private const PREFERENCE_NAME_PREFIX = 'filters_report_';

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
     * Generate user preference name for given report
     *
     * @param int $reportid
     * @param int $index
     * @return string
     */
    private static function user_preference_name(int $reportid, int $index): string {
        return static::PREFERENCE_NAME_PREFIX . "{$reportid}" . ($index > 0 ? "[$index]" : '');
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
     * Get available filters for a given report. That is, all filters that are not in use
     *
     * @param report_base $report
     * @param array $active
     * @return array
     */
    public static function get_available_filters(report_base $report, array $active) : array {
        $available = [];

        $activekeys = array_column($active, 'key');

        $filters = $report->get_filters();
        foreach ($report->get_entities() as $entity => $title) {
            $values = [];

            foreach ($filters as $filter) {
                $identifier = $filter->get_unique_identifier();

                // For each entity, we want to get all filters that aren't in use.
                if (strcasecmp($filter->get_entity(), $entity) !== 0 || in_array($identifier, $activekeys)) {
                    continue;
                }

                $values[] = [
                    'visiblename' => $filter->get_header(),
                    'value' => $identifier,
                ];
            }

            if (count($values) > 0) {
                $available[] = [
                    'optiongroup' => [
                        'text' => $title->out(),
                        'values' => $values,
                    ],
                ];
            }
        }

        return $available;
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
        $storedvalue = json_encode($values);
        $jsonchunks = str_split($storedvalue, static::PREFERENCE_CHUNK_SIZE);

        foreach ($jsonchunks as $index => $jsonchunk) {
            $userpreference = static::user_preference_name($reportid, $index);
            set_user_preference($userpreference, $jsonchunk);
        }

        // Ensure any subsequent preferences are reset (to account for number of chunks decreasing).
        $instance = new self($reportid);
        $instance->reset_all($index + 1);
        return true;
    }

    /**
     * Get filters from user preferences for the given report.
     *
     * @return array
     */
    public function get_report_filters(): array {
        $jsonvalues = '';
        $index = 0;

        // We'll repeatedly append chunks to our JSON string, until we hit one that is below the maximum length.
        do {
            $userpreference = static::user_preference_name($this->reportid, $index++);
            $jsonchunk = get_user_preferences($userpreference, '');
            $jsonvalues .= $jsonchunk;
        } while (core_text::strlen($jsonchunk) === static::PREFERENCE_CHUNK_SIZE);

        return (array) json_decode($jsonvalues, true);
    }

    /**
     * Add a filter to the given report, based on it's unique key.
     *
     * If the filter is already added it will return the existing filter
     *
     * Will throw an exception if filterkey can not be found
     *
     * @param report_base $report
     * @param string $filterkey
     * @return reportbuilder_filter
     *
     * @throws \moodle_exception
     */
    public static function add_filter_from_key(report_base $report, string $filterkey) : reportbuilder_filter {
        $filters = $report->get_filters();
        if (!array_key_exists($filterkey, $filters)) {
            throw new \moodle_exception('invalidfilter', 'tool_reportbuilder', '', null, $filterkey);
        }

        // Check this filter was not already added.
        $filter = $filters[$filterkey];
        if ($exists = reportbuilder_filter::get_record(
                ['reportid' => $report->get_id(), 'entity' => $filter->get_entity(), 'name' => $filter->get_name()])) {

            return $exists;
        }

        $record = new \stdClass();
        $record->reportid = $report->get_id();
        $record->entity = $filter->get_entity();
        $record->name = $filter->get_name();
        $record->heading = '';
        $record->sortorder = 1 + reportbuilder_filter::get_max_sortorder($report->get_id());

        $filterpersistent = new reportbuilder_filter(0, $record);
        $filterpersistent->save();

        return $filterpersistent;
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
     * @param reportbuilder_filter[] $activefilters
     * @param report_filter[] $reportfilters
     * @return array
     */
    public static function get_filters_with_data(array $activefilters, array $reportfilters): array {
        global $PAGE;

        $output = $PAGE->get_renderer('tool_reportbuilder');

        // Ensure the given filter is available for the current report.
        $filters = array_filter($reportfilters, function(report_filter $filter) {
            return $filter->get_is_available();
        });

        $filtersdata = array();
        foreach ($activefilters as $filter) {
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
     * @param int $index If specified, then preferences will be reset starting from this index
     * @return bool
     * @throws \coding_exception
     */
    public function reset_all(int $index = 0) : bool {
        // We'll repeatedly retrieve and reset preferences, until we hit one that is below the maximum length.
        do {
            $userpreference = static::user_preference_name($this->reportid, $index++);
            $jsonchunk = get_user_preferences($userpreference, '');
            unset_user_preference($userpreference);
        } while (core_text::strlen($jsonchunk) === static::PREFERENCE_CHUNK_SIZE);

        return true;
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
        unset($activefilter[$filterkey]);
        unset($activefilter[$filterkey.'_op']);
        return self::set_filter($this->reportid, (object) $activefilter);
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
