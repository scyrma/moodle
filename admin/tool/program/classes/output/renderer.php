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
 * Renderer for tool_program
 *
 * @package    tool_program
 * @copyright  2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\output;

use context_system;
use plugin_renderer_base;
use tool_program\output\tab\program_calendar_tab;
use tool_program\output\tab\program_content_tab;
use tool_program\output\tab\program_dynamic_rules_tab;
use tool_program\output\tab\program_manager_list_active_tab;
use tool_program\output\tab\program_manager_list_archived_tab;
use tool_program\output\tab\program_users_tab;
use tool_program\permission;
use tool_wp\output\tabs;

defined('MOODLE_INTERNAL') || die();

/**
 * Class renderer
 *
 * @package    tool_program
 * @copyright  2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Renders program manager view
     *
     * @return string HTML
     */
    public function render_program_manager(): string {
        $context = context_system::instance();
        $attributes = [
            'contextid' => $context->id,
        ];

        // We use tabs from tool_wp plugin.
        $tabsoutput = new tabs($attributes);
        $tabsoutput->add_tab(new program_manager_list_active_tab($attributes));
        if (permission::can_view_archived_list()) {
            $tabsoutput->add_tab(new program_manager_list_archived_tab($attributes));
        }

        $tabscontext = $tabsoutput->export_for_template($this);
        return $this->render_from_template('tool_wp/tabs', $tabscontext);
    }

    /**
     * Renders edit program view
     *
     * @param int $programid
     * @param int $basesetid
     * @param int $contextid
     * @return string HTML
     */
    public function render_edit_program($programid, $basesetid, $contextid): string {
        $attributes = [
            'id'        => $programid,
            'basesetid' => $basesetid,
            'contextid' => $contextid,
        ];

        $tabsoutput = new tabs($attributes);
        $tabsoutput->add_tab(new program_content_tab($attributes));
        $tabsoutput->add_tab(new program_calendar_tab($attributes));
        $tabsoutput->add_tab(new program_users_tab($attributes));
        $tabsoutput->add_tab(new program_dynamic_rules_tab($attributes));
        $tabs = $tabsoutput->export_for_template($this);

        return $this->render_from_template('tool_program/program', $tabs);
    }

    /**
     * Renders programs overview view
     *
     * @param programs_overview_view $renderable
     * @return string HTML
     */
    public function render_programs_overview_view(programs_overview_view $renderable): string {
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
     * Renders program progress view
     *
     * @param users_progress_view $renderable
     * @return string HTML
     */
    public function render_users_progress_view(users_progress_view $renderable): string {
        $context = $renderable->export_for_template($this);
        return $this->render_from_template('tool_program/program_progress_report', $context);
    }

    /**
     * Renders user programs view
     *
     * @param programs_progress_view $renderable
     * @return string HTML
     */
    public function render_programs_progress_view(programs_progress_view $renderable): string {
        $context = $renderable->export_for_template($this);
        return $this->render_from_template('tool_program/programs_user_report', $context);
    }

    /**
     * Renders user programs view
     *
     * @param program_progress_view $renderable
     * @return string HTML
     */
    public function render_program_progress_view(program_progress_view $renderable): string {
        $context = $renderable->export_for_template($this);
        return $this->render_from_template('tool_program/program_user_report', $context);
    }
}
