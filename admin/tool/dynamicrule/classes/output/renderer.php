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
 * Renderer.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

use plugin_renderer_base;

/**
 * Renderer class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
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
     *
     * @deprecated since 3.11 - this method should no longer be used.
     */
    public function render_placeholders(array $placeholders): array {
        debugging('Function renderer::render_placeholders() should not be used', DEBUG_DEVELOPER);
        return $placeholders;
    }
}
