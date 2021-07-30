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

/**
 * Privacy class for requesting user data.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
namespace tool_organisation\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\approved_userlist;
use \core_privacy\local\request\helper;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\userlist;
use \core_privacy\local\request\writer;

/**
 * Privacy provider for tool_organisation
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Get information about the user data stored by this plugin.
     *
     * @param  collection $collection An object for storing metadata.
     * @return collection The metadata.
     */
    public static function get_metadata(collection $collection) : collection {
        $jobs = [
            'userid' => 'privacy:metadata:userid',
            'department' => 'privacy:metadata:department',
            'position' => 'privacy:metadata:position',
            'startdate' => 'privacy:metadata:startdate',
            'enddate' => 'privacy:metadata:enddate',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ];
        $collection->add_database_table('tool_organisation_job', $jobs, 'privacy:metadata:jobssummary');
        return $collection;
    }

    /**
     * Return all contexts for this userid. In this situation the user context.
     *
     * @param  int $userid The user ID.
     * @return contextlist The list of context IDs.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();

        // The data is associated at the user context level, so retrieve the user's context id.
        $sql = "SELECT c.id
                  FROM {tool_organisation_job} j
                  JOIN {context} c
                    ON (c.instanceid = j.userid AND c.contextlevel = :contextuser)
                 WHERE j.userid = :userid
              GROUP BY c.id";

        $params = [
            'contextuser' => CONTEXT_USER,
            'userid'      => $userid
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_user::class)) {
            return;
        }

        $params = [
            'contextid' => $context->id,
            'contextuser' => CONTEXT_USER,
        ];

        $sql = "SELECT j.userid
                  FROM {context} ctx
                  JOIN {tool_organisation_job} j ON ctx.instanceid = j.userid
                       AND ctx.contextlevel = :contextuser
                 WHERE ctx.id = :contextid";

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all event organisation information for the list of contexts and this user.
     *
     * @param  approved_contextlist $contextlist The list of approved contexts for a user.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        $sql = "SELECT j.id as jobid, j.startdate, j.enddate, j.timecreated, j.timemodified,
                       d.name as departmentname, p.name as positionname
                  FROM {tool_organisation_job} j
                  JOIN {tool_organisation_department} d
                    ON (j.departmentid = d.id)
                  JOIN {tool_organisation_position} p
                    ON (j.positionid = p.id)
                 WHERE j.userid = :userid
                 ORDER BY j.startdate";

        $data = [];

        $recordset = $DB->get_recordset_sql($sql, ['userid' => $user->id]);
        foreach ($recordset as $record) {
            $data[] = [
                'department' => format_string($record->departmentname),
                'position' => format_string($record->positionname),
                'startdate' => transform::datetime($record->startdate),
                'enddate' => $record->enddate ? transform::datetime($record->enddate) : null,
                'timecreated' => transform::datetime($record->timecreated),
                'timemodified' => transform::datetime($record->timemodified)
            ];
        }
        $recordset->close();

        if (count($data) > 0) {
            $context = \context_user::instance($user->id);
            $contextpath = [get_string('pluginname', 'tool_organisation')];

            writer::with_context($context)->export_data($contextpath, (object) ['jobs' => $data]);
        }
    }

    /**
     * Delete all user data for this context.
     *
     * @param  \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // Only delete data for user contexts.
        if ($context->contextlevel == CONTEXT_USER) {
            \tool_organisation\job_manager::delete_user_jobs($context->instanceid);
        }
    }

    /**
     * Delete all user data for this user only.
     *
     * @param  approved_contextlist $contextlist The list of approved contexts for a user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if (empty($contextlist->count())) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_user) {
                return;
            }
        }
        \tool_organisation\job_manager::delete_user_jobs($contextlist->get_user()->id);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        $userid = reset($userids);

        // Only delete data for user context, which should be a single user.
        if ($context->contextlevel == CONTEXT_USER && count($userids) == 1 && $userid == $context->instanceid) {
            \tool_organisation\job_manager::delete_user_jobs($userid);
        }
    }
}
