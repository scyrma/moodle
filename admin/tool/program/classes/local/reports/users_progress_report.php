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

use lang_string;
use moodle_url;
use pix_icon;
use tool_certification\api;
use tool_certification\local\helpers\certificationuser_format;
use tool_organisation\organisation;
use tool_program\local\helpers\format;
use tool_program\local\helpers\programcompletion_format;
use tool_program\local\helpers\programuser_format;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use context_system;
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
        $pu = 'pu'; // Program users table alias.
        $pr = 'pr'; // Programs table alias
        $ps = 'ps'; // Program sets table alias.
        $psc = 'psc'; // Program set completions table alias.
        $u = 'u'; // User table alias.
        $tenantid = tenancy::get_tenant_id();

        $this->set_columns($pu, $u, $ps, $psc);
        $this->set_main_table(program_user::TABLE, $pu);
        $this->add_base_condition_simple("$pu.programid", $programid);
        $this->add_base_fields("$pu.id, $pu.certificationid, $pu.programid, $pu.userid, $pu.status, $pu.enddate");
        $this->add_base_join("INNER JOIN {" . program::TABLE . "} $pr ON $pr.id = $pu.programid");
        $this->add_base_join("INNER JOIN {" . program_set::TABLE . "} $ps ON $ps.programid = $pu.programid AND $ps.parent = 0");
        $this->add_base_join("INNER JOIN {user} $u ON $u.id = $pu.userid");
        $this->add_base_condition_simple("{$pr}.tenantid", $tenantid);
        $this->add_base_condition_simple("{$u}.deleted", 0);

        // Check tenant id on users in case they have been moved to another tenant.
        [$join, $where, $params] = tenancy::get_users_sql('u', $tenantid);
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);

        if (!permission::has_allocateuser_capability($this->get_program()->get_context())) {
            // Managers with no system capability are only allowed to see the users they manage.
            if ($manager = organisation::get_user_with_jobs()) {
                [$where, $params] = $manager->get_managed_users_select($u, organisation::PERM_ALLOCATE_PROGRAMS);
                $this->add_base_condition_sql($where, $params);
            }
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
     *
     * @param string $pu Program users table alias.
     * @param string $u Users table alias.
     * @param string $ps Program sets table alias.
     * @param string $psc Program set completions table alias.
     * @return mixed
     */
    protected function set_columns(string $pu, string $u, string $ps, string $psc): void {
        $this->annotate_entity(program::TABLE, new lang_string('entityprogram', 'tool_program'));
        $this->annotate_entity(program_user::TABLE, new lang_string('entityprogramusers', 'tool_program'));
        $this->annotate_entity(program_set::TABLE, new lang_string('entityprogramset', 'tool_program'));
        $this->annotate_entity(program_set_completion::TABLE, new lang_string('entityprogramcompletion', 'tool_program'));
        $this->annotate_entity('user', new lang_string('entityuser', 'tool_reportbuilder'));
        $this->annotate_entity('tool_organisation_position', new lang_string('entityposition', 'tool_organisation'));
        $this->annotate_entity('tool_organisation_department', new lang_string('entitydepartment', 'tool_organisation'));

        // Column "userinfo".
        $newcolumn = (new report_column(
            'userinfo',
            new lang_string('fullname', 'tool_program'),
            'user'
        ))
            ->add_fields("$pu.id as programuserid," . implode(',', $this->get_user_columns($u)))
            ->set_is_default(true, 1);
        $newcolumn->add_callback([programuser_format::class, 'userinfo']);
        $this->add_column($newcolumn);

        // Column "startdate".
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_program'),
            'tool_program_users'
        ))
            ->add_fields("$pu.startdate, $pu.startdatelocked")
            ->set_is_default(true, 2)
            ->add_callback([programuser_format::class, 'startdate']);
        $this->add_column($newcolumn);

        // Column "duedate".
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_program'),
            'tool_program_users'
        ))
            ->add_fields("$pu.duedate, $pu.duedatelocked")
            ->set_is_default(true, 3)
            ->add_callback([programuser_format::class, 'duedate']);
        $this->add_column($newcolumn);

        // Column "enddate".
        $newcolumn = (new report_column(
            'enddate',
            new lang_string('enddate', 'tool_program'),
            'tool_program_users'
        ))
            ->add_fields("$pu.enddate, $pu.enddatelocked")
            ->set_is_default(true, 4)
            ->add_callback([programuser_format::class, 'enddate']);
        $this->add_column($newcolumn);

        // Column "allocationdate".
        $newcolumn = (new report_column(
            'allocationdate',
            new lang_string('allocationdate', 'tool_program'),
            'tool_program_set_completion'
        ))
            ->add_field("$pu.timecreated")
            ->set_is_default(true, 5)
            ->add_callback([programuser_format::class, 'timecreated']);
        $this->add_column($newcolumn);

        // Column "allocationtype".
        $newcolumn = (new report_column(
            'allocationtype',
            new lang_string('allocationsource', 'tool_program'),
            'tool_program_users'
        ))
            ->add_field("$pu.allocationtype")
            ->set_is_default(true, 6)
            ->add_callback([programuser_format::class, 'allocationtype']);
        $this->add_column($newcolumn);

        // Column "certification".
        $newcolumn = (new report_column(
            'certificationuser',
            new lang_string('certification', 'tool_program'),
            'tool_program'
        ))
            ->add_field("$pu.certificationid", 'certificationuser')
            ->set_is_default(true, 7)
            ->add_callback([programuser_format::class, 'certificationuser']);
        $this->add_column($newcolumn);

        // Column "certification status".
        $tccjoin = "LEFT JOIN {tool_certification_users} tcu
        ON tcu.certificationid = $pu.certificationid AND tcu.userid = $pu.userid
        LEFT JOIN {tool_certification_compltion} tcc
        ON tcc.certificationid = tcu.certificationid
        AND tcc.userid = tcu.userid
        AND tcc.timerevoked = 0";

        $newcolumn = (new report_column(
            'certificationstatus',
            new lang_string('certificationstatus', 'tool_program'),
            'tool_program_users'
        ))
            ->add_join($tccjoin)
            ->add_field(api::get_status_sql_cases(0, 'tcu', 'tcc'), 'status')
            ->set_is_default(true, 8)
            ->add_callback([certificationuser_format::class, 'status']);
        $this->add_column($newcolumn);

        // Column "program status".
        $newcolumn = (new report_column(
            'programstatus',
            new lang_string('programstatus', 'tool_program'),
            'tool_program_users'
        ))
            ->add_fields("$pu.userid, $pu.certificationid, $pu.programid")
            ->set_is_default(true, 9)
            ->add_callback([programuser_format::class, 'programstatus']);
        $this->add_column($newcolumn);

        // Column "program progress".
        $newcolumn = (new report_column(
            'programprogress',
            new lang_string('programprogress', 'tool_program'),
            'tool_program_users'
        ))
            ->add_fields("$pu.userid, $pu.certificationid, $pu.programid")
            ->set_is_default(true, 10)
            ->add_callback([programuser_format::class, 'programprogress']);
        $this->add_column($newcolumn);

        // Column "completion date".
        $newcolumn = (new report_column(
            'completiondate',
            new lang_string('completiondate', 'tool_program'),
            'tool_program_set_completion'
        ))
            ->add_join("LEFT JOIN {" . program_set_completion::TABLE . "} $psc ON $psc.setid = $ps.id AND $psc.userid = $pu.userid")
            ->add_fields("$psc.completeddate")
            ->set_is_default(true, 11)
            ->add_callback([programcompletion_format::class, 'completeddate']);
        $this->add_column($newcolumn);
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
     * @param string $u
     * @return array
     */
    private function get_user_columns(string $u): array {
        global $DB;
        return array_map(static function($column) use ($u) {
            return "$u.$column";
        }, array_keys($DB->get_columns('user')));
    }
}
