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
use context_system;
use stdClass;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
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
            'tenantid' => 'privacy:metadata:page:tenantid',
            'global' => 'privacy:metadata:page:global',
            'name' => 'privacy:metadata:page:name',
            'title' => 'privacy:metadata:page:title',
            'weight' => 'privacy:metadata:page:weight',
            'usercreated' => 'privacy:metadata:page:usercreated',
            'usermodified' => 'privacy:metadata:page:usermodified',
            'timecreated' => 'privacy:metadata:page:timecreated',
            'timemodified' => 'privacy:metadata:page:timemodified',
        ], 'privacy:metadata:page');

        $collection->add_database_table(audience::TABLE, [
            'pageid' => 'privacy:metadata:audience:pageid',
            'classname' => 'privacy:metadata:audience:classname',
            'configdata' => 'privacy:metadata:audience:configdata',
            'usercreated' => 'privacy:metadata:audience:usercreated',
            'usermodified' => 'privacy:metadata:audience:usermodified',
            'timecreated' => 'privacy:metadata:audience:timecreated',
            'timemodified' => 'privacy:metadata:audience:timemodified',
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
        $contextlist = new contextlist();

        $select = 'usercreated = ? OR usermodified = ?';
        $params = array_fill(0, 2, $userid);

        if (page::record_exists_select($select, $params) || audience::record_exists_select($select, $params)) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }

        $sql = 'SELECT usercreated, usermodified FROM {' . page::TABLE . '}';
        $userlist->add_from_sql('usercreated', $sql, []);
        $userlist->add_from_sql('usermodified', $sql, []);

        $sql = 'SELECT usercreated, usermodified FROM {' . audience::TABLE . '}';
        $userlist->add_from_sql('usercreated', $sql, []);
        $userlist->add_from_sql('usermodified', $sql, []);
    }

    /**
     * Export all user data for the specified user in the specified contexts
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        // We need to get all pages that the user has edited themselves, or those they have created audiences for.
        $select = 'usercreated = ? OR usermodified = ?
            OR id IN (
                SELECT pageid
                  FROM {' . audience::TABLE . '}
                 WHERE usercreated = ? OR usermodified = ?
            )';
        $params = array_fill(0, 4, $user->id);

        foreach (page::get_records_select($select, $params) as $page) {
            $subcontext = static::get_export_subcontext($page);
            static::export_page($subcontext, $page);

            // Audiences.
            $audienceselect = 'pageid = ? AND (usercreated = ? OR usermodified = ?)';
            $audienceparams = [$page->get('id'), $user->id, $user->id];
            if ($audiences = audience::get_records_select($audienceselect, $audienceparams)) {
                static::export_audiences($subcontext, $audiences);
            }
        }
    }

    /**
     * Get export subcontext for a page
     *
     * @param page $page
     * @return string[]
     */
    public static function get_export_subcontext(page $page): array {
        return [
            get_string('pluginname', 'tool_custompage'),
            $page->get('id') . '-' . clean_filename($page->get_formatted_name()),
        ];
    }

    /**
     * Export given page
     *
     * @param array $subcontext
     * @param page $page
     */
    protected static function export_page(array $subcontext, page $page): void {
        $context = context_system::instance();

        writer::with_context($context)->export_data($subcontext, (object) [
            'tenantid' => $page->get('tenantid'),
            'global' => transform::yesno($page->get('global')),
            'name' => $page->get_formatted_name(),
            'title' => $page->get('title'),
            'weight' => $page->get('weight'),
            'usercreated' => transform::user($page->get('usercreated')),
            'usermodified' => transform::user($page->get('usermodified')),
            'timecreated' => transform::datetime($page->get('timecreated')),
            'timemodified' => transform::datetime($page->get('timemodified')),
        ]);
    }

    /**
     * Export given audiences
     *
     * @param array $subcontext
     * @param audience[] $audiences
     */
    protected static function export_audiences(array $subcontext, array $audiences): void {
        $context = context_system::instance();

        writer::with_context($context)->export_related_data($subcontext, 'audiences', (object) [
            'data' => array_map(static function(audience $audience): stdClass {
                return (object) [
                    'classname' => $audience->get('classname'),
                    'configdata' => $audience->get('configdata'),
                    'usercreated' => transform::user($audience->get('usercreated')),
                    'usermodified' => transform::user($audience->get('usermodified')),
                    'timecreated' => transform::datetime($audience->get('timecreated')),
                    'timemodified' => transform::datetime($audience->get('timemodified')),
                ];
            }, $audiences)
        ]);
    }

    /**
     * Delete all data for all users in the specified context
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        // We don't perform any deletion of user data.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // We don't perform any deletion of user data.
    }

    /**
     * Delete data for multiple users within a single context
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        // We don't perform any deletion of user data.
    }
}
