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
 * Class certifications_user_report.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\output;

use renderable;
use renderer_base;
use templatable;
use core_reportbuilder\local\filters\select;
use tool_certification\reportbuilder\local\systemreports\user_certifications;
use tool_tenant\system_report_factory;

/**
 * Class certifications_user_report
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certifications_user_report implements templatable, renderable {

    /**
     * @var int userid
     */
    private $userid;

    /**
     * @var int type
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
        $report = system_report_factory::create(user_certifications::class, ['userid' => $this->userid]);

        // Set initial certification status filter if type property is specified.
        if ($this->type !== -1) {
            $report->set_filter_values([
                'certification_user:filterablestatus_operator' => select::EQUAL_TO,
                'certification_user:filterablestatus_value' => $this->type,
            ]);
        }

        return ['certificationslisttable' => $report->output()];
    }
}
