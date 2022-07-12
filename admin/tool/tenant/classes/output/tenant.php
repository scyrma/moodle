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
 * Class tenant
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\output;

use core_renderer;
use renderer_base;
use tool_tenant\permission;

/**
 * Class tenant
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenant implements \renderable, \templatable {

    /** @var \tool_tenant\tenant */
    protected $tenant;

    /**
     * tenant constructor.
     *
     * @param \tool_tenant\tenant $tenant
     */
    public function __construct(\tool_tenant\tenant $tenant) {
        $this->tenant = $tenant;
    }

    /**
     * Exports for template
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $PAGE;

        $tenantid = $this->tenant->get('id');
        $isarchived = $this->tenant->get('archived');
        $tenantname = $this->tenant->get_formatted_name();
        $loginurls = $this->tenant->get_login_urls();

        $params = [
            'id' => $tenantid,
            'archived' => (int)$isarchived,
            'defaultlabel' => $this->tenant->get_default_tenant_label(),
            'formattedname' => $tenantname,
            'editablename' => $this->tenant->get_editable_name()->export_for_template($output),
            'loginurl' => $loginurls ? join('<br>', $loginurls) : get_string('notspecified', 'tool_tenant'),
        ];

        if (permission::can_browse_users($tenantid)) {
            $params['userscount'] = $this->tenant->get_users_count();
        }

        if (!$isarchived) {
            $category = $this->tenant->get_category();
            $params['category'] = !empty($category) ? $category->get_formatted_name() :
                get_string('nocategory', 'tool_tenant');
        }

        if (permission::can_move_tenant($tenantid)) {
            $params['movetitle'] = get_string('movetenant', 'tool_tenant', $tenantname);
        }

        /** @var renderer|core_renderer $renderer */
        $renderer = $PAGE->get_renderer('tool_tenant');
        $params['actions'] = $renderer->get_action_menu_links($this->tenant);

        return $params;
    }
}
