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

use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use moodle_url;
use pix_icon;
use tool_certification\certification;
use tool_certification\permission;
use tool_certification\reportbuilder\local\entities\certification as certification_entity;
use tool_certification\reportbuilder\local\entities\certification_completion;
use tool_certification\reportbuilder\local\entities\certification_user;
use tool_organisation\organisation;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_user;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;
use tool_wp\reportbuilder\local\entities\user;

/**
 * This class defines a system report that shows the progress/completion/status of users within one given certification.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class progress extends system_report {

    /** @var certification */
    protected $certification;

    /**
     * Get current certiication
     *
     * @return certification
     */
    protected function get_certification(): certification {
        if (!$this->certification) {
            $certificationid = $this->get_parameter('id', 0, PARAM_INT);
            $this->certification = new certification($certificationid);
        }
        return $this->certification;
    }

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        // Our main entity.
        $entitymain = new certification_user();
        $entitymainalias = $entitymain->get_table_alias('tool_certification_users');

        $this->set_main_table('tool_certification_users', $entitymainalias);
        $this->add_entity($entitymain);

        // Add user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity);

        // We are adding the JOIN to the report, rather than the entity because we query some of it's fields below.
        $this->add_join("
            JOIN {user} {$useralias}
              ON {$entitymainalias}.userid = {$useralias}.id
        ");

        // Add certification entity.
        $certificationentity = new certification_entity();
        $certificationalias = $certificationentity->get_table_alias('tool_certification');
        $this->add_entity($certificationentity);

        // We are adding the JOIN to the report, rather than the entity because we query some of it's fields below.
        $this->add_join("
            JOIN {tool_certification} {$certificationalias}
              ON {$certificationalias}.id = {$entitymainalias}.certificationid
        ");

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

        // Join with tool_program table.
        $entityprogram = new program();
        $entityprogramalias = $entityprogram->get_table_alias('tool_program');
        $programjoin = "
            LEFT JOIN {tool_program} {$entityprogramalias}
                   ON {$entityprogramalias}.id = {$entitymainalias}.currentprogramid
        ";
        $this->add_entity($entityprogram->add_join($programjoin));

        // Join with tool_program_users table.
        $entityprogramuser = new program_user();
        $entityprogramuseralias = $entityprogramuser->get_table_alias('tool_program_users');
        $programuserjoin = "
            LEFT JOIN {tool_program_users} {$entityprogramuseralias}
                   ON {$entityprogramuseralias}.userid = {$entitymainalias}.userid
                  AND {$entityprogramuseralias}.programid = {$entityprogramalias}.id
                  AND {$entityprogramuseralias}.certificationid = {$entitymainalias}.certificationid
        ";
        $this->add_entity($entityprogramuser->add_joins([$programjoin, $programuserjoin]));

        // Tenant condition.
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql("{$certificationalias}.tenantid",
            "{$certificationalias}.shared=1");
        $this->add_base_condition_sql($sql, $params);

        // Managers with no system capability are only allowed to see the users they manage.
        if (!permission::has_allocateuser_capability($this->get_certification()->get_context()) &&
            $manager = organisation::get_user_with_jobs()) {
            [$where, $params] = $manager->get_managed_users_select($useralias, organisation::PERM_VIEW_REPORTS);
            $this->add_base_condition_sql($where, $params);
        }

        // Show only users from the current tenant and/or its subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$entitymainalias}.userid"));

        $this->add_base_fields("{$entitymainalias}.userid");
        $this->add_base_condition_simple("{$certificationalias}.id", $this->get_certification()->get('id'));
        $this->add_base_condition_simple("{$useralias}.deleted", 0);

        $this->add_columns($entitymainalias, $useralias);
        $this->add_actions();

        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_users_progress($this->get_certification());
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
        $columns = [
            'user:fullname',
            'program_user:startdate',
            'program_user:duedate',
            'certification_user:expirydate',
            'certification_user:timecreated',
            'certification_user:allocationtype',
            'program:fullnamewithlink',
            'certification_user:certificationstatus',
            'program_user:programstatus',
            'program_user:programprogress',
            'certification_completion:certifieddate',
        ];

        $this->add_columns_from_entities($columns);

        // Apply user info dropdown on user fullname column.
        $this->get_column('user:fullname')
            ->add_fields("{$entitymainalias}.id as certificationuserid," .
                implode(',', $this->get_user_columns($useralias)))
            ->set_callback([\tool_certification\reportbuilder\local\formatters\certification::class, 'userinfo']);

        // Reset the program name column title.
        $this->get_column('program:fullnamewithlink')
            ->set_title(new lang_string('programname', 'tool_program'));

        $this->set_initial_sort_column('user:fullname', SORT_ASC);
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     */
    protected function add_actions(): void {
        // Action to message user.
        $this->add_action((new action(
            new moodle_url('/message/index.php', ['id' => ':userid']),
            new pix_icon('t/messages', '', 'core'),
            [],
            false,
            new lang_string('sendmessage', 'core_message')
        )));

        // Action to go to user profile.
        $this->add_action((new action(
            new moodle_url('/user/profile.php', ['id' => ':userid']),
            new pix_icon('i/user', '', 'core'),
            [],
            false,
            new lang_string('profile', 'core')
        )));
    }

    /**
     * Returns an array of user column names prefixed with the given user table alias.
     *
     * @param string $tablealias
     * @return array
     */
    private function get_user_columns(string $tablealias): array {
        global $DB;

        // Get all fields required to show the user profile (note: any of the fields may be used in callbacks).
        return array_map(static function(string $column) use ($tablealias): string {
            return "{$tablealias}.{$column}";
        }, array_keys($DB->get_columns('user')));
    }
}
