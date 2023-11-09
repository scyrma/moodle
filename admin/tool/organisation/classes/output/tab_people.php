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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_organisation\output;

use context_system;
use renderer_base;
use tool_organisation\permission;
use tool_organisation\reportbuilder\local\systemreports\people;
use tool_tenant\system_report_factory;
use tool_wp\output\tab;

/**
 * Class tab_people
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tab_people extends tab {
    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('people', 'tool_organisation');
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
        return 'tool_organisation/people';
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $report = system_report_factory::create(people::class);
        $canassignjobs = permission::can_assign_job_to_anybody();
        $canassignmanagers = permission::has_assignmanuallymgr_capability();

        $bulkactions = [];
        $actions = get_string('actions');
        if ($canassignjobs) {
            $bulkactions['actions'][$actions]['addjobs'] = get_string('addajob', 'tool_organisation');
            $bulkactions['actions'][$actions]['setjobsfinished'] = get_string('setjobsfinished', 'tool_organisation');
        }
        if ($canassignmanagers) {
            $bulkactions['actions'][$actions]['assignmanagers'] = get_string('assignmanagermanually', 'tool_organisation');
            $bulkactions['actions'][$actions]['unassignmanager'] = get_string('unassignmanagers', 'tool_organisation');
        }
        if ($canassignjobs) {
            $bulkactions['actions'][$actions]['transferalltojob'] = get_string('transfertoanewjob', 'tool_organisation');
        }

        $select = new \single_select(new \moodle_url('#'), 'bulkactions', $bulkactions);
        $select->set_label(get_string('withselectedusers'));

        return [
            'tabheading' => $this->get_tab_label(),
            'peoplelist' => $report->output(),
            'systemcontextid' => context_system::instance()->id,
            'bulkactionsselect' => $select->export_for_template($output),
        ];
    }
}
