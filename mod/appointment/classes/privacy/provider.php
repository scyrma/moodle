<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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
 * Privacy Subsystem implementation for mod_appointment.
 *
 * @package    mod_appointment
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Implementation of the privacy subsystem plugin provider for the appointment activity module.
 *
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'appointment_signups',
            [
                'id'        => 'privacy:metadata:appointment_signups:id',
                'sessionid' => 'privacy:metadata:appointment_signups:sessionid',
                'userid' => 'privacy:metadata:userid',
                'mailedreminder' => 'privacy:metadata:appointment_signups:mailedreminder',
                'notificationtype' => 'privacy:metadata:appointment_signups:notificationtype',
            ],
            'privacy:metadata:appointment_signups'
        );

        $collection->add_database_table(
            'appointment_signups_status',
            [
                'signupid' => 'privacy:metadata:appointment_signups_status:signupid',
                'statuscode' => 'privacy:metadata:appointment_signups_status:statuscode',
                'grade' => 'privacy:metadata:appointment_signups_status:grade',
                'note' => 'privacy:metadata:appointment_signups_status:note',
                'timecreated' => 'privacy:metadata:appointment_signups_status:timecreated',
            ],
            'privacy:metadata:appointment_signups_status'
        );

        $collection->add_database_table(
            'appointment_session_roles',
            [
                'userid' => 'privacy:metadata:userid',
                'roleid' => 'privacy:metadata:roleid',
            ],
            'privacy:metadata:appointment_session_roles'
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
        // Fetch all appointment contexts with userdata.
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {appointment} f ON f.id = cm.instance
            INNER JOIN {appointment_sessions} fs ON fs.appointment = f.id
            INNER JOIN {appointment_signups} fsi ON fsi.sessionid = fs.id
                 WHERE fsi.userid = :userid";

        $params = [
            'modname'       => 'appointment',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist  The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $params = [
            'modulename' => 'appointment',
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_MODULE
        ];

        $sql = "SELECT sr.userid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {appointment} a ON a.id = cm.instance
                  JOIN {appointment_sessions} s ON a.id = s.appointment
                  JOIN {appointment_session_roles} sr ON s.id = sr.sessionid
                 WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT ss.userid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {appointment} a ON a.id = cm.instance
                  JOIN {appointment_sessions} s ON a.id = s.appointment
                  JOIN {appointment_signups} ss ON s.id = ss.sessionid
                 WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export personal data for the given approved_contextlist.
     * User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        // Remove contexts different from COURSE_MODULE.
        $contexts = array_reduce($contextlist->get_contexts(), function($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->id;
            }
            return $carry;
        }, []);

        if (empty($contexts)) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        list($insql, $inparams) = $DB->get_in_or_equal($contexts, SQL_PARAMS_NAMED);

        // Get Appointment sessions.
        $sql = "SELECT ss.id,
                       ss.sessionid,
                       ss.mailedreminder,
                       ss.notificationtype,
                       fss.statuscode,
                       fss.grade,
                       fss.note,
                       fss.timecreated,
                       ctx.id as contextid
                  FROM {appointment_signups} ss
                  JOIN {appointment_sessions} s ON s.id = ss.sessionid
                  JOIN {appointment} f ON f.id = s.appointment
                  JOIN {course_modules} cm ON cm.instance = f.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id
             LEFT JOIN {appointment_signups_status} fss ON fss.signupid = ss.id
                 WHERE ctx.id $insql
                   AND ss.userid = :userid";
        $params = array_merge($inparams, ['userid' => $userid]);

        $signups = [];
        $signupstatus = [];
        $sessions = $DB->get_recordset_sql($sql, $params);
        foreach ($sessions as $session) {
            if (empty($signups[$session->contextid][$session->id])) {
                if ($session->mailedreminder > 100) { // Mailed reminder uses magic numbers or timestamp.
                    $session->mailedreminder = transform::datetime($session->mailedreminder);
                }
                $signups[$session->contextid][$session->id] = (object)[
                    'id' => $session->id,
                    'sessionid' => $session->sessionid,
                    'mailedreminder' => $session->mailedreminder,
                ];
            }
            $signupstatus[$session->contextid][$session->id][] = (object)[
                'id' => $session->id,
                'statuscode' => $session->statuscode,
                'grade' => $session->grade,
                'note' => $session->note,
                'timecreated' => transform::datetime($session->timecreated),
            ];
        }
        $sessions->close();

        array_walk($signups, function($data, $contextid) {
            $context = \context::instance_by_id($contextid);
            writer::with_context($context)->export_related_data(
                [],
                'sessions',
                (object)['signups' => $data]
            );
        });

        array_walk($signupstatus, function($data, $contextid) {
            $context = \context::instance_by_id($contextid);
            array_walk($data, function($data, $attempt) use ($context) {
                writer::with_context($context)->export_related_data(
                    [],
                    'signupstatus',
                    (object)[$attempt => $data]
                );
            });
        });

        // Get Appointment roles.
        $sql = "SELECT sr.roleid,
                       r.shortname,
                       ctx.id as contextid
                  FROM {appointment_session_roles} sr
                  JOIN {role} r on r.id = sr.roleid
                  JOIN {appointment_sessions} s ON s.id = sr.sessionid
                  JOIN {appointment} f ON f.id = s.appointment
                  JOIN {course_modules} cm ON cm.instance = f.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id
                 WHERE ctx.id $insql
                   AND sr.userid = :userid";
        $params = array_merge($inparams, ['userid' => $userid]);
        $roles = $DB->get_recordset_sql($sql, $params);
        foreach ($roles as $role) {
            $context = \context::instance_by_id($role->contextid);
            writer::with_context($context)->export_related_data(
                [],
                'trainer',
                (object)['role' => $role->shortname]
            );
        }
        $roles->close();
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        if ($cm = get_coursemodule_from_id('appointment', $context->instanceid)) {
            $params = array('fid' => $cm->instance);
            $select = "IN (SELECT s.id FROM {appointment_sessions} s
                                JOIN {appointment} f ON f.id = s.appointment
                                WHERE f.id = :fid)";

            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records_select('appointment_signups_status',
                'signupid IN (SELECT id FROM {appointment_signups} WHERE sessionid ' . $select . ')', $params);
            $DB->delete_records_select('appointment_signups', 'sessionid ' . $select, $params);
            $DB->delete_records_select('appointment_session_roles', 'sessionid ' . $select, $params);

            $transaction->allow_commit();
        }

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

            if (!$context instanceof \context_module) {
                return;
            }

            if ($cm = get_coursemodule_from_id('appointment', $context->instanceid)) {
                $params = array('userid' => $userid, 'fid' => $cm->instance);
                $select = "IN (SELECT s.id FROM {appointment_sessions} s
                                JOIN {appointment} f ON f.id = s.appointment
                                WHERE f.id = :fid)";

                $transaction = $DB->start_delegated_transaction();
                $DB->delete_records_select('appointment_signups_status',
                    'signupid IN (SELECT id FROM {appointment_signups} WHERE userid = :userid AND sessionid ' . $select . ')',
                    $params);
                $DB->delete_records_select('appointment_signups', 'userid = :userid AND sessionid ' . $select, $params);
                $DB->delete_records_select('appointment_session_roles', 'userid = :userid AND sessionid ' . $select, $params);

                // Clean up calendar events.
                $sessions = $DB->get_records('appointment_sessions', ['appointment' => $cm->instance]);
                foreach ($sessions as $session) {
                    appointment_remove_session_from_calendar($session, 0, $userid);
                }

                $transaction->allow_commit();
            }
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
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $userids = $userlist->get_userids();

        if ($cm = get_coursemodule_from_id('appointment', $context->instanceid)) {
            list($sql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $params['fid'] = $cm->instance;
            $select = "IN (SELECT s.id FROM {appointment_sessions} s
                            JOIN {appointment} f ON f.id = s.appointment
                            WHERE f.id = :fid)";

            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records_select('appointment_signups_status',
                "signupid IN (SELECT id FROM {appointment_signups} WHERE userid $sql AND sessionid $select)", $params);
            $DB->delete_records_select('appointment_signups', "userid $sql AND sessionid $select", $params);
            $DB->delete_records_select('appointment_session_roles', "userid $sql AND sessionid $select", $params);

            $transaction->allow_commit();
        }
    }
}
