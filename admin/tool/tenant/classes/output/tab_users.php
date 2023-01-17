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
 * Class tab_users
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\output;

use tool_tenant\permission;
use tool_wp\output\tab;
use tool_tenant\users_report;
use tool_reportbuilder\system_report_factory;
/**
 * Class tab_users - tab that lists users in the current tenant
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
