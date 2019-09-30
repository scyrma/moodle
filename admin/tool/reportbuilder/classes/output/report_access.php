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
 * Class report_access
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use stdClass;
use templatable;
use tool_organisation\organisation;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;
use tool_reportbuilder\reportbuilder;
use tool_tenant\tenancy;
use context_system;
use user_picture;

/**
 * Class report_access
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_access implements templatable, renderable {
    /**
     * @var report_base
     */
    protected $report;

    /**
     * report_access constructor.
     *
     * @param report_base $report
     */
    public function __construct(report_base $report) {
        $this->report = $report;
    }

    /**
     * Implementation of exporter from templatable interface
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $OUTPUT;
        $context = context_system::instance();
        $reportbase = $this->report;
        $tenantid = $reportbase->get_tenant_id();
        $supportsorg = ($reportbase instanceof \tool_reportbuilder\datasource) &&
            $reportbase->supports_organisation_filter();

        // We get all users by edit and read capability.
        $userswithcapabilities = [];
        $usersedit = get_users_by_capability($context, 'tool/reportbuilder:edit');
        $usersread = get_users_by_capability($context, 'tool/reportbuilder:read');
        $usersreadwrite = array_unique(array_merge($usersedit, $usersread), SORT_REGULAR);
        foreach ($usersreadwrite as $user) {
            if ((int)$tenantid === tenancy::get_tenant_id($user->id)) {
                $userswithcapabilities[] = [
                    'fullname'     => fullname($user),
                    'profileimage' => $OUTPUT->user_picture($user)
                ];
            }
        }

        // We get all organisation managers.
        $managerslist = organisation::get_all_managers(organisation::PERM_VIEW_REPORTS);
        $managerslist = $this->group_managers_by_position($managerslist);

        return (object)[
            'managers'    => $managerslist,
            'users'       => $userswithcapabilities,
            'supportsorg' => $supportsorg
        ];
    }

    /**
     * Groups organisation users by position.
     *
     * @param array $userslist
     * @return array
     */
    private function group_managers_by_position(array $userslist): array {
        global $OUTPUT;
        $managers = [];
        if ($userslist) {
            foreach ($userslist as $record) {
                $managers[$record->positionid]['position'] = format_string($record->positionname, true, ['escape' => false]);
                $managers[$record->positionid]['data'][] = (object)[
                    'fullname'     => fullname($record),
                    'profileimage' => $OUTPUT->user_picture((object)['id' => $record->userid], array('class' => 'userpicture'))
                ];
            }
        }
        return array_values($managers);
    }
}