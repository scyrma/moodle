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
 * File for the class programs_overdue_report.
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\reports;

defined('MOODLE_INTERNAL') || die();

use tool_certification\certification_user;
use tool_certification\local\helpers\certificationuser_entity;
use tool_organisation\organisation;
use tool_program\api;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programuser_entity;
use tool_program\permission;
use tool_reportbuilder\local\entities\user;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;
use tool_wp\db;

/**
 * Class programs_overdue_report
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_program
 */
class programs_overdue_report extends system_report {
    /**
     * Initialise report
     */
    protected function initialise(): void {

        $u = 'u'; // User table alias.
        $tp = 'tp'; // Program table alias.
        $tpu = 'tpu'; // Program user table alias.
        $tps = 'tps'; // Program set table alias.
        $tpsc = 'tpsc'; // Program set completion table alias.
        $tcu = 'tcu'; // Certification user table alias.
        $this->set_columns($tp, $tpu, $u, $tcu, $tpsc);
        $this->set_main_table('user', $u);
        $this->add_base_join(api::get_status_sql_join($u, $tpu, $tp, $tps, $tpsc));

        $certificationuserjoin = "LEFT JOIN {" . certification_user::TABLE . "} $tcu
                                  ON $tcu.userid = $tpu.userid AND $tcu.certificationid = $tpu.certificationid";
        $this->add_base_join($certificationuserjoin);

        // Base condition is a visibility check (programs non archived, from correct tenant and not hidden).
        $usertenantid = tenancy::get_tenant_id();
        $tenant = db::generate_param_name();
        $timeclosetooverdue = db::generate_param_name();
        $timenow = db::generate_param_name();

         $this->add_base_condition_sql("
                {$u}.deleted = 0
                AND {$tp}.archived = 0
                AND {$tp}.tenantid = :{$tenant}
                AND {$tp}.visible = 1
                AND {$tpu}.duedate < :{$timeclosetooverdue} AND {$tpu}.duedate > 0
                AND {$tpsc}.id IS NULL
                ", [$tenant => $usertenantid, $timeclosetooverdue => strtotime('+7 day'), $timenow => time()]);

        // Check tenant id on users in case they have been moved to another tenant.
        [$join, $where, $params] = tenancy::get_users_sql('u', tenancy::get_tenant_id());
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);

        // Managers with no system capability are only allowed to see the users they manage.
        if ($manager = organisation::get_user_with_jobs()) {
            $permissions = organisation::PERM_ALLOCATE_PROGRAMS + organisation::PERM_VIEW_REPORTS;
            [$where, $params] = $manager->get_managed_users_select($u, $permissions);
            $this->add_base_condition_sql($where, $params);
        } else {
            $this->add_base_condition_sql('1=0');
        }

        if ($column = $this->get_column('tool_program:fullname')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true, 1);
        }
        if ($column = $this->get_column('user:fullname')) {
            $column->set_is_default(true, 2);
            $column->set_is_sortable(true, true, 2);
        }
        if ($column = $this->get_column('tool_program_users:associatedcertification')) {
            $column->set_is_default(true, 3);
            $column->set_is_sortable(true, true, 3);
        }
        if ($column = $this->get_column('tool_certification_users:expirydate')) {
            $column->set_is_default(true, 4);
            $column->set_is_sortable(true, true, 4);
        }
        if ($column = $this->get_column('tool_program_users:duedate')) {
            $column->set_is_default(true, 5);
            $column->set_is_sortable(true, true, 5);
        }
        if ($column = $this->get_column('tool_program_users:programstatus')) {
            $column->set_is_default(true, 6);
            $column->set_is_sortable(true, true, 6);
        }
        if ($column = $this->get_column('tool_program_users:programprogress')) {
            $column->set_is_default(true, 7);
            $column->set_is_sortable(true, true, 7);
        }

        $this->set_show_actions_header(true);
        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_programs_overdue();
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
     * @param string $tp Programs table alias.
     * @param string $tpu Program users table alias.
     * @param string $u User table alias.
     * @param string $tcu Certification user table alias.
     * @param string $tpsc Programs set completion table alias.
     */
    protected function set_columns($tp = 'tp', $tpu = 'tpu', $u = 'u', $tcu = 'tcu', $tpsc = 'tpsc'): void {
        $this->add_entity(new user('', $u));
        $this->add_entity(new program_entity('', $tp, $this->get_program_excluded_columns()));
        $this->add_entity(new programuser_entity('', $tpu, $this->get_programuser_excluded_columns(), $tpsc));
        $this->add_entity(new certificationuser_entity('', $tcu, $this->get_certificationuser_excluded_columns()));
    }

    /**
     * Returns an array with the excluded columns for program_entity.
     *
     * @return array
     */
    private function get_program_excluded_columns(): array {
        return ['fullnamewithimage', 'programimage', 'idnumber', 'description', 'startdate', 'duedate', 'enddate',
            'archived', 'allowdirectallocation', 'allocationstartdate', 'allocationenddate', 'visible',
            'timemodified', 'timecreated', 'numbercoursesunique', 'associatedcertifications', 'numbercurrentallocatedusers'];
    }

    /**
     * Returns an array with the excluded columns for programuser_entity.
     *
     * @return array
     */
    private function get_programuser_excluded_columns(): array {
        return ['startdate', 'enddate', 'programprogresswithoverview', 'suspended', 'timesuspended', 'allocationtype',
            'timecreated', 'timemodified'];
    }

    /**
     * Returns an array with the excluded columns for certificationuser_entity.
     *
     * @return array
     */
    private function get_certificationuser_excluded_columns(): array {
        return ['allocationtype', 'startdate', 'duedate', 'suspended', 'timesuspended', 'timecreated', 'timemodified',
            'certificationstatus', 'daystakingcertification', 'dayssinceallocation'];
    }
}
