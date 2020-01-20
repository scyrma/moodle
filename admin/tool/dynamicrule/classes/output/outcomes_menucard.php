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
 * outcomes_menucard renderable.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;

/**
 * outcomes_menucard renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class outcomes_menucard implements \renderable, \templatable {

    /** @var \tool_dynamicrule\outcome_base[] */
    protected $outcomes;

    /** @var string */
    protected $categoryid;

    /** @var string */
    protected $categorytitle;

    /**
     * Constructor.
     *
     * @param string $categoryid
     * @param string $categorytitle
     * @param array $outcomes
     */
    public function __construct($categoryid, $categorytitle, $outcomes) {
        $this->categoryid = $categoryid;
        $this->categorytitle = $categorytitle;
        $this->outcomes = $outcomes;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $menucarditems = array_map(function(\tool_dynamicrule\outcome_base $outcome) use ($output) {
            return (new outcomes_menucard_item($outcome))->export_for_template($output);
        }, $this->outcomes);

        // Put together context for outcomes_menucard template.
        $params = [
            'categoryid' => $this->categoryid,
            'categorytitle' => $this->categorytitle,
            'menucarditems' => $menucarditems,
        ];

        return $params;
    }
}
