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
 * Class tab_details
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\output;

use tool_tenant\form\view_tenant_form;
use tool_tenant\permission;
use tool_wp\output\tab_form;

/**
 * Class tab_details - read-only details of this tenant
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tab_details extends tab_form {

    /**
     * Name of the class that contains the form (must extend tool_wp\modal_form)
     *
     * @return string
     */
    public function get_form_class(): string {
        return view_tenant_form::class;
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return mixed
     */
    public function is_available(): bool {
        return permission::can_view_tenant_details($this->data['tenantid']);
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_tenant/details';
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('tenantdetails', 'tool_tenant');
    }

    /**
     * Export for template
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $data = parent::export_for_template($output);
        $data['isdefault'] = $this->data['isdefault'];
        $data['message'] = get_string('defaulttenantmobileconfig', 'tool_tenant');
        $data['tabheading'] = $heading = get_string('tenantdetails', 'tool_tenant');
        return $data;
    }
}
