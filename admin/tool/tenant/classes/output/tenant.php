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
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_tenant\manager;
use tool_tenant\tenancy;

/**
 * Class tenant
 *
 * @package     tool_tenant
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $isdefault = $this->tenant->get('isdefault');
        $isarchived = $this->tenant->get('archived');
        $canmanage = has_capability('tool/tenant:manage', \context_system::instance());
        $canallocate = has_capability('tool/tenant:allocate', \context_system::instance());

        $params = [
            'id' => $tenantid,
            'archived' => (int)$isarchived,
            'formattedname' => $this->tenant->get_formatted_name(),
            'editablename' => $this->tenant->get_editable_name()->export_for_template($output),
            'actions' => [],
        ];

        if (!$isarchived) {
            $params['userscount'] = $this->tenant->get_users_count();
            $category = $this->tenant->get_category();
            $params['category'] = !empty($category) ? $category->get_formatted_name() :
                get_string('nocategory', 'tool_tenant');
        }

        if (!$isarchived && $canmanage && !$isdefault) {
            $params['movetitle'] = get_string('movetenant', 'tool_tenant', $this->tenant->get_formatted_name());
        }

        if (!$isarchived && (manager::can_browse_users($tenantid) || manager::can_edit_tenant_themes($tenantid))) {
            // Manage tenant icon.
            $editurl = manager::get_edit_tenant_url($tenantid);
            $tenantname = $this->tenant->get_formatted_name();
            $params['actions'][] = (new \action_link($editurl, '', null,
                ['data-action' => 'manage_tenant', 'data-id' => $tenantid,
                    'data-form-title' => get_string('viewusers', 'tool_tenant', $tenantname),
                    'title' => get_string('viewusers', 'tool_tenant', $tenantname)],
                new \pix_icon('t/right', '', 'core'))
            )->export_for_template($output);
        }

        if ($canmanage) {
            $url = new \moodle_url('#');
            $tenantname = $this->tenant->get_formatted_name();
            if (!$isarchived) {
                $params['actions'][] = (new \action_link($url, '', null,
                    ['data-action' => 'edit', 'data-id' => $tenantid,
                        'data-form-title' => get_string('edittenant', 'tool_tenant', $tenantname),
                        'title' => get_string('edittenant', 'tool_tenant', $tenantname)],
                    new \pix_icon('i/settings', '', 'core'))
                )->export_for_template($output);

                if (!$isdefault) {
                    $params['actions'][] = (new \action_link($url, '', null,
                        ['data-action' => 'archive', 'data-id' => $tenantid,
                            'data-confirm' => get_string('confirmarchivetenant', 'tool_tenant', $tenantname)],
                        new \pix_icon('archive', get_string('archivetenant', 'tool_tenant'), 'tool_wp'))
                    )->export_for_template($output);
                }
            } else {
                $params['actions'][] = (new \action_link($url, '', null,
                    ['data-action' => 'restore', 'data-id' => $tenantid,
                        'data-confirm' => get_string('confirmrestoretenant', 'tool_tenant', $tenantname)],
                    new \pix_icon('arrow-circle-left', get_string('restoretenant', 'tool_tenant'), 'tool_wp'))
                )->export_for_template($output);
                $params['actions'][] = (new \action_link($url, '', null,
                    ['data-action' => 'delete', 'data-id' => $tenantid,
                        'data-confirm' => get_string('confirmdeletetenant', 'tool_tenant', $tenantname)],
                    new \pix_icon('i/trash', get_string('deletetenant', 'tool_tenant'), 'core'))
                )->export_for_template($output);
            }
        }

        return $params;
    }
}
