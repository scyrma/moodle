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

use core_user;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use context_system;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use tool_certification\certification;
use tool_certification\permission;
use tool_certification\reportbuilder\local\entities\certification as certification_entity;
use tool_certification\reportbuilder\local\entities\certification_completion;
use tool_certification\reportbuilder\local\entities\certification_user;
use tool_organisation\organisation;
use tool_organisation\reportbuilder\local\entities\job;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_user;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * This class defines a system report that shows the progress/completion/status of certification users
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class progress extends system_report {

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        $exportfilename = get_string('certificationprogress', 'tool_certification');

        // Our main entity.
        $entitymain = new certification_user();
        $entitymainalias = $entitymain->get_table_alias('tool_certification_users');

        $this->set_main_table('tool_certification_users', $entitymainalias);
        $this->add_entity($entitymain);

        // Show only users from the current tenant and/or its subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$entitymainalias}.userid"));

        // Add user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity);

        // Required for row callback/actions.
        $this->add_base_fields("{$entitymainalias}.userid, {$entitymainalias}.certificationid" .
            core_user\fields::for_name()->get_sql($useralias)->selects);

        // We are adding the JOIN to the report, rather than the entity because we query some of it's fields directly.
        $this->add_join("JOIN {user} {$useralias} ON {$entitymainalias}.userid = {$useralias}.id");

        // Specific certification based on report parameters.
        if ($userid = $this->get_parameter('userid', 0, PARAM_INT)) {
            $this->add_base_condition_simple("{$useralias}.id", $userid);

            $userfullname = fullname(core_user::get_user($userid, '*', MUST_EXIST),
                has_capability('moodle/site:viewfullnames', $this->get_context()));
            $exportfilename = get_string('certificationprogressexport', 'tool_certification', $userfullname);
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

        // Add certification entity.
        $certificationentity = new certification_entity();
        $certificationalias = $certificationentity->get_table_alias('tool_certification');
        $this->add_entity($certificationentity);

        // We are adding the JOIN to the report, rather than the entity because we query some of it's fields directly.
        $this->add_join("
            JOIN {tool_certification} {$certificationalias}
              ON {$certificationalias}.id = {$entitymainalias}.certificationid
        ");

        // Tenant condition.
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql("{$certificationalias}.tenantid",
            "{$certificationalias}.shared=1");
        $this->add_base_condition_sql($sql, $params);

        // Specific certification based on report parameters.
        if ($certificationid = $this->get_parameter('certificationid', 0, PARAM_INT)) {
            $this->add_base_condition_simple("{$certificationalias}.id", $certificationid);

            $exportfilename = get_string('certificationprogressexport', 'tool_certification',
                (new certification($certificationid))->get_formatted_name());
        }

        // Add certification completion entity.
        $certificationcompletion = new certification_completion();
        $completionalias = $certificationcompletion->get_table_alias('tool_certification_compltion');
        $this->add_entity($certificationcompletion->add_join("
            LEFT JOIN {tool_certification_compltion} {$completionalias}
                   ON {$completionalias}.certificationid = {$entitymainalias}.certificationid
                  AND {$completionalias}.userid = {$entitymainalias}.userid
                  AND {$completionalias}.timerevoked = 0
                  AND {$completionalias}.islast = 1
        "));

        // Add program entity.
        $entityprogram = new program();
        $entityprogramalias = $entityprogram->get_table_alias('tool_program');
        $this->add_entity($entityprogram);

        // We are adding the JOIN to the report, rather than the entity because we query some of it's fields directly.
        $this->add_join("
            LEFT JOIN {tool_program} {$entityprogramalias}
                   ON {$entityprogramalias}.id = {$entitymainalias}.currentprogramid
        ");

        // Add program user entity.
        $entityprogramuser = new program_user();
        $entityprogramuseralias = $entityprogramuser->get_table_alias('tool_program_users');
        $this->add_entity($entityprogramuser);

        // We are adding the JOIN to the report, rather than the entity because we query some of it's fields directly.
        $this->add_join("
            LEFT JOIN {tool_program_users} {$entityprogramuseralias}
                   ON {$entityprogramuseralias}.userid = {$entitymainalias}.userid
                  AND {$entityprogramuseralias}.programid = {$entityprogramalias}.id
                  AND {$entityprogramuseralias}.certificationid = {$entitymainalias}.certificationid
        ");

        $this->add_base_fields("{$entityprogramuseralias}.id AS programuserid");

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
        if ($certificationid = $this->get_parameter('certificationid', 0, PARAM_INT)) {
            return permission::can_view_users_progress(new certification($certificationid));
        } else if ($userid = $this->get_parameter('userid', 0, PARAM_INT)) {
            return permission::can_view_user_progress($userid);
        }
        // Check a general permission for a global report.
        // Report will only show their own subordinates for users without the capability to allocate.
        return permission::can_view_list();
    }

    /**
     * Adds the columns we want to display in the report
     */
    protected function add_columns(): void {
        $certificationid = $this->get_parameter('certificationid', 0, PARAM_INT);
        $userid = $this->get_parameter('userid', 0, PARAM_INT);

        $this->add_columns_from_entities([
            'user:fullnamewithpicturelink',
            'certification:fullname',
            'certification_user:timecreated',
            'program_user:duedate',
            'certification_completion:expirydate',
            'certification_user:certificationstatus',
            'certification_completion:certifieddate',
            'certification_user:isrecertification',
            'program:fullnamewithlink',
            'program_user:programstatus',
            'program_user:programprogress',
        ]);

        // Certification/user columns are dependent on whether we are pre-filtering.
        $this->get_column('certification:fullname')->set_is_available($certificationid === 0 || $userid !== 0);
        $this->get_column('user:fullnamewithpicturelink')->set_is_available($userid === 0);

        // Rename the current program column title.
        $this->get_column('program:fullnamewithlink')
            ->set_title(new lang_string('currentprogram', 'tool_certification'));
        // Rename the current program status column title.
        $this->get_column('program_user:programstatus')
            ->set_title(new lang_string('currentprogramstatus', 'tool_certification'));
        // Rename the current program progress column title.
        $this->get_column('program_user:programprogress')
            ->set_title(new lang_string('currentprogramprogress', 'tool_certification'));

        if (!$certificationid) {
            $this->set_initial_sort_column('certification:fullname', SORT_ASC);
        } else if (!$userid) {
            $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
        }
    }

    /**
     * Add report filters
     */
    protected function add_filters(): void {
        $certificationid = $this->get_parameter('certificationid', 0, PARAM_INT);
        $userid = $this->get_parameter('userid', 0, PARAM_INT);

        $this->add_filters_from_entities([
            'user:fullname',
            'job:orgstructure',
            'certification:fullname',
            'certification_user:timecreated',
            'program_user:duedate',
            'certification_completion:expirydate',
            'certification_user:filterablestatus',
            'certification_completion:certifieddate',
            'certification_user:isrecertification',
            'program:fullname',
            'program_user:filterablestatus',
        ]);

        $this->get_filter('program:fullname')
            ->set_header(new lang_string('currentprogram', 'tool_certification'));
        $this->get_filter('program_user:filterablestatus')
            ->set_header(new lang_string('currentprogramstatus', 'tool_certification'));

        // Certification/user columns are dependent on whether we are pre-filtering.
        $this->get_filter('certification:fullname')->set_is_available($certificationid === 0);
        $this->get_filter('user:fullname')->set_is_available($userid === 0);
        $this->get_filter('job:orgstructure')->set_is_available($userid === 0);
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     */
    protected function add_actions(): void {

        // Certification user log modal.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('check-circle-o', '', 'tool_wp'),
            [
                'data-action' => 'view_certification_user_log',
                'data-userid' => ':userid',
                'data-id' => ':certificationid',
            ],
            false,
            new lang_string('viewcertificationuserlog', 'tool_certification')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return permission::can_view_user_progress((int) $row->userid);
            })
        );

        // Progress overview modal.
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
        ))
            ->add_callback(static function(stdClass $row): bool {
                $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
                $row->userfullname = fullname($row, $viewfullnames);
                return $row->programuserid && permission::can_view_user_progress((int) $row->userid);
            })
        );
    }
}
