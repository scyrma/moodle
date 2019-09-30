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
 * Class report
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\output;

use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class report_view
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_view implements \templatable, \renderable {

    /** @var report_base */
    protected $report;

    /** @var bool $editon */
    protected $editon;

    /** @var int $reportid */
    protected $reportid;
    /**
     * report_view constructor.
     *
     * @param int $reportid
     * @param int $editon
     *
     * @throws \coding_exception
     */
    public function __construct($reportid, $editon) {
        $this->report = manager::get_report($reportid);
        $this->editon = $editon;
        $this->reportid = $reportid;
    }

    /**
     * Export the context for a template.
     *
     * @param \renderer_base $output
     *
     * @return array|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function export_for_template(\renderer_base $output) {
        $content = $this->report->export($output, (int)$this->editon);
        return $content;
    }
}