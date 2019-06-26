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
 * Class table
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_reportbuilder\output\tabs;

use tool_reportbuilder\manager;
use tool_reportbuilder\report_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class table
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table extends \tool_wp\output\tab {

    /** @var string  */
    const TEMPLATE = 'tool_reportbuilder/tab_table';

    /**
     * Export this for use in a mustache template context.
     *
     * @param \renderer_base $output
     *
     * @return array|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function export_for_template(\renderer_base $output) {
        $report = manager::get_report($this->data['reportid']);
        $content = $report->export($output, true);
        $content->reportid = $this->data['reportid'];
        $content->tabheading = $this->get_tab_label();
        return $content;
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_tab_label(): string {
        return get_string('tabletab', 'tool_reportbuilder');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function is_available(): bool {
        $context = \context_system::instance();
        if (!$this->data['reportid'] || !has_capability('tool/reportbuilder:edit', $context)) {
            return false;
        }
        return true;
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