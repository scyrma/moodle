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

namespace tool_program\output\tab;

use renderer_base;
use stdClass;
use tool_program\constants;
use tool_program\permission;
use tool_wp\output\tab;

/**
 * Tab showing reports for all programs.
 *
 * @package   tool_program
 * @copyright 2023 Moodle Pty Ltd <support@moodle.com>
 * @author    2023 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_manager_reports_tab extends tab {
    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data['reports'] = [
            [
                'name' => get_string('programprogress', 'tool_program'),
                'url' => (new \moodle_url('/admin/tool/program/programsprogress.php'))->out(false),
            ],
            [
                'name' => get_string('overdueprograms', 'tool_program'),
                'url' => (new \moodle_url('/admin/tool/program/programsprogress.php',
                    ['type' => constants::STATUS_OVERDUE]))->out(false),
            ]
        ];
        $data['tabheading'] = get_string('reports', 'moodle');
        return $data;
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('reports', 'moodle');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available(): bool {
        return permission::can_view_programs_progress_report();
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_program/reports';
    }
}
