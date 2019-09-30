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
 * File for class certification_progress_report
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\output;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use renderable;
use renderer_base;
use templatable;
use tool_certification\local\reports\certification_progress;
use tool_reportbuilder\system_report_factory;

/**
 * Class certification_progress_report
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certification_progress_report implements templatable, renderable {
    /**
     * @var int $certificationid
     */
    private $certificationid;

    /**
     * Constructor
     *
     * @param int $certificationid
     */
    public function __construct(int $certificationid) {
        $this->certificationid = $certificationid;
    }

    /**
     * Implementation of exporter from templatable interface
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $params = ['certificationid' => $this->certificationid];

        // Check if tool_reportbuilder is installed.
        if (class_exists('\\tool_reportbuilder\\system_report_factory')) {
            // User allocations report.
            $report = system_report_factory::create(certification_progress::class, $params);
            $table = $report->output();
        } else {
            $str = get_string('reportbuilderuserallocations', 'tool_certification');
            $table = html_writer::tag('div', $str, ['class' => 'alert alert-warning']);
        }

        $context['table'] = $table;
        return $context;
    }
}