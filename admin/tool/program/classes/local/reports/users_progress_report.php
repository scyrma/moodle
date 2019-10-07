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
 * File that contains the class users_progress_report.
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\reports;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use pix_icon;
use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_organisation\organisation;
use tool_program\local\helpers\programcompletion_entity;
use tool_program\local\helpers\programuser_entity;
use tool_program\local\helpers\programuser_format;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\report_action;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;

/**
 * This class defines a system report that shows the progress/completion/status of users within one given program.
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_program
 */
class users_progress_report extends system_report {

    /** @var program */
    protected $program;

    /**
     * Current program
     *
     * @return program
     */
    protected function get_program(): program {
        if (!$this->program) {
            $programid = $this->get_parameter('programid', 0, PARAM_INT);
            $this->program = new program($programid);
        }
        return $this->program;
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $programid = $this->get_program()->get('id');
        $tenantid = tenancy::get_tenant_id();

        $this->set_columns();
        $this->set_main_table(program_user::TABLE, 'tpu');
        $this->add_base_condition_simple('tpu.programid', $programid);
        $this->add_base_fields('tpu.id, tpu.certificationid, tpu.programid, tpu.userid, tpu.status, tpu.enddate');
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tpu.programid');
        $this->add_base_join('INNER JOIN {tool_program_sets} tps ON tps.programid = tpu.programid AND tps.parent = 0');
        $this->add_base_join('LEFT JOIN {tool_program_set_completion} tpsc ON tpsc.setid = tps.id AND tpsc.userid = tpu.userid');
        $this->add_base_join('INNER JOIN {user} u ON u.id = tpu.userid');
        $this->add_base_join('LEFT JOIN {tool_certification} tc ON tc.id = tpu.certificationid');
        $this->add_base_join('LEFT JOIN {tool_certification_users} tcu
        ON tcu.userid = tpu.userid AND tcu.certificationid = tc.id');
        $this->add_base_join('LEFT JOIN {tool_certification_compltion} tcc
        ON tcc.certificationid = tpu.certificationid AND tcc.userid = tpu.userid AND tcc.timerevoked = 0');
        $this->add_base_condition_simple('tp.tenantid', $tenantid);
        $this->add_base_condition_simple('u.deleted', 0);

        // Check tenant id on users in case they have been moved to another tenant.
        [$join, $where, $params] = tenancy::get_users_sql('u', $tenantid);
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);

        if (!permission::has_allocateuser_capability($this->get_program()->get_context())) {
            // Managers with no system capability are only allowed to see the users they manage.
            if ($manager = organisation::get_user_with_jobs()) {
                [$where, $params] = $manager->get_managed_users_select('u', organisation::PERM_ALLOCATE_PROGRAMS);
                $this->add_base_condition_sql($where, $params);
            }
        }

        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);

        // Add default columns.
        if ($column = $this->get_column('user:fullname')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
            $column->add_fields('tpu.id as programuserid,' . implode(',', $this->get_user_columns()));
            $column->set_callback([programuser_format::class, 'userinfo']);
        }
        if ($column = $this->get_column('tool_program_users:startdate')) {
            $column->set_is_default(true, 2);
        }
        if ($column = $this->get_column('tool_program_users:duedate')) {
            $column->set_is_default(true, 3);
        }
        if ($column = $this->get_column('tool_program_users:enddate')) {
            $column->set_is_default(true, 4);
        }
        if ($column = $this->get_column('tool_program_users:timecreated')) {
            $column->set_is_default(true, 5);
        }
        if ($column = $this->get_column('tool_program_users:allocationtype')) {
            $column->set_is_default(true, 6);
        }
        if ($column = $this->get_column('tool_certification:fullnamewithlink')) {
            $column->set_is_default(true, 7);
            $column->set_visiblename(new \lang_string('certification', 'tool_program'));
        }
        if ($column = $this->get_column('tool_certification_users:certificationstatus')) {
            $column->set_is_default(true, 8);
        }
        if ($column = $this->get_column('tool_program_users:programstatus')) {
            $column->set_is_default(true, 9);
        }
        if ($column = $this->get_column('tool_program_users:programprogress')) {
            $column->set_is_default(true, 10);
        }
        if ($column = $this->get_column('tool_program_set_completion:completeddate')) {
            $column->set_is_default(true, 11);
        }
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_users_progress($this->get_program());
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportprogramprogress', 'tool_program');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns(): void {
        $this->add_entity(new programuser_entity('', 'tpu', $this->get_programuser_excluded_columns(), 'tpsc'));
        $this->add_entity(new programcompletion_entity('', 'tpsc', $this->get_programcompletion_excluded_columns()));
        $this->add_entity(new user_entity('', 'u'));
        $this->add_entity(new certification_entity('', 'tc', $this->get_certification_excluded_columns()));
        $this->add_entity(new certificationuser_entity('', 'tcu', $this->get_certificationuser_excluded_columns(), 'tcc'));
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        $url = new moodle_url('/message/index.php', ['id' => ':userid']);
        $icon = new pix_icon('t/messages', get_string('sendmessage', 'core_message'), 'core');
        $params = [
            'class' => 'action-icon',
        ];
        $action = new report_action($url, $icon, $params);
        $this->add_action($action);

        $url = new moodle_url('/user/profile.php', ['id' => ':userid']);
        $icon = new pix_icon('i/user', get_string('profile'), 'core');
        $params = [
            'class' => 'action-icon',
        ];
        $action = new report_action($url, $icon, $params);
        $this->add_action($action);
    }

    /**
     * Returns an array of user column names prefixed with the given user table alias.
     * TODO we don't need all fields.
     *
     * @return array
     */
    private function get_user_columns(): array {
        global $DB;
        return array_map(static function($column) {
            return "u.$column";
        }, array_keys($DB->get_columns('user')));
    }

    /**
     * Returns an array with the excluded columns for programuser_entity.
     *
     * @return array
     */
    private function get_programuser_excluded_columns(): array {
        return ['programprogresswithoverview', 'suspended', 'timesuspended', 'timemodified', 'associatedcertification'];
    }

    /**
     * Returns an array with the excluded columns for programcompletion_entity.
     *
     * @return array
     */
    private function get_programcompletion_excluded_columns(): array {
        return ['completed', 'timemodified', 'timecreated'];
    }

    /**
     * Returns an array with the excluded columns for certification_entity.
     *
     * @return array
     */
    private function get_certification_excluded_columns(): array {
        return ['fullname', 'idnumber', 'timearchived', 'archived', 'startdate', 'duedate', 'expirydate',
            'allocationstartdate', 'allocationenddate', 'timemodified', 'timecreated'];
    }

    /**
     * Returns an array with the excluded columns for certificationuser_entity.
     *
     * @return array
     */
    private function get_certificationuser_excluded_columns(): array {
        return ['allocationtype', 'startdate', 'duedate', 'expirydate', 'suspended', 'timesuspended', 'timecreated',
            'timemodified', 'daystakingcertification', 'dayssinceallocation'];
    }
}
