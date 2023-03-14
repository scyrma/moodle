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

use action_menu_link;
use lang_string;
use moodle_url;
use pix_icon;
use plugin_renderer_base;
use tool_dynamicrule\permission;
use tool_dynamicrule\rule;

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

    /**
     * Get action menu links for rule edit page.
     *
     * @param rule $rule
     * @return action_menu_link[]
     */
    public function get_action_menu_links(rule $rule): array {
        $actionmenulinks = [];
        // Action to duplicate rule.
        if (permission::can_duplicate_rule($rule)) {
            $actionmenulinks[] = new action_menu_link(
                new moodle_url('#'),
                new pix_icon('e/manage_files', '', 'core'),
                new lang_string('duplicate'),
                null,
                [
                    'data-action' => 'duplicate',
                    'data-ruleid' => $rule->get('id'),
                    'data-name' => $rule->get_formatted_name(),
                ]
            );
        }

        // Action to archive rule.
        if (permission::can_archive_rule($rule)) {
            $actionmenulinks[] = new action_menu_link(
                new moodle_url(
                    '/admin/tool/dynamicrule/index.php',
                    null,
                    'archivedrules'
                ),
                new pix_icon('archive', '', 'tool_wp'),
                new lang_string('rulearchive', 'tool_dynamicrule'),
                null,
                [
                    'data-action' => 'archive',
                    'data-ruleid' => $rule->get('id'),
                    'data-name' => $rule->get_formatted_name(),
                ]
            );
        }

        return $actionmenulinks;
    }
}
