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
 * Class tab_jobs
 *
 * @package     tool_organisation
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation\output;

use renderer_base;
use tool_organisation\department_manager;
use tool_organisation\jobs_list;
use tool_organisation\permission;
use tool_organisation\position_manager;
use tool_reportbuilder\system_report_factory;
use tool_wp\output\tab;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tab_jobs
 *
 * @package     tool_organisation
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        return permission::can_view_jobs() &&
            \tool_organisation\job_manager::is_tab_available();
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
            'addbuttontitle' => get_string('addjob', 'tool_organisation'),
        ];
        $report = system_report_factory::create(jobs_list::class);
        $rv['systemcontextid'] = \context_system::instance()->id;
        $rv['jobslist'] = $report->output();
        return $rv;
    }
}
