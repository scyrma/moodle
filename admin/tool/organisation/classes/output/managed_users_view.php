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
 * Class managed_users_view
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\output;

use renderer_base;
use tool_organisation\helper;
use tool_organisation\managed_users_table;
use tool_organisation\organisation;
use tool_organisation\permission;
use tool_reportbuilder\system_report_factory;
use tool_program\output\programs_overdue_view;

defined('MOODLE_INTERNAL') || die();

/**
 * Class managed_users_view
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class managed_users_view implements \templatable, \renderable {

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $user = organisation::get_user_with_jobs();
        $ismanager = $user && $user->is_manager();
        $rv = ['ismanager' => $ismanager];
        if (!$ismanager) {
            // Nothing to show for non-manager.
            return $rv;
        }

        $report = system_report_factory::create(managed_users_table::class);
        $rv['userstable'] = $report->output();
        if (class_exists(programs_overdue_view::class)) {
            if (permission::can_view_user_programs_overdue($user)) {
                $rv['reportoverdueurl'] = new \moodle_url('/admin/tool/program/programsoverdue.php');
            }
        }

        return $rv;
    }

}
