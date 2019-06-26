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
 * File for the class that defines the system report of the users allocated to certification.
 *
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\reports;

defined('MOODLE_INTERNAL') || die();

use tool_certification\certification;
use tool_certification\permission;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_organisation\organisation;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use context_system;
use lang_string;
use tool_tenant\tenancy;

/**
 * Class that defines the system report of the users allocated to certification.
 *
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_certification
 */
class users_table extends system_report {

    /**
     * Initialise report
     */
    protected function initialise() {
        $certificationid = $this->get_parameter('id', 0, PARAM_INT);
        $this->set_columns();
        $this->set_main_table('tool_certification_users', 'tcu');
        $this->add_base_join('INNER JOIN {tool_certification} tc ON tc.id = tcu.certificationid');
        $this->add_base_condition_simple('tcu.certificationid', $certificationid);
        $this->add_base_condition_simple('tc.tenantid', tenancy::get_tenant_id());
        $this->add_base_condition_simple('tc.archived', 0);
        $this->add_base_fields('tcu.id, tcu.certificationid, tcu.userid'); // Fields necessary for actions.

        if (!permission::has_allocateuser_capability(context_system::instance())
            && class_exists('\\tool_organisation\\organisation')) {
            // Managers with no system capability are only allowed to see the users they manage.
            $manager = organisation::get_user_with_jobs();
            [$where, $params] = $manager->get_managed_users_select('u', organisation::PERM_ALLOCATE_PROGRAMS);
            $this->add_base_condition_sql($where, $params);
        }

        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_list(context_system::instance());
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('reportcertsusers', 'tool_certification');
    }

    /**
     * Set the columns for the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function set_columns(): void {
        $this->annotate_entity('user', new lang_string('entityuser', 'tool_reportbuilder'));
        $this->annotate_entity('tool_certification_users', new lang_string('entitycertificationusers', 'tool_certification'));

        // Column "fullname".
        $newcolumn = (new report_column(
            'fullname',
            new lang_string('fullname', 'tool_certification'),
            'user'
        ))
            ->add_join('INNER JOIN {user} u ON u.id = tcu.userid')
            ->add_fields(user_entity::get_all_user_name_fields(true, 'u') . ', tcu.userid')
            ->set_is_sortable(true, true)
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true, 0)
            ->add_callback([\tool_reportbuilder\local\helpers\format::class, 'fullname']);
        $this->add_column($newcolumn);

        // Column "duedate".
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_fields('duedate, duedatelocked')
            ->set_is_default(true, 2)
            ->add_callback([\tool_certification\local\helpers\format::class, 'duedate']);
        $this->add_column($newcolumn);

        // Column "allocationtype".
        $newcolumn = (new report_column(
            'allocationtype',
            new lang_string('allocationsource', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field('tcu.allocationtype')
            ->set_is_default(true, 3)
            ->set_is_sortable(true, true, 1, SORT_DESC)
            ->add_callback([\tool_certification\local\helpers\format::class, 'allocation_source']);
        $this->add_column($newcolumn);

        // Column "status".
        $newcolumn = (new report_column(
            'status',
            new lang_string('certificationstatus', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_fields('tcu.status, tcu.userid, tcu.certificationid')
            ->set_is_default(true, 4);
        $newcolumn->add_callback([\tool_certification\local\helpers\format::class, 'status']);
        $this->add_column($newcolumn);

        // Column "user expirydate".
        $newcolumn = (new report_column(
            'userexpirydate',
            new lang_string('expirydate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_fields('tcu.expirydate, tcu.expirydatelocked, tcu.userid, tcu.certificationid')
            ->set_is_default(true, 4);
        $newcolumn->add_callback([\tool_certification\local\helpers\format::class, 'userexpirydate']);
        $this->add_column($newcolumn);

        // Column "programstatus".
        $newcolumn = (new report_column(
            'programstatus',
            new lang_string('programstatus', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_join('LEFT JOIN {tool_certification} cer ON tcu.certificationid = cer.id')
            ->add_fields('tcu.status, tcu.userid, tcu.certificationid, cer.program')
            ->set_is_default(true, 5);
        $newcolumn->add_callback([\tool_certification\local\helpers\format::class, 'programstatus']);
        $this->add_column($newcolumn);
    }

    /**
     * Set the actions icons of the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function add_actions(): void {
        $context = context_system::instance();
        $certificationid = $this->get_parameter('id', 0, PARAM_INT);
        $certification = new certification($certificationid);

        if (permission::can_manage_user_allocation($context)) {
            // User allocation edit icon.
            $editurl = new \moodle_url('/admin/tool/certification/edit.php', ['id' => ':id']);
            $editicon = new \pix_icon('i/settings', get_string('edit'), 'core');
            $action = new report_action($editurl, $editicon, [
                'class' => 'action-icon edit_user',
                'data-action' => 'user_edit_form',
                'data-userid' => ':userid',
                'data-certificationuserid' => ':id',
                'data-certificationid' => ':certificationid'
            ]);
            $this->add_action($action);
        }

        if (permission::can_edit_details($certification, $context)) {
            // Certify icon.
            $certifyurl = new \moodle_url('/admin/tool/certification/certify.php');
            $certifystr = get_string('certifyuser', 'tool_certification');
            $certifyicon = new \pix_icon('e/tick', $certifystr, 'core');
            $action = new report_action($certifyurl, $certifyicon, [
                'class' => 'action-icon confirm_certify_user',
                'data-certificationuserid' => ':id',
                'data-userid' => ':userid',
                'data-id' => ':certificationid'
            ]);
            $action->add_callback([\tool_certification\permission::class, 'can_view_certify_user_icon']);
            $this->add_action($action);

            // Revoke icon.
            $revokeurl = new \moodle_url('/admin/tool/certification/revoke.php');
            $revokestr = get_string('revokecertification', 'tool_certification');
            $revokeicon = new \pix_icon('arrow-circle-left', $revokestr, 'tool_wp');
            $action = new report_action($revokeurl, $revokeicon, [
                'class' => 'action-icon confirm_revoke_user',
                'data-certificationuserid' => ':id',
                'data-userid' => ':userid',
                'data-id' => ':certificationid'
            ]);
            $action->add_callback([\tool_certification\permission::class, 'can_view_revoke_user_icon']);
            $this->add_action($action);
        }

        if (permission::can_manage_user_allocation($context)) {
            // User allocation delete icon.
            $deleteurl = new \moodle_url('/admin/tool/certification/delete.php', ['id' => ':id']);
            $deleteicon = new \pix_icon('i/trash', get_string('delete'), 'core');
            $action = new report_action($deleteurl, $deleteicon, [
                'class' => 'action-icon confirm_deallocate_user',
                'data-userid' => ':userid',
                'data-id' => ':certificationid'
            ]);
            $action->add_callback([\tool_certification\permission::class, 'can_view_deallocate_icon']);
            $this->add_action($action);
        }
    }
}
