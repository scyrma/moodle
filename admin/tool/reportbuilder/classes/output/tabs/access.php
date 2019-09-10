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
 * Class access
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\output\tabs;

use tool_reportbuilder\manager;
use tool_reportbuilder\output\report_access;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class access
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access extends \tool_wp\output\tab {

    /** @var string  */
    const TEMPLATE = 'tool_reportbuilder/report_access';

    /** @var report_base */
    protected $report;

    /**
     * Current report
     *
     * @return report_base
     */
    public function get_report() {
        if (!$this->report) {
            $this->report = manager::get_report($this->data['reportid']);
        }
        return $this->report;
    }

    /**
     * Export this for use in a mustache template context.
     *
     * @param \renderer_base $output
     *
     * @return array|\stdClass
     */
    public function export_for_template(\renderer_base $output) {
        $report = $this->get_report();

        $reportaccess = new report_access($report);
        $data = $reportaccess->export_for_template($output);

        return [
            'tabheading'            => $this->get_tab_label(),
            'userswithcapabilities' => $data->users,
            'managers'              => $data->managers,
            'supportsorg'           => $data->supportsorg,
            'id'                    => $report->get_id()
        ];
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('accesstab', 'tool_reportbuilder');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return mixed
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function is_available(): bool {
        return permission::can_view_access_tab($this->get_report());
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