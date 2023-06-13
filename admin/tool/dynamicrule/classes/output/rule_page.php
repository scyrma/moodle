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

namespace tool_dynamicrule\output;

use renderer_base;
use tool_dynamicrule\permission;

use tool_wp\output\secondary_tabs;

/**
 * Class tool_dynamicrule\output\edit_page
 *
 * @package   tool_dynamicrule
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class rule_page implements \templatable, \renderable {
    /** @var \tool_wp\output\secondary_tabs */
    protected $tabsoutput;

    /**
     * rule_page constructor.
     *
     * @param int $ruleid ruleid
     */
    public function __construct(int $ruleid) {
        $data = ['ruleid' => $ruleid, 'id' => $ruleid];
        $attributes = $data + ['contextid' => (\context_system::instance())->id];
        $this->tabsoutput = new secondary_tabs($attributes, [
            new tab_ruleconditions($data),
            new tab_ruleoutcomes($data),
            new tab_ruledetails($data),
        ]);
    }

    /**
     * Implementation of exporter from templatable interface
     *
     * @param renderer_base $output
     *
     * @return array|\stdClass
     * @throws \coding_exception
     */
    public function export_for_template(renderer_base $output) {
        return $this->tabsoutput->export_for_template($output);
    }
}
