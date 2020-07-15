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
 * Privacy provider for tool_program.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;

/**
 * Class provider
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,

    // This plugin stores data in user preferences.
    \core_privacy\local\request\user_preference_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        // Allocations to programs.
        $collection->add_database_table(
            program_user::TABLE,
            [
                'programid' => 'privacy:metadata:program_users:programid',
                'userid' => 'privacy:metadata:program_users:userid',
                'certificationid' => 'privacy:metadata:program_users:certificationid',
                'startdate' => 'privacy:metadata:program_users:startdate',
                'startdatelocked' => 'privacy:metadata:program_users:startdatelocked',
                'duedate' => 'privacy:metadata:program_users:duedate',
                'duedatelocked' => 'privacy:metadata:program_users:duedatelocked',
                'enddate' => 'privacy:metadata:program_users:enddate',
                'enddatelocked' => 'privacy:metadata:program_users:enddatelocked',
                'status' => 'privacy:metadata:program_users:status',
                'allocationtype' => 'privacy:metadata:program_users:allocationtype',
                'timemodified' => 'privacy:metadata:program_users:timemodified',
            ],
            'privacy:metadata:program_users'
        );

        // Program set completions.
        $collection->add_database_table(
            program_set_completion::TABLE,
            [
                'setid' => 'privacy:metadata:program_set_completion:setid',
                'userid' => 'privacy:metadata:program_set_completion:userid',
                'completeddate' => 'privacy:metadata:program_set_completion:completeddate',
                'timemodified' => 'privacy:metadata:program_set_completion:timemodified',
            ],
            'privacy:metadata:program_set_completion'
        );

        // Programs can be tagged.
        $collection->add_subsystem_link('core_tag', [], 'privacy:metadata:core_tag');

        // Program dashboard user preferences filter.
        $collection->add_user_preference('tool_program_program_status_filter',
            'privacy:metadata:preference:tool_program_program_status_filter');

        return $collection;
    }

    /**
     * Return all contexts for this userid.
     *
     * @param  int $userid The user ID.
     * @return contextlist The list of context IDs.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        // We always use system context in programs.
        $sql = "SELECT COUNT(1)
                  FROM {" . program_user::TABLE . "}
                 WHERE userid = :userid";

        if ($DB->count_records_sql($sql, ['userid' => $userid])) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param  approved_contextlist $contextlist The list of approved contexts for a user.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $context = self::retrieve_system_context($contextlist->get_contexts());
        if ($context === null) {
            return;
        }

        // Allocations to programs.
        $sql = "SELECT pu.*
                  FROM {" . program_user::TABLE . "} pu
            INNER JOIN {" . program::TABLE . "} p
                    ON p.id = pu.programid
                 WHERE pu.userid = :userid ";
        $allocations = $DB->get_records_sql($sql, ['userid' => $user->id]);

        // Program set completions.
        $sql = "SELECT psc.*, ps.programid, ps.name
                  FROM {" . program_set_completion::TABLE . "} psc
            INNER JOIN {" . program_set::TABLE . "} ps
                    ON ps.id = psc.setid
            INNER JOIN {" . program::TABLE . "} p
                    ON p.id = ps.programid
                 WHERE psc.userid = :userid ";
        $completions = $DB->get_records_sql($sql, ['userid' => $user->id]);

        // Programs.
        $sql = "SELECT p.*
                  FROM {" . program::TABLE . "} p
            INNER JOIN {" . program_user::TABLE . "} pu
                    ON pu.programid = p.id
                 WHERE pu.userid = :userid ";

        $stores = [];
        $programs = $DB->get_recordset_sql($sql, ['userid' => $user->id]);
        foreach ($programs as $program) {

            if (isset($stores[$program->id])) {
                continue;
            }

            $stores[$program->id] = [
                'programid' => (int) $program->id,
                'program_name' => format_string($program->fullname),
            ];

            $relatedallocations = array_filter($allocations, static function($allocation) use ($program) {
                return (int) $program->id === (int) $allocation->programid;
            });

            $stores[$program->id]['allocations'] = [];
            foreach ($relatedallocations as $relatedallocation) {
                if (!isset($stores[$program->id]['allocations'][$relatedallocation->id])) {
                    $stores[$program->id]['allocations'][$relatedallocation->id] = [
                        'program_user' => transform::user($relatedallocation->userid),
                        'start_date' => $relatedallocation->startdate ? transform::datetime($relatedallocation->startdate) : '-',
                        'due_date' => $relatedallocation->duedate ? transform::datetime($relatedallocation->duedate) : '-',
                        'time_added' => transform::datetime($relatedallocation->timecreated),
                        'time_last_modified' => transform::datetime($relatedallocation->timemodified),
                    ];
                }
            }

            $relatedcompletions = array_filter($completions, static function ($completion) use ($program) {
                return (int) $program->id === (int) $completion->programid;
            });

            $stores[$program->id]['completions'] = [];
            foreach ($relatedcompletions as $relatedcompletion) {
                if (!isset($stores[$program->id]['completions'][$relatedcompletion->id])) {
                    $stores[$program->id]['completions'][$relatedcompletion->id] = [
                        'completion_user' => transform::user($relatedcompletion->userid),
                        'program_set_id' => (int) $relatedcompletion->setid,
                        'program_completed_date' => transform::datetime($relatedcompletion->completeddate),
                        'time_last_modified' => transform::datetime($relatedcompletion->timemodified),
                    ];
                }
            }
        }
        $programs->close();

        $programsstr = get_string('programs', 'tool_program');
        foreach ($stores as $store) {
            $directories = [$programsstr, $store['program_name'] . $store['programid']];
            writer::with_context($context)->export_data($directories, (object) $store);

            // Export associated tags.
            \core_tag\privacy\provider::export_item_tags($user->id, $context, $directories, 'tool_program', 'tool_program',
                $store['programid'], false);
        }
    }

    /**
     * Delete all use data which matches the specified deletion criteria.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        // Delete all allocations.
        $DB->delete_records(program_user::TABLE);

        // Delete all set completions.
        $DB->delete_records(program_set_completion::TABLE);
    }

    /**
     * Delete all user data for approved contexts lists provided in the collection.
     *
     * This call relates to the forgetting of an entire user.
     *
     * Note: userid and component are stored in each respective approved_contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $context = self::retrieve_system_context($contextlist->get_contexts());
        if ($context === null) {
            return;
        }

        // Delete this user allocations.
        $DB->delete_records(program_user::TABLE, ['userid' => $user->id]);

        // Delete this user set completions.
        $DB->delete_records(program_set_completion::TABLE, ['userid' => $user->id]);
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (CONTEXT_SYSTEM !== $context->contextlevel) {
            return;
        }

        $params = [];

        // Users allocated to programs.
        $sql = "SELECT userid
                  FROM {". program_user::TABLE . "} ";
        $userlist->add_from_sql('userid', $sql, $params);

        // Users that completed a program set.
        $sql = 'SELECT userid
                  FROM {' . program_set_completion::TABLE . '} ';
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        // Delete this users allocations.
        $DB->delete_records_list(program_user::TABLE, 'userid', $userlist->get_userids());

        // Delete this users set completions.
        $DB->delete_records_list(program_set_completion::TABLE, 'userid', $userlist->get_userids());
    }

    /**
     * Retrieves system context if found within the passed list. Null otherwise.
     *
     * @param context[] $contexts
     * @return context|null
     */
    private static function retrieve_system_context($contexts): ?context {
        $context = null;
        foreach ($contexts as $currentcontext) {
            if ($currentcontext->contextlevel === CONTEXT_SYSTEM) {
                $context = $currentcontext;
                break;
            }
        }
        return $context;
    }

    /**
     * Export all user preferences for the plugin
     *
     * @param int $userid
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $value = get_user_preferences('tool_program_program_status_filter', null, $userid);
        if ($value) {
            $str = get_string('privacy:metadata:preference:tool_program_program_status_filter', 'tool_program');
            writer::export_user_preference('tool_program', 'tool_program_program_status_filter', $value, $str);
        }
    }
}
