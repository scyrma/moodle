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
 * Class tenant
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_tenant\manager;
use tool_tenant\permission;
use tool_tenant\tenancy;

/**
 * Class tenant
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $tenantid = $this->tenant->get('id');
        $isarchived = $this->tenant->get('archived');
        $tenantname = $this->tenant->get_formatted_name();
        $loginurls = $this->tenant->get_login_urls();

        $params = [
            'id' => $tenantid,
            'archived' => (int)$isarchived,
            'formattedname' => $this->tenant->get_formatted_name(),
            'editablename' => $this->tenant->get_editable_name()->export_for_template($output),
            'loginurl' => $loginurls ? join('<br>', $loginurls) : get_string('notspecified', 'tool_tenant'),
            'actions' => [],
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
            $params['movetitle'] = get_string('movetenant', 'tool_tenant', $this->tenant->get_formatted_name());
        }

        if (permission::can_browse_users($tenantid)) {
            // View users icon.
            $editurl = manager::get_edit_tenant_url($tenantid);
            $params['actions'][] = (new \action_link($editurl, '', null,
                ['data-action' => 'manage_tenant', 'data-id' => $tenantid,
                    'data-form-title' => get_string('viewusers', 'tool_tenant', $tenantname),
                    'title' => get_string('viewusers', 'tool_tenant', $tenantname)],
                new \pix_icon('t/right', '', 'core'))
            )->export_for_template($output);
        }

        $emptyurl = new \moodle_url('#');
        if (permission::can_edit_tenant_details($tenantid)) {
            $params['actions'][] = (new \action_link($emptyurl, '', null,
                ['data-action' => 'edit', 'data-id' => $tenantid,
                    'data-form-title' => get_string('edittenant', 'tool_tenant', $tenantname),
                    'title' => get_string('edittenant', 'tool_tenant', $tenantname)],
                new \pix_icon('i/settings', '', 'core'))
            )->export_for_template($output);
        }

        if (permission::can_archive_tenant($tenantid)) {
            $params['actions'][] = (new \action_link($emptyurl, '', null,
                ['data-action' => 'archive', 'data-id' => $tenantid,
                    'data-confirm' => get_string('confirmarchivetenant', 'tool_tenant', $tenantname)],
                new \pix_icon('archive', get_string('archivetenant', 'tool_tenant'), 'tool_wp'))
            )->export_for_template($output);
        }

        if (permission::can_restore_tenant($tenantid)) {
            $params['actions'][] = (new \action_link($emptyurl, '', null,
                ['data-action' => 'restore', 'data-id' => $tenantid,
                    'data-confirm' => get_string('confirmrestoretenant', 'tool_tenant', $tenantname)],
                new \pix_icon('arrow-circle-left', get_string('restoretenant', 'tool_tenant'), 'tool_wp'))
            )->export_for_template($output);
        }

        if (permission::can_delete_tenant($tenantid)) {
            $params['actions'][] = (new \action_link($emptyurl, '', null,
                ['data-action' => 'delete', 'data-id' => $tenantid,
                    'data-confirm' => get_string('confirmdeletetenant', 'tool_tenant', $tenantname)],
                new \pix_icon('i/trash', get_string('deletetenant', 'tool_tenant'), 'core'))
            )->export_for_template($output);
        }

        return $params;
    }
}
