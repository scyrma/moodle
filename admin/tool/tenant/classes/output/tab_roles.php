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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class tab_roles
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\output;

use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_wp\output\content_with_heading;
use tool_wp\output\tab;
use tool_tenant\users_report;
use tool_reportbuilder\system_report_factory;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tab_roles - a tab that helps tenant admin to view all assignable roles together
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tab_roles extends tab {

    /**
     * Check permission of the current user to access this tab
     *
     * @return mixed
     */
    public function is_available(): bool {
        $tenantid = !empty($this->data['tenantid']) ? (int)$this->data['tenantid'] : 0;
        return permission::can_see_roles_tab($tenantid);
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_wp/content_with_heading';
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('roles');
    }

    /**
     * Export for template
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        // For main admin display/count only users in the given tenant.
        tenancy::force_tenantid_for_users_subquery($this->data['tenantid']);

        $context = \context_system::instance();
        $rows = $this->get_rows_for_roles_table($context);

        $categoryid = tenancy::get_tenants()[$this->data['tenantid']]->categoryid;
        if ($categoryid && ($context = \context_coursecat::instance($categoryid, IGNORE_MISSING))) {
            $rows = array_merge($rows, $this->get_rows_for_roles_table($context));
        }
        tenancy::force_tenantid_for_users_subquery(0);

        $content = $this->roles_table($rows);
        $data = new content_with_heading($content, $this->get_tab_label());
        return $data->export_for_template($output);
    }

    /**
     * For each assignable role in the given context get role name, description, users list, link to assign page
     *
     * @param \context $context
     * @return array
     */
    protected function get_rows_for_roles_table(\context $context) {
        global $DB;

        $rows = [];
        list($assignableroles, $assigncounts, $nameswithcounts) = get_assignable_roles($context, ROLENAME_BOTH, true);

        // TODO there is no way to return from assign page back to this page.
        $url = new \moodle_url('/admin/roles/assign.php', ['contextid' => $context->id]);
        $roleholdernames = $this->get_role_holders($context, $assignableroles, $assigncounts, $url);

        $postfix = '';
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            $postfix = '<br>(' . $context->get_context_name() . ')';
        }

        foreach ($assignableroles as $roleid => $rolename) {
            $description = format_string($DB->get_field('role', 'description', array('id' => $roleid)));
            $assignurl = new \moodle_url($url, ['roleid' => $roleid]);
            $rows[] = [
                \html_writer::link($assignurl, $rolename) . $postfix,
                $description,
                $assigncounts[$roleid],
                $roleholdernames[$roleid]
            ];
        }

        return $rows;
    }

    /**
     * Get the names of role holders for roles with between 1 and 10 users
     *
     * Copied from admin/role/assign.php
     *
     * @param \context $context
     * @param array $assignableroles
     * @param array $assigncounts
     * @param \moodle_url $url
     * @return array
     */
    protected function get_role_holders(\context $context, array $assignableroles, array $assigncounts, \moodle_url $url) {
        $roleholdernames = [];
        $maxuserstolistperrole = 10; // Same as MAX_USERS_TO_LIST_PER_ROLE in role/assign.php .
        $strmorethanmax = get_string('morethan', 'core_role', $maxuserstolistperrole);
        foreach ($assignableroles as $roleid => $notused) {
            if (0 < $assigncounts[$roleid] && $assigncounts[$roleid] <= $maxuserstolistperrole) {
                $userfields = 'u.id, u.username, ' . get_all_user_name_fields(true, 'u');
                $roleusers = get_role_users($roleid, $context, false, $userfields);
                if (!empty($roleusers)) {
                    $strroleusers = array();
                    foreach ($roleusers as $user) {
                        $strroleusers[] = \html_writer::link(
                            new \moodle_url('/user/view.php', ['id' => $user->id]), fullname($user));
                    }
                    $roleholdernames[$roleid] = implode('<br />', $strroleusers);
                }
            } else if ($assigncounts[$roleid] > $maxuserstolistperrole) {
                $assignurl = new \moodle_url($url, ['roleid' => $roleid]);
                $roleholdernames[$roleid] = \html_writer::link($assignurl, $strmorethanmax);
            } else {
                $roleholdernames[$roleid] = '';
            }
        }

        return $roleholdernames;
    }

    /**
     * Print overview table.
     *
     * Copied from admin/role/assign.php
     *
     * @param array $rows
     * @return string
     */
    protected function roles_table(array $rows) {
        $table = new \html_table();
        $table->id = 'assignrole';
        $table->head = [
            get_string('role'),
            get_string('description'),
            get_string('userswiththisrole', 'core_role')
        ];
        $table->colclasses = ['leftalign role', 'leftalign', 'centeralign userrole', 'leftalign roleholder'];
        $table->attributes['class'] = 'admintable generaltable';
        $table->headspan = [1, 1, 2];

        $table->data = $rows;

        return \html_writer::table($table);
    }
}
