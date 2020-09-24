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
 * File for class program_users_tab.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\output\tab;

use html_writer;
use renderer_base;
use stdClass;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_reportbuilder\system_report_factory;
use tool_program\local\reports\allocations_report;
use tool_wp\output\content_with_heading;
use tool_wp\output\tab;

defined('MOODLE_INTERNAL') || die();

/**
 * Class program_users_tab.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_users_tab extends tab {

    /** @var program current program */
    protected $program = null;

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(\renderer_base $output) {

        // Check if tool_reportbuilder is installed.
        if (class_exists('\\tool_reportbuilder\\system_report_factory')) {
            // Users list.
            $report = system_report_factory::create(allocations_report::class, ['id' => $this->data['id']]);
            $userstable = $report->output();
        } else {
            $str = get_string('reportbuilderuserlist', 'tool_program');
            $userstable = html_writer::tag('div', $str, ['class' => 'alert alert-warning']);
        }
        $content = new content_with_heading($userstable, $this->get_tab_label());

        // We check if we have permission to show allocation button.
        $canshowbutton = permission::can_allocate_anybody($this->get_program(), false);
        if ($canshowbutton) {
            $addbuttontitle = get_string('allocateusers', 'tool_program');
            $params = [];
            $program = $this->get_program();
            if (!api::is_allocation_window_open($program)) {
                // Get strings and date for allocation window closed modal.
                $params = $this->get_data_for_modal($program);
            }
            $content->add_button($addbuttontitle, null, $params, false);
        }
        return $content->export_for_template($output);
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('users', 'tool_program');
    }

    /**
     * Current program
     *
     * @return program
     */
    protected function get_program() : program {
        if (!$this->program) {
            $this->program = new program(!empty($this->data['id']) ? $this->data['id'] : 0);
        }
        return $this->program;
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available(): bool {
        return permission::can_view_allocated_users($this->get_program());
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_program/edit_program_user_allocation';
    }

    /**
     * Returns correct string and calculated date for allocation window closed modal in user allocation list.
     *
     * @param program $program
     * @return mixed
     * @throws \coding_exception
     */
    private function get_data_for_modal(program $program) {
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
