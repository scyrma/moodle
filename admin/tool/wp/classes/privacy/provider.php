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
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\import_persistent;

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

        // TODO! Flesh out export/import fields as schema matures/stabilizes.
        $collection->add_database_table(
            export_persistent::TABLE,
            [
                'createdby' => 'privacy:metadata:exportcreatedby',
                'timecreated' => 'privacy:metadata:timecreated',
                'tenantid' => 'privacy:metadata:tenantid',
                'status' => 'privacy:metadata:exportstatus',
            ],
            'privacy:metadata:tool_wp_export'
        );

        $collection->add_database_table(
            import_persistent::TABLE,
            [
                'createdby' => 'privacy:metadata:importcreatedby',
                'timecreated' => 'privacy:metadata:timecreated',
                'tenantid' => 'privacy:metadata:tenantid',
                'status' => 'privacy:metadata:importstatus',
            ],
            'privacy:metadata:tool_wp_import'
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
        $contextlist = new contextlist();

        // Course contexts containing course reset requests made by the user.
        $sql = "SELECT ctx.id
                FROM {context} ctx
                JOIN {course} c ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
                JOIN {tool_wp_course_reset} cr ON cr.courseid = c.id AND cr.userid = :userid
        ";
        $contextlist->add_from_sql($sql, ['contextcourse' => CONTEXT_COURSE, 'userid' => $userid]);

        // If user has made performed any import/exports then add the system context.
        if (export_persistent::record_exists_select('createdby = ?', [$userid]) ||
                import_persistent::record_exists_select('createdby = ?', [$userid])) {

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
        // TODO! Should course reset requests by exported here too?

        $contexts = array_filter($contextlist->get_contexts(), function($context) {
            return $context instanceof \context_system;
        });

        if (empty($contexts)) {
            return;
        }

        $context = reset($contexts);
        $user = $contextlist->get_user();

        if ($exports = export_persistent::get_records(['createdby' => $user->id])) {
            $data = [];

            foreach ($exports as $export) {
                $data[] = (object) [
                    'createdby' => transform::user($export->get('createdby')),
                    'timecreated' => transform::datetime($export->get('timecreated')),
                    'tenantid' => $export->get('tenantid'),
                    'status' => $export->get('status'),
                ];
            }

            writer::with_context($context)->export_related_data([get_string('pluginname', 'tool_wp')],
                'exports', $data);
        }

        if ($imports = import_persistent::get_records(['createdby' => $user->id])) {
            $data = [];

            foreach ($imports as $import) {
                $data[] = (object) [
                    'createdby' => transform::user($import->get('createdby')),
                    'timecreated' => transform::datetime($import->get('timecreated')),
                    'tenantid' => $import->get('tenantid'),
                    'status' => $import->get('status'),
                ];
            }

            writer::with_context($context)->export_related_data([get_string('pluginname', 'tool_wp')],
                'imports', $data);
        }
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

        if ($contextlist->count() == 0) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $DB->delete_records('tool_wp_course_reset', ['courseid' => $context->instanceid, 'userid' => $user->id]);
            }
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
