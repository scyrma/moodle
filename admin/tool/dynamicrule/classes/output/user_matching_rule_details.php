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

/**
 * user_matching_rule_details renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_matching_rule_details implements \renderable, \templatable {

    /** @var int */
    protected $matchingid;

    /**
     * Constructor.
     *
     * @param int $matchingid
     */
    public function __construct(int $matchingid) {
        $this->matchingid = $matchingid;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $DB;

        $matchrecord = $DB->get_record('tool_dynamicrule_match', ['id' => $this->matchingid]);
        $errordata = !empty($matchrecord->errordata) ? json_decode($matchrecord->errordata, true) : [];
        $outcomes = [];
        foreach (\tool_dynamicrule\api::get_rule_outcomes($matchrecord->ruleid) as $outcome) {
            if (array_key_exists($outcome->get_id(), $errordata)) {
                $statusclass = 'tool_dynamicrule_match_status_error';
                $statustitle = get_string('matchstatuserror', 'tool_dynamicrule');
                $debugmessage = $errordata[$outcome->get_id()]['message'];
                $debugtrace = $errordata[$outcome->get_id()]['trace'];
            } else {
                $statusclass = 'tool_dynamicrule_match_status_done';
                $statustitle = get_string('matchstatusdone', 'tool_dynamicrule');
                $debugmessage = '';
                $debugtrace = '';
            }
            $outcomes[] = [
                'outcomeid' => $outcome->get_id(),
                'title' => $outcome->get_title(),
                'statusclass' => $statusclass,
                'statustitle' => $statustitle,
                'debugmessage' => $debugmessage,
                'debugtrace' => $debugtrace,
            ];
        }

        return ['outcomes' => $outcomes, 'debug' => debugging()];
    }
}
