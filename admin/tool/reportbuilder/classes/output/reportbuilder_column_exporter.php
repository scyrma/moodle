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
 * Class for column exporter.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\output;

use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Class reportbuilder_column_exporter
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reportbuilder_column_exporter extends \core\external\persistent_exporter {

    /** @var \tool_reportbuilder\reportbuilder_column The persistent object we will export. */
    protected $persistent = null;

    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class() {
        return \tool_reportbuilder\reportbuilder_column::class;
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
            'enabledsorting' => [
                'type' => PARAM_BOOL
            ],
            'sorticon' => [
                'type' => PARAM_ALPHAEXT
            ],
            'formattedheading' => [
                'type' => PARAM_RAW
            ],
            'movetitle' => [
                'type' => PARAM_RAW
            ],
            'sortdirectionlabel' => [
                'type' => PARAM_RAW
            ],
            'sortenabledlabel' => [
                'type' => PARAM_RAW
            ]
        ];
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related() {
        // TODO SP-422 we don't need reportuniqueid and context in this exporter.
        return [
            'reportuniqid' => 'string',
            'context' => 'context',
            'reportcolumn' => report_column::class,
            'columncount' => 'int'
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param \renderer_base $output
     *
     * @return array Keys are the property names, values are their values.
     * @throws \coding_exception
     */
    protected function get_other_values(\renderer_base $output) {
        $displayname = columns::get_formatted_header($this->related['reportcolumn'], $this->persistent->get('heading'),
            $this->related['columncount']);
        $isdesc = ($this->persistent->get('sortdirection') == SORT_DESC);
        return [
            'key' => $this->persistent->get_unique_identifier(),
            'enabledsorting' => $this->persistent->get('sortenabled') ? true : false,
            'sorticon' => $isdesc ? 'sort_desc' : 'sort_asc',
            'formattedheading' => $displayname,
            'movetitle' => get_string('movesorting', 'tool_reportbuilder', $displayname),
            'sortdirectionlabel' => $isdesc ? get_string('desc', 'tool_reportbuilder', $displayname)
                : get_string('asc', 'tool_reportbuilder', $displayname),
            'sortenabledlabel' => get_string('enablesortingon', 'tool_reportbuilder', $displayname)
        ];
    }
}
