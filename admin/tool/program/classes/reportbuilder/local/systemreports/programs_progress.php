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
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use moodle_url;
use pix_icon;
use tool_certification\reportbuilder\local\entities\certification_user;
use tool_program\api;
use tool_program\permission;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_completion;
use tool_program\reportbuilder\local\entities\program_user;
use tool_program\reportbuilder\local\formatters\program as programformatter;

/**
 * Programs progress system report
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * @package   tool_program
 */
class programs_progress extends system_report {

    /** @var int $userid */
    private $userid;

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        $this->userid = $this->get_parameter('userid', 0, PARAM_INT);

        // Our main entity, it contains all of the column definitions that we need.
        $entitymain = new user();
        $entitymainalias = $entitymain->get_table_alias('user');

        $this->set_main_table('user', $entitymainalias);
        $this->add_entity($entitymain);

        // Show only information related to this user.
        $this->add_base_condition_simple("{$entitymainalias}.id", $this->userid);

        // Join with tool_program table.
        $entityprogram = new program();
        $entityprogramalias = $entityprogram->get_table_alias('tool_program');
        $this->add_entity($entityprogram);

        // Add program user entity.
        $entityprogramuser = new program_user();
        $entityprogramuseralias = $entityprogramuser->get_table_alias('tool_program_users');
        $this->add_entity($entityprogramuser);

        $this->add_join(api::get_status_sql_join($entitymainalias, $entityprogramuseralias, $entityprogramalias, 'tps', 'tpsc'));

        // Add certification user entity.
        $entitycertificationuser = new certification_user();
        $certificationuser = $entitycertificationuser->get_table_alias('tool_certification_users');
        $certificationcompletion = $entitycertificationuser->get_table_alias('tool_certification_compltion');
        $this->add_entity($entitycertificationuser->add_joins([
            "LEFT JOIN {tool_certification_users} {$certificationuser}
                    ON {$certificationuser}.userid = {$entityprogramuseralias}.userid
                   AND {$certificationuser}.certificationid = {$entityprogramuseralias}.certificationid",
            "LEFT JOIN {tool_certification_compltion} {$certificationcompletion}
                    ON {$certificationcompletion}.userid = {$certificationuser}.userid
                   AND {$certificationcompletion}.certificationid = {$certificationuser}.certificationid
                   AND {$certificationcompletion}.timerevoked = 0
                   AND {$certificationcompletion}.islast = 1",
        ]));

        // Add program completion entity.
        $entityprogramcompletion = new program_completion();
        $this->add_entity($entityprogramcompletion);

        // Base condition is a visibility check (programs non archived, from correct tenant and not hidden).
        $this->add_base_condition_sql("
                {$entityprogramalias}.archived = 0
                AND tps.parent = 0
                AND {$entityprogramalias}.visible = 1 ", []);

        $this->add_base_fields("{$entityprogramalias}.id AS programid, {$entityprogramuseralias}.id AS allocationid");

        $this->add_columns($entityprogramalias);
        $this->add_filters();
        $this->add_actions();
        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_user_programs_progress($this->userid);
    }

    /**
     * Adds the columns we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     *
     * @param string $entityprogramalias
     */
    protected function add_columns(string $entityprogramalias): void {
        $columns = [
            'program:fullname',
            'program:associatedcertifications',
            'certification_user:expirydate',
            'program_user:duedate',
            'program_user:programstatus',
            'program_user:programprogress',
            'program_completion:completeddate',
        ];

        $this->add_columns_from_entities($columns);

        // Append custom formatting to the program fullname according to the current user.
        $this->get_column('program:fullname')
            ->add_field("{$entityprogramalias}.id", 'programid')
            ->add_callback([programformatter::class, 'userprogramname'], ['userid' => $this->userid]);

        $this->set_initial_sort_column('program:fullname', SORT_ASC);
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_filters(): void {
        $filters = [
            'program_user:filterablestatus',
        ];

        $this->add_filters_from_entities($filters);
    }

    /**
     * Set the actions icons of the report.
     */
    protected function add_actions(): void {

        // Action to show progress report.
        $urlparams = ['programid' => ':programid', 'userid' => $this->userid];
        $this->add_action((new action(
            new moodle_url('/admin/tool/program/programprogress.php', $urlparams),
            new pix_icon('bar-chart', '', 'tool_wp'),
            [],
            false,
            new lang_string('progressreport', 'tool_program')
        ))->add_callback(function(): bool {
            return permission::can_view_user_programs_progress($this->userid);
        }));

        // Action to show progress overview.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/dashboard', '', 'core'),
            [
                'data-action' => 'program_progress_overview',
                'data-allocationid' => ':allocationid',
                'data-title' => get_string('progressoverview', 'tool_program'),
                'data-contextid' => context_system::instance()->id
            ],
            false,
            new lang_string('progressoverview', 'tool_program')
        ))->add_callback(function(): bool {
            return permission::can_view_user_programs_progress($this->userid);
        }));
    }
}
