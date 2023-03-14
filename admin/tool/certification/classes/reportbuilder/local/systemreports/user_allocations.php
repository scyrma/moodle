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

declare(strict_types=1);

namespace tool_certification\reportbuilder\local\systemreports;

use context_system;
use core_reportbuilder\local\report\action;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use core_user\fields;
use html_writer;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_certification\permission;
use tool_organisation\organisation;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_user;
use tool_tenant\hierarchy;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * Allocations system report implementation
 *
 * @package   tool_certification
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_allocations extends system_report {

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
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        // Our main entity, it contains all of the column definitions that we need.
        $entitymain = new \tool_certification\reportbuilder\local\entities\certification_user();
        $entitymainalias = $entitymain->get_table_alias('tool_certification_users');

        $this->set_main_table('tool_certification_users', $entitymainalias);
        $this->add_entity($entitymain);

        // Add certification entity.
        $certificationentity = new \tool_certification\reportbuilder\local\entities\certification();
        $certificationalias = $certificationentity->get_table_alias('tool_certification');
        $certificationentity->add_join("JOIN {tool_certification} {$certificationalias}
        ON {$certificationalias}.id = {$entitymainalias}.certificationid");
        $this->add_entity($certificationentity);

        // Add user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $userentity->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$entitymainalias}.userid");
        $this->add_entity($userentity);

        // Add completion table join.
        $completionalias = $entitymain->get_table_alias('tool_certification_compltion');
        $this->add_join("LEFT JOIN {tool_certification_compltion} {$completionalias}
            ON {$completionalias}.certificationid = {$entitymainalias}.certificationid
            AND {$completionalias}.userid = {$entitymainalias}.userid
            AND {$completionalias}.timerevoked = 0
            AND {$completionalias}.islast = 1");

        // Join with tool_program table.
        $entityprogram = new program();
        $entityprogramalias = $entityprogram->get_table_alias('tool_program');
        $programjoin = "LEFT JOIN {tool_program} {$entityprogramalias}
        ON {$entityprogramalias}.id = {$entitymainalias}.currentprogramid";
        $this->add_entity($entityprogram->add_join($programjoin));

        // Join with tool_program_users table.
        $entityprogramuser = new program_user();
        $entityprogramuseralias = $entityprogramuser->get_table_alias('tool_program_users');
        $programuserjoin = "LEFT JOIN {tool_program_users} {$entityprogramuseralias}
        ON {$entityprogramuseralias}.userid = {$entitymainalias}.userid
        AND {$entityprogramuseralias}.programid = {$entityprogramalias}.id
        AND {$entityprogramuseralias}.certificationid = {$entitymainalias}.certificationid";
        $this->add_entity($entityprogramuser->add_join($programjoin)->add_join($programuserjoin));

        // Add tenant entity if current tenant has subtenants.
        if (hierarchy::has_subtenants(tenancy::get_tenant_id())) {
            $tenantentity = new tenant();
            $tenantentity->add_joins($tenantentity->get_user_tenant_joins("{$entitymainalias}.userid"));
            $this->add_entity($tenantentity);
        }

        // Set certification id condition.
        $this->add_base_condition_simple("{$entitymainalias}.certificationid", $this->get_certification()->get('id'));

        // Show only users from the current tenant and/or its subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$entitymainalias}.userid"));

        // Fields necessary for actions and row class.
        $tcufields = "{$entitymainalias}." . implode(", {$entitymainalias} . ",
                array_diff(array_keys(certification_user::properties_definition()), ['usermodified', 'description']));
        $this->add_base_fields($tcufields . ', '. "tcc.id AS completionid, {$entityprogramalias}.id AS programid,
        tcc.programid AS lastprogramid, tpu.id AS tpuid, (SELECT tpu2.id FROM {tool_program_users} tpu2
        WHERE tpu2.userid = tcu.userid AND tpu2.programid = tcc.programid
        AND tpu2.certificationid = tcu.certificationid) AS lasttpuid, {$entityprogramuseralias}.id AS programuserid" .
        fields::for_name()->get_sql($useralias)->selects);

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        // TODO check correct REPORT tenantid (can_view_inactive_users($this->get_tenant_id())).
        if (!\tool_tenant\permission::can_view_inactive_users()) {
            $this->add_base_condition_simple("{$useralias}.suspended", 0);
            $this->add_base_condition_simple("{$useralias}.confirmed", 1);
        }

        if (!permission::has_allocateuser_capability($this->get_certification()->get_context())) {
            // Managers with no system capability are only allowed to see the users they manage.
            if ($manager = organisation::get_user_with_jobs()) {
                [$where, $params] = $manager->get_managed_users_select('u', organisation::PERM_ALLOCATE_PROGRAMS);
                $this->add_base_condition_sql($where, $params);
            }
        }

        $this->add_columns($entitymainalias, $useralias);
        $this->add_filters();
        $this->add_actions();

        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_allocated_users($this->get_certification());
    }

    /**
     * Adds the columns we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     *
     * @param string $entitymainalias
     * @param string $useralias
     */
    public function add_columns(string $entitymainalias, string $useralias): void {

        // Checkboxes column for bulk actions.
        if (permission::can_view_allocated_users($this->get_certification())) {
            $this->add_column((new column(
                'check',
                null,
                'user'
            ))
                ->add_fields("{$useralias}.id" . fields::for_name()->get_sql($useralias)->selects .
                    ",{$entitymainalias}.id as certificationuserid")
                ->add_callback([$this, 'col_checkbox']));
        }

        $this->add_column_from_entity('user:fullnamewithpicturelink');

        $canshowtenantcolumn = hierarchy::has_subtenants(tenancy::get_tenant_id());
        if ($canshowtenantcolumn) {
            $this->add_column_from_entity('tenant:name');
        }

        $columns = [
            'certification_user:allocationtype',
            'certification_user:certificationstatus',
            'certification_user:timecreated',
            'certification_user:expirydate',
            'program:fullname',
            'program_user:duedate',
            'program_user:programstatus',
        ];

        $this->add_columns_from_entities($columns);

        // Change program fullname column title to 'Current program'.
        $this->get_column('program:fullname')
            ->set_title(new lang_string('currentprogram', 'tool_certification'));
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_filters(): void {
        $canshowtenantcolumn = hierarchy::has_subtenants(tenancy::get_tenant_id());
        if ($canshowtenantcolumn) {
            $this->add_filter_from_entity('tenant:name');
        }

        $filters = [
            'user:fullname',
            'certification_user:suspended',
            'certification_user:filterablestatus',
            'certification_user:allocationtype',
            'program_user:duedate',
            'certification_user:timecreated',
        ];

        $this->add_filters_from_entities($filters);
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     *
     * Note the use of ":id" placeholder which will be substituted according to actual values in the row
     */
    protected function add_actions(): void {

        // Action to edit user allocation.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/settings', '', 'core'),
            [
                'data-action' => 'user_edit_form',
                'data-userid' => ':userid',
                'data-certificationuserid' => ':id',
                'data-id' => ':certificationid'
            ],
            false,
            new lang_string('editallocation', 'tool_certification')
        ))->add_callback(function() {
            return permission::can_edit_user_allocation($this->lastcertuser);
        }));

        // Action to certify user.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/competencies', '', 'core'),
            [
                'data-action' => 'certify_user',
                'data-certificationuserid' => ':id',
                'data-userid' => ':userid',
                'data-id' => ':certificationid',
            ],
            false,
            new lang_string('certifyuser', 'tool_certification')
        ))->add_callback(function(stdClass $row) {
            return permission::can_certify_user($this->lastcertuser, (bool)$row->completionid);
        }));

        // Action to delete user allocation.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/trash', '', 'core'),
            [
                'data-action' => 'deallocate_user',
                'data-userid' => ':userid',
                'data-id' => ':certificationid'
            ],
            false,
            new lang_string('deleteallocation', 'tool_certification')
        ))->add_callback(function() {
            return permission::can_delete_user_allocation($this->lastcertuser);
        }));

        // Action to revoke user certification.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('arrow-circle-left', '', 'tool_wp'),
            [
                'data-action' => 'revoke_user',
                'data-certificationuserid' => ':id',
                'data-userid' => ':userid',
                'data-id' => ':certificationid'
            ],
            false,
            new lang_string('revokecertification', 'tool_certification')
        ))->add_callback(function(stdClass $row) {
            return permission::can_revoke_user_certification($this->lastcertuser, (bool)$row->completionid, (int)$row->programid);
        }));

        // Add divider.
        $this->add_action_divider();

        // Action to show certification user log.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/viewdetails', '', 'core'),
            [
                'data-action' => 'view_certification_user_log',
                'data-userid' => ':userid',
                'data-id' => ':certificationid'
            ],
            false,
            new lang_string('viewcertificationuserlog', 'tool_certification')
        ))->add_callback(function() {
            return permission::can_view_user_progress((int) $this->lastcertuser->get('userid'));
        }));

        // Action to show progress overview modal.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/dashboard', '', 'core'),
            [
                'data-action' => 'program_progress_overview',
                'data-userfullname' => ':userfullname',
                'data-allocationid' => ':programuserid',
                'data-contextid' => context_system::instance()->id,
            ],
            false,
            new lang_string('progressoverview', 'tool_program')
        ))->add_callback(function(stdClass $row) {
            $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
            $row->userfullname = fullname($row, $viewfullnames);
            return $row->programuserid && \tool_program\permission::can_view_user_programs_progress((int) $row->userid);
        }));
    }

    /**
     * Takes a column and creates a checkbox element with it.
     *
     * @param string $value
     * @param stdClass $row
     * @return string The checkbox element.
     */
    public function col_checkbox(string $value, stdClass $row) : string {
        $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
        $userfullname = fullname($row, $viewfullnames);
        $id = 'selectuser' . $value;
        $checkbox = html_writer::checkbox('bulkcheckbox', $value, false, null,
            [
                'id' => $id,
                'data-bulkuserid' => $value,
                'data-action' => 'toggle',
                'data-toggle' => 'slave',
                'data-fullname' => $userfullname,
                'data-togglegroup' => 'certification-users',
                'data-certificationuserid' => $row->certificationuserid,
            ]);
        return $checkbox . html_writer::tag('label', $userfullname, ['for' => $id, 'class' => 'accesshide']);
    }

    /**
     * Executed before each row
     *
     * @param stdClass $row
     */
    public function row_callback(stdClass $row): void {
        tenancy::mark_user_as_same_tenant((int) $row->userid);
        $this->lastcertuser = new certification_user(0, $row);
        $this->lastcertuser->set_certification($this->get_certification());
    }

    /**
     * Row class
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        return (int) $row->status === constants::STATUS_SUSPENDED ? 'text-muted' : '';
    }
}
