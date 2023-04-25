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
 * Active rules tab.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

use renderer_base;
use tool_dynamicrule\permission;
use tool_wp\output\content_with_heading;
use tool_wp\output\tab;

/**
 * Active rules tab class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tab_activerules extends tab {

    /**
     * Check permission of the current user to access this tab.
     *
     * @return mixed
     */
    public function is_available(): bool {
        return permission::can_view_rules_list();
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
        return get_string('active', 'tool_dynamicrule');
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;
        $report = \tool_reportbuilder\system_report_factory::create(\tool_dynamicrule\rules_list::class);
        $content = new content_with_heading($report->output(), get_string('activerules', 'tool_dynamicrule'));

        if (permission::can_create_rule(true)) {
            $attr = [];
            if (permission::is_site_limit_reached()) {
                // Dynamic rules per site limit reached. In this case we don't
                // display number as site limit is compared against all rules
                // that may belong to different tenant, so the number may not be
                // accurate from user perspective.
                $attr['data-limitreached'] = 0;
            } else if (permission::is_tenant_limit_reached()) {
                // Dynamic rules per tenant limit reached.
                $attr['data-limitreached'] = $CFG->tool_dynamicrule_tenantlimit;
            }
            $content->add_button(get_string('newrule', 'tool_dynamicrule'), null, $attr);
        }
        $content->add_content_wrapper(['data-region' => 'ruleslistwrapper']);
        return $content->export_for_template($output);
    }
}
