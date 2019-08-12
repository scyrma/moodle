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
 * Archived rules tab.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_dynamicrule\permission;
use tool_wp\output\content_with_heading;
use tool_wp\output\tab;
use tool_dynamicrule\api;

/**
 * Archived rules tab class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_archivedrules extends tab {

    /**
     * Check permission of the current user to access this tab.
     *
     * @return mixed
     */
    public function is_available(): bool {
        return permission::can_view_archived_rules_list();
    }

    /**
     * Template to use to display tab contents.
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_dynamicrule/rules_list';
    }

    /**
     * The label to be displayed on the tab.
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('archived', 'tool_dynamicrule');
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $report = \tool_reportbuilder\system_report_factory::create(\tool_dynamicrule\rules_list::class,
            ['archived' => true]);
        $content = new content_with_heading($report->output(), get_string('archivedrules', 'tool_dynamicrule'));
        $content->add_content_wrapper(['data-region' => 'ruleslistwrapper']);
        return $content->export_for_template($output);
    }
}
