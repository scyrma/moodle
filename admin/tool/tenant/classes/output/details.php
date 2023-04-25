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

namespace tool_tenant\output;

use tool_tenant\permission;
use tool_wp\output\secondary_tabs;

/**
 * Class details
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class details extends secondary_tabs {

    /**
     * details constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);

        if (permission::can_edit_tenant_theme($attributes['tenantid'])) {
            $this->add_tab(new tab_appearance($attributes));
        }

        $this->add_tab(new tab_details($attributes));

        // Hide dashboard tab for users who doesn't have permission to edit it.
        if (permission::can_see_tenant_dashboard_tab($attributes['tenantid'])) {
            $this->add_tab(new tab_dashboard($attributes));
        }

        $tab = new tab_mobile($attributes);
        if ($tab->is_available()) {
            $this->add_tab($tab);
        }
    }
}
