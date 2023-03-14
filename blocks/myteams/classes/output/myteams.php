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

declare(strict_types=1);

namespace block_myteams\output;

use block_myteams\api;
use block_myteams\reportbuilder\local\systemreports\managed_users;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use tool_tenant\system_report_factory;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to prepare teams block for display.
 *
 * @package    block_myteams
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class myteams implements renderable, templatable {
    /** @var int Filter Only my own direct reports */
    public const FILTER_ONLY_MY_OWN = 1;
    /** @var int Filter Everybody reporting to me */
    public const FILTER_REPORTING_TO_ME = 2;
    /** @var int Sort by recent access */
    public const SORT_RECENT_ACCESS = 0;
    /** @var int Sort by user name */
    public const SORT_USER_NAME = 1;

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return object Export data as an object.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        $report = system_report_factory::create(managed_users::class);

        // Hide default filter form.
        $report->set_filter_form_default(false);

        // Get search and filter values from report filters.
        $filters = $report->get_filter_values();
        if (isset($filters['user:fullname_operator']) && $filters['user:fullname_operator'] == 1) {
            $search = $filters['user:fullname_value'] ?? '';
        }
        $filter = (int) ($filters['job:orgstructure_op'] ?? self::FILTER_REPORTING_TO_ME);

        return (object) [
            'userid' => $USER->id,
            'report' => $report->output(),
            'search' => $search ?? '',
            'filteronlymyown' => $filter === self::FILTER_ONLY_MY_OWN,
            'filterreportingtome' => $filter === self::FILTER_REPORTING_TO_ME,
            'filteroverdue' => (bool) get_user_preferences('block_myteams_filter_overdue', 0),
            'globalreports' => api::get_all_global_report_links(),
        ];
    }
};
