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

use core_reportbuilder\local\helpers\database;
use core_reportbuilder\system_report;
use tool_certification\reportbuilder\local\entities\certification;
use tool_certification\reportbuilder\local\entities\certification_user;
use tool_organisation\organisation;
use tool_program\api;
use tool_program\permission;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_user;
use tool_wp\reportbuilder\local\entities\user;

/**
 * Programs overdue system report implementation
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programs_overdue extends system_report {

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {

        $entitymain = new user();
        $entitymainalias = $entitymain->get_table_alias('user');

        $this->set_main_table('user', $entitymainalias);
        $this->add_entity($entitymain);

        // Add program entity (required JOIN added below).
        $programentity = new program();
        $program = $programentity->get_table_alias('tool_program');
        $this->add_entity($programentity);

        // Add program user entity (required JOIN added below).
        $programuserentity = new program_user();
        $programuser = $programuserentity->get_table_alias('tool_program_users');
        $this->add_entity($programuserentity);

        // Add joins needed to recover user program(s) statuses.
        $this->add_join(api::get_status_sql_join($entitymainalias, $programuser, $program, 'tps', 'tpsc'));

        // Join with certification table.
        $certificationentity = new certification();
        $certification = $certificationentity->get_table_alias('tool_certification');
        $this->add_entity($certificationentity
            ->add_join("LEFT JOIN {tool_certification} {$certification}
                               ON {$certification}.id = {$programuser}.certificationid")
        );

        // Join with certification user table.
        $certificationuserentity = new certification_user();
        $certificationuser = $certificationuserentity->get_table_alias('tool_certification_users');
        $certificationcompletion = $certificationuserentity->get_table_alias('tool_certification_compltion');
        $this->add_entity($certificationuserentity
            ->add_join("LEFT JOIN {tool_certification_users} {$certificationuser}
                               ON {$certificationuser}.userid = {$programuser}.userid
                              AND {$certificationuser}.certificationid = {$programuser}.certificationid")
            ->add_join("LEFT JOIN {tool_certification_compltion} {$certificationcompletion}
                               ON {$certificationcompletion}.certificationid = {$certificationuser}.certificationid
                              AND {$certificationcompletion}.userid = {$certificationuser}.userid
                              AND {$certificationcompletion}.timerevoked = 0
                              AND {$certificationcompletion}.islast = 1")
        );

        // Base condition is a visibility check (programs non archived and not hidden).
        // We don't need to check any tenant conditions, it should be responsibility of get_user_with_jobs().
        $timeclosetooverdue = database::generate_param_name();
        $timenow = database::generate_param_name();

        // Add base conditions to the report.
        $this->add_base_condition_sql("
                {$entitymainalias}.deleted = 0
                AND {$program}.archived = 0
                AND {$program}.visible = 1
                AND {$programuser}.duedate < :{$timeclosetooverdue} AND {$programuser}.duedate > 0
                AND tpsc.id IS NULL
                ", [$timeclosetooverdue => strtotime('+7 day'), $timenow => time()]);

        // This report is only for organisation managers, as such only users that are managed by the current user are listed.
        if ($manager = organisation::get_user_with_jobs()) {
            $permissions = organisation::PERM_ALLOCATE_PROGRAMS + organisation::PERM_VIEW_REPORTS;
            [$where, $params] = $manager->get_managed_users_select($entitymainalias, $permissions);
            $this->add_base_condition_sql($where, $params);
        } else {
            $this->add_base_condition_sql('1=0');
        }

        $this->add_columns();
        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_programs_overdue();
    }

    /**
     * Adds the columns we want to display in the report
     */
    protected function add_columns(): void {
        $columns = [
            'program:fullname',
            'user:fullnamewithpicturelink',
            'certification:fullname',
            'certification_user:expirydate',
            'program_user:duedate',
            'program_user:programstatus',
            'program_user:programprogress',
        ];

        $this->add_columns_from_entities($columns);
        $this->set_initial_sort_column('program:fullname', SORT_ASC);
    }
}
