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
 * Renderer.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

/**
 * Renderer class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class renderer extends plugin_renderer_base {

    /**
     * Render rules listing page content.
     *
     * @return string HTML
     */
    public function ruleslist() {
        $tabs = new \tool_wp\output\tabs([], [
            new tab_activerules([]),
            new tab_archivedrules([]),
        ]);

        $context = $tabs->export_for_template($this);
        return $this->render_from_template('tool_wp/tabs', $context);
    }

    /**
     * Render rule editing page content.
     *
     * @param int $ruleid ruleid
     * @return string HTML
     */
    public function rule($ruleid) {
        $data = ['ruleid' => $ruleid];
        $attributes = $data + ['contextid' => (\context_system::instance())->id];
        $tabs = new \tool_wp\output\tabs($attributes, [
            new tab_ruleconditions($data),
            new tab_ruleoutcomes($data),
        ]);
        $context = $tabs->export_for_template($this);
        return $this->render_from_template('tool_wp/tabs', $context);
    }

    /**
     * Render placeholder icons to each placeholder in the array
     *
     * @param array $placeholders
     * @return array
     */
    public function render_placeholders(array $placeholders): array {
        // TODO: Deprecate in 3.11 WP-2439.
        return $placeholders;
    }
}
