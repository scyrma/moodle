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
 * Class users_list
 *
 * @package     tool_tenant
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_tenant\manager;
use tool_tenant\permission;

/**
 * Class users_list
 *
 * @package     tool_tenant
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users_list implements \renderable , \templatable {

    /** @var \tool_tenant\users_report $userreport The system report of tenant users. */
    protected $userreport;

    /** @var int $tenantid The ID of the tenant. */
    protected $tenantid;

    /**
     * users_list constructor.
     *
     * @param \tool_tenant\users_report $userreport The users associated with this tenant.
     * @param int $tenantid The ID of the tenant.
     */
    public function __construct(\tool_tenant\users_report $userreport, int $tenantid) {
        $this->userreport = $userreport;
        $this->tenantid = $tenantid;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return \stdClass|array
     */
    public function export_for_template(renderer_base $output) {

        $params = [];
        $context = \context_system::instance();

        $bulkactions = [];
        if (permission::can_suspend_users($this->tenantid)) {
            $bulkactions['actions'] = [
                get_string('actions') => [
                    'suspendusers' => get_string('suspendusers', 'tool_tenant'),
                    'unsuspendusers' => get_string('unsuspendusers', 'tool_tenant'),
                ]
            ];
        }
        if (permission::can_delete_users($this->tenantid)) {
            $actions = get_string('actions');
            $bulkactions['actions'][$actions]['deleteusers'] = get_string('deleteusers', 'tool_tenant');
        }
        if (permission::can_assign_tenant_admin($this->tenantid)) {
            $bulkactions['admin'] = [
                get_string('tenantadministration', 'tool_tenant') => [
                    'assigntenantadmin' => get_string('assigntenantadmins', 'tool_tenant'),
                    'unassigntenantadmin' => get_string('unassigntenantadmins', 'tool_tenant')
                ]
            ];
        }
        if (permission::can_move_users_between_tenants()) {
            // Add a tenant selector.
            $manager = new manager();
            $tenants = array_map(function (\tool_tenant\tenant $t) {
                return $t->get_formatted_name();
            }, $manager->get_tenants());
            unset($tenants[$this->tenantid]);
            if ($tenants) {
                $bulkactions['tenants'] = [get_string('movebetweentenants', 'tool_tenant') => $tenants];
            }
        }

        $select = new \single_select(new \moodle_url('#'), 'bulkactions', $bulkactions);
        $select->set_label(get_string('withselectedusers'));
        $params['bulkactionsselect'] = $select->export_for_template($output);;

        if (permission::can_create_users($this->tenantid)) {
            $params['adduser'] = true;
            $params['addbuttontitle'] = get_string('adduser', 'tool_tenant');
            $params['systemcontextid'] = $context->id; // TODO not needed?
        }
        $params['tenantid'] = $this->tenantid;
        $params['userslist'] = $this->userreport->output();

        return $params;
    }
}
