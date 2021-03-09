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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * File for class programs_user_report
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\output;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use renderable;
use renderer_base;
use templatable;
use tool_program\local\reports\programs_progress_report;
use tool_reportbuilder\local\helpers\filters;
use tool_reportbuilder\system_report_factory;

/**
 * Class programs_user_report
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programs_progress_view implements templatable, renderable {

    /**
     * @var int userid
     */
    private $userid;

    /**
     * @var int $type Status filter: -1=no filter, 0=active, 1=overdue, 2=completed
     */
    private $type;

    /**
     * Constructor
     *
     * @param int $userid
     * @param int $type
     */
    public function __construct(int $userid, int $type = -1) {
        $this->userid = $userid;
        $this->type = $type;
    }

    /**
     * Implementation of exporter from templatable interface
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $params = ['userid' => $this->userid, 'type' => $this->type];

        // Check if tool_reportbuilder is installed.
        if (class_exists('\\tool_reportbuilder\\system_report_factory')) {
            // User allocations report.
            $report = system_report_factory::create(programs_progress_report::class, $params);

            if (-1 !== $this->type) {
                $reportid = $report->get_id();
                $filter = new \stdClass();
                $filter->{'tool_program_users:filterablestatus_op'} = 1;
                $filter->{'tool_program_users:filterablestatus'} = $this->type;
                filters::set_filter($reportid, $filter);
            }

            $table = $report->output();
        } else {
            $str = get_string('reportbuilderuserallocations', 'tool_program');
            $table = html_writer::tag('div', $str, ['class' => 'alert alert-warning']);
        }

        $exporter['programslisttable'] = $table;
        return $exporter;
    }
}
