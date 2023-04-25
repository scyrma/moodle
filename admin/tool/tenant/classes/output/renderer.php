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
 * Renderer for tool_tenant
 *
 * @package    tool_tenant
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\output;

use action_menu_link;
use lang_string;
use moodle_url;
use pix_icon;
use tool_tenant\permission;
use tool_tenant\tenant;

/**
 * Class renderer
 *
 * @package    tool_tenant
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class renderer extends \plugin_renderer_base {

    /**
     * Get action menu links for tenant edit page.
     *
     * @param tenant $tenant
     * @return action_menu_link[]
     */
    public function get_action_menu_links(tenant $tenant): array {
        $tenantid = $tenant->get('id');
        $tenantname = $tenant->get_formatted_name();
        $actionmenulinks = [];

        if (permission::can_archive_tenant($tenantid)) {
            $actionmenulinks[] = new action_menu_link(
                new moodle_url(
                    '/admin/tool/tenant/index.php',
                    null,
                    'archivedtenants'
                ),
                new pix_icon('archive', '', 'tool_wp'),
                new lang_string('archive', 'tool_tenant'),
                null,
                [
                    'data-id' => $tenantid,
                    'data-action' => 'archive',
                    'data-confirm' => get_string('confirmarchivetenant', 'tool_tenant', $tenantname),
                    'data-name' => $tenantname
                ]
            );
        }

        if (permission::can_restore_tenant($tenantid)) {
            $actionmenulinks[] = new action_menu_link(
                new moodle_url('#'),
                new pix_icon('restorearchived', '', 'tool_wp'),
                new lang_string('restore'),
                null,
                [
                    'data-id' => $tenantid,
                    'data-action' => 'restore',
                    'data-confirm' => get_string('confirmrestoretenant', 'tool_tenant', $tenantname)
                ]
            );
        }

        if (permission::can_delete_tenant($tenantid)) {
            $actionmenulinks[] = new action_menu_link(
                new moodle_url('#'),
                new pix_icon('i/trash', '', 'core'),
                new lang_string('delete'),
                null,
                [
                    'data-id' => $tenantid,
                    'data-action' => 'delete',
                    'data-confirm' => get_string('confirmdeletetenant', 'tool_tenant', $tenantname)
                ]
            );
        }

        return $actionmenulinks;
    }

}
