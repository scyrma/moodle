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
 * Privacy Subsystem implementation for tool_wp.
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy Subsystem for tool_wp
 *
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'tool_wp_course_reset',
            [
                'courseid' => 'privacy:metadata:courseid',
                'userid' => 'privacy:metadata:userid',
                'programid' => 'privacy:metadata:programid',
                'certificationid' => 'privacy:metadata:certificationid',
                'reason' => 'privacy:metadata:reason',
                'timerequested' => 'privacy:metadata:timerequested',
                'userrequested' => 'privacy:metadata:userrequested',
                'wascompleted' => 'privacy:metadata:wascompleted',
                'grade' => 'privacy:metadata:grade',
                'resetstatus' => 'privacy:metadata:resetstatus',
                'resetinfo' => 'privacy:metadata:resetinfo',
                'timemodified' => 'privacy:metadata:timemodified',
                'usermodified' => 'privacy:metadata:usermodified',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:tool_wp_course_reset'
        );

        return $collection;
    }

    /**
     * Return all contexts for this userid.
     *
     * @param  int $userid The user ID.
     * @return contextlist The list of context IDs.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                FROM {context} ctx
                JOIN {course} c ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
                JOIN {tool_wp_course_reset} cr ON cr.courseid = c.id AND cr.userid = :userid
        ";
        $params['contextcourse'] = CONTEXT_COURSE;
        $params['userid'] = $userid;
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param  approved_contextlist $contextlist The list of approved contexts for a user.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        return;
    }

    /**
     * Delete all use data which matches the specified deletion criteria.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context instanceof \context_course) {
            $DB->delete_records('tool_wp_course_reset', ['courseid' => $context->instanceid]);
        }
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

        if ($context instanceof \context_course) {
            $DB->delete_records('tool_wp_course_reset', ['courseid' => $context->instanceid, 'userid' => $user->id]);
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context instanceof \context_course) {
            $params = ['courseid' => $context->instanceid];

            $sql = "SELECT userid FROM {tool_wp_course_reset} WHERE courseid = :courseid";
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $userids = $userlist->get_userids();

        [$useridsql, $useridsqlparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = ['courseid' => $context->instanceid] + $useridsqlparams;

        $DB->delete_records_select('tool_wp_course_reset', "courseid = :courseid AND userid {$useridsql}",
            $params);
    }
}