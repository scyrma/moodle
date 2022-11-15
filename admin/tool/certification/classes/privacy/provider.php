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
 * Class provider
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use tool_certification\certification;
use tool_certification\certification_completion;
use tool_certification\certification_user;

/**
 * Class provider
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider implements
    // This tool stores user data.
    \core_privacy\local\metadata\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,

    // This tool may provide access to and deletion of user data.
    \core_privacy\local\request\plugin\provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {

        // Allocations to certifications.
        $collection->add_database_table(
            certification_user::TABLE,
            [
                'certificationid' => 'privacy:metadata:certification_users:certificationid',
                'userid' => 'privacy:metadata:certification_users:userid',
                'status' => 'privacy:metadata:certification_users:status',
                'timemodified' => 'privacy:metadata:certification_users:timemodified',
                'currentprogramid' => 'privacy:metadata:certification_users:currentprogramid',
                'isrecertification' => 'privacy:metadata:certification_users:isrecertification',
            ],
            'privacy:metadata:certification_users'
        );

        // Certification completions.
        $collection->add_database_table(
            certification_completion::TABLE,
            [
                'certificationid' => 'privacy:metadata:certification_completions:certificationid',
                'userid' => 'privacy:metadata:certification_completions:userid',
                'expirydate' => 'privacy:metadata:certification_completions:expirydate',
                'certifiedby' => 'privacy:metadata:certification_completions:certifiedby',
                'revokedby' => 'privacy:metadata:certification_completions:revokedby',
                'timecreated' => 'privacy:metadata:certification_completions:timecreated',
                'timemodified' => 'privacy:metadata:certification_completions:timemodified',
                'timecertified' => 'privacy:metadata:certification_completions:timecertified',
            ],
            'privacy:metadata:certification_completions'
        );

        // Certifications can be tagged.
        $collection->add_subsystem_link('core_tag', [], 'privacy:metadata:core_tag');

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

        // We always use system context in certifications.
        $sql = "SELECT COUNT(1)
                  FROM {" . certification_user::TABLE . "}
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

        // Allocations to certifications.
        $sql = "SELECT cu.*
                  FROM {" . certification_user::TABLE . "} cu
            INNER JOIN {" . certification::TABLE . "} ce
                    ON ce.id = cu.certificationid
                 WHERE cu.userid = :userid ";
        $allocations = $DB->get_records_sql($sql, ['userid' => $user->id]);

        // Certification completions.
        $sql = "SELECT cc.*
                  FROM {" . certification_completion::TABLE . "} cc
            INNER JOIN {" . certification::TABLE . "} ce
                    ON ce.id = cc.certificationid
                 WHERE cc.userid = :userid ";
        $completions = $DB->get_records_sql($sql, ['userid' => $user->id]);

        // Certifications.
        $sql = "SELECT ce.*
                  FROM {" . certification::TABLE . "} ce
            INNER JOIN {" . certification_user::TABLE . "} cu
                    ON cu.certificationid = ce.id
                 WHERE cu.userid = :userid ";

        $stores = [];
        $certifications = $DB->get_recordset_sql($sql, ['userid' => $user->id]);
        foreach ($certifications as $certification) {
            if (isset($stores[$certification->id])) {
                continue;
            }

            $stores[$certification->id] = [
                'certificationid' => (int) $certification->id,
                'certification_name' => format_string($certification->fullname),
            ];

            $relatedallocations = array_filter($allocations, static function($allocation) use ($certification) {
                return (int) $certification->id === (int) $allocation->certificationid;
            });

            $stores[$certification->id]['allocations'] = [];
            foreach ($relatedallocations as $relatedallocation) {
                if (!isset($stores[$certification->id]['allocations'][$relatedallocation->id])) {
                    $stores[$certification->id]['allocations'][$relatedallocation->id] = [
                        'certification_user' => transform::user($relatedallocation->userid),
                        'time_added' => transform::datetime($relatedallocation->timecreated),
                        'time_last_modified' => transform::datetime($relatedallocation->timemodified),
                    ];
                }
            }

            $relatedcompletions = array_filter($completions, static function($completion) use ($certification) {
                return (int) $certification->id === (int) $completion->certificationid;
            });

            $stores[$certification->id]['completions'] = [];
            foreach ($relatedcompletions as $relatedcompletion) {
                if (!isset($stores[$certification->id]['completions'][$relatedcompletion->id])) {
                    $stores[$certification->id]['completions'][$relatedcompletion->id] = [
                        'completion_user' => transform::user($relatedcompletion->userid),
                        'time_last_modified' => transform::datetime($relatedcompletion->timemodified),
                        'time_added' => transform::datetime($relatedcompletion->timecreated),
                        'time_certified' => transform::datetime($relatedcompletion->timecertified),
                        'certified_by' => $relatedcompletion->certifiedby ? transform::user($relatedcompletion->certifiedby) : null,
                        'revoked_by' => $relatedcompletion->revokedby ? transform::user($relatedcompletion->revokedby) : null,
                    ];
                }
            }
        }
        $certifications->close();

        $certificationsstr = get_string('certifications', 'tool_certification');
        foreach ($stores as $store) {
            $directories = [$certificationsstr, $store['certification_name'] . $store['certificationid']];
            writer::with_context($context)->export_data($directories, (object) $store);

            // Export associated tags.
            \core_tag\privacy\provider::export_item_tags($user->id, $context, $directories, 'tool_certification',
                'tool_certification', $store['certificationid'], false);
        }
    }

    /**
     * Delete all use data which matches the specified deletion criteria.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        // Delete all users from certifications.
        $DB->delete_records(certification_user::TABLE);

        // Delete all users from certifications completion.
        $DB->delete_records(certification_completion::TABLE);
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

        // Only delete records for the users assigned to a certification.
        $DB->delete_records(certification_user::TABLE, ['userid' => $user->id]);

        // Only delete records for the users assigned to a certification completion.
        $DB->delete_records(certification_completion::TABLE, ['userid' => $user->id]);
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $params = [];

        // Users allocated to certifications.
        $sql = "SELECT userid
                  FROM {". certification_user::TABLE . "} ";
        $userlist->add_from_sql('userid', $sql, $params);

        // Users that completed certifications.
        $sql = 'SELECT userid
                  FROM {' . certification_completion::TABLE . '} ';
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
        $DB->delete_records_list(certification_user::TABLE, 'userid', $userlist->get_userids());

        // Delete this users completions.
        $DB->delete_records_list(certification_completion::TABLE, 'userid', $userlist->get_userids());
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
}
