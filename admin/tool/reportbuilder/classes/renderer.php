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
 * Class tool_reportbuilder_renderer
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for tool_reportbuilder
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_renderer extends plugin_renderer_base {
    /**
     * Render the index page for the reports.
     *
     * @param \tool_reportbuilder\output\index_page $page
     *
     * @return bool|string
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function render_index_page(\tool_reportbuilder\output\index_page $page) {
        $context = $page->export_for_template($this);
        return $this->render_from_template('tool_reportbuilder/index_page', $context);
    }

    /**
     * Render a system report view.
     *
     * @param \tool_reportbuilder\output\system_report $page
     *
     * @return bool|string
     * @throws moodle_exception
     */
    protected function render_system_report(\tool_reportbuilder\output\system_report $page) {
        $context = $page->export_for_template($this);
        return $this->render_from_template('tool_reportbuilder/system_report', $context);
    }
}