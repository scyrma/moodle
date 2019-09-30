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
 * Active programs list tab.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\output\tab;

use context_system;
use html_writer;
use moodle_url;
use renderer_base;
use stdClass;
use tool_program\permission;
use tool_program\local\reports\active_programs_report;
use tool_reportbuilder\system_report_factory;
use tool_wp\output\tab;

defined('MOODLE_INTERNAL') || die();

/**
 * -
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_manager_list_active_tab extends tab {
    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {

        // Check if tool_reportbuilder is installed.
        if (class_exists('\\tool_reportbuilder\\system_report_factory')) {
            // Active programs list.
            $report = system_report_factory::create(active_programs_report::class);
            $table = $report->output();
        } else {
            $str = get_string('reportbuilderactiveprograms', 'tool_program');
            $table = html_writer::tag('div', $str, ['class' => 'alert alert-warning']);
        }

        $data['programslisttable'] = $table;
        $canshowbutton = permission::can_create(context_system::instance());
        if ($canshowbutton) {
            $data['addbuttontitle'] = get_string('addnewprogram', 'tool_program');
        }
        $data['tabheading'] = get_string('activeprograms', 'tool_program');
        $newprogramurl = new moodle_url('/admin/tool/program/edit.php');
        $data['addbuttonurl'] = $newprogramurl->out(false);
        return $data;
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('active', 'tool_program');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available(): bool {
        return permission::can_view_list();
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_program/active_programs_list';
    }
}
