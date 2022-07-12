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

namespace tool_organisation\output;

use renderer_base;
use tool_organisation\permission;
use tool_organisation\reportbuilder\local\systemreports\jobs;
use tool_tenant\system_report_factory;
use tool_wp\output\tab;

/**
 * Class tab_jobs
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tab_jobs extends tab {

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string($this->get_tab_id(), 'tool_organisation');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return mixed
     */
    public function is_available(): bool {
        return permission::can_view_jobs();
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_organisation/jobs';
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $rv = [
            'tabheading' => $this->get_tab_label(),
            'addbutton' => true,
            'addbuttontitle' => get_string('addjob', 'tool_organisation'),
            'addbuttonicon' => true,
        ];
        $deptforjobs = (new \tool_organisation\department_manager())->has_any_department_for_jobcreate();
        $posforjobs = (new \tool_organisation\position_manager())->has_any_position_for_jobcreate();
        if (!$deptforjobs && !$posforjobs) {
            $message = get_string('departmentandpositionrequiredforjobcreate', 'tool_organisation');
        } else if (!$deptforjobs) {
            $message = get_string('departmentrequiredforjobcreate', 'tool_organisation');
        } else if (!$posforjobs) {
            $message = get_string('positionrequiredforjobcreate', 'tool_organisation');
        }
        if (isset($message)) {
            $rv['warnings'][] = ['message' => $message, "closebutton" => 0, "announce" => 1];
            $rv['addbuttonattrs'] = [['name' => 'data-cancreatejobs', 'value' => 0]];
        } else {
            $rv['addbuttonattrs'] = [['name' => 'data-cancreatejobs', 'value' => 1]];
        }
        $report = system_report_factory::create(jobs::class);
        $rv['systemcontextid'] = \context_system::instance()->id;
        $rv['jobslist'] = $report->output();
        return $rv;
    }
}
