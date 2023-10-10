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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Class tool_reportbuilder_renderer
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
/**
 * Renderer for tool_reportbuilder
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
        global $SESSION;
        if ($contents = \tool_wp\workplace::workplace_license_not_agreed_message()) {
            return $contents;
        }

        $context = $page->export_for_template($this);
        if (empty($SESSION->rbadmin) && is_siteadmin() && !defined('BEHAT_SITE_RUNNING')) {
            $this->output->page->requires->js_amd_inline('M.cfg.rbadmin=1');
            $SESSION->rbadmin = 1;
        }
        return $this->render_from_template('tool_reportbuilder/system_report', $context);
    }
}
