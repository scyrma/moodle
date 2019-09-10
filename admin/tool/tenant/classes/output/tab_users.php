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
 * Class tab_users
 *
 * @package     tool_tenant
 * @copyright   2019 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant\output;

use tool_tenant\permission;
use tool_wp\output\tab;
use tool_tenant\users_report;
use tool_reportbuilder\system_report_factory;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tab_users - tab that lists users in the current tenant
 *
 * @package     tool_tenant
 * @copyright   2019 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_users extends tab {

    /**
     * Check permission of the current user to access this tab
     *
     * @return mixed
     */
    public function is_available(): bool {
        $tenantid = !empty($this->data['tenantid']) ? (int)$this->data['tenantid'] : 0;
        return permission::can_browse_users($tenantid);
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_tenant/users_list';
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('users');
    }

    /**
     * Export for template
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        // TODO SP-390 to just note that removing the report builder will result in errors. Need some sort of check
        // or fallback.
        /** @var users_report $report */
        $report = system_report_factory::create(users_report::class, ['id' => $this->data['tenantid']]);

        $userdata = new \tool_tenant\output\users_list($report, $this->data['tenantid']);
        $data = $userdata->export_for_template($output);
        $data['tabheading'] = get_string('users');
        return $data;
    }
}
