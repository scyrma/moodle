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

namespace tool_program\output;

use renderable;
use renderer_base;
use templatable;
use tool_program\reportbuilder\local\systemreports\program_progress;

/**
 * Class program_user_report
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_progress_view implements templatable, renderable {

    /** @var int userid */
    private $userid;

    /** @var int $programid */
    private $programid;

    /**
     * Constructor
     *
     * @param int $programid
     * @param int $userid
     */
    public function __construct(int $programid, int $userid) {
        $this->programid = $programid;
        $this->userid = $userid;
    }

    /**
     * Implementation of exporter from templatable interface
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $params = ['userid' => $this->userid, 'programid' => $this->programid];
        $report = \tool_tenant\system_report_factory::create(program_progress::class, $params);
        $table = $report->output();

        $exporter['programslisttable'] = $table;
        return $exporter;
    }
}
