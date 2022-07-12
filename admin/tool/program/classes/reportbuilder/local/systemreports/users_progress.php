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

use lang_string;
use moodle_url;
use pix_icon;
use core_reportbuilder\system_report;
use core_reportbuilder\local\report\action;
use tool_certification\reportbuilder\local\entities\certification;
use tool_certification\reportbuilder\local\entities\certification_user;
use tool_organisation\organisation;
use tool_program\reportbuilder\local\entities\program_completion;
use tool_program\reportbuilder\local\entities\program_user;
use tool_program\permission;
use tool_program\persistent\program;
use tool_tenant\tenancy;
use tool_wp\reportbuilder\local\entities\user;

/**
 * This class defines a system report that shows the progress/completion/status of users within one given program.
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * @package   tool_program
 */
class users_progress extends system_report {

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
        // Our main entity.
        $programuserentity = new program_user();
        $programuser = $programuserentity->get_table_alias('tool_program_users');

        $this->set_main_table('tool_program_users', $programuser);
        $this->add_entity($programuserentity);

        // Add user entity.
        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $this->add_entity($userentity);

        // We are adding the JOIN to the report, rather than the entity because we query some of it's fields below.
        $this->add_join("JOIN {user} {$user} ON {$user}.id = {$programuser}.userid");

        // Add certification entity.
        $certificationentity = new certification();
        $certification = $certificationentity->get_table_alias('tool_certification');
        $this->add_entity($certificationentity
            ->add_join("LEFT JOIN {tool_certification} {$certification} ON {$certification}.id = {$programuser}.certificationid")
        );

        // Add certification user entity.
        $certificationuserentity = (new certification_user())
            ->set_table_alias('tool_program_users', $programuser);

        $certificationuser = $certificationuserentity->get_table_alias('tool_certification_users');
        $certificationcompletion = $certificationuserentity->get_table_alias('tool_certification_compltion');

        $this->add_entity($certificationuserentity->add_joins([
            "LEFT JOIN {tool_certification_users} {$certificationuser}
                    ON {$certificationuser}.userid = {$programuser}.userid
                   AND {$certificationuser}.certificationid = {$programuser}.certificationid",
            "LEFT JOIN {tool_certification_compltion} {$certificationcompletion}
                    ON {$certificationcompletion}.certificationid = {$programuser}.certificationid
                   AND {$certificationcompletion}.userid = {$programuser}.userid
                   AND {$certificationcompletion}.timerevoked = 0
                   AND {$certificationcompletion}.islast = 1",
        ]));

        // Add program completion entity.
        $programcompletionentity = new program_completion();
        $programcompletion = $programcompletionentity->get_table_alias('tool_program_set_completion');
        $this->add_entity($programcompletionentity->add_joins([
            "JOIN {tool_program_sets} tps
               ON tps.programid = {$programuser}.programid
              AND tps.parent = 0",
            "LEFT JOIN {tool_program_set_completion} {$programcompletion}
               ON {$programcompletion}.setid = tps.id
              AND {$programcompletion}.userid = {$programuser}.userid"
        ]));

        // Managers with no system capability are only allowed to see the users they manage.
        if (!permission::has_allocateuser_capability($this->get_program()->get_context()) &&
                $manager = organisation::get_user_with_jobs()) {

            [$where, $params] = $manager->get_managed_users_select($user, organisation::PERM_ALLOCATE_PROGRAMS);
            $this->add_base_condition_sql($where, $params);
        }

        // Show only users from the current tenant and/or its subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$programuser}.userid"));

        $this->add_base_fields("{$programuser}.userid");
        $this->add_base_condition_simple("{$programuser}.programid", $this->get_program()->get('id'));
        $this->add_base_condition_simple("{$user}.deleted", 0);

        $this->add_columns($programuser, $user);
        $this->add_actions();

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
     * Set the columns for the report.
     *
     * @param string $programuseralias
     * @param string $useralias
     */
    private function add_columns(string $programuseralias, string $useralias): void {
        $columns = [
            'user:fullname',
            'program_user:startdate',
            'program_user:duedate',
            'program_user:enddate',
            'program_user:timecreated',
            'program_user:allocationtype',
            'certification:fullnamewithlink',
            'certification_user:certificationstatus',
            'program_user:programstatus',
            'program_user:programprogress',
            'program_completion:completeddate',
        ];

        $this->add_columns_from_entities($columns);

        // Apply user info dropdown on user fullname column.
        $this->get_column('user:fullname')
            ->add_fields("{$programuseralias}.id AS programuserid," .
                implode(',', $this->get_user_columns($useralias)))
            ->set_callback([\tool_program\local\helpers\programuser_format::class, 'userinfo']);

        // Reset the certification name column title.
        $this->get_column('certification:fullnamewithlink')
            ->set_title(new lang_string('certificationname', 'tool_certification'));

        $this->set_initial_sort_column('user:fullname', SORT_ASC);
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
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
