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
 * Class program_users_list
 *
 * @package     tool_program
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_program\api;
use tool_program\constants;
use tool_program\local\reports\allocations_report;
use tool_program\persistent\program;
use tool_reportbuilder\system_report_factory;

/**
 * Class program_users_list
 *
 * @package     tool_program
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_users_list implements \renderable , \templatable {

    /** @var allocations_report $userreport The system report of program users. */
    protected $userreport;

    /** @var program $program The program instance. */
    protected $program;

    /**
     * program_users_list constructor.
     *
     * @param program $program The program instance
     */
    public function __construct(program $program) {
        $this->program = $program;

        // Check if tool_reportbuilder is installed.
        if (class_exists('\\tool_reportbuilder\\system_report_factory')) {
            // Users list.
            $report = system_report_factory::create(allocations_report::class, ['id' => $program->get('id')]);
            $this->userreport = $report->output();
        } else {
            $str = get_string('reportbuilderuserlist', 'tool_program');
            $this->userreport = \html_writer::tag('div', $str, ['class' => 'alert alert-warning']);
        }
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $params = [];
        $context = \context_system::instance();

        $canshowbutton = \tool_program\permission::can_allocate_anybody($this->program, false, false);
        if ($canshowbutton) {
            $attributes = [];
            $params['adduser'] = true;
            $params['addbutton'] = true;
            $params['addbuttontitle'] = get_string('allocateusers', 'tool_program');
            $params['addbuttonicon'] = false;
            $params['systemcontextid'] = $context->id;

            if ((int)$this->program->get('allowdirectallocation') === 0 || !api::is_allocation_window_open($this->program)) {
                // Get strings and date for allocation window closed modal.
                $attributes = $this->get_data_for_modal($this->program);
            }
            foreach ($attributes as $key => $value) {
                if ($key === 'class') {
                    $params['addbuttonclasses'] .= ' ' . $value;
                    continue;
                }
                $params['addbuttonattrs'][] = ['name' => $key, 'value' => $value];
            }
        }

        $params['userslist'] = $this->userreport;
        $params['programid'] = $this->program->get('id');
        $params['bulkactionsselect'] = $this->get_bulk_actions($output);

        return $params;
    }

    /**
     * Return bulk actions selector
     *
     * @param renderer_base $output
     * @return \stdClass
     * @throws \coding_exception
     */
    private function get_bulk_actions(renderer_base $output): \stdClass {
        $bulkactions = [];

        $actions = get_string('actions');
        $bulkactions['actions'][$actions]['editstatusanddates'] = get_string('editstatusanddates', 'tool_program');
        if (has_capability('tool/program:coursereset', \context_system::instance())) {
            $bulkactions['actions'][$actions]['resetusersprogram'] = get_string('resetusersprogram', 'tool_program');
        }
        $bulkactions['actions'][$actions]['deallocateusers'] = get_string('deallocateusers', 'tool_program');

        $select = new \single_select(new \moodle_url('#'), 'bulkactions', $bulkactions);
        $select->set_label(get_string('withselectedusers'));

        return $select->export_for_template($output);
    }

    /**
     * Returns correct string and calculated date for allocation window closed modal in user allocation list.
     *
     * @param program $program
     * @return mixed
     * @throws \coding_exception
     */
    private function get_data_for_modal(program $program) {
        // Check if direct allocation to the program is disabled.
        if ((int)$program->get('allowdirectallocation') === 0) {
            $params['data-directallocationdisabled'] = 1;
            return $params;
        }

        // Check if allocation window to the program is active.
        $params['data-allocationwindow'] = 'closed';

        if ((int)$program->get('allocationstartdatetype') === constants::DATE_ABSOLUTE &&
            $program->get('allocationstartdateabsolute') > time()) {
            $params['data-allocationwindowtype'] = 'allocationwindowstartson';
            $params['data-allocationwindowtime'] = userdate($program->get('allocationstartdateabsolute'),
                get_string('strftimedatefullshort'));

        } else if ((int)$program->get('allocationenddatetype') === constants::DATE_ABSOLUTE &&
            $program->get('allocationenddateabsolute') < time()) {
            $params['data-allocationwindowtype'] = 'allocationwindowendedon';
            $params['data-allocationwindowtime'] = userdate($program->get('allocationenddateabsolute'),
                get_string('strftimedatefullshort'));

        } else if ((int)$program->get('allocationenddatetype') === constants::DATE_AFTER_ALLOCATION_STARTS &&
            (int)$program->get('allocationstartdatetype') === constants::DATE_ABSOLUTE ) {
            $params['data-allocationwindowtype'] = 'allocationwindowendedon';
            // Calculate absolute date from the relative one.
            $startdate = (int)$program->get('allocationstartdateabsolute');
            $enddaterelative = (int)$program->get('allocationenddaterelative');
            $enddate = strtotime('+' . $enddaterelative, $startdate);
            $params['data-allocationwindowtime'] = userdate($enddate, get_string('strftimedatefullshort'));
        }

        return $params;
    }
}
