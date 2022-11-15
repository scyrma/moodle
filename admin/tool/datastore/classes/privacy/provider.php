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
 * Privacy Subsystem implementation for tool_datastore.
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_datastore\privacy;

use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_datastore\local\models\action;
use tool_datastore\local\models\entity;
use tool_datastore\local\models\field;

/**
 * Privacy Subsystem for tool_datastore
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
            action::TABLE,
            [
                'action' => 'privacy:metadata:tool_datastore:action',
                'relateduserid' => 'privacy:metadata:tool_datastore:relateduserid',
                'originalcourseid' => 'privacy:metadata:tool_datastore:originalcourseid',
                'originalprogramid' => 'privacy:metadata:tool_datastore:originalprogramid',
                'tenantid' => 'privacy:metadata:tool_datastore:tenantid',
                'usermodified' => 'privacy:metadata:tool_datastore:usermodified',
                'timecreated' => 'privacy:metadata:tool_datastore:timecreated',
            ],
            'privacy:metadata:tool_datastore_action'
        );

        $collection->add_database_table(
            entity::TABLE,
            [
                'actionid' => 'privacy:metadata:tool_datastore:actionid',
                'originalid' => 'privacy:metadata:tool_datastore:originalid',
                'type' => 'privacy:metadata:tool_datastore:type',
            ],
            'privacy:metadata:tool_datastore_entity'
        );

        $collection->add_database_table(
            field::TABLE,
            [
                'entityid' => 'privacy:metadata:tool_datastore:entityid',
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
        $contextlist = new contextlist();

        if (action::record_exists_select('relateduserid = ? OR usermodified = ?', [$userid, $userid])) {
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
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }

        $sql = 'SELECT relateduserid, usermodified FROM {' . action::TABLE . '}';

        $userlist->add_from_sql('relateduserid', $sql, []);
        $userlist->add_from_sql('usermodified', $sql, []);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param  approved_contextlist $contextlist The list of approved contexts for a user.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // We're only interested in the system context.
        $contexts = array_filter($contextlist->get_contexts(), function($context) {
            return $context instanceof context_system;
        });

        if (empty($contexts)) {
            return;
        }

        $context = reset($contexts);
        $user = $contextlist->get_user();

        if ($actions = action::get_records_select('relateduserid = ? OR usermodified = ?', [$user->id, $user->id])) {
            $data = [];

            foreach ($actions as $action) {
                $data[] = (object) [
                    'action' => $action->get('action'),
                    'relateduserid' => transform::user($action->get('relateduserid')),
                    'originalcourseid' => $action->get('originalcourseid'),
                    'originalprogramid' => $action->get('originalprogramid'),
                    'tenantid' => $action->get('tenantid'),
                    'usermodified' => transform::user($action->get('usermodified')),
                    'timecreated' => transform::datetime($action->get('timecreated')),
                ];
            }

            writer::with_context($context)->export_related_data([get_string('pluginname', 'tool_datastore')],
                'actions', $data);
        }
    }

    /**
     * Delete all use data which matches the specified deletion criteria.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // We don't perform any deletion of user data.
    }

    /**
     * Delete all user data for approved contexts lists provided in the collection.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // We don't perform any deletion of user data.
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // We don't perform any deletion of user data.
    }
}
