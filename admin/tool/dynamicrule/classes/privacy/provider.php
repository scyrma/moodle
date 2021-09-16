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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Privacy Subsystem implementation for tool_dynamicrule.
 *
 * @package     tool_dynamicrule
 * @category    privacy
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Workplace team
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\approved_userlist;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\userlist;
use \core_privacy\local\request\helper;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\writer;

/**
 * Privacy Subsystem for tool_dynamicrule.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider implements \core_privacy\local\metadata\provider,
                          \core_privacy\local\request\subsystem\plugin_provider,
                          \core_privacy\local\request\core_user_data_provider,
                          \core_privacy\local\request\core_userlist_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'tool_dynamicrule_match',
            [
                'ruleid' => 'privacy:metadata:tool_dynamicrule_match:ruleid',
                'userid' => 'privacy:metadata:tool_dynamicrule_match:userid',
                'matchedtime' => 'privacy:metadata:tool_dynamicrule_match:matchedtime',
                'unmatchedtime' => 'privacy:metadata:tool_dynamicrule_match:unmatchedtime',
            ],
            'privacy:metadata:tool_dynamicrule_match'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('tool_dynamicrule_match', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $sql = "SELECT userid FROM {tool_dynamicrule_match}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        $sql = 'SELECT m.*, r.name as rulename
                  FROM {tool_dynamicrule_match} m
                  JOIN {tool_dynamicrule} r
                    ON (r.id = m.ruleid)
                 WHERE m.userid = :userid
              ORDER BY m.matchedtime, m.unmatchedtime, r.id ASC';

        $data = [];

        $recordset = $DB->get_recordset_sql($sql, ['userid' => $user->id]);
        foreach ($recordset as $record) {
            $data[] = [
                'rulename' => format_string($record->rulename),
                'matchedtime' => transform::datetime($record->matchedtime),
                'unmatchedtime' => $record->unmatchedtime ? transform::datetime($record->unmatchedtime) : null,
            ];
        }
        $recordset->close();

        if (count($data) > 0) {
            $context = \context_system::instance();
            $contextpath = [get_string('pluginname', 'tool_dynamicrule')];

            writer::with_context($context)->export_data($contextpath, (object) ['matches' => $data]);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        $DB->delete_records('tool_dynamicrule_match');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->delete_records('tool_dynamicrule_match', ['userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('tool_dynamicrule_match', ' userid ' . $userinsql, $userinparams);
    }
}
