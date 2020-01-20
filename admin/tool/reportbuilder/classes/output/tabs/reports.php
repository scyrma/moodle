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
 * Class for tab "Reports" in the reports main view.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
namespace tool_reportbuilder\output\tabs;

use tool_reportbuilder\permission;
use tool_reportbuilder\system_report_factory;
use tool_reportbuilder\local\systemreports\reports_list;
use tool_wp\output\content_with_heading;
use tool_wp\output\tab;

defined('MOODLE_INTERNAL') || die();

/**
 * Class reports
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reports extends tab {

    /** @var string Template to render */
    const TEMPLATE = 'tool_reportbuilder/tab_reports';

    /**
     * Export this for use in a mustache template context.
     *
     * @param \renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(\renderer_base $output) {
        global $CFG;

        $report = system_report_factory::create(reports_list::class);
        $content = new content_with_heading($report->output(), $this->get_tab_label());

        // If user has permission to create reports (ignoring set limits), show the button to do so.
        if (permission::can_create(true)) {
            $limitvalue = 0;

            // We only notify the user of the limit if it's the tenant limit that's been reached.
            if (permission::is_sitelimit_reached()) {
                $limitvalue = 0;
            } else if (permission::is_tenantlimit_reached()) {
                $limitvalue = $CFG->tool_reportbuilder_tenantlimit;
            }

            $attributes = [
                'data-limit-reached' => (int) !permission::can_create(),
                'data-limit-value' => $limitvalue,
            ];

            $content->add_button(get_string('addreport', 'tool_reportbuilder'), null, $attributes);
        }

        return $content->export_for_template($output);
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_tab_label(): string {
        return get_string('reportstab', 'tool_reportbuilder');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function is_available(): bool {
        if (permission::can_view_reports_list()) {
            return true;
        }
        return false;
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return self::TEMPLATE;
    }
}