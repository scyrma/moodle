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
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_reportbuilder\output\tabs;

use tool_reportbuilder\permission;
use tool_reportbuilder\system_report_factory;
use tool_reportbuilder\local\systemreports\reports_list;

defined('MOODLE_INTERNAL') || die();

/**
 * Class reports
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reports extends \tool_wp\output\tab {

    /** @var string Template to render */
    const TEMPLATE = 'tool_reportbuilder/tab_reports';

    /**
     * Export this for use in a mustache template context.
     *
     * @param \renderer_base $output
     *
     * @return array|\stdClass|string
     * @throws \ReflectionException
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \core\session\exception
     * @throws \dml_exception
     */
    public function export_for_template(\renderer_base $output) {
        $report = system_report_factory::create(reports_list::class);
        $table = $report->output();
        $btnstr = '';
        if (permission::can_create()) {
            $btnstr = get_string('addreport', 'tool_reportbuilder');
        }

        return [
            'content' => $table,
            'reportid' => $report->get_id(),
            'addbuttontitle' => $btnstr,
            'tabheading' => $this->get_tab_label()
        ];
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