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
 * File for the class that defines the system report of the users allocated to certification.
 *
 * @package    tool_certification
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\reports;

use lang_string;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\local\helpers\certificationcompletion_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_certification\permission;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programuser_entity;
use tool_reportbuilder\local\entities\user;
use tool_organisation\organisation;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use context_system;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;

/**
 * Class that defines the system report of the users allocated to certification.
 *
 * @package    tool_certification
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class users_table extends system_report {
    /** @var certification */
    protected $certification;
    /** @var certification_user */
    protected $lastcertuser;

    /**
     * Current certification
     *
     * @return certification
     */
    protected function get_certification(): certification {
        if (!$this->certification) {
            $this->certification = new certification($this->get_parameter('id', 0, PARAM_INT));
        }
        return $this->certification;
    }

    /**
     * Initialise report
     */
    protected function initialise() {
        $certificationid = $this->get_certification()->get('id');

        $this->set_columns();
        $this->set_main_table('tool_certification_users', 'tcu');
        $this->add_base_join('INNER JOIN {tool_certification} tc ON tc.id = tcu.certificationid');
        $this->add_base_join('INNER JOIN {user} u ON u.id = tcu.userid');
        $this->add_base_join('LEFT JOIN {tool_program} tp ON tp.id = tcu.currentprogramid');
        $this->add_base_join('LEFT JOIN {tool_program_users} tpu ON tpu.userid = tcu.userid AND tpu.programid = tp.id
            AND tpu.certificationid = tcu.certificationid');
        $this->add_base_join('LEFT JOIN {tool_certification_compltion} tcc
            ON tcc.certificationid = tcu.certificationid
            AND tcc.userid = tcu.userid
            AND tcc.timerevoked = 0
            AND tcc.islast = 1');

        $this->add_base_condition_simple('tcu.certificationid', $certificationid);
        $this->add_base_condition_simple('tc.archived', 0);
        $this->add_base_condition_simple('u.deleted', 0);
        $tcufields = 'tcu.'.join(', tcu.', array_diff(array_keys(certification_user::properties_definition()),
                ['usermodified', 'description']));
        // Fields necessary for actions.
        $this->add_base_fields($tcufields . ', '. "tcc.id AS completionid, tp.id AS programid, tcc.programid AS lastprogramid,
        tpu.id AS tpuid, (SELECT tpu2.id FROM {tool_program_users} tpu2 WHERE tpu2.userid = tcu.userid AND
        tpu2.programid = tcc.programid AND tpu2.certificationid = tcu.certificationid) AS lasttpuid");

        // Tenant checks.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, 'tcu.userid'));

        if (!permission::has_allocateuser_capability(context_system::instance())
            && class_exists('\\tool_organisation\\organisation')) {
            // Managers with no system capability are only allowed to see the users they manage.
            $manager = organisation::get_user_with_jobs();
            [$where, $params] = $manager->get_managed_users_select('u', organisation::PERM_ALLOCATE_PROGRAMS);
            $this->add_base_condition_sql($where, $params);
        }

        $this->add_actions();
        $this->set_downloadable(false);

        // Checkboxes column for bulk actions.
        if (permission::can_view_allocated_users($this->get_certification())) {
            $column = (new report_column(
                'check',
                new \lang_string('select'),
                'user'
            ))
                ->add_fields('u.id,' . user::get_all_user_name_fields(true, 'u') . ',tcu.id as certificationuserid')
                ->add_attributes(['class' => 'sr-only-header', 'data-togglegroup-name' => 'certification-users'])
                ->set_is_default(true, 0)
                ->add_callback([$this, 'col_checkbox']);
            $this->add_column($column);
        }

        // Default columns.
        if ($column = $this->get_column('user:fullnamewithpicturelink')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
            $column->set_visiblename(new \lang_string('fullname'));
        }
        if ($column = $this->get_column('user:tenant')) {
            $column->set_is_default(true, 2);
        }
        if ($column = $this->get_column('tool_certification_users:allocationtype')) {
            $column->set_is_default(true, 3);
        }
        if ($column = $this->get_column('tool_certification_users:certificationstatus')) {
            $column->set_is_default(true, 4);
        }
        if ($column = $this->get_column('tool_certification_users:timecreated')) {
            $column->set_is_default(true, 5);
        }
        if ($column = $this->get_column('tool_certification_compltion:expirydate')) {
            $column->set_is_default(true, 6);
        }
        if ($column = $this->get_column('tool_program:fullname')) {
            $column->set_is_default(true, 7);
            $column->set_visiblename(new lang_string('currentprogram', 'tool_certification'));
        }
        if ($column = $this->get_column('tool_program_users:duedate')) {
            $column->set_is_default(true, 8);
        }
        if ($column = $this->get_column('tool_program_users:programstatus')) {
            $column->set_is_default(true, 9);
            $column->add_fields('tpu.programid, tpu.certificationid, tpu.userid, tcu.userid as certuserid,
            tcu.certificationid as certcertificationid, tcc.programid as certprogramid');
        }

        if ($filter = $this->get_filter('user:tenant')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('user:fullname')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_certification_users:suspended')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_certification_users:filterablestatus')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_certification_users:allocationtype')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_program_users:duedate')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_certification_users:timecreated')) {
            $filter->set_is_default(true);
        }
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_allocated_users($this->get_certification());
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
     */
    protected function set_columns(): void {
        $this->add_entity(new certificationuser_entity());
        $this->add_entity((new user())->set_allow_tenant_columns(sharedspace::is_shared_space()));
        $this->add_entity(new programuser_entity());
        $this->add_entity(new certificationcompletion_entity());
        $this->add_entity(new program_entity());
    }

    /**
     * Takes a column and creates a checkbox element with it.
     *
     * @param  int $value
     * @param \stdClass $row
     * @return string The checkbox element.
     */
    public function col_checkbox(int $value, \stdClass $row) : string {
        $userfullname = format::fullname($value, $row);
        $id = 'selectuser' . $value;
        $checkbox = \html_writer::checkbox('users[' . $value . ']', $value, false, null,
            ['id' => $id,
                'data-bulkuserid' => $value,
                'data-action' => 'toggle',
                'data-toggle' => 'slave',
                'data-fullname' => $userfullname,
                'data-togglegroup' => 'certification-users',
                'data-certificationuserid' => $row->certificationuserid,
            ]);
        return $checkbox . \html_writer::tag('label', $userfullname,
                ['for' => $id, 'class' => 'accesshide']);
    }

    /**
     * Set the actions icons of the report.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function add_actions(): void {

        // User allocation edit icon.
        $editicon = new \pix_icon('i/settings', get_string('edit'), 'core');
        $action = new report_action(new \moodle_url('#'), $editicon, [
            'class' => 'edit_user',
            'data-action' => 'user_edit_form',
            'data-userid' => ':userid',
            'data-certificationuserid' => ':id',
            'data-id' => ':certificationid'
        ]);
        $action->add_callback(function() {
            return permission::can_edit_user_allocation($this->lastcertuser);
        });
        $this->add_action($action);

        // Certify icon.
        $certifyurl = new \moodle_url('/admin/tool/certification/certify.php');
        $certifystr = get_string('certifyuser', 'tool_certification');
        $certifyicon = new \pix_icon('e/tick', $certifystr, 'core');
        $action = new report_action($certifyurl, $certifyicon, [
            'class' => 'confirm_certify_user',
            'data-certificationuserid' => ':id',
            'data-userid' => ':userid',
            'data-id' => ':certificationid'
        ]);
        $action->add_callback(function(\stdClass $row) {
            return permission::can_certify_user($this->lastcertuser, (bool)$row->completionid);
        });
        $this->add_action($action);

        // Revoke icon.
        $revokeurl = new \moodle_url('/admin/tool/certification/revoke.php');
        $revokestr = get_string('revokecertification', 'tool_certification');
        $revokeicon = new \pix_icon('arrow-circle-left', $revokestr, 'tool_wp');
        $action = new report_action($revokeurl, $revokeicon, [
            'class' => 'confirm_revoke_user',
            'data-certificationuserid' => ':id',
            'data-userid' => ':userid',
            'data-id' => ':certificationid'
        ]);
        $action->add_callback(function(\stdClass $row) {
            return permission::can_revoke_user_certification($this->lastcertuser, (bool)$row->completionid, $row->programid);
        });
        $this->add_action($action);

        // Certification user log icon.
        $logurl = new \moodle_url('#');
        $logicon = new \pix_icon('check-circle-o',
            get_string('viewcertificationuserlog', 'tool_certification'),
            'tool_wp');
        $action = new report_action($logurl, $logicon, [
            'class' => 'view_certification_user_log',
            'data-userid' => ':userid',
            'data-id' => ':certificationid'
        ]);
        $action->add_callback(function() {
            return permission::can_view_user_progress($this->lastcertuser->get('userid'));
        });
        $this->add_action($action);

        // Progress report icon.
        $urlparams = ['programid' => ':programid', 'userid' => ':userid', 'lastprogramid' => ':lastprogramid'];
        $progressreporturl = new \moodle_url("/admin/tool/program/programprogress.php", $urlparams);
        $progressreporticon = new \pix_icon('bar-chart',
            get_string('progressreport', 'tool_program'),
            'tool_wp');
        $action = new report_action($progressreporturl, $progressreporticon);
        $action->add_callback(function(\stdClass $row) {
            global $USER;
            return $USER->id != $row->userid && \tool_program\permission::can_view_user_programs_progress($row->userid);
        });
        $this->add_action($action);

        // Progress overview icon.
        $progressoverviewurl = new \moodle_url("#");
        $progressoverviewicon = new \pix_icon('i/dashboard',
            get_string('progressoverview', 'tool_program'),
            'core');
        $action = new report_action($progressoverviewurl, $progressoverviewicon, [
            'class' => 'program-progress-overview-trigger',
            'data-allocationid' => ':tpuid',
            'data-lastallocationid' => ':lasttpuid',
            'data-title' => get_string('progressoverview', 'tool_program'),
            'data-contextid' => \context_system::instance()->id
        ]);
        $action->add_callback(function(\stdClass $row) {
            global $USER;
            return $USER->id != $row->userid && \tool_program\permission::can_view_user_programs_progress($row->userid);
        });
        $this->add_action($action);

        // User allocation delete icon.
        $deleteurl = new \moodle_url('/admin/tool/certification/delete.php', ['id' => ':id']);
        $deleteicon = new \pix_icon('i/trash', get_string('delete'), 'core');
        $action = new report_action($deleteurl, $deleteicon, [
            'class' => 'confirm_deallocate_user',
            'data-userid' => ':userid',
            'data-id' => ':certificationid'
        ]);
        $action->add_callback(function() {
            return permission::can_delete_user_allocation($this->lastcertuser);
        });
        $this->add_action($action);
    }

    /**
     * Executed before each row
     *
     * @param \stdClass $row
     */
    public function row_callback(\stdClass $row): void {
        tenancy::mark_user_as_same_tenant($row->userid);
        $this->lastcertuser = new certification_user(0, $row);
        $this->lastcertuser->set_certification($this->get_certification());
    }
}
