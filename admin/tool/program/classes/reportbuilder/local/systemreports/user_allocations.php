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

namespace tool_program\reportbuilder\local\systemreports;

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
use tool_certification\reportbuilder\local\entities\certification;
use tool_certification\reportbuilder\local\entities\certification_completion;
use tool_certification\reportbuilder\local\entities\certification_user;
use tool_organisation\organisation;
use tool_program\constants;
use tool_program\reportbuilder\local\entities\program;
use tool_program\persistent\program as programpersistent;
use tool_program\permission;
use tool_program\reportbuilder\local\entities\program_content;
use tool_program\reportbuilder\local\entities\program_user;
use tool_program\task\reset_program;
use tool_tenant\hierarchy;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_tenant\tenancy;
use tool_wp\reportbuilder\local\entities\user;

/**
 * Allocations system report implementation
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_allocations extends system_report {

    /** @var programpersistent */
    protected $program;
    /** @var array */
    protected $tasks = [];

    /**
     * Current program
     *
     * @return programpersistent
     */
    protected function get_program(): programpersistent {
        if (!$this->program) {
            $programid = $this->get_parameter('id', 0, PARAM_INT);
            $this->program = new programpersistent($programid);
        }
        return $this->program;
    }

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        // Our main entity, it contains all of the column definitions that we need.
        $entitymain = new program_user();
        $entitymainalias = $entitymain->get_table_alias('tool_program_users');

        $this->set_main_table('tool_program_users', $entitymainalias);
        $this->add_entity($entitymain);

        // Join with tool_program table.
        $entityprogram = new program();
        $entityprogramalias = $entityprogram->get_table_alias('tool_program');
        $programjoin = "LEFT JOIN {tool_program} {$entityprogramalias} ON {$entityprogramalias}.id = {$entitymainalias}.programid";
        $this->add_entity($entityprogram->add_join($programjoin));

        // Add program content entity.
        $programcontententity = new program_content();
        $programset = $programcontententity->get_table_alias('tool_program_set');
        $programcontentjoin = "LEFT JOIN {tool_program_sets} {$programset}
        ON {$programset}.programid = {$entitymainalias}.programid AND {$programset}.parent = 0";
        $this->add_entity($programcontententity->add_join($programcontentjoin));

        // Add certification entity.
        $certificationentity = new certification();
        $certificationalias = $certificationentity->get_table_alias('tool_certification');
        $certificationentity->add_join("LEFT JOIN {tool_certification} {$certificationalias}
        ON {$certificationalias}.id = {$entitymainalias}.certificationid");
        $this->add_entity($certificationentity);

        // Add certification user entity.
        $certificationuserentity = new certification_user();
        $certificationuseralias = $certificationuserentity->get_table_alias('tool_certification_users');
        $certificationcompletionalias = $certificationuserentity->get_table_alias('tool_certification_compltion');

        // Add certification completion entity.
        $certificationcompletionentity = new certification_completion();
        // Ensure table alias is the same on both joins.
        $certificationcompletionentity->set_table_alias('tool_certification_compltion', $certificationcompletionalias);

        $certificationuserjoin = "
            LEFT JOIN {tool_certification_users} {$certificationuseralias}
                   ON {$certificationuseralias}.userid = {$entitymainalias}.userid
                  AND {$certificationuseralias}.certificationid = {$entitymainalias}.certificationid
        ";
        $certificationcompletionjoin = "
            LEFT JOIN {tool_certification_compltion} {$certificationcompletionalias}
                   ON {$certificationcompletionalias}.certificationid = {$entitymainalias}.certificationid
                  AND {$certificationcompletionalias}.userid = {$entitymainalias}.userid
                  AND {$certificationcompletionalias}.timerevoked = 0
                  AND {$certificationcompletionalias}.islast = 1
        ";

        $this->add_entity($certificationuserentity->add_joins([$certificationuserjoin, $certificationcompletionjoin]));
        $this->add_entity($certificationcompletionentity->add_joins([$certificationuserjoin, $certificationcompletionjoin]));

        // Add user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $userentity->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$entitymainalias}.userid");
        $this->add_entity($userentity);

        // Add tenant entity if current tenant has subtenants.
        if (hierarchy::has_subtenants(tenancy::get_tenant_id())) {
            $tenantentity = new tenant();
            $tenantentity->add_joins($tenantentity->get_user_tenant_joins("$entitymainalias.userid"));
            $this->add_entity($tenantentity);
        }

        // Set program id condition.
        $this->add_base_condition_simple("{$entitymainalias}.programid", $this->get_program()->get('id'));

        // Show only users from the current tenant and/or its subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$entitymainalias}.userid"));

        // Fields necessary for actions and row class.
        $this->add_base_fields("{$entitymainalias}.id, {$entitymainalias}.certificationid, {$entitymainalias}.programid,
        {$entitymainalias}.userid, {$entitymainalias}.status, {$entitymainalias}.enddate, {$entitymainalias}.allocationtype,
        '' as userfullname " . fields::for_name()->get_sql($useralias)->selects);

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        // TODO check correct REPORT tenantid (can_view_inactive_users($this->get_tenant_id())).
        if (!\tool_tenant\permission::can_view_inactive_users()) {
            $this->add_base_condition_simple("$useralias.suspended", 0);
            $this->add_base_condition_simple("$useralias.confirmed", 1);
        }

        if (!permission::has_allocateuser_capability($this->get_program()->get_context())) {
            // Managers with no system capability are only allowed to see the users they manage.
            if ($manager = organisation::get_user_with_jobs()) {
                [$where, $params] = $manager->get_managed_users_select('u', organisation::PERM_ALLOCATE_PROGRAMS);
                $this->add_base_condition_sql($where, $params);
            }
        }

        // Get list of program allocations pending to be reset.
        $tasks = \core\task\manager::get_adhoc_tasks(reset_program::class);
        foreach ($tasks as $task) {
            $this->tasks[$task->get_custom_data()->userid][$task->get_custom_data()->programid] = true;
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
        return permission::can_view_allocated_users($this->get_program());
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
        if (permission::can_view_allocated_users($this->get_program())) {
            $this->add_column((new column(
                'check',
                null,
                'user'
            ))
                ->add_fields("{$useralias}.id" . fields::for_name()->get_sql($useralias)->selects .
                    ",{$entitymainalias}.id as programuserid")
                ->add_callback([$this, 'col_checkbox']));
        }

        $this->add_column_from_entity('user:fullnamewithpicturelink');

        $canshowtenantcolumn = hierarchy::has_subtenants(tenancy::get_tenant_id());
        if ($canshowtenantcolumn) {
            $this->add_column_from_entity('tenant:name');
        }

        $columns = [
            'program_user:timecreated',
            'program_user:duedate',
            'program_user:allocationtype',
            'certification:fullname',
            'certification_user:certificationstatus',
            'program_user:programstatus',
        ];

        $this->add_columns_from_entities($columns);
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
            'program_user:suspended',
            'program_user:filterablestatus',
            'program_user:allocationtype',
            'certification:fullname',
            'program_user:duedate',
            'certification_completion:expirydate',
            'program_user:timecreated',
        ];

        $this->add_filters_from_entities($filters);
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     *
     * Note the use of ":id" placeholder which will be substituted according to actual values in the row
     */
    protected function add_actions(): void {
        $program = $this->get_program();

        // Action to edit user allocation.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/settings', '', 'core'),
            [
                'data-action' => 'user_edit_form',
                'data-userid' => ':userid',
                'data-userfullname' => ':userfullname',
                'data-programuserid' => ':id',
                'data-programid' => ':programid',
            ],
            false,
            new lang_string('editallocation', 'tool_program')
        ))->add_callback(static function(stdClass $row) use ($program) {
            $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
            $row->userfullname = fullname($row, $viewfullnames);
            $programuser = new \tool_program\persistent\program_user(0, $row);
            $programuser->set_program($program);
            return permission::can_edit_user_allocation($programuser);
        }));

        // Action for program re-completion.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('a/refresh', '', 'core'),
            [
                'data-action' => 'recalculate_completion',
                'data-userid' => ':userid',
                'data-id' => ':programid',
                'data-programuserid' => ':id',
            ],
            false,
            new lang_string('recalculateprogramcompletion', 'tool_program')
        ))->add_callback(static function(stdClass $row) use ($program) {
            $programuser = new \tool_program\persistent\program_user(0, $row);
            $programuser->set_program($program);
            return permission::can_recalculate_user_completion($programuser);
        }));

        // Action to reset program.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('step-backward', '', 'tool_wp'),
            [
                'data-action' => 'reset_program',
                'data-userid' => ':userid',
                'data-userfullname' => ':userfullname',
                'data-id' => ':programid',
                'data-programuserid' => ':id',
            ],
            false,
            new lang_string('programreset', 'tool_program')
        ))->add_callback(function(stdClass $row) use ($program) {
            $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
            $row->userfullname = fullname($row, $viewfullnames);

            // Find any existing pending tasks that reference the current userid & program - prevent user resetting it again.
            if (isset($this->tasks[$row->userid][$row->programid]) && $this->tasks[$row->userid][$row->programid]) {
                return false;
            }

            $programuser = new \tool_program\persistent\program_user(0, $row);
            $programuser->set_program($program);
            return permission::can_reset_progress($programuser);
        }));

        // Action for delete allocation.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/trash', '', 'core'),
            [
                'data-action' => 'deallocate_user',
                'data-userid' => ':userid',
                'data-userfullname' => ':userfullname',
                'data-id' => ':programid'
            ],
            false,
            new lang_string('deleteallocation', 'tool_program')
        ))->add_callback(static function(stdClass $row) use ($program) {
            $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
            $row->userfullname = fullname($row, $viewfullnames);
            $programuser = new \tool_program\persistent\program_user(0, $row);
            $programuser->set_program($program);
            return permission::can_delete_user_allocation($programuser);
        }));

        // Action to show progress report.
        $urlparams = ['programid' => ':programid', 'userid' => ':userid'];
        $this->add_action((new action(
            new moodle_url('/admin/tool/program/programprogress.php', $urlparams),
            new pix_icon('bar-chart', '', 'tool_wp'),
            [],
            false,
            new lang_string('progressreport', 'tool_program')
        ))->add_callback(static function(stdClass $row) {
            global $USER;
            return $USER->id != $row->userid && permission::can_view_user_programs_progress((int) $row->userid);
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
                'data-togglegroup' => 'program-users',
                'data-programuserid' => $row->programuserid,
            ]);
        return $checkbox . html_writer::tag('label', $userfullname, ['for' => $id, 'class' => 'accesshide']);
    }

    /**
     * Row class
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        return (int) $row->status === constants::STATUS_OVERRIDE_SUSPENDED ? 'text-muted' : '';
    }
}
