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
 * Class reportbuilder_filter_exporter
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\output;

use core\external\persistent_exporter;
use tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_reportbuilder\report_filter;

defined('MOODLE_INTERNAL') || die();

/**
 * Class reportbuilder_filter_exporter
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reportbuilder_filter_exporter extends persistent_exporter {

    /** @var \tool_reportbuilder\local\report\reportbuilder_filter The persistent object we will export. */
    protected $persistent = null;

    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class() {
        return \tool_reportbuilder\local\report\reportbuilder_filter::class;
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related() {
        return [
            'filtersdefinition' => \tool_reportbuilder\report_filter::class . '[]'
        ];
    }

    /**
     * Return the list of additional properties.

     * @return array
     */
    protected static function define_other_properties() {
        return [
            'key' => [
                'type' => PARAM_RAW
            ],
            'customheader' => [
                'type' => PARAM_RAW
            ],
            'classname' => [
                'type' => PARAM_NOTAGS
            ],
            'values' => [
                'type' => PARAM_RAW,
                'optional' => true,
                'multiple' => true
            ],
            'formattedheading' => [
                'type' => PARAM_RAW
            ]
        ];
    }

    /**
     * Checks if filter is valid (has a definition)
     * @return bool
     */
    public function is_valid() : bool {
        return array_key_exists($this->persistent->get_unique_identifier(), $this->related['filtersdefinition']);
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param \renderer_base $output
     *
     * @return array
     * @throws \coding_exception
     */
    protected function get_other_values(\renderer_base $output) {
        /** @var report_filter[] $filterssource */
        $filterssource = $this->related['filtersdefinition'];
        $key = $this->persistent->get_unique_identifier();
        $tmpl = filters_helper::get_header_inplace_editable($filterssource[$key], $this->persistent->get('heading'),
            $this->persistent->get('id'));
        $displayname = filters_helper::get_formatted_header($filterssource[$key], $this->persistent->get('heading'));
        return [
            'key' => $key,
            'customheader' => $output->render($tmpl),
            'classname' => $filterssource[$key]->get_classname(),
            'values' => $this->get_filter_values($key),
            'formattedheading' => $displayname
        ];
        // TODO SP-422 header in \tool_reportbuilder\local\report\reportbuilder_filter should have FORMAT_TEXT
        // In this class there should be proper formatter. In this case 'formattedheading' is not needed.
        // Also 'customheader' should be called 'editableheader'.
    }

    /**
     * Get the value and the operator from user preferences.
     *
     * @param string $uniqueidentifier
     * @return array $values All filter values.
     */
    private function get_filter_values(string $uniqueidentifier): array {
        $userpreference = 'filters_report_' . $this->persistent->get('reportid');
        $filtersvalues = json_decode(get_user_preferences($userpreference), true);

        if (empty($filtersvalues)) {
            return [];
        }

        // Match report filters for the current persistent.
        $values = array_filter($filtersvalues, static function(string $filterkey) use ($uniqueidentifier): bool {
            return strpos($filterkey, $uniqueidentifier) === 0;
        }, ARRAY_FILTER_USE_KEY);

        // Values should be a flat array, re-encode non-scalar values (e.g. array).
        $values = array_map(static function($value): string {
            return is_scalar($value) ? $value : json_encode($value);
        }, $values);

        return $values;
    }
}
