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
 * Class reportbuilder_condition_exporter
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\output;

use tool_reportbuilder\local\models\reportbuilder_conditions;
use tool_reportbuilder\report_filter;

defined('MOODLE_INTERNAL') || die();

/**
 * Class reportbuilder_condition_exporter
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reportbuilder_condition_exporter extends \core\external\persistent_exporter {

    /** @var reportbuilder_conditions The persistent object we will export. */
    protected $persistent;

    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class() {
        return reportbuilder_conditions::class;
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related() {
        return [
            'conditionsdefinition' => \tool_reportbuilder\report_filter::class . '[]'
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
            'default' => [
                'type' => PARAM_INT,
                'optional' => true
            ],
        ];
    }

    /**
     * Get the context fot the alias field.
     * @return array
     * @throws \dml_exception
     */
    protected function get_format_parameters_for_alias() {
        return [
            'context' => \context_system::instance()
        ];
    }

    /**
     * Checks if filter is valid (has a definition)
     * @return bool
     */
    public function is_valid() : bool {
        return array_key_exists($this->persistent->get_unique_identifier(), $this->related['conditionsdefinition']);
    }

    /**
     * Get the additional values to inject while exporting.
     * @param \renderer_base $output
     *
     * @return array
     * @throws \coding_exception
     */
    protected function get_other_values(\renderer_base $output) {
        /** @var report_filter[] $conditionssource */
        $conditionssource = $this->related['conditionsdefinition'];
        $key = $this->persistent->get_unique_identifier();

        return [
            'key' => $key,
            'customheader' => $this->persistent->get('heading'), // TODO SP-422 What is the point of duplicating the property?
            'classname' => $conditionssource[$key]->get_classname(),
            'default' => 1,
        ];
        // TODO SP-422 pretty sure neither of headers is used.
    }
}
