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

namespace tool_custompage\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use tool_custompage\local\models\{page, audience};

/**
 * Plugin privacy provider
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata about the component
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(page::TABLE, [
            'name' => 'privacy:metadata:page:name',
            'usercreated' => 'privacy:metadata:page:usercreated',
            'usermodified' => 'privacy:metadata:page:usermodified',
        ], 'privacy:metadata:page');

        $collection->add_database_table(audience::TABLE, [
            'usercreated' => 'privacy:metadata:audience:usercreated',
            'usermodified' => 'privacy:metadata:audience:usermodified',
        ], 'privacy:metadata:audience');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        // TODO: Implement get_contexts_for_userid() method.
        return new contextlist();
    }

    /**
     * Get the list of users who have data within a context
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        // TODO: Implement get_users_in_context() method.
    }

    /**
     * Export all user data for the specified user in the specified contexts
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        // TODO: Implement export_user_data() method.
    }

    /**
     * Delete all data for all users in the specified context
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        // TODO: Implement delete_data_for_all_users_in_context() method.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // TODO: Implement delete_data_for_user() method.
    }

    /**
     * Delete data for multiple users within a single context
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        // TODO: Implement delete_data_for_users() method.
    }
}
