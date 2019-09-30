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
 * Active certifications list tab.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\output\tab;

use context_system;
use tool_certification\external\certifications_manager_view_exporter;
use tool_certification\permission;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certification_manager_list_active_tab
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certification_manager_list_active_tab extends \tool_wp\output\tab {
    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param \renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $context = \context_system::instance();
        $exporter = new certifications_manager_view_exporter(null, ['context' => $context]);

        $info = $exporter->export($output);

        if (permission::can_create(context_system::instance())) {
            $data['addbuttontitle'] = get_string('addnewcertification', 'tool_certification');
        }
        $data['tabheading'] = get_string('activecertifications', 'tool_certification');
        $data['certificationslisttable'] = $info->certificationslisttable;
        $data['addbuttonurl'] = $info->newcertificationurl;
        return $data;
    }
    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('active', 'tool_certification');
    }
    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available(): bool {
        return permission::can_view_list(context_system::instance());
    }
    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_certification/active_certifications_list';
    }
}