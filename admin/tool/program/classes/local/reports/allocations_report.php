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
 * Class for define the system report of the users allocated to program.
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\local\reports;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certificationcompletion_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_organisation\organisation;
use tool_program\constants;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programuser_entity;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_reportbuilder\local\entities\user;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use moodle_url;
use pix_icon;
use tool_program\task\reset_program;

/**
 * This class defines a system report that shows the users allocated to a given program and their allocation origin.
 *
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * @package    tool_program
 */
class allocations_report extends system_report {

    /** @var program */
    protected $program;
    /** @var array */
    protected $tasks = [];

    /**
     * Current program
     *
     * @return program
     */
    protected function get_program(): program {
        if (!$this->program) {
            $programid = $this->get_parameter('id', 0, PARAM_INT);
            $this->program = new program($programid);
        }
        return $this->program;
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $programid = $this->get_program()->get('id');

        $this->set_columns();
        $this->set_main_table('tool_program_users', 'tpu');
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tpu.programid');
        $this->add_base_join('INNER JOIN {tool_program_sets} tps ON tps.programid = tpu.programid AND tps.parent = 0');
        $this->add_base_join('LEFT JOIN {tool_program_set_completion} tpsc ON tpsc.setid = tps.id AND tpsc.userid = tpu.userid');
        $this->add_base_join('LEFT JOIN {tool_certification} tc ON tc.id = tpu.certificationid');
        $this->add_base_join('LEFT JOIN {tool_certification_users} tcu
        ON tcu.userid = tpu.userid AND tcu.certificationid = tpu.certificationid');
        $this->add_base_join('LEFT JOIN {tool_certification_compltion} tcc
        ON tcc.certificationid = tpu.certificationid AND tcc.userid = tpu.userid AND tcc.timerevoked = 0 AND tcc.islast = 1');
        $this->add_base_join('INNER JOIN {user} u ON u.id = tpu.userid');
        $this->add_base_condition_simple('tpu.programid', $programid);
        $this->add_base_condition_simple('u.deleted', 0);
        // Fields necessary for actions and row class.
        $this->add_base_fields('tpu.id, tpu.certificationid, tpu.programid, tpu.userid, tpu.status,
        tpu.enddate, tpu.allocationtype, \'\' as userfullname ' . \core_user\fields::for_name()->get_sql('u')->selects);

        // Show only users from the current tenant and/or its subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, 'tpu.userid'));

        if (!permission::has_allocateuser_capability($this->get_program()->get_context())) {
            // Managers with no system capability are only allowed to see the users they manage.
            if ($manager = organisation::get_user_with_jobs()) {
                [$where, $params] = $manager->get_managed_users_select('u', organisation::PERM_ALLOCATE_PROGRAMS);
                $this->add_base_condition_sql($where, $params);
            }
        }

        // Get list of program allocations pending to be reseted.
        $tasks = \core\task\manager::get_adhoc_tasks(reset_program::class);
        foreach ($tasks as $task) {
            $this->tasks[] = [
                'programid' => $task->get_custom_data()->programid,
                'userid' => $task->get_custom_data()->userid,
            ];
        }

        $this->add_actions();
        $this->set_downloadable(false);

        // Checkboxes column for bulk actions.
        if (permission::can_view_allocated_users($this->get_program())) {
            $column = (new report_column(
                'check',
                new \lang_string('select'),
                'user'
            ))
                ->add_fields('u.id,' . user::get_all_user_name_fields(true, 'u') . ',tpu.id as programuserid')
                ->add_attributes(['class' => 'sr-only-header', 'data-togglegroup-name' => 'program-users'])
                ->set_is_default(true, 0)
                ->add_callback([$this, 'col_checkbox']);
            $this->add_column($column);
        }

        // Default columns.
        if ($column = $this->get_column('user:fullnamewithpicturelink')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true, 1);
            $column->set_visiblename(new \lang_string('fullname'));
        }
        if ($column = $this->get_column('user:tenant')) {
            $column->set_is_default(true, 2);
        }
        if ($column = $this->get_column('tool_program_users:timecreated')) {
            $column->set_is_default(true, 3);
        }
        if ($column = $this->get_column('tool_program_users:duedate')) {
            $column->set_is_default(true, 4);
        }
        if ($column = $this->get_column('tool_program_users:allocationtype')) {
            $column->set_is_default(true, 5);
            $column->set_is_sortable(true, true, 2);
        }
        if ($column = $this->get_column('tool_certification:fullname')) {
            $column->set_is_default(true, 6);
            $column->set_is_sortable(true, true, 3);
        }
        if ($column = $this->get_column('tool_certification_users:certificationstatus')) {
            $column->set_is_default(true, 7);
        }
        if ($column = $this->get_column('tool_program_users:programstatus')) {
            $column->set_is_default(true, 8);
        }

        // Default filters.
        if ($filter = $this->get_filter('user:tenant')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('user:fullname')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_program_users:suspended')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_program_users:filterablestatus')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_program_users:allocationtype')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_certification:fullname')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_program_users:duedate')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_certification_compltion:expirydate')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('tool_program_users:timecreated')) {
            $filter->set_is_default(true);
        }
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_allocated_users($this->get_program());
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportprogramusers', 'tool_program');
    }

    /**
     * Set the columns for the report.
     *
     * @return mixed
     */
    protected function set_columns(): void {
        $this->add_entity(new program_entity());
        $this->add_entity(new programuser_entity());
        $this->add_entity((new user())->set_allow_tenant_columns(sharedspace::is_shared_space()));
        $this->add_entity(new certification_entity());
        $this->add_entity(new certificationuser_entity());
        $this->add_entity(new certificationcompletion_entity());
    }

    /**
     * Takes a column and creates a checkbox element with it.
     *
     * @param  int $value
     * @param stdClass $row
     * @return string The checkbox element.
     */
    public function col_checkbox(int $value, stdClass $row) : string {
        $userfullname = format::fullname($value, $row);
        $id = 'selectuser' . $value;
        $checkbox = \html_writer::checkbox('users[' . $value . ']', $value, false, null,
            ['id' => $id,
                'data-bulkuserid' => $value,
                'data-action' => 'toggle',
                'data-toggle' => 'slave',
                'data-fullname' => $userfullname,
                'data-togglegroup' => 'program-users',
                'data-programuserid' => $row->programuserid,
            ]);
        return $checkbox . \html_writer::tag('label', $userfullname,
                ['for' => $id, 'class' => 'accesshide']);
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        $program = $this->get_program();

        // User allocation edit icon.
        $editurl = new moodle_url('/admin/tool/program/edit.php', ['id' => ':id']);
        $editicon = new pix_icon('i/settings', get_string('edit'), 'core');
        $action = new report_action($editurl, $editicon, [
            'class' => 'edit_user',
            'data-action' => 'user_edit_form',
            'data-userid' => ':userid',
            'data-userfullname' => ':userfullname',
            'data-programuserid' => ':id',
            'data-programid' => ':programid',
        ]);
        // Check if allocation can be edited.
        $action->add_callback(static function($row) use ($program) {
            $row->userfullname = format::fullname(null, $row);
            $programuser = new program_user(0, $row);
            $programuser->set_program($program);
            return permission::can_edit_user_allocation($programuser);
        });
        $this->add_action($action);

        // User allocation reset program icon.
        $reseturl = new moodle_url('/admin/tool/program/reset.php', ['id' => ':id']);
        $reseticon = new pix_icon('step-backward', get_string('programreset', 'tool_program'), 'tool_wp');
        $action = new report_action($reseturl, $reseticon, [
            'class' => 'confirm_reset_program',
            'data-action' => 'reset_program',
            'data-userid' => ':userid',
            'data-userfullname' => ':userfullname',
            'data-id' => ':programid',
            'data-programuserid' => ':id',
        ]);
        // Check if program can be reseted for this user or if reset is in task queue.
        $action->add_callback(function($row) use ($program) {
            $row->userfullname = format::fullname(null, $row);
            // Find any existing tasks that reference the current userid & program - prevent user resetting it again.
            $allocationsinpendingtask = array_filter($this->tasks, static function($task) use ($row) {
                return (int) $row->programid === (int) $task['programid'] && (int) $row->userid === (int) $task['userid'];
            });
            if (!empty($allocationsinpendingtask)) {
                return false;
            }
            $programuser = new program_user(0, $row);
            $programuser->set_program($program);
            return permission::can_edit_user_allocation($programuser);
        });
        $this->add_action($action);

        // Progress report icon.
        $urlparams = ['programid' => ':programid', 'userid' => ':userid'];
        $progressreporturl = new \moodle_url("/admin/tool/program/programprogress.php", $urlparams);
        $progressreporticon = new \pix_icon('bar-chart',
            get_string('progressreport', 'tool_program'),
            'tool_wp');
        $action = new report_action($progressreporturl, $progressreporticon);
        $action->add_callback(function(stdClass $row) {
            global $USER;
            return $USER->id != $row->userid && permission::can_view_user_programs_progress($row->userid);
        });
        $this->add_action($action);

        // Progress overview icon.
        $progressoverviewurl = new \moodle_url("#");
        $progressoverviewicon = new \pix_icon('i/dashboard',
            get_string('progressoverview', 'tool_program'),
            'core');
        $action = new report_action($progressoverviewurl, $progressoverviewicon, [
            'class' => 'program-progress-overview-trigger',
            'data-allocationid' => ':id',
            'data-title' => get_string('progressoverview', 'tool_program'),
            'data-contextid' => \context_system::instance()->id
        ]);
        $action->add_callback(function(stdClass $row) {
            global $USER;
            return $USER->id != $row->userid && permission::can_view_user_programs_progress($row->userid);
        });
        $this->add_action($action);

        // User allocation delete icon.
        $deleteurl = new moodle_url('/admin/tool/program/delete.php', ['id' => ':id']);
        $deleteicon = new pix_icon('i/trash', get_string('delete'), 'core');
        $action = new report_action($deleteurl, $deleteicon, [
            'class' => 'confirm_deallocate_user',
            'data-action' => 'deallocate_user',
            'data-userid' => ':userid',
            'data-userfullname' => ':userfullname',
            'data-id' => ':programid'
        ]);
        // Check if allocation can be deleted.
        $action->add_callback(static function($row) use ($program) {
            $row->userfullname = format::fullname(null, $row);
            $programuser = new program_user(0, $row);
            $programuser->set_program($program);
            return permission::can_delete_user_allocation($programuser);
        });
        $this->add_action($action);
    }

    /**
     * CSS class for the row
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        $issuspendedorafterenddate = constants::STATUS_OVERRIDE_SUSPENDED === (int) $row->status
            || ($row->enddate && $row->enddate < time());
        return $issuspendedorafterenddate ? 'dimmed_text' : '';
    }
}
