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
 * Users tab.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\output\tab;

use tool_certification\api;
use tool_certification\certification;
use tool_certification\constants;
use tool_certification\permission;
use tool_reportbuilder\system_report_factory;
use tool_certification\local\reports\users_table;
use tool_wp\output\content_with_heading;

defined('MOODLE_INTERNAL') || die();

/**
 * Users tab.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_users_tab extends \tool_wp\output\tab {
    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param \renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return \stdClass|array
     */
    public function export_for_template(\renderer_base $output) {
        $params = ['id' => $this->data['id']];

        // Check if tool_reportbuilder is installed.
        if (class_exists('\\tool_reportbuilder\\system_report_factory')) {
            // Users list.
            $report = system_report_factory::create(users_table::class, $params);
            $userstable = $report->output();
        } else {
            $str = get_string('reportbuilderuserlist', 'tool_certification');
            $userstable = \html_writer::tag('div', $str, array('class' => 'alert alert-warning'));
        }
        $content = new content_with_heading($userstable, $this->get_tab_label());

        // We check if we have permission to show allocation button.
        $certification = new certification($this->data['id']);
        if (permission::can_allocate_anybody($certification, false)) {
            $addbuttontitle = get_string('allocateusers', 'tool_certification');
            $params = [];
            if (!api::is_certification_allocation_open($certification)) {
                // Get strings and date for allocation window closed modal.
                $params = $this->get_data_for_modal($certification);
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
        return get_string('users', 'tool_certification');
    }
    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available(): bool {
        if (0 === (int)$this->data['id']) {
            return false;
        }

        $certification = new certification($this->data['id']);
        return permission::can_view_allocated_users($certification);

    }
    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_certification/edit_certification_user_allocation';
    }

    /**
     * Returns correct string and calculated date for allocation window closed modal in user allocation list.
     *
     * @param certification $certification
     * @return mixed
     * @throws \coding_exception
     */
    private function get_data_for_modal(certification $certification) {
        $params['data-allocationwindow'] = 'closed';

        if ((int)$certification->get('allocationstartdatetype') === constants::DATE_ABSOLUTE &&
            $certification->get('allocationstartdateabsolute') > time()) {
            $params['data-allocationwindowtype'] = 'allocationwindowstartson';
            $params['data-allocationwindowtime'] = userdate($certification->get('allocationstartdateabsolute'),
                get_string('strftimedatefullshort'));

        } else if ((int)$certification->get('allocationenddatetype') === constants::DATE_ABSOLUTE &&
            $certification->get('allocationenddateabsolute') < time()) {
            $params['data-allocationwindowtype'] = 'allocationwindowendedon';
            $params['data-allocationwindowtime'] = userdate($certification->get('allocationenddateabsolute'),
                get_string('strftimedatefullshort'));

        }

        return $params;
    }
}
