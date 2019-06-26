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
 * Privacy Subsystem implementation for tool_datastore.
 *
 * @package   tool_datastore
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_datastore\privacy;
defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy Subsystem for tool_datastore implementing null_provider.
 *
 * @package   tool_datastore
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider,
                          \core_privacy\local\request\plugin\provider,
                          \core_privacy\local\request\core_userlist_provider {

    /**
     * Get information about the user data stored by this plugin.
     *
     * @param  collection $collection An object for storing metadata.
     * @return collection The metadata.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'tool_datastore_action',
            [
                'action' => 'privacy:metadata:tool_datastore:action',
                'relateduserid' => 'privacy:metadata:tool_datastore:relateduserid',
                'originalcourseid' => 'privacy:metadata:tool_datastore:originalcourseid',
                'originalprogramid' => 'privacy:metadata:tool_datastore:originalprogramid',
                'usermodified' => 'privacy:metadata:tool_datastore:usermodified',
            ],
            'privacy:metadata:tool_datastore_action'
        );

        $collection->add_database_table(
            'tool_datastore_entity',
            [
                'actionid' => 'privacy:metadata:tool_datastore:actionid',
                'originalid' => 'privacy:metadata:tool_datastore:originalid',
                'type' => 'privacy:metadata:tool_datastore:type',
                'snapshotid' => 'privacy:metadata:tool_datastore:snapshotid',
            ],
            'privacy:metadata:tool_datastore_entity'
        );

        $collection->add_database_table(
            'tool_datastore_snapshot',
            [
                'hash' => 'privacy:metadata:tool_datastore:hash',
                'data' => 'privacy:metadata:tool_datastore:data'
            ],
            'privacy:metadata:tool_datastore_snapshot'
        );

        $collection->add_database_table(
            'tool_datastore_idx_fields',
            [
                'entityid' => 'privacy:metadata:tool_datastore:entityid',
                'actionid' => 'privacy:metadata:tool_datastore:actionid',
                'name' => 'privacy:metadata:tool_datastore:name',
                'value' => 'privacy:metadata:tool_datastore:value'
            ],
            'privacy:metadata:tool_datastore_idx_fields'
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
        $userid;
        // TODO: Implement get_contexts_for_userid() method.
        $contextlist = new \core_privacy\local\request\contextlist();
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param  approved_contextlist $contextlist The list of approved contexts for a user.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $contextlist;
        // TODO: Implement export_user_data() method.
    }

    /**
     * Delete all use data which matches the specified deletion criteria.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        $context;
        // TODO: Implement delete_data_for_all_users_in_context() method.
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
        $contextlist;
        // TODO: Implement delete_data_for_user() method.
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        // TODO: Implement get_users_in_context() method.
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // TODO: Implement delete_data_for_users() method.
    }
}