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
 * Class reportbuilder_condition_exporter
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\output;

use tool_reportbuilder\local\models\reportbuilder_conditions;
use tool_reportbuilder\report_filter;

defined('MOODLE_INTERNAL') || die();

/**
 * Class reportbuilder_condition_exporter
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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