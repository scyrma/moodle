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
use core_user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use core_user\fields;
use html_writer;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use tool_certification\reportbuilder\local\entities\certification;
use tool_organisation\organisation;
use tool_organisation\reportbuilder\local\entities\job;
use tool_program\permission;
use tool_program\persistent\program as persistent;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_completion;
use tool_program\reportbuilder\local\entities\program_user;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * Programs progress system report
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class progress extends system_report {

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        $exportfilename = get_string('programprogress', 'tool_program');

        // Our main entity.
        $entitymain = new program_user();
        $entitymainalias = $entitymain->get_table_alias('tool_program_users');

        $this->set_main_table('tool_program_users', $entitymainalias);
        $this->add_entity($entitymain);

        // Show only users from the current tenant and/or its subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$entitymainalias}.userid"));

        // Add user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity);

        // We are adding the JOIN to the report, rather than the entity because we query some of it's fields directly.
        $this->add_join("JOIN {user} {$useralias} ON {$entitymainalias}.userid = {$useralias}.id");

        // Required for row callback/actions.
        $this->add_base_fields("{$entitymainalias}.userid, {$entitymainalias}.programid, {$entitymainalias}.id" .
            fields::for_name()->get_sql($useralias)->selects);

        // Specific program based on report parameters.
        if ($userid = $this->get_parameter('userid', 0, PARAM_INT)) {
            $this->add_base_condition_simple("{$useralias}.id", $userid);

            $userfullname = fullname(core_user::get_user($userid, '*', MUST_EXIST),
                has_capability('moodle/site:viewfullnames', $this->get_context()));
            $exportfilename = get_string('programprogressexport', 'tool_program', $userfullname);
        }
        $this->add_base_condition_simple("{$useralias}.deleted", 0);

        // Users without the capability to allocate users are only allowed to see their own subordinates.
        if (!$userid && !permission::has_allocateuser_capability()) {
            $manager = organisation::get_user_with_jobs();
            if (!$manager || !$manager->is_manager()) {
                $this->add_base_condition_sql('1=2');
            } else {
                [$where, $params] = $manager->get_managed_users_select($useralias, organisation::PERM_VIEW_REPORTS);
                $this->add_base_condition_sql($where, $params);
            }
        }

        // Add program entity.
        $programentity = new program();
        $programalias = $programentity->get_table_alias('tool_program');
        $this->add_entity($programentity);

        // We are adding the JOIN to the report, rather than the entity because we query some of it's fields directly.
        $this->add_join("
            JOIN {tool_program} {$programalias}
              ON {$programalias}.id = {$entitymainalias}.programid
        ");

        // Tenant condition.
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql("{$programalias}.tenantid",
            "{$programalias}.shared=1");
        $this->add_base_condition_sql($sql, $params);

        // Specific certification based on report parameters.
        if ($programid = $this->get_parameter('programid', 0, PARAM_INT)) {
            $this->add_base_condition_simple("{$programalias}.id", $programid);

            $exportfilename = get_string('programprogressexport', 'tool_program',
                (new persistent($programid))->get_formatted_name());
        }

        // Add certification entity.
        $certificationentity = new certification();
        $certificationalias = $certificationentity->get_table_alias('tool_certification');
        $this->add_entity($certificationentity->add_join("
            LEFT JOIN {tool_certification} {$certificationalias}
                   ON {$certificationalias}.id = {$entitymainalias}.certificationid
        "));

        // Add program completion entity.
        $entityprogramcompletion = new program_completion();
        $programsets = database::generate_alias();
        $programcompletion = $entityprogramcompletion->get_table_alias('tool_program_set_completion');
        $this->add_entity($entityprogramcompletion->add_joins([
            "     JOIN {tool_program_sets} {$programsets}
                    ON {$programsets}.programid = {$programalias}.id
                   AND {$programsets}.parent = 0",
            "LEFT JOIN {tool_program_set_completion} {$programcompletion}
                    ON {$programcompletion}.setid = {$programsets}.id
                   AND {$programcompletion}.userid = {$useralias}.id",
        ]));

        // Base condition is a visibility check (programs non archived, from correct tenant and not hidden).
        $this->add_base_condition_sql("
                {$programalias}.archived = 0
                AND {$programalias}.visible = 1 ", []);

        // Add job entity, just for the organisation structure (doesn't require joins).
        $this->add_entity(new job());

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(true, $exportfilename);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        $userid = $this->get_parameter('userid', 0, PARAM_INT);
        $programid = $this->get_parameter('programid', 0, PARAM_INT);

        // General programs report.
        if (empty($userid) && empty($programid)) {
            // Check a general permission for a global report.
            // Report will only show their own subordinates for users without the capability to allocate.
            return permission::can_view_list();
        }

        // Users progress report.
        if (empty($userid)) {
            return permission::can_view_users_progress(new \tool_program\persistent\program($programid));
        }

        // Programs progress report.
        $program = !empty($programid) ? new \tool_program\persistent\program($programid) : null;
        return permission::can_view_user_programs_progress($userid, $program);
    }

    /**
     * Adds the columns we want to display in the report
     */
    protected function add_columns(): void {
        $programid = $this->get_parameter('programid', 0, PARAM_INT);
        $userid = $this->get_parameter('userid', 0, PARAM_INT);

        $this->add_columns_from_entities([
            'program:fullnamewithlink',
            'user:fullnamewithpicturelink',
            'program_user:allocationtype',
            'certification:fullname',
            'program_user:startdate',
            'program_user:duedate',
            'program_user:programstatus',
            'program_user:programprogress',
            'program_completion:completeddate',
        ]);

        // Program/user columns are dependent on whether we are pre-filtering.
        $this->get_column('program:fullnamewithlink')->set_is_available($programid === 0 || $userid !== 0);
        $this->get_column('user:fullnamewithpicturelink')->set_is_available($userid === 0);

        // Reset the program name column title.
        $this->get_column('program:fullnamewithlink')
            ->set_title(new lang_string('programname', 'tool_program'));

        // We need to link to users certification progress report.
        $certificationalias = $this->get_entity('certification')->get_table_alias('tool_certification');
        $useralias = $this->get_entity('user')->get_table_alias('user');
        $this->get_column('certification:fullname')
            ->add_fields("{$certificationalias}.id, {$useralias}.id AS userid")
            ->add_callback(static function(string $fullname, stdClass $row): string {
                if ($row->id !== null) {
                    $fullname = html_writer::link(new moodle_url('/admin/tool/certification/report.php', [
                        'userid' => $row->userid,
                        'certificationid' => $row->id,
                    ]), $fullname);
                }

                return $fullname;
            });

        if (!$programid) {
            $this->set_initial_sort_column('program:fullnamewithlink', SORT_ASC);
        } else if (!$userid) {
            $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
        }
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_filters(): void {
        $programid = $this->get_parameter('programid', 0, PARAM_INT);
        $userid = $this->get_parameter('userid', 0, PARAM_INT);

        $this->add_filters_from_entities([
            'user:fullname',
            'job:orgstructure',
            'program:fullname',
            'program_user:allocationtype',
            'program_user:startdate',
            'program_user:duedate',
            'program_user:filterablestatus',
            'program_completion:completeddate',
        ]);

        // Program/user columns are dependent on whether we are pre-filtering.
        $this->get_filter('program:fullname')->set_is_available($programid === 0);
        $this->get_filter('user:fullname')->set_is_available($userid === 0);
        $this->get_filter('job:orgstructure')->set_is_available($userid === 0);
    }

    /**
     * Set the actions icons of the report.
     */
    protected function add_actions(): void {

        // Action to show progress overview.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/dashboard', '', 'core'),
            [
                'data-action' => 'program_progress_overview',
                'data-allocationid' => ':id',
                'data-userfullname' => ':userfullname',
                'data-contextid' => context_system::instance()->id
            ],
            false,
            new lang_string('progressoverview', 'tool_program')
        ))
            ->add_callback(static function(stdClass $row): bool {
                $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
                $row->userfullname = fullname($row, $viewfullnames);
                return permission::can_view_user_programs_progress((int) $row->userid);
            })
        );
    }
}
