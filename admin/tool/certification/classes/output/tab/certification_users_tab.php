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
 * Users tab.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\output\tab;

use context_system;
use tool_certification\certification;
use tool_certification\permission;
use tool_reportbuilder\system_report_factory;
use tool_certification\local\reports\users_table;

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

        $exporteddata['userstable'] = $userstable;

        // We check if we have permission to show allocation button.
        $certification = new certification($this->data['id']);
        if (permission::can_allocate_anybody($certification)) {
            $exporteddata['addbuttontitle'] = get_string('allocateusers', 'tool_certification');
        }
        $exporteddata['tabheading'] = get_string('users', 'tool_certification');
        return $exporteddata;
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
}
