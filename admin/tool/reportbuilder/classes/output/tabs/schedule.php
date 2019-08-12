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
 * Class schedule
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\output\tabs;

use tool_reportbuilder\permission;
use tool_reportbuilder\system_report_factory;
use tool_reportbuilder\local\systemreports\schedules_list;

defined('MOODLE_INTERNAL') || die();

/**
 * Class schedule
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule extends \tool_wp\output\tab {

    /** @var string  */
    const TEMPLATE = 'tool_reportbuilder/tab_schedules';

    /**
     * Export this for use in a mustache template context.
     *
     * @param \renderer_base $output
     *
     * @return array|\stdClass
     * @throws \ReflectionException
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \core\session\exception
     * @throws \dml_exception
     */
    public function export_for_template(\renderer_base $output) {
        $btnstr = '';
        if (permission::can_create_schedule()) {
            $btnstr = get_string('addschedule', 'tool_reportbuilder');
        }

        $report = system_report_factory::create(schedules_list::class, ['reportid' => $this->data['reportid']]);
        $table = $report->output();

        return [
            'content' => $table,
            'addbuttontitle' => $btnstr,
            'reportid' => $this->data['reportid'],
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
        return get_string('scheduletab', 'tool_reportbuilder');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function is_available(): bool {
        return permission::can_manage_schedules();
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