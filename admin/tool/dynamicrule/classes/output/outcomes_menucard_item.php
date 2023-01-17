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
 * outcomes_menucard_item renderable.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

use renderer_base;
use tool_dynamicrule\outcome_base;

/**
 * outcomes_menucard_item renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class outcomes_menucard_item implements \renderable, \templatable {

    /** @var \tool_dynamicrule\outcome_base */
    protected $outcome;

    /**
     * Constructor.
     *
     * @param outcome_base $outcome
     */
    public function __construct(outcome_base $outcome) {
        $this->outcome = $outcome;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $explodedclass = explode('\\', get_class($this->outcome));

        // Put together context for outcomes_menucard_item template.
        $params = [
            'title' => $this->outcome->get_title(),
            'configclass' => $explodedclass[0] . ':' . $explodedclass[3],
            'addlabel' => get_string('addoutcome', 'tool_dynamicrule'),
            'isavailable' => $this->outcome::is_available(),
            'notavailablelabel' => $this->outcome->get_not_available_label(),
        ];

        return $params;
    }
}
