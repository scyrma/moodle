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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_certification\output\tab;
use tool_certification\certification;
use tool_certification\permission;

/**
 * Reports tab in certifications
 *
 * @package    tool_certification
 * @author     2023 Marina Glancy
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_reports_tab extends \tool_wp\output\tab {
    /**
     * The label to be displayed on the tab
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('reports', 'moodle');
    }

    /**
     * Check permission of the current user to access this tab
     * @return bool
     */
    public function is_available(): bool {
        $certification = new certification($this->data['id'] ?? 0);
        return permission::can_view_users_progress($certification);
    }

    /**
     * Template to use to display tab contents
     * @return string
     */
    public function get_template(): string {
        return 'tool_program/reports';
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param \renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $data['reports'] = [
            [
                'name' => get_string('certificationprogress', 'tool_certification'),
                'url' => (new \moodle_url('/admin/tool/certification/certification_report.php',
                    ['id' => $this->data['id'] ?? 0]))->out(false),
            ]
        ];
        $data['tabheading'] = get_string('reports', 'moodle');
        return $data;
    }
}
