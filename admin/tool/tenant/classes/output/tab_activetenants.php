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
 * Class activetenants
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\output;

use renderer_base;
use tool_tenant\permission;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_wp\output\tab;

/**
 * Class activetenants
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tab_activetenants extends tab {

    /**
     * Check permission of the current user to access this tab
     *
     * @return mixed
     */
    public function is_available(): bool {
        return permission::can_view_tenants_list();
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_tenant/active_tenants_list';
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string($this->get_tab_id(), 'tool_tenant');
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;
        $manager = new \tool_tenant\manager();

        // TODO SP-389 this is not ideal to combine retrieving tab contents with an action. Should really be two requests.
        $this->data += ['action' => '', 'id' => 0];
        $manager->manage_action($this->data['action'], $this->data['id']);

        $rv = [
            'tabheading' => get_string('activetenants', 'tool_tenant'),
            'systemcontextid' => \context_system::instance()->id
        ];
        if (permission::can_create_tenant(true)) {
            $rv['addbutton'] = true;
            $rv['addbuttontitle'] = get_string('addtenant', 'tool_tenant');
            $rv['addbuttonicon'] = true;

            // Re-check permission, this time observing limit. Disables shared space creation, adds dialog for "add" button.
            $cancreateobservinglimit = permission::can_create_tenant();

            // Only show if not already enabled and site is multi tenant and user is allowed to switch to tenants.
            if (sharedspace::get_shared_space_id() <= 0 && tenancy::is_site_multi_tenant() && permission::can_switch_tenant() &&
                    $cancreateobservinglimit) {

                $rv['enablesharedspacebutton'] = true;
                $rv['enablesharedspacebuttontitle'] = get_string('enablesharedspace', 'tool_tenant');
                $rv['enablesharedspacebuttonattrs'] = [['name' => 'data-showninmenu',
                    'value' => (int)sharedspace::show_shared_space_in_switch_operations()]];
            }
            if (!$cancreateobservinglimit) {
                $rv['addbuttonattrs'] = [['name' => 'data-tenantlimit', 'value' => $CFG->tool_tenant_tenantlimit]];
            }
        }

        return $rv + (new active_tenants_list())->export_for_template($output);
    }
}
