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
 * Class for define the system report for active/overdue programs.
 *
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\reports;

defined('MOODLE_INTERNAL') || die();

use lang_string;
use tool_certification\certification_user;
use tool_program\api;
use tool_program\local\helpers\format;
use tool_program\local\helpers\programcompletion_format;
use tool_program\local\helpers\programuser_format;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;
use tool_wp\db;

/**
 * Class user_programs
 *
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_program
 */
class programs_progress_report extends system_report {
    /** @var int $userid */
    private $userid;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->userid = $this->get_parameter('userid', 0, PARAM_INT);

        $u = 'u'; // User table alias.
        $p = 'p'; // Program table alias.
        $pu = 'pu'; // Program user table alias.
        $ps = 'ps'; // Program set table alias.
        $psc = 'psc'; // Program set completion table alias.
        $this->set_columns($p, $pu, $psc);
        $type = $this->get_parameter('type', -1, PARAM_INT);
        $this->set_filters($type, $pu, $psc);

        $this->set_main_table('user', $u);
        $this->add_base_condition_simple("{$u}.id", $this->userid);
        $this->add_base_join(api::get_status_sql_join($u, $pu, $p, $ps, $psc));

        // Base condition is a visibility check (programs non archived, from correct tenant and not hidden).
        $usertenantid = tenancy::get_tenant_id($this->userid);
        $tenant = db::generate_param_name();
        $this->add_base_condition_sql("
                {$p}.archived = 0
                AND {$ps}.parent = 0
                AND {$p}.tenantid = :{$tenant}
                AND {$p}.visible = 1 ", [$tenant => $usertenantid]);

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
        return permission::can_view_user_programs_progress($this->userid);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportuserprograms', 'tool_program');
    }

    /**
     * Set the columns for the report.
     *
     * @param string $p Programs table alias.
     * @param string $pu Program users table alias.
     * @param string $psc Program set completion table alias.
     */
    protected function set_columns($p = 'p', $pu = 'pu', $psc = 'psc'): void {
        $this->annotate_entity(program::TABLE, new lang_string('entityprogram', 'tool_program'));
        $this->annotate_entity(program_user::TABLE, new lang_string('entityprogramusers', 'tool_program'));
        $this->annotate_entity(program_set_completion::TABLE, new lang_string('entityprogramcompletion', 'tool_program'));
        $this->annotate_entity(certification_user::TABLE, new lang_string('entitycertificationusers', 'tool_certification'));

        // Column "fullname" (program name).
        $newcolumn = (new report_column(
            'fullname',
            new lang_string('programname', 'tool_program'),
            'tool_program'
        ))
            ->add_field("{$p}.fullname")
            ->add_field("{$p}.id", 'programid')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true)
            ->add_callback([programuser_format::class, 'userprogramname'], ['userid' => $this->userid]);
        $this->add_column($newcolumn);

        // Column "certificationid" (associated certification, if any).
        $newcolumn = (new report_column(
            'certificationid',
            new lang_string('associatedcertifications', 'tool_program'),
            'tool_program_users'
        ))
            ->add_field("{$pu}.certificationid")
            ->set_is_default(true, 2)
            ->add_callback([programuser_format::class, 'certificationname']);
        $this->add_column($newcolumn);

        // Column "certified expirydate".
        $cu = 'cu'; // Certification user table alias.
        $certificationuserjoin = "LEFT JOIN {" . certification_user::TABLE . "} $cu
                                         ON $cu.userid = $pu.userid
                                        AND $cu.certificationid = $pu.certificationid";
        $newcolumn = (new report_column(
            'userexpirydate',
            new lang_string('expirydate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_fields("$cu.expirydate, $cu.expirydatelocked, $cu.userid, $cu.certificationid")
            ->add_join($certificationuserjoin)
            ->set_is_default(true, 3);
        $newcolumn->add_callback([programuser_format::class, 'userexpirydate']);
        $this->add_column($newcolumn);

        // Column "duedate" (program or certification due date).
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_program'),
            'tool_program_users'
        ))
            ->add_field("{$pu}.duedate")
            ->add_field("{$pu}.duedatelocked")
            ->set_is_default(true, 4)
            ->add_callback([programuser_format::class, 'duedate']);
        $this->add_column($newcolumn);

        // Column "status" (program or certification status).
        $newcolumn = (new report_column(
            'status',
            new lang_string('programstatus', 'tool_program'),
            'tool_program_users'
        ))
            ->add_field("{$pu}.userid")
            ->add_field("{$pu}.certificationid")
            ->add_field("{$pu}.programid")
            ->set_is_default(true, 5)
            ->add_callback([programuser_format::class, 'programstatus']);
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            'progress',
            new lang_string('programprogress', 'tool_program'),
            'tool_program_users'
        ))
            ->add_fields("$pu.id, $pu.userid, $pu.certificationid, $pu.programid")
            ->set_is_default(true, 6)
            ->add_callback([programuser_format::class, 'progressoverviewlink']);
        $this->add_column($newcolumn);

        // Column "completion date".
        $newcolumn = (new report_column(
            'completiondate',
            new lang_string('completiondate', 'tool_program'),
            'tool_program_set_completion'
        ))
            ->add_fields("$psc.completeddate")
            ->set_is_default(true, 7)
            ->add_callback([programcompletion_format::class, 'completeddate']);
        $this->add_column($newcolumn);
    }

    /**
     * Set filters.
     *
     * @param int $status value of the filtered status
     * @param string $pu program user table alias
     * @param string $psc program set completion table alias
     */
    protected function set_filters(int $status, $pu = 'pu', $psc = 'psc'): void {
        // Filter by status.
        $filter = (new report_filter(
            select::class,
            'filterablestatus',
            new lang_string('status', 'tool_program'),
            'tool_program_users',
            api::get_status_sql_cases($status, $pu, $psc)
        ))
            ->set_is_default(true)
            ->set_options(api::get_program_statuses_fieldset());
        $this->add_filter($filter);
    }

    /**
     * Set conditions.
     */
    protected function set_conditions(): void {
        // No conditions defined.
    }

    /**
     * Set the actions icons of the report.
     */
    protected function add_actions(): void {
        // No actions defined.
    }
}
