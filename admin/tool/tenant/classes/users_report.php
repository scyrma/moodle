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
 * Class for the users report
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant;

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\report_column;

/**
 * Class for the users report
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users_report extends \tool_reportbuilder\system_report {

    /** @var int */
    protected $tenantid;

    /**
     * Initialise the report.
     */
    public function initialise() {
        $this->tenantid = $this->get_parameter('id', 0, PARAM_INT) ?: tenancy::get_tenant_id();
        $this->set_main_table('user', 'u');
        $this->set_downloadable(false);
        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u', $this->tenantid);
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);
        $this->add_base_fields('u.id, u.suspended'); // Necessary for actions.
        $this->add_actions();
        $this->set_columns();
        $this->set_filters();
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_browse_users($this->tenantid);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('userscount', 'tool_tenant');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns() {
        global $CFG;
        $this->add_entity(new user_entity('', 'u'));

        $showcheckboxes =
            permission::can_suspend_users($this->tenantid) ||
            permission::can_delete_users($this->tenantid) ||
            permission::can_move_users_between_tenants();

        if ($showcheckboxes) {
            $movecolumn = (new report_column(
                'check',
                new \lang_string('select'),
                'user'
            ))
                ->add_fields('u.id,'.user_entity::get_all_user_name_fields(true, 'u'))
                ->set_is_default(true, 0)
                ->add_callback([$this, 'col_checkbox']);
            $this->add_column($movecolumn);
        }

        $adminsql = '(SELECT 1
                       FROM {role_assignments} ra
                      WHERE ra.userid = u.id
                        AND ra.component = :comp
                        AND ra.itemid = :itemid
                        AND ra.roleid = :roleid)';
        $adminparams = ['comp' => 'tool_tenant', 'itemid' => $this->tenantid, 'roleid' => (int)$CFG->tool_tenant_adminrole];

        $this->get_column('user:fullnamewithpicturelink')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true, 1)
            ->add_field($adminsql, 'tenantadmin', $adminparams)
            ->set_visiblename(new \lang_string('fullname'))
            ->add_callback([$this, 'append_admin_label']);

        // Get additional fields.
        $context = \context_system::instance();
        $additionaluserfields = \get_extra_user_fields($context);
        $extra = preg_split('/,/', $CFG->showuseridentity, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($extra as $key => $userfield) {
            $this->get_column('user:'.$userfield)
                ->set_is_default(true, $key + 2)
                ->set_is_available(in_array($userfield, $additionaluserfields))
                ->add_callback([$this, 'country_code_transform']);
        }

        $this->get_column('user:lastaccess')
            ->set_is_default(true, $key + 3)
            ->set_is_sortable(true, true, 1);
    }

    /**
     * Set the filters for the report.
     */
    protected function set_filters() {
        $filters = $this->get_filters();
        $filters['user:fullname']->set_is_default(true);
        $filters['user:username']->set_is_default(true);
        $filters['user:email']->set_is_default(true);
    }

    /**
     * Takes a column and creates a checkbox element with it.
     *
     * @param  int $value
     * @param  \stdClass $row
     * @return string The checkbox element.
     */
    public function col_checkbox(int $value, \stdClass $row) : string {
        $id = 'selectuser' . $value;
        $checkbox = \html_writer::checkbox('users[' . $value . ']', $value, false, null,
            ['id' => $id, 'data-bulkuserid' => $value]);
        $label = get_string('selectuser', 'tool_tenant', fullname($row));
        return $checkbox . \html_writer::tag('label', $label,
                ['for' => $id, 'class' => 'accesshide']);
    }

    /**
     * Changes the country code to a language string.
     *
     * @param  string    $value Value of the row.
     * @param  \stdClass $row   The whole row
     * @return string the converted country string.
     */
    public function country_code_transform(string $value, \stdClass $row) : string {
        if (!empty($row->country) && $value == $row->country) {
            return get_string($value, 'countries');
        }
        return $value;
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions() {
        $tenantid = $this->tenantid;
        $blankurl = new \moodle_url('#');

        $icon = new \pix_icon('i/settings', get_string('edituser', 'tool_tenant'), 'core');
        $action = (new \tool_reportbuilder\report_action($blankurl, $icon, [
            'data-action' => 'edit',
            'data-id' => ':id',
        ]))
            ->add_callback(function(\stdClass $row) use ($tenantid) {
                return permission::can_update_user($row, $tenantid);
            });
        $this->add_action($action);

        $icon = new \pix_icon('t/hide', get_string('suspenduser', 'tool_tenant'), 'core');
        $action = (new \tool_reportbuilder\report_action($blankurl, $icon, [
            'data-action' => 'suspend',
            'data-id' => ':id',
        ]))
            ->add_callback(function(\stdClass $row) use ($tenantid) {
                return permission::can_suspend_user($row, $tenantid);
            });
        $this->add_action($action);

        $icon = new \pix_icon('t/show', get_string('unsuspenduser', 'tool_tenant'), 'core');
        $action = (new \tool_reportbuilder\report_action($blankurl, $icon, [
            'data-action' => 'unsuspend',
            'data-id' => ':id',
        ]))
            ->add_callback(function(\stdClass $row) use ($tenantid) {
                return permission::can_unsuspend_user($row, $tenantid);
            });
        $this->add_action($action);

        $icon = new \pix_icon('t/delete', get_string('deleteuser', 'tool_tenant'), 'core');
        $action = (new \tool_reportbuilder\report_action($blankurl, $icon, [
            'data-action' => 'delete',
            'data-id' => ':id',
        ]))
            ->add_callback(function(\stdClass $row) use ($tenantid) {
                return permission::can_delete_user($row, $tenantid);
            });
        $this->add_action($action);
    }

    /**
     * Row class
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        return $row->suspended ? 'dimmed_text' : '';
    }

    /**
     * Append the 'Tenant administrator' label/tag after user fullname when needed.
     *
     * @param string $value
     * @param \stdClass $row
     * @return string
     */
    public function append_admin_label($value, \stdClass $row): string {
        if ($row->tenantadmin > 0) {
            $value .= \html_writer::tag('span', get_string('tenantadmin', 'tool_tenant'), ['class' => 'label ml-2']);
        }
        return $value;
    }
}
