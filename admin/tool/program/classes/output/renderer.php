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

namespace tool_program\output;

use action_menu_link;
use context_system;
use lang_string;
use moodle_url;
use pix_icon;
use plugin_renderer_base;
use tool_program\persistent\program;
use tool_program\permission;

/**
 * Renderer for tool_program
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class renderer extends plugin_renderer_base {

    /**
     * Renders programs overview view
     *
     * @param programs_overview_view $renderable
     * @return string HTML
     */
    public function render_programs_overview_view(programs_overview_view $renderable): string {
        if ($wp = \tool_wp\workplace::workplace_license_not_agreed_message()) {
            return $wp;
        }
        $context = $renderable->export_for_template($this);
        return $this->render_from_template('tool_program/programs_overview_view', $context);
    }

    /**
     * Renders program overview view
     *
     * @param program_progress_overview $renderable
     * @return string HTML
     */
    public function render_program_progress_overview(program_progress_overview $renderable): string {
        $context = $renderable->export_for_template($this);
        return $this->render_from_template('tool_program/programs_overview_view', $context);
    }

    /**
     * Get action menu links for program edit page.
     *
     * @param program $program
     * @return action_menu_link[]
     */
    public function get_action_menu_links(program $program): array {
        $actionmenulinks = [];
        // Action to duplicate program.
        if (permission::can_duplicate($program)) {
            $actionmenulinks[] = new action_menu_link(
                new moodle_url('#'),
                new pix_icon('e/manage_files', '', 'core'),
                new lang_string('duplicate', 'tool_program'),
                null,
                [
                    'data-action' => 'duplicate',
                    'data-programid' => $program->get('id'),
                ]
            );
        }

        // Action to hide/show program.
        if (permission::can_edit_details($program)) {
            $actionmenulinks[] = new action_menu_link(
                new moodle_url('#'),
                new pix_icon('i/show', '', 'core'),
                new lang_string('hide'),
                null,
                [
                    'data-action' => 'updatevisibility',
                    'data-programid' => $program->get('id'),
                    'data-visibility' => '0',
                ] + (!$program->get('visible') ? ['class' => 'hidden'] : [])
            );
            $actionmenulinks[] = new action_menu_link(
                new moodle_url('#'),
                new pix_icon('i/hide', '', 'core'),
                new lang_string('show'),
                null,
                [
                    'data-action' => 'updatevisibility',
                    'data-programid' => $program->get('id'),
                    'data-visibility' => '1',
                ] + ($program->get('visible') ? ['class' => 'hidden'] : [])
            );
        }

        // Action to archive program.
        if (permission::can_archive($program)) {
            $actionmenulinks[] = new action_menu_link(
                new moodle_url(
                    '/admin/tool/program/index.php',
                    null,
                    'program_manager_list_archived_tab'
                ),
                new pix_icon('archive', '', 'tool_wp'),
                new lang_string('archive', 'tool_program'),
                null,
                [
                    'data-action' => 'archive',
                    'data-programid' => $program->get('id'),
                    'data-name' => $program->get_formatted_name(),
                ]
            );
        }

        return $actionmenulinks;
    }
}
