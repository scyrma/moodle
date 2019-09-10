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
 * Class system_report
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\output;

use tool_reportbuilder\report_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class system_report
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class system_report implements \templatable, \renderable {

    /** @var report_base */
    protected $report;

    /**
     * system_report constructor.
     *
     * @param string $source
     */
    public function __construct($source) {
        $this->report = $source;
    }

    /**
     * Export for template.
     *
     * @param \renderer_base $output
     *
     * @return array|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function export_for_template(\renderer_base $output) {
        $content = $this->report->export($output, false);
        return $content;
    }
}