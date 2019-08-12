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
 * Class tool_certification\output\renderer
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\output;

use plugin_renderer_base;
use tool_certification\output\tab\certification_calendar_tab;
use tool_certification\output\tab\certification_dynamic_rules_tab;
use tool_certification\output\tab\certification_manager_list_active_tab;
use tool_certification\output\tab\certification_manager_list_archived_tab;
use tool_certification\output\tab\certification_users_tab;
use tool_certification\permission;
use tool_wp\output\tabs;

defined('MOODLE_INTERNAL') || die();

/**
 * Class renderer
 * @package tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Renders certification manager
     *
     * @return string HTML
     */
    public function render_certification_manager() {
        $context = \context_system::instance();
        $attributes = [
            'contextid' => $context->id,
        ];
        $tabsoutput = new tabs($attributes);

        $tabsoutput->add_tab(new certification_manager_list_active_tab($attributes));
        if (permission::can_view_archived_list($context)) {
            $tabsoutput->add_tab(new certification_manager_list_archived_tab($attributes));
        }

        $tabscontext = $tabsoutput->export_for_template($this);
        return $this->render_from_template('tool_wp/tabs', $tabscontext);

    }

    /**
     * Renders edit certification.
     *
     * @param int $certificationid
     * @param int $contextid
     * @return bool|string
     */
    public function render_edit_certification(int $certificationid, int $contextid) {
        $attributes = [
            'id' => $certificationid,
            'contextid' => $contextid
        ];

        $tabsoutput = new tabs($attributes);
        $tabsoutput->add_tab(new certification_calendar_tab($attributes));
        $tabsoutput->add_tab(new certification_users_tab($attributes));
        $tabsoutput->add_tab(new certification_dynamic_rules_tab($attributes));
        $tabs = $tabsoutput->export_for_template($this);

        return $this->render_from_template('tool_certification/certification', $tabs);
    }

    /**
     * Renders user certifications report
     *
     * @param int $userid
     * @param int $type
     * @return string HTML
     */
    public function render_certification_user_report(int $userid, int $type): string {
        $exporter = new certifications_user_report($userid, $type);
        return $this->render_from_template('tool_certification/certifications_user_report', $exporter->export_for_template($this));
    }

    /**
     * Renders certification progress report
     *
     * @param certification_progress_report $renderable
     * @return string
     * @throws \moodle_exception
     */
    public function render_certification_progress_report(certification_progress_report $renderable): string {
        $context = $renderable->export_for_template($this);
        return $this->render_from_template('tool_certification/certification_progress_report', $context);
    }
}